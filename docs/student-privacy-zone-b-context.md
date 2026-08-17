# Encrypted Roster Vault — Zone B Context

Session context: completed August 2026.

The `member_id → real name` map for a class, encrypted in the teacher's browser and stored on Curriki's server as an opaque blob the server cannot read. This is the other half of [Zone A](student-privacy-zone-a-context.md), which holds the pseudonymous side.

**Passphrase-only in this phase.** WebAuthn PRF is a later upgrade, not a prerequisite — see §7.

---

## 1. The problem this solves

Encrypting the roster is trivial. The hard question is *where the key lives*, and every obvious answer fails:

| Key location | Why it fails |
|---|---|
| On the server | Curriki can read it — defeats the whole design |
| The teacher's WP password | The server sees it at login |
| A downloaded key file | Lost, emailed, synced to three clouds |
| A TOTP code | Rotates every 30s and is ~20 bits |

The answer is a passphrase that **never leaves the browser**, stretched with PBKDF2 into a key that only ever exists in page memory.

---

## 2. Envelope encryption

```
DEK   random 32 bytes → AES-256-GCM over the roster JSON
KEK   PBKDF2-SHA256(passphrase, salt, 600 000) → wraps the DEK
escrow  a second DEK copy, RSA-OAEP-wrapped with the district's public key
```

Two wrapped copies of one DEK, rather than encrypting the roster twice. Adding a recovery holder means wrapping 32 bytes again; the roster ciphertext never moves.

### Why PBKDF2 and not Argon2id

WebCrypto has no Argon2id — only PBKDF2 — and bundling a WASM build is a real dependency (binary in the repo, licence, CSP/MIME behaviour). 600 000 iterations of PBKDF2-SHA256 meets OWASP 2023 guidance.

**`kdf_params.algo` is persisted with every vault**, so Argon2id can be introduced later and existing vaults keep opening. That column is the entire migration story; don't drop it.

The server floors `iterations` at 100 000 on save — a tampered-with low count would make offline cracking of the passphrase cheap.

---

## 3. Schema

Table `{prefix}lxp_roster_vault`, created by `tl_lxp_install_tables()` in [TinyLxp-wp-plugin.php](../TinyLxp-wp-plugin.php). `TL_LXP_DB_VERSION` is now `1.2.0`.

| Column | Contents |
|---|---|
| `class_id` | `tl_class` post ID, UNIQUE — one vault per class |
| `ciphertext` | base64 AES-256-GCM of `{v:1, names:{member_id: "Real Name"}}` |
| `iv` | base64, 12 bytes, **fresh on every save** |
| `wrapped_dek_teacher` | base64 `iv‖ciphertext` of the DEK under the passphrase KEK |
| `wrapped_dek_escrow` | base64 RSA-OAEP of the DEK; NULL when the district set no key |
| `kdf_params` | JSON `{algo, hash, iterations, salt}` — all non-secret |
| `version` | optimistic-concurrency counter |

> Divergence from the client's schema: `longtext`, not `longblob`, because payloads are base64. That keeps `$wpdb->prepare()` free of binary-escaping hazards and survives mysqldump/restore intact.

Names are keyed on **`lxp_class_members.id`**, not `alias_label` — renaming a seat must not orphan a name.

---

## 4. Code map

| Piece | File |
|---|---|
| Browser crypto | [lxp-roster-vault.js](../includes/widgets/assets/js/lxp-roster-vault.js) — `window.LXPRosterVault` |
| REST | [roster-vault.php](../lms/lms-rest-apis/roster-vault.php) — `Rest_Lxp_Roster_Vault` |
| DB | [class-roster-vault-repository.php](../lms/repositories/class-roster-vault-repository.php) |
| UI | [class-roster-modal.php](../lms/templates/tinyLxpTheme/lxp/class-roster-modal.php) |
| Escrow key setting | `admin-district-modal.php`, `districts.php` |
| Tests | [tests/roster-vault.test.js](../tests/roster-vault.test.js) — `node tests/roster-vault.test.js` |

### Routes

| Route | Purpose |
|---|---|
| `POST /lms/v1/class/vault` | Release the blob + the district escrow public key |
| `POST /lms/v1/class/vault/save` | Store a replacement blob (version-checked) |
| `POST /lms/v1/class/vault/delete` | Destroy the vault |

