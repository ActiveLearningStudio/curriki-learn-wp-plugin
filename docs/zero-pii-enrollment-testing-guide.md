# Zero-PII Enrollment — Manual E2E Testing Guide

Working checklist for testing the code-redemption feature (Zone A) and the encrypted roster vault (Zone B) end to end. Check off each `[ ]` as you go; note failures inline so this file doubles as a test log.

Related reading: [student-privacy-zone-a-context.md](student-privacy-zone-a-context.md), [student-privacy-zone-b-context.md](student-privacy-zone-b-context.md), [CLAUDE.md](../CLAUDE.md) gotchas 12–16.

---

## Known fixes already applied this cycle

Worth knowing before you start, since they explain why earlier test runs may have behaved differently:

- **Enrollment now goes through LearnPress's own `UserCourseModel::save()`** instead of a raw `$wpdb` insert — fixes stale "students enrolled" counts on course pages, and UTC `start_time`. See CLAUDE.md gotcha #12.
- **`reconcile_class_student_meta()`** — a class save no longer silently evicts token students from `lxp_student_ids` if they're absent from the modal's checkbox list. See gotcha #14 and Phase 5 below.
- **Post-join landing page is now `/student-courses/?class_code=…`** — it used to redirect into the class's first course. The `class_code` deep-links past the Student Courses widget's class-picker step. Filterable via `tl_lxp_redirect_after_join_url`.
- **Claim URLs now carry `class_code` too**, for the same reason: `/student-courses/?claim=…&class_code=…`.
- **New ticket screen after joining** — the claim link is now shown to the student with a bookmark prompt instead of being silently stashed in `localStorage` and redirected past. Text is editable in Elementor under the widget's *Ticket Screen* section.
- **New "LXP Bookmark Prompt" widget** — a standalone Elementor widget for the post-join landing page, with an "I've bookmarked it" button that strips the `claim` secret from the URL. Renders only when `?claim=` is in the URL (so effectively only right after a join); login-state visibility is left to Elementor's display conditions.
- **Prerequisite — two widgets on `/student-courses/`:**
  - **LXP Class Join** — required. Claim links point here and need it to process them; without it returning students cannot get back in at all. It renders only "You are signed in." for logged-in users, so it is easy to place and easy to forget.
  - **LXP Bookmark Prompt** — required for the bookmark step below. Set its display conditions as you like.
- **Missing `X-WP-Nonce` header** on teacher-only routes (`class/roster`, `class/roster/provision`, `class/member/reissue`, all `class/vault*`) — WordPress core silently demotes a cookie-authenticated request with no nonce to anonymous rather than erroring, which made `can_manage_class()` reject with "You are not allowed to manage this class." even when logged in as the class's own teacher. Fixed in `class-roster-modal.php` and `lxp-roster-vault.js`. See gotcha #16. **If you still see this error after pulling the fix, hard-reload the page** (the nonce is baked into the page's inline script at render time, so a cached page will carry a stale/missing one).

---

## Phase 0 — Setup

- [ ] **0.1** Visit any admin page once (fires `tl_lxp_maybe_upgrade_db()`), then confirm both new tables exist:
  ```sql
  SHOW TABLES LIKE '%lxp_class_members%';
  SHOW TABLES LIKE '%lxp_roster_vault%';
  DESCRIBE wp_lxp_class_members;
  DESCRIBE wp_lxp_roster_vault;
  ```
- [ ] **0.2** Confirm routes registered:
  ```bash
  wp rest route list --namespace=lms/v1 --fields=route,methods | grep -i "class/redeem\|class/claim\|class/seats\|class/code\|class/roster\|class/vault\|classes"
  ```
  Expect `/class/redeem`, `/class/claim`, `/class/seats`, `/class/code/settings`, `/class/roster`, `/class/roster/provision`, `/class/member/reissue`, `/class/vault`, `/class/vault/save`, `/class/vault/delete`.
- [ ] **0.3** Check LearnPress version:
  ```bash
  wp plugin list --name=learnpress --fields=name,status,version
  ```
  Confirmed dev environment is on **4.3.3**. If your test target differs, note it — the enrollment fix targets LP 4.2.5+; older LP falls back to the direct-write path.
- [ ] **0.4** Two browser contexts ready: a normal window as teacher/admin, and a **separate private/incognito window with no cookies** for the student. Redemption sets a new session cookie via `wp_set_auth_cookie()` — testing both roles in the same browser will log the teacher out.

