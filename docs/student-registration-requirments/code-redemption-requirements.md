# Code-Redemption Endpoint — Requirements

**One job:** a student submits a class registration code, and the system provisions a **pseudonymous
token account**, enrolls it in the class's course with `parent_id = lxp_class`, and starts a session —
**without collecting or storing any student PII.**

This is a requirements spec, not an implementation. It defines *what must be true*, the inputs/outputs,
the rules, and the edge cases — so the eventual LearnPress hook has a fixed target.

---

## 1. Actors & trust

| Actor | Role in this flow |
|---|---|
| **Teacher** | Pre-creates the `lxp_class` + registration code offline. **Their act of issuing the code is the COPPA school-consent gate** — the endpoint trusts a valid code = authorized enrollment. |
| **Student** | Unauthenticated browser (may be a minor). Supplies only a code + a non-PII alias. |
| **Endpoint** | Validates, provisions token, enrolls, starts session. Writes only to Zone A. |
| **Roster vault (Zone B)** | **Out of scope for this endpoint.** The `alias → real name` map is added later by the teacher, client-side. The endpoint never sees a real name. |

---

## 2. Inputs (from the student, unauthenticated)

| Field | Required | Notes |
|---|---|---|
| `reg_code` | yes | The class code, e.g. `MRW-5A-3KF9`. |
| `alias_label` | yes | Non-PII display label. Either teacher-preassigned (a seat number the student was handed) **or** student-chosen from a constrained set. **Must not accept a free-text real name.** |
| anti-abuse token | yes | Rate-limit / bot signal (see §6). No CAPTCHA that the assistant solves. |

**Explicitly NOT collected:** real name, email, date of birth, address, phone. If any such field
exists in the form, it is a requirement violation.

---

## 3. Success outputs (all in Zone A)

1. **Token WP user** — role `lxp_student`; `user_login` = UUID; `user_email` = system sink
   `stu_<uuid>@students.curriki.local`; `display_name` = `alias_label`; usermeta
   `lxp_is_token_student=1`, `lxp_no_marketing=1`, `lxp_class_id`.
2. **Membership row** — `lxp_class_members(class_id, student_user_id, alias_label, joined_via='code', status='active', created_at)`.
3. **Enrollment row** — `learnpress_user_items(user_id=token, item_id=course, item_type='lp_course', status='enrolled', parent_id=lxp_class, start_time=now)`.
4. **Session** — the token account is signed in and redirected to the course.
5. **Return credential** — a per-student re-login secret is issued (see §5).

**Never produced by this endpoint:** any row containing a real name/email; any marketing-list entry;
any write to the encrypted roster vault.

---

## 4. Validation rules (ordered; fail closed)

1. `reg_code` exists → else `invalid_code`.
2. Class `status='active'` and code not revoked → else `code_revoked`.
3. `reg_code_expires` is null or in the future → else `code_expired`.
4. Active members `< max_seats` → else `class_full`.
5. `alias_label` passes the non-PII constraint (allowed charset/length; not an email pattern) → else `bad_alias`.
6. Anti-abuse / rate-limit check passes → else `rate_limited`.

Only after **all** pass are the three writes (token, membership, enrollment) performed as **one atomic
unit** — partial provisioning (a user with no enrollment, or a seat consumed with no user) is a
requirement violation. On any write failure, roll back all three.

---

## 5. Returning students (re-authentication requirement)

Token accounts have no usable email and no student-known password, so re-login must work **without PII**:

- **Requirement:** each token account gets a **unique per-student access secret** (a short PIN or a
  QR/claim link) generated at provisioning and surfaced **once** to the teacher for distribution.
- The class code alone must **not** be sufficient to log back into an existing account (classmates
  share the class code) — re-login requires `alias_label` + the per-student secret, or the claim link.
- **Decision to confirm:** PIN-per-student vs. QR/claim-link vs. teacher-mediated reset. Recommended:
  per-student claim link the teacher hands out; falls back to teacher re-issue if lost.

---

## 6. Non-functional requirements

- **No PII, ever** — enforced at the schema and form level, not just policy.
- **Idempotency / no duplicate seats** — a returning student presenting a valid claim credential
  **resumes** their existing token account; they do **not** get a second account or consume a second seat.
- **Brute-force resistance** — code entropy + `max_seats` cap + expiry + per-IP/per-code rate limit.
  A guessed-but-full or expired code yields no account.
- **Minimal audit** — log provisioning events (class_id, token id, `joined_via`, timestamp) for the
  teacher/school. **Do not** store raw student IP or device fingerprints as identifying data (minimize;
  hash or omit — it may be a minor's PII).
- **Marketing wall** — `lxp_no_marketing=1` set atomically with account creation; noptin/Mailgun list
  queries must exclude it.
- **Consent provenance** — record that enrollment happened via a teacher-issued code (the school-consent
  basis), tying the token account to the issuing `tl_teacher`/`tl_school`.

---

## 7. Edge cases → expected behavior

| Case | Behavior |
|---|---|
| Invalid / revoked / expired code | Reject before any write; generic message; no account. |
| Class at `max_seats` | Reject `class_full`; no account, no seat consumed. |
| Alias collides with existing member | Allow but disambiguate (e.g. append suffix) **or** reject `bad_alias` — decision to confirm. |
| Free-text real name entered as alias | Reject `bad_alias`; do not persist the value. |
| Returning student with valid claim credential | Resume existing token + session; no new account. |
| Returning student, lost credential | Teacher re-issues; endpoint does not self-serve via PII. |
| Write fails mid-provision | Atomic rollback of all three writes. |
| Code brute-forced | Rate-limit + entropy make it infeasible; failures are not distinguishable per reason to the client. |

---

## 8. Explicit non-goals (handled elsewhere)

- Writing the `alias → real name` map (teacher, client-side, Zone B vault).
- Roster CSV upload (separate teacher flow; splits names → vault, tokens → Zone A).
- Teacher/class creation and code generation (admin flow).
- Any parental-consent UX beyond the school-consent-by-code model.