All gated by `Rest_Lxp_Class_Redemption::can_manage_class()` — deliberately the *same* rule as Zone A, so ownership can't drift between the two.

`before_delete_post` purges the vault when its class is deleted: a roster map must not outlive the class it describes.

> **If you ever add a decrypt call to `roster-vault.php`, the design has been broken.** That file's only jobs are access control and storage.

---

## 5. Escrow

The district pastes an **RSA public key** (SPKI PEM) into Admin → Districts → *Roster recovery key*. The teacher's browser wraps a second DEK copy with it on every save. The matching **private** key lives in the district's KMS or offline safe — never in WordPress, which is what keeps the zero-knowledge claim true.

`districts.php::is_valid_public_key()` rejects anything without a `PUBLIC KEY` PEM header, refuses a pasted **private** key outright, and verifies it via OpenSSL where available.

Resolution walks class → teacher → school → district. With no key configured, the vault saves without a recovery copy and **the setup UI warns in bold that a forgotten passphrase is permanent data loss**.

Verified working end to end: test 7 in the harness decrypts a real vault using only the escrow private key, with no passphrase.

---

## 6. CSV import — names never leave the browser

The teacher picks a file; JavaScript parses it in-page. There is **no upload, no `wp_handle_upload`, no temp file**.

Accepted columns: `first_name`, `last_name`, and an optional `alias_label`. Nothing else.

Columns in `BANNED_HEADERS` cause the import to be **rejected with a message naming them**, rather than silently ignored — a teacher must know the file was wrong. That list covers identifiers (`student_id`, `sis_id`, `ssn`), contact details (`email`, `phone`, `address`, `parent_email`), and special-category fields (`iep`, `accommodations`, `ell`, `frl`, `gender`, `ethnicity`) which carry their own protections under IDEA and the school-lunch act.

Flow: parse → confirm count → `POST /class/roster/provision` with seat labels only → pair each returned `member_id` with its row's name → encrypt → save. The vault must be unlocked first, since names have nowhere to go otherwise.

---

## 7. Adding WebAuthn PRF later

Nothing here needs rewriting. PRF derives a KEK from a passkey instead of a passphrase; the DEK, the ciphertext, the escrow copy and the table are all unchanged. It becomes a third `wrapped_dek_*` column plus a different unlock gesture.

Worth knowing before that work starts:

- **PRF must be requested at credential *creation*.** It cannot be retrofitted onto passkeys that already exist — those users re-register.
- **A newly registered passkey has different PRF output.** Synced passkeys (iCloud Keychain, Google Password Manager) carry across devices and still work; a fresh registration does not. This is the most likely way a teacher loses access, months after setup.
- **PRF is bound to the RP ID.** Changing the site's domain makes every PRF-wrapped vault permanently unopenable.
- **Coverage is not universal** — Safari can't pass extension data to external security keys, and Windows Hello only returns PRF after the Feb 2026 update (KB5077181). The passphrase path stays mandatory, which is exactly why it was built first.

---

## 8. Gotchas

1. **The passphrase is not held in memory.** Saving re-prompts, because the DEK is kept but the KEK is not. This is deliberate; don't "fix" it by caching the passphrase.
2. **A fresh IV per save is mandatory.** Reusing a GCM nonce under one key leaks the keystream — catastrophic, not merely weakening. Both `sealWithIv()` and `save()` generate one every time; test 5 guards this.
3. **Version conflicts are real.** Two teachers on one class will hit 409. The client surfaces "reload before saving again" rather than clobbering.
4. **A malformed district key must not block a save.** The escrow wrap is wrapped in its own `.catch()`; a bad key means no recovery copy that save, not a failed save.
5. **A wrong passphrase surfaces as a GCM auth failure**, which is what we want — there's no oracle telling an attacker they were close.
6. **`escrowPem` is fetched on load and applied on next save.** A district adding a key later doesn't retroactively protect existing vaults until each is re-saved; the modal shows a notice when it spots this.
7. The vault stores names for **members**, so a student must have a seat before they can have a name. Provisioning always precedes naming.