---

## Phase 1 — Zone A: class setup and code redemption

- [ ] **1.1** Create a test class (teacher or admin dashboard). Attach 2 published LearnPress courses. Set `Max Seats = 3` and `Code Expires` = tomorrow. Save, note the class code.
  *(There is no Student Name Mode field any more — every class is nickname-typed.)*
  ```sql
  SELECT ID, post_title FROM wp_posts WHERE post_type = 'tl_class' ORDER BY ID DESC LIMIT 1;
  SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = <class_id> AND meta_key LIKE 'lxp_class%';
  ```
  Expect `lxp_class_code`, `lxp_class_max_seats=3`, `lxp_class_code_expires`, repeating `lxp_class_seat_labels`, and repeating `lxp_class_grades` inherited from the teacher's signup. `lxp_class_alias_mode` should be **absent** on a newly created class — it is vestigial and no longer written.

- [ ] **1.2** Open the class's Roster modal. Expect unclaimed seat labels (`Student 01`, `Student 02`, `Student 03`), no claim links yet. *(Seat labels are the Roster/claim-link flow and are unaffected by nickname joining.)*

- [ ] **1.3** Join (student, private window): enter the class code.
  - As soon as the 6th character lands, expect **"Joining: &lt;class name&gt;"** and the **nickname field to appear**. *(If the nickname field stays hidden, that is the `display:none` cascade bug returning — see §3b of the Zone A doc.)*
  - Type a nickname and submit.
  - Expect the **ticket screen** (not an immediate redirect): the nickname on the badge, the claim link shown as selectable text, a "Copy my link" button, the bookmark instruction, and a "Go to my class" button. **Save the link now** — it can never be re-shown.
  - Check the copy button works. On plain `http://` it falls back to `document.execCommand('copy')`, so test it on whatever protocol your dev site actually uses.
  - Click "Go to my class" → lands on `/student-courses/?claim=…&class_code=…`. Expect: the **⭐ Save this page!** banner (showing `Ctrl+D`, or `⌘D` on a Mac), and the Student Courses widget already open on **your class's courses**, not its class-picker step.
  - Click **"I've bookmarked it"** → banner disappears and the URL becomes `/student-courses/?class_code=…` with **no `claim` param**. Confirm the courses list stays put (no reload, still on your class).
  - ⚠️ Requires both **LXP Class Join** and **LXP Bookmark Prompt** widgets placed on `/student-courses/` — see the prerequisite note above.
  ```sql
  SELECT ID, user_login, user_email FROM wp_users WHERE user_login LIKE 'stu_%' ORDER BY ID DESC LIMIT 1;
  SELECT meta_key, meta_value FROM wp_usermeta WHERE user_id = <new_user_id> AND meta_key LIKE 'lxp_%';
  ```
  Expect: login `stu_<12 hex>`, sink email, **no first/last name**, `lxp_is_token_student=1`, `lxp_no_marketing=1`, `lxp_class_id`.
  ```sql
  SELECT * FROM wp_lxp_class_members WHERE class_id = <class_id>;
  ```
  Expect one row, `joined_via='code'`, `status='active'`, 64-hex `claim_token_hash`, `consent_teacher_id`/`consent_school_id` populated.
  ```sql
  SELECT ID FROM wp_posts WHERE post_type = 'tl_student' ORDER BY ID DESC LIMIT 1;
  SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = <student_post_id>;
  ```
  Expect `post_title` = alias, `student_id` meta = token login, no `lxp_student_password`.
  ```sql
  SELECT * FROM wp_postmeta WHERE post_id = <class_id> AND meta_key = 'lxp_student_ids';
  ```
  Expect the new `tl_student` post ID appended.

- [ ] **1.4** Verify LearnPress enrollment:
  ```sql
  SELECT user_item_id, user_id, item_id, item_type, status, graduation, ref_type, parent_id, start_time
  FROM wp_learnpress_user_items WHERE user_id = <new_user_id>;
  ```
  Expect 2 rows (one per course), `item_type='lp_course'`, `status='enrolled'`, `graduation='in-progress'`, `ref_type=''`, `parent_id=0`. `start_time` should be **UTC** — compare against `SELECT UTC_TIMESTAMP();` run at the same moment.
  ```sql
  SELECT ui.user_item_id, uim.meta_key, uim.meta_value
  FROM wp_learnpress_user_items ui
  JOIN wp_learnpress_user_itemmeta uim ON uim.learnpress_user_item_id = ui.user_item_id
  WHERE ui.user_id = <new_user_id> AND uim.meta_key = '_lxp_class_id';
  ```
  Expect the class ID on both rows.

