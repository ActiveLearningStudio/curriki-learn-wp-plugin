# Class + Encrypted Roster — Schema Design

**Goal:** let a teacher enroll students (via registration code or roster upload) so they can take a
LearnPress course, while keeping Curriki's servers free of student PII (COPPA/FERPA), yet still
letting the teacher — and only the teacher — re-identify each student.

**Design principle:** *pseudonymization, not anonymization.* The server stores an opaque token per
student. The `token → real name` map lives in a per-class **encrypted vault** that only decrypts in
the teacher's browser. Everything student-generated (enrollment, quiz/capstone/workbook responses)
is keyed to the token, so it is pseudonymous by construction.

---

## 1. What exists today (do not change)

| Object | Type | Key links |
|---|---|---|
| `tl_district` #9299 | post type | meta `lxp_district_admin` → user (role `lxp_client_admin`) |
| `tl_school` #9300 | post type | meta `lxp_school_district_id` → district, `lxp_school_admin_id` → user (`lxp_school_admin`) |
| `tl_teacher` #9301 | post type | meta `lxp_teacher_school_id` → school, `lxp_teacher_admin_id` → user (`lp_teacher`), `grades` |
| Enrollment | `dev_c_learnpress_user_items` | `user_id → item_id(course)`, `item_type='lp_course'` |
| Student work | `dev_c_lxp_capstone_submissions`, `dev_c_lxp_workbook_submissions` | `user_id`, `course_id`, `lesson_id`, `response` |

Gaps: **no student entity, no class/section entity, no teacher↔student link.** All current learners
are full WP users with real personal emails — acceptable now (audience is adult educators), unsafe
the moment a minor enrolls.

---

## 2. What to build

### 2a. `lxp_class` — new post type (the section a teacher runs)

Anchors a group of students to one teacher + one course. Registration code lives here.

| Meta key | Value |
|---|---|
| `lxp_class_teacher_id` | → `tl_teacher` post ID |
| `lxp_class_school_id` | → `tl_school` post ID (denormalized for scoping) |
| `lxp_class_course_id` | → `lp_course` post ID |
| `lxp_class_grade` | e.g. `"5th"` |
| `lxp_class_term` | e.g. `"2026-Fall"` |
| `lxp_class_reg_code` | short human code, e.g. `MRW-5A-3KF9` (unique) |
| `lxp_class_reg_code_expires` | datetime; null = open |
| `lxp_class_max_seats` | int cap |

### 2b. Token student — WP user, zero PII

Created on code redemption or roster import. **Never** collect real email/name/DOB here.

| Field | Value |
|---|---|
| `user_login` | UUID (e.g. `stu_8f3a1c…`) |
| `user_email` | system sink `stu_<uuid>@students.curriki.local` |
| `display_name` | teacher-chosen alias label (non-PII, e.g. `Student 14`) |
| role | **`lxp_student`** (new) |
| usermeta `lxp_is_token_student` | `1` |
| usermeta `lxp_no_marketing` | `1` — hard gate; token accounts never enter noptin/Mailgun |
| usermeta `lxp_class_id` | → `lxp_class` post ID |

Enrollment reuses LearnPress: insert into `learnpress_user_items` with the **token** `user_id`,
`item_id = course`, and `parent_id = lxp_class` post ID to tie the enrollment to the class.
Capstone/workbook rows are already keyed by `user_id` → automatically pseudonymous, **no change**.

### 2c. `dev_c_lxp_class_members` — membership + provisioning audit

```sql
CREATE TABLE dev_c_lxp_class_members (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  class_id       BIGINT UNSIGNED NOT NULL,      -- lxp_class post ID
  student_user_id BIGINT UNSIGNED NOT NULL,     -- token WP user
  alias_label    VARCHAR(64) NOT NULL,          -- non-PII, teacher-facing ("Student 14")
  joined_via     ENUM('code','roster') NOT NULL,
  status         ENUM('active','removed') NOT NULL DEFAULT 'active',
  created_at     DATETIME NOT NULL,
  KEY (class_id), KEY (student_user_id)
);
```

### 2d. `dev_c_lxp_roster_vault` — the encrypted `token → real name` map (Zone B)

One ciphertext blob per class. **The server never holds the plaintext or the decryption key.**
Envelope encryption: a random DEK encrypts the roster JSON; the DEK is wrapped twice.

```sql
CREATE TABLE dev_c_lxp_roster_vault (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  class_id           BIGINT UNSIGNED NOT NULL,
  teacher_user_id    BIGINT UNSIGNED NOT NULL,
  ciphertext         LONGBLOB NOT NULL,   -- AES-256-GCM({ token: "Real Name", ... })
  iv                 VARBINARY(16) NOT NULL,
  wrapped_dek_teacher LONGBLOB NOT NULL,  -- DEK wrapped by teacher KEK (passkey-PRF or passphrase)
  wrapped_dek_escrow  LONGBLOB NOT NULL,  -- DEK wrapped by district recovery key (KMS/HSM)
  kdf_params         JSON NULL,           -- Argon2id salt/params if passphrase path
  version            INT NOT NULL DEFAULT 1,
  updated_at         DATETIME NOT NULL,
  UNIQUE KEY (class_id)
);
```

- **Teacher KEK:** WebAuthn PRF from the teacher's passkey (preferred — the 2FA hardware *is* the
  key source) or Argon2id(passphrase) entered client-side. Server sees neither.
- **Escrow KEK:** district-held recovery key in a KMS, so a lost passkey doesn't destroy the roster.
  Break-glass + logged. Disclosed in the DPA.
- **2FA's role:** authorizes *release* of the blob server-side; the passkey PRF additionally *unlocks*
  it client-side. TOTP can gate access but cannot be the key (codes rotate).

---

## 3. Two zones

- **Zone A — server (pseudonymous):** token WP users, enrollment, quiz/capstone/workbook responses,
  `lxp_class`, `lxp_class_members`. Fully queryable by Curriki; contains no student PII.
- **Zone B — encrypted, client-only:** `lxp_roster_vault` ciphertext. Decrypts only in the teacher's
  browser via WebCrypto after 2FA. Curriki cannot read it.

**Tradeoff:** the server cannot search/sort/report on real student names (that's the point) — any
view needing real names is rendered in-browser after decryption. Since only the roster map is
identifying, and grades/progress stay in Zone A, this is workable.

---

## 4. Enrollment flows

**Registration code (preferred, lowest PII):** student enters class code + a teacher-assigned alias
(not a real name) → provision token account → enroll in course → append `{token: realName}` to the
class vault *client-side* when the teacher later labels them (or teacher pre-loads names).

**Roster upload:** teacher uploads CSV in-browser → split immediately: names encrypt into the vault
(Zone B), tokens + aliases provision accounts (Zone A). Raw CSV is never persisted server-side.

## 5. Build checklist

- [ ] `lxp_class` post type + meta + admin UI under a `tl_teacher`
- [ ] `lxp_student` role + token-account provisioner
- [ ] `dev_c_lxp_class_members`, `dev_c_lxp_roster_vault` tables
- [ ] Registration-code redemption endpoint → provision + enroll (`parent_id = class`)
- [ ] Browser WebCrypto module: passkey-PRF unlock, roster decrypt/edit/re-encrypt, escrow re-wrap
- [ ] Marketing-wall: enforce `lxp_no_marketing` everywhere noptin/Mailgun reads the user list
- [ ] DPA text covering escrow key + sub-processors
