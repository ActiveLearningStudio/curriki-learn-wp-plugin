/**
 * LXP Roster Vault — client-side crypto for Zone B.
 *
 * The `member_id -> real name` map for a class is encrypted here, in the
 * teacher's browser, and only ever leaves as opaque base64. The server stores
 * the blob and hands it back; it holds no key and cannot read the contents.
 *
 * Envelope encryption:
 *
 *   DEK   random 32 bytes. Encrypts the roster JSON with AES-256-GCM.
 *   KEK   PBKDF2-SHA256(passphrase, salt, 600k). Encrypts the DEK.
 *   escrow  a second copy of the DEK, wrapped with the district's RSA-OAEP
 *           public key, so a forgotten passphrase is recoverable by the
 *           district — never by Curriki, which holds no private key.
 *
 * Why PBKDF2 and not Argon2id: WebCrypto has no Argon2id, and pulling in a
 * WASM build is a real dependency. `kdf_params.algo` is persisted with every
 * vault, so Argon2id can be introduced later and existing vaults keep opening.
 *
 * Nothing here persists a key. The DEK lives in a closure for the life of the
 * unlocked session and dies on lock or navigation.
 */
(function (window) {
	'use strict';

	var PBKDF2_ITERATIONS = 600000;   // OWASP 2023 guidance for PBKDF2-SHA256
	var SALT_BYTES        = 16;
	var IV_BYTES          = 12;       // AES-GCM standard nonce length
	var DEK_BYTES         = 32;       // AES-256

	// ---------------------------------------------------------------------
	// Encoding helpers
	// ---------------------------------------------------------------------

	function toB64(buffer) {
		var bytes  = new Uint8Array(buffer);
		var binary = '';
		for (var i = 0; i < bytes.length; i++) {
			binary += String.fromCharCode(bytes[i]);
		}
		return window.btoa(binary);
	}

	function fromB64(b64) {
		var binary = window.atob(b64);
		var bytes  = new Uint8Array(binary.length);
		for (var i = 0; i < binary.length; i++) {
			bytes[i] = binary.charCodeAt(i);
		}
		return bytes;
	}

	function utf8(str)      { return new TextEncoder().encode(str); }
	function fromUtf8(buf)  { return new TextDecoder().decode(buf); }

	function randomBytes(n) {
		return window.crypto.getRandomValues(new Uint8Array(n));
	}

	/** Strip PEM armour and return the raw DER bytes. */
	function pemToDer(pem) {
		var body = String(pem)
			.replace(/-----BEGIN [^-]+-----/, '')
			.replace(/-----END [^-]+-----/, '')
			.replace(/\s+/g, '');
		return fromB64(body);
	}

	// ---------------------------------------------------------------------
	// Key derivation
	// ---------------------------------------------------------------------

	/**
	 * Derive the key-encryption key from a passphrase.
	 *
	 * @param {string} passphrase
	 * @param {Uint8Array} salt
	 * @param {number} iterations
	 * @returns {Promise<CryptoKey>} AES-GCM key, non-extractable.
	 */
	function deriveKek(passphrase, salt, iterations) {
		return window.crypto.subtle
			.importKey('raw', utf8(passphrase), 'PBKDF2', false, ['deriveKey'])
			.then(function (baseKey) {
				return window.crypto.subtle.deriveKey(
					{
						name: 'PBKDF2',
						salt: salt,
						iterations: iterations || PBKDF2_ITERATIONS,
						hash: 'SHA-256'
					},
					baseKey,
					{ name: 'AES-GCM', length: 256 },
					false,                       // never extractable
					['encrypt', 'decrypt']
				);
			});
	}

	// ---------------------------------------------------------------------
	// AES-GCM helpers
	// ---------------------------------------------------------------------

	/**
	 * Encrypt and return iv||ciphertext as one base64 string.
	 *
	 * A fresh IV per call is mandatory: reusing an IV under the same GCM key
	 * leaks the keystream and is catastrophic, not merely weakening.
	 */
	function sealWithIv(key, plaintextBytes) {
		var iv = randomBytes(IV_BYTES);
		return window.crypto.subtle
			.encrypt({ name: 'AES-GCM', iv: iv }, key, plaintextBytes)
			.then(function (ct) {
				var out = new Uint8Array(iv.length + ct.byteLength);
				out.set(iv, 0);
				out.set(new Uint8Array(ct), iv.length);
				return toB64(out);
			});
	}

	/** Reverse of sealWithIv(). */
	function openWithIv(key, b64) {
		var raw = fromB64(b64);
		var iv  = raw.slice(0, IV_BYTES);
		var ct  = raw.slice(IV_BYTES);
		return window.crypto.subtle.decrypt({ name: 'AES-GCM', iv: iv }, key, ct);
	}

	// ---------------------------------------------------------------------
	// Vault
	// ---------------------------------------------------------------------

	/**
	 * @param {object} opts
	 * @param {string} opts.apiUrl   REST base, e.g. https://host/wp-json/lms/v1/
	 * @param {number} opts.classId
	 */
	function RosterVault(opts) {
		this.apiUrl   = opts.apiUrl;
		this.classId  = opts.classId;

		this.dek       = null;   // CryptoKey, only while unlocked
		this.names     = {};     // member_id -> real name
		this.version   = 0;
		this.kdfParams = null;
		this.escrowPem = '';
		this.exists    = false;
		this.hasEscrow = false;
	}

	RosterVault.prototype.isUnlocked = function () {
		return this.dek !== null;
	};

	/** Wipe the in-memory key and names. */
	RosterVault.prototype.lock = function () {
		this.dek   = null;
		this.names = {};
	};

	RosterVault.prototype._post = function (path, data) {
		var body = new FormData();
		Object.keys(data).forEach(function (k) {
			if (data[k] !== null && data[k] !== undefined) { body.append(k, data[k]); }
		});
		return window.fetch(this.apiUrl + path, {
			method: 'POST',
			body: body,
			credentials: 'same-origin'
		}).then(function (res) {
			return res.json().then(function (json) {
				return { ok: res.ok, status: res.status, json: json };
			});
		});
	};

	/** Fetch the blob (still encrypted) plus the district escrow key. */
	RosterVault.prototype.load = function () {
		var self = this;
		return this._post('class/vault', { class_id: this.classId }).then(function (r) {
			if (!r.ok || !r.json || !r.json.success) {
				throw new Error('Could not load the roster vault.');
			}
			var d = r.json.data;
			self.exists    = d.exists;
			self.escrowPem = d.escrow_public_key || '';
			if (d.vault) {
				self.blob      = d.vault;
				self.version   = d.vault.version;
				self.kdfParams = d.vault.kdf_params;
				self.hasEscrow = !!d.vault.has_escrow;
			}
			return d;
		});
	};

	/**
	 * Create a brand-new vault for this class.
	 *
	 * @param {string} passphrase
	 * @param {object} names  Optional initial member_id -> name map.
	 */
	RosterVault.prototype.create = function (passphrase, names) {
		var self = this;
		var salt = randomBytes(SALT_BYTES);

		self.kdfParams = {
			algo: 'PBKDF2-SHA256',
			hash: 'SHA-256',
			iterations: PBKDF2_ITERATIONS,
			salt: toB64(salt)
		};
		self.names = names || {};

		return window.crypto.subtle
			.generateKey({ name: 'AES-GCM', length: 256 }, true, ['encrypt', 'decrypt'])
			.then(function (dek) {
				self.dek = dek;
				return self.save(passphrase, true);
			});
	};

	/**
	 * Unlock an existing vault with the teacher's passphrase.
	 *
	 * A wrong passphrase surfaces as a GCM authentication failure, which is
	 * exactly what we want — there is no oracle telling an attacker they got
	 * "close".
	 */
	RosterVault.prototype.unlock = function (passphrase) {
		var self = this;

		if (!self.blob) {
			return Promise.reject(new Error('No vault to unlock.'));
		}

		var kdf  = self.kdfParams || {};
		var salt = fromB64(kdf.salt);

		return deriveKek(passphrase, salt, kdf.iterations)
			.then(function (kek) {
				return openWithIv(kek, self.blob.wrapped_dek_teacher);
			})
			.then(function (dekRaw) {
				return window.crypto.subtle.importKey(
					'raw', dekRaw, { name: 'AES-GCM', length: 256 }, true, ['encrypt', 'decrypt']
				);
			})
			.then(function (dek) {
				self.dek = dek;
				var raw = fromB64(self.blob.ciphertext);
				var iv  = fromB64(self.blob.iv);
				return window.crypto.subtle.decrypt({ name: 'AES-GCM', iv: iv }, dek, raw);
			})
			.then(function (plaintext) {
				var payload = JSON.parse(fromUtf8(plaintext));
				self.names  = payload.names || {};
				return self.names;
			})
			.catch(function (err) {
				self.lock();
				// Distinguish "wrong passphrase" from "server/network broke".
				if (err instanceof Error && err.message.indexOf('vault') !== -1) { throw err; }
				throw new Error('That passphrase did not work.');
			});
	};

	/** Set (or clear) one student's real name. Requires an unlocked vault. */
	RosterVault.prototype.setName = function (memberId, name) {
		if (!this.isUnlocked()) { throw new Error('Vault is locked.'); }
		var key = String(memberId);
		if (name) {
			this.names[key] = String(name);
		} else {
			delete this.names[key];
		}
	};

	RosterVault.prototype.getName = function (memberId) {
		return this.names[String(memberId)] || '';
	};

	/**
	 * Re-encrypt and persist.
	 *
	 * The passphrase is needed to re-wrap the DEK. A fresh IV is generated for
	 * both the roster ciphertext and the DEK wrap on every save.
	 *
	 * @param {string}  passphrase
	 * @param {boolean} isNew  True on first creation.
	 */
	RosterVault.prototype.save = function (passphrase, isNew) {
		var self = this;

		if (!self.isUnlocked()) {
			return Promise.reject(new Error('Vault is locked.'));
		}

		var kdf  = self.kdfParams;
		var salt = fromB64(kdf.salt);
		var payload = utf8(JSON.stringify({ v: 1, names: self.names }));
		var iv = randomBytes(IV_BYTES);

		var ciphertextB64;
		var wrappedTeacherB64;
		var wrappedEscrowB64 = null;

		return window.crypto.subtle
			.encrypt({ name: 'AES-GCM', iv: iv }, self.dek, payload)
			.then(function (ct) {
				ciphertextB64 = toB64(ct);
				return deriveKek(passphrase, salt, kdf.iterations);
			})
			.then(function (kek) {
				return window.crypto.subtle.exportKey('raw', self.dek).then(function (dekRaw) {
					return sealWithIv(kek, dekRaw);
				});
			})
			.then(function (wrapped) {
				wrappedTeacherB64 = wrapped;

				// Escrow copy — only when the district has published a key.
				if (!self.escrowPem) { return null; }

				return window.crypto.subtle
					.importKey(
						'spki',
						pemToDer(self.escrowPem),
						{ name: 'RSA-OAEP', hash: 'SHA-256' },
						false,
						['encrypt']
					)
					.then(function (pub) {
						return window.crypto.subtle.exportKey('raw', self.dek).then(function (dekRaw) {
							return window.crypto.subtle.encrypt({ name: 'RSA-OAEP' }, pub, dekRaw);
						});
					})
					.then(function (wrappedEscrow) {
						wrappedEscrowB64 = toB64(wrappedEscrow);
					})
					.catch(function () {
						// A malformed district key must not block the teacher's
						// save — it just means no recovery copy this time.
						wrappedEscrowB64 = null;
					});
			})
			.then(function () {
				return self._post('class/vault/save', {
					class_id: self.classId,
					ciphertext: ciphertextB64,
					iv: toB64(iv),
					wrapped_dek_teacher: wrappedTeacherB64,
					wrapped_dek_escrow: wrappedEscrowB64,
					kdf_params: JSON.stringify(kdf),
					version: isNew ? 0 : self.version
				});
			})
			.then(function (r) {
				if (r.status === 409) {
					throw new Error('This roster was changed in another session. Reload before saving again.');
				}
				if (!r.ok || !r.json || !r.json.success) {
					throw new Error('Could not save the roster vault.');
				}
				self.version   = r.json.data.version;
				self.exists    = true;
				self.hasEscrow = wrappedEscrowB64 !== null;
				return self.version;
			});
	};

	window.LXPRosterVault = RosterVault;
})(window);