- [ ] **1.5** ⭐ **Priority check.** Confirm the course's "N students enrolled" count updates immediately on its admin/public page — no manual cache clear. This is the main thing the LP-model enrollment rewrite fixed; if it's still stale, that's a real regression.

- [ ] **1.6** (Optional) Confirm `learn-press/assigned-course-to-user` fires — add a temporary debug-log probe and redeem another seat, or check any LP addon that hooks enrollment (certificates, stats).

- [ ] **1.7** In the private/student window, visit LP's "My Courses". Both courses should be listed and enterable — proves LP itself (not just our tables) sees the enrollment.

---

## Phase 2 — Claim link resume (idempotency)

- [ ] **2.1** Log the student out, or open a second private window.
- [ ] **2.2** Visit the claim URL from 1.3. Expect: signs back into the **same** user ID, no new `tl_student` post, no new `lxp_class_members` row.
  ```sql
  SELECT COUNT(*) FROM wp_posts WHERE post_type='tl_student'; -- unchanged
  SELECT COUNT(*) FROM wp_lxp_class_members WHERE class_id = <class_id>; -- still 1
  SELECT claim_last_used FROM wp_lxp_class_members WHERE id = <member_id>; -- now populated
  ```
- [ ] **2.3** If possible, complete a lesson as the token student, then resume via the claim link again — confirm progress persists (this exercises the "no `delete_user_items_old()`" decision).

---

## Phase 3 — Reject paths

For each, confirm: no new user, no new `tl_student` post, no new `lxp_class_members` row, seat count unchanged, and the **error message is the same generic text** regardless of cause (anti-enumeration).

