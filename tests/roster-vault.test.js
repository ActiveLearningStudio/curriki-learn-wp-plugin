/**
 * Exercise the Zone B vault crypto against Node's WebCrypto.
 * Simulates the server with an in-memory store.
 */
const fs = require('fs');
const path = require('path');
const { webcrypto } = require('crypto');
const crypto = require('crypto');

// ---- Minimal browser shim -------------------------------------------------
const store = {};   // stands in for the lxp_roster_vault table

const win = {
  crypto: webcrypto,
  btoa: (s) => Buffer.from(s, 'binary').toString('base64'),
  atob: (s) => Buffer.from(s, 'base64').toString('binary'),
  fetch: async (url, opts) => {
    const body = opts.body;              // our fake FormData
    if (url.endsWith('class/vault')) {
      const row = store[body.class_id];
      return {
        ok: true,
        status: 200,
        json: async () => ({
          success: true,
          data: {
            exists: !!row,
            vault: row ? { ...row, kdf_params: JSON.parse(row.kdf_params), has_escrow: !!row.wrapped_dek_escrow } : null,
            escrow_public_key: ESCROW_PEM,
          },
        }),
      };
    }
    if (url.endsWith('class/vault/save')) {
      const existing = store[body.class_id];
      const expected = parseInt(body.version, 10);
      if (existing && expected !== existing.version) {
        return { ok: false, status: 409, json: async () => ({ success: false }) };
      }
      const version = existing ? existing.version + 1 : 1;
      store[body.class_id] = {
        ciphertext: body.ciphertext,
        iv: body.iv,
        wrapped_dek_teacher: body.wrapped_dek_teacher,
        wrapped_dek_escrow: body.wrapped_dek_escrow || null,
        kdf_params: body.kdf_params,
        version,
      };
      return { ok: true, status: 200, json: async () => ({ success: true, data: { version } }) };
    }
    throw new Error('unexpected url ' + url);
  },
};

class FakeFormData {
  constructor() { this._d = {}; }
  append(k, v) { this._d[k] = v; }
  get class_id() { return this._d.class_id; }
  get version() { return this._d.version; }
  get ciphertext() { return this._d.ciphertext; }
  get iv() { return this._d.iv; }
  get wrapped_dek_teacher() { return this._d.wrapped_dek_teacher; }
  get wrapped_dek_escrow() { return this._d.wrapped_dek_escrow; }
  get kdf_params() { return this._d.kdf_params; }
}
global.FormData = FakeFormData;
global.TextEncoder = TextEncoder;
global.TextDecoder = TextDecoder;

// ---- District escrow keypair (private half stays here, never in the app) ---
const { publicKey, privateKey } = crypto.generateKeyPairSync('rsa', {
  modulusLength: 2048,
  publicKeyEncoding: { type: 'spki', format: 'pem' },
  privateKeyEncoding: { type: 'pkcs8', format: 'pem' },
});
const ESCROW_PEM = publicKey;

// ---- Load the module under test -------------------------------------------
const src = fs.readFileSync(
  path.resolve(__dirname, '../../../../../../d:/projects/Triasoft/xampp/htdocs/tinylxp/wp-content/plugins/curriki-learn-wp-plugin/includes/widgets/assets/js/lxp-roster-vault.js'.replace(/^.*?d:/i, 'd:')),
  'utf8'
);
new Function('window', src)(win);
const RosterVault = win.LXPRosterVault;

// ---- Assertions -----------------------------------------------------------
let pass = 0, fail = 0;
function check(label, cond) {
  if (cond) { pass++; console.log('  PASS  ' + label); }
  else { fail++; console.log('  FAIL  ' + label); }
}

(async () => {
  const PASSPHRASE = 'correct horse battery staple';

  console.log('\n1. Create a vault and store names');
  const v1 = new RosterVault({ apiUrl: '/', classId: 9301 });
  await v1.load();
  check('no vault exists initially', v1.exists === false);
  check('escrow key was offered by the server', v1.escrowPem.includes('BEGIN PUBLIC KEY'));

  await v1.create(PASSPHRASE, {});
  v1.setName(101, 'Maria Garcia');
  v1.setName(102, 'Jamal Wright');
  await v1.save(PASSPHRASE, false);
  check('vault saved at version 2', v1.version === 2);

  console.log('\n2. Server holds only ciphertext');
  const row = store[9301];
  const blob = JSON.stringify(row);
  check('no plaintext name anywhere in stored row', !/Maria|Garcia|Jamal|Wright/.test(blob));
  check('no passphrase in stored row', !blob.includes(PASSPHRASE));
  check('kdf algo recorded for future migration', JSON.parse(row.kdf_params).algo === 'PBKDF2-SHA256');
  check('iteration count is 600k', JSON.parse(row.kdf_params).iterations === 600000);
  check('escrow copy was written', !!row.wrapped_dek_escrow);

  console.log('\n3. Reopen in a fresh session');
  const v2 = new RosterVault({ apiUrl: '/', classId: 9301 });
  await v2.load();
  check('vault now reported as existing', v2.exists === true);
  const names = await v2.unlock(PASSPHRASE);
  check('decrypted Maria', names['101'] === 'Maria Garcia');
  check('decrypted Jamal', names['102'] === 'Jamal Wright');

  console.log('\n4. Wrong passphrase is rejected');
  const v3 = new RosterVault({ apiUrl: '/', classId: 9301 });
  await v3.load();
  let rejected = false;
  try { await v3.unlock('wrong passphrase entirely'); }
  catch (e) { rejected = true; }
  check('wrong passphrase throws', rejected);
  check('vault left locked after failure', v3.isUnlocked() === false);

  console.log('\n5. IV is fresh on every save (GCM nonce reuse would be fatal)');
  const ivBefore = store[9301].iv;
  await v2.save(PASSPHRASE, false);
  check('iv changed between saves', store[9301].iv !== ivBefore);

  console.log('\n6. Optimistic concurrency blocks a stale write');
  const stale = new RosterVault({ apiUrl: '/', classId: 9301 });
  await stale.load();
  await stale.unlock(PASSPHRASE);
  await v2.save(PASSPHRASE, false);          // another session saves first
  let conflicted = false;
  try { await stale.save(PASSPHRASE, false); }
  catch (e) { conflicted = /another session/.test(e.message); }
  check('stale save rejected with a conflict', conflicted);

  console.log('\n7. District can recover with the escrow private key');
  const wrappedEscrow = Buffer.from(store[9301].wrapped_dek_escrow, 'base64');
  const dekRaw = crypto.privateDecrypt(
    { key: privateKey, padding: crypto.constants.RSA_PKCS1_OAEP_PADDING, oaepHash: 'sha256' },
    wrappedEscrow
  );
  check('escrow unwrapped a 32-byte DEK', dekRaw.length === 32);

  const dek = await webcrypto.subtle.importKey('raw', dekRaw, { name: 'AES-GCM', length: 256 }, false, ['decrypt']);
  const plain = await webcrypto.subtle.decrypt(
    { name: 'AES-GCM', iv: Buffer.from(store[9301].iv, 'base64') },
    dek,
    Buffer.from(store[9301].ciphertext, 'base64')
  );
  const recovered = JSON.parse(Buffer.from(plain).toString('utf8'));
  check('district recovered the real names without the passphrase', recovered.names['101'] === 'Maria Garcia');

  console.log(`\n${pass} passed, ${fail} failed\n`);
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error('HARNESS ERROR', e); process.exit(2); });