- [ ] Wrong code
- [ ] Revoked code (toggle `Revoke`, try the valid code)
- [ ] Expired code (set `Code Expires` to yesterday)
- [ ] Class full (redeem until `max_seats` hit, try one more)
- [ ] Nickname too short (`a`) and too long (33+ characters)
- [ ] Email-shaped nickname (`student@test.com`)
- [ ] Phone-shaped nickname (`5551234567`)
- [ ] Duplicate nickname — two students typing the same thing. The second must become `<nickname> 2`, **not** an error, and must be a separate account
- [ ] Rate limit (>10 redeem/claim attempts in <10 min from one IP — the 11th should fail)
- [ ] **Legacy class regression:** pick a class created before nickname-only joining (`SELECT post_id FROM wp_postmeta WHERE meta_key='lxp_class_alias_mode' AND meta_value='assigned'`). A typed nickname must be **accepted**. If it comes back rejected, `resolve_alias()` is still branching on the old meta.
- [ ] **Seat cap regression:** pick a class created before the 150-seat cap (`SELECT post_id FROM wp_postmeta WHERE meta_key='lxp_class_max_seats' AND meta_value='0'`). It must behave as a **150**-seat class, not an unlimited one — the class list shows `N / 150`, not `N / ∞`, and the modal rehydrates `Max Seats` as `150`. If anything still shows `0` or `∞`, a `get_post_meta()` call bypassed `lxp_get_class_max_seats()`.
- [ ] **Cap cannot be exceeded:** POST `lxp_class_max_seats=9999` straight to `/lms/v1/classes/save` (bypassing the modal's `max=` attribute). Re-read the class — it must store `150`.

> Each rejection must leave **no** WP user, **no** `tl_student` post and **no** `lxp_class_members` row, and must not consume a seat.
>
> Messages: bad nickname / seat taken / class full / rate limited each say what is actually wrong. Wrong, revoked and expired codes must all return the **same** generic text — that indistinguishability is deliberate anti-enumeration, so if you can tell them apart, that is a regression.

---

## Phase 4 — Roster provisioning (teacher pre-creates seats)

- [ ] **4.1** From the roster modal, provision 5 aliases.
  ```sql
  SELECT alias_label, joined_via, status FROM wp_lxp_class_members WHERE class_id = <class_id> AND joined_via = 'roster';
  ```
  Expect 5 rows, `joined_via='roster'`, each with a claim link.
- [ ] **4.2** Re-submit the same 5 aliases — expect no duplicates.
- [ ] **4.3** A student redeems one pre-provisioned claim link — confirm it resumes that specific seat.

---

## Phase 5 — Reconciliation fix (class-save clobber bug)

- [ ] **5.1** Confirm the Phase 1 token student is in `lxp_student_ids` and `active` in `lxp_class_members`.
- [ ] **5.2** Open the class in the teacher/admin modal, save **without touching the Students checkbox list** (e.g. edit an unrelated field).
- [ ] **5.3** ⭐ **Priority check.**
  ```sql
  SELECT meta_value FROM wp_postmeta WHERE post_id = <class_id> AND meta_key = 'lxp_student_ids';
  ```
  The token student's post ID **must still be present**. If gone, `reconcile_class_student_meta()` isn't firing from `classes.php::create()` — report back.
- [ ] **5.4** Confirm the Student Courses widget still shows the student after that save.

---

## Phase 6 — Token-mode gate

- [ ] **6.1** Enable `Student privacy` / token mode on the test school.
- [ ] **6.2** Attempt legacy 4-column CSV import against that school — expect **403**.
- [ ] **6.3** Attempt Manage Students → add/edit with a real name for that school — expect the same refusal.
- [ ] **6.4** Confirm a school WITHOUT token mode is unaffected — legacy CSV and manual add/edit work as before.

---

## Phase 7 — Zone B: encrypted roster vault

- [ ] **7.1** As teacher, set up the vault with a passphrase you'll remember for this session.
- [ ] **7.2** Enter real names for the 5 roster-provisioned seats from Phase 4, save.
  ```sql
  SELECT class_id, LEFT(ciphertext,60) AS ciphertext_preview, LENGTH(ciphertext), version FROM wp_lxp_roster_vault WHERE class_id = <class_id>;
  ```
  `ciphertext_preview` must be unintelligible base64. **If you can read a name in it, stop testing immediately — critical finding.**
- [ ] **7.3** Reload the page — names hidden/blank until unlocked again.
- [ ] **7.4** Unlock with the correct passphrase — 5 real names reappear, correctly paired to aliases.
- [ ] **7.5** Unlock with a WRONG passphrase — fails generically, no hint of "how close" it was.
- [ ] **7.6** ⭐ **Priority check — IV freshness.** Save the vault twice in a row (edit a name, save; edit back, save). Compare the `iv` column between saves — **must differ**. Identical `iv` under one key across two saves is a critical crypto bug (GCM nonce reuse) — stop and report immediately.
- [ ] **7.7** ⭐ **Priority check — concurrency.** Open the roster modal in two tabs as teacher. Unlock + edit + save in Tab A. Then edit a different name in Tab B (stale `version`) and save — Tab B must get a 409/"reload before saving", not silently overwrite Tab A.
- [ ] **7.8** District escrow (if a district recovery key is configured): save the vault, confirm `wrapped_dek_escrow` populates.
  ```sql
  SELECT wrapped_dek_escrow IS NOT NULL AS has_escrow FROM wp_lxp_roster_vault WHERE class_id = <class_id>;
  ```
  Full recovery-without-passphrase is already proven by `tests/roster-vault.test.js` (real generated RSA keypair) — you don't need to re-prove it manually unless you want to test with your actual district key.
- [ ] **7.9** Remove/blank the district key, save a vault fresh on a different test class — confirm the bold "forgotten passphrase = permanent data loss" warning shows when no recovery key is configured.
- [ ] **7.10** CSV import: use a `first_name,last_name[,alias_label]` file. Confirm in dev-tools Network tab that only `/class/roster/provision` fires and it carries aliases only, never names. Try a file with a banned header (`student_id`, `email`, etc.) — must be **rejected with the offending column named**, not silently stripped.

---

## Phase 8 — Regression

- [ ] **8.1** Legacy 4-column CSV import (non-token-mode school) — unaffected.
- [ ] **8.2** Student-ID kiosk login for pre-existing students — unaffected.
- [ ] **8.3** Student Courses / Student Access widgets for regular students — unaffected.
- [ ] **8.4** A normal (non-token) student's LearnPress enrollment via your regular purchase/checkout flow — unaffected by the `TL_Enrollment_Repository` change (spot-check one if you have a free-course or test-payment flow).

---

## Reporting back

Note pass/fail per phase directly in this file (or a copy of it). Priority order if you only have time for a subset:

1. **1.5** — enrolled-count cache
2. **5.3** — reconciliation
3. **7.6** — IV freshness (critical if it fails)
4. **7.7** — concurrency 409
5. **3** — reject paths / anti-enumeration
