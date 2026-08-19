# Zero-Knowledge Student Enrollment — Zone A Context

Session context: completed August 2026.

Lets a student enroll in a LearnPress course by redeeming a class registration code, **without the server collecting or storing any student PII** — the COPPA/FERPA "zero knowledge" model requested by the client.

Builds on [Class–Course Association](class-course-association-context.md) and [Student Identity & Access](student-identity-and-access-context.md).

---

## 1. The two zones

| Zone | Contents | Who can read it |
|---|---|---|
| **A — server (pseudonymous)** | Token WP users, `lxp_class_members`, LearnPress enrollment, quiz/capstone/workbook responses | Curriki, fully queryable. Contains **no student PII**. |
| **B — encrypted, client-only** | The `alias → real name` map | The teacher's browser only |

**Zone B now exists** (passphrase-based) — see [student-privacy-zone-b-context.md](student-privacy-zone-b-context.md). Names are keyed on `lxp_class_members.id`, which is the only coupling between the two zones.

**Consent basis:** the teacher issuing the code *is* the COPPA school-consent gate. A valid code is treated as authorized enrollment, and the issuing `tl_teacher` / `tl_school` are recorded on every membership row as consent provenance.

> Legal note: the FTC's 2025 COPPA amendments (effective 22 April 2026) deliberately **did not codify** a school-authorization exception — the Commission deferred to the Dept. of Education's pending FERPA rulemaking. The school-permission pathway rests on FTC guidance, not rule text, so DPA language must not cite a codified exception.

---

## 2. What was reused, not rebuilt

The client's spec assumed a greenfield `lxp_class` CPT and a new `lxp_student` role. Both already existed:

| Spec asked for | Used instead |
|---|---|
| new `lxp_class` CPT | existing `tl_class` ([class-class-post-type.php](../lms/class-class-post-type.php)) |
| `lxp_class_reg_code` | existing `lxp_class_code` (6-char, unambiguous alphabet) |
| `lxp_class_course_id` (single) | existing `lxp_class_course_ids` (multi) |
| new `lxp_student` role | already registered in [class-tiny-lxp-platform-tool.php:717](../includes/class-tiny-lxp-platform-tool.php#L717) |

---

## 3. Two deliberate divergences from the spec

### 3a. `parent_id` is **not** the class ID

The spec asked for `learnpress_user_items.parent_id = lxp_class` post ID. LearnPress already owns that column: for a course row it is `0`, and for lesson/quiz rows it holds the parent **course** `user_item_id`. Writing a class post ID there would collide with LP's own child-item lookups.

**Instead:** `parent_id` stays `0`; the class link lives in `learnpress_user_itemmeta` under **`_lxp_class_id`**, plus the `lxp_class_members` row. Query it via `TL_Enrollment_Repository::get_courses_for_class()`.

Confirmed against LearnPress 4.3.3 source: LP's own bulk-enrol tool (`LP_REST_Admin_Tools_Controller::assign_courses_to_users()`) also leaves `parent_id` at `0` on course rows, so this is alignment with LP rather than a workaround.

### 3a-bis. Enrollment goes through LP's model, not a raw INSERT

`TL_Enrollment_Repository::enroll()` builds a `LearnPress\Models\UserItems\UserCourseModel` and calls `save()` — the same path as LP's admin *Assign courses to users* tool. This matters because `UserCourseModel::clean_caches()` clears more than the user-item caches: it also clears `clean_total_students_enrolled()`, `clean_total_students_enrolled_or_purchased()` and the `LP_Courses_Cache::KEYS_COUNT_STUDENT_COURSES` group. A raw INSERT leaves all three stale, so course pages keep showing the old enrolled-student count.

Details worth knowing:

- **`ref_type` is set to `''`.** `UserCourseModel` defaults it to `LP_ORDER_CPT`; a redeemed class seat has no order behind it. LP's own assign tool clears it the same way.
- **Times are UTC** (`gmdate`), which is LP's convention — not `current_time( 'mysql' )`.
- **`access_level` is not written.** It is absent from `UserItemsFilter::$all_fields`, so LP's model never writes it either; the column defaults to `50`.
- **We fire `learn-press/assigned-course-to-user`**, matching LP's assign tool. We deliberately do **not** fire `learnpress/user/course-enrolled` — that is the *purchase* path, its first argument is an order ID we do not have, and it triggers enrollment email to what is a sink address for a token student.
- **We do not call `delete_user_items_old()` first.** LP's tool deletes and recreates, wiping progress on every re-assign; a student resuming a claim link must keep theirs, so `enroll()` no-ops when a row already exists.
- **The direct `$wpdb` write survives as a fallback** for LP older than 4.2.5 (before `UserCourseModel` existed), guarded by `class_exists()`. `flush_lp_caches()` now serves only that path.

### 3b. "No free-text real name" is enforced by removing the text field

No regex can reject "Maria Garcia" while accepting "Student Fourteen". So classes default to **`alias_mode = assigned`**: the student picks an unclaimed label from the teacher's seat pool via a dropdown — there is no free-text input to abuse. `open` mode (student types a nickname) is opt-in per class and backstopped by `Rest_Lxp_Class_Redemption::looks_like_pii()`, which rejects email- and phone-shaped values.

---

## 4. Data model

### New meta on `tl_class`

| Key | Value |
|---|---|
| `lxp_class_max_seats` | int; `0` = unlimited |
| `lxp_class_code_expires` | datetime; empty = never |
| `lxp_class_code_revoked` | `'1'` or `''` |
| `lxp_class_alias_mode` | `assigned` (default) or `open` |
| `lxp_class_seat_labels` | repeating — the alias pool (`Student 01`…) |

Written through the existing `Rest_Lxp_Class::create()` (behind `POST /lms/v1/classes/save`), guarded by a `lxp_class_code_controls` hidden field so checkbox-absence isn't misread as "unchecked" by other callers. Returned by `get_one()`.

### New meta on `tl_school`

`lxp_school_token_mode` — when set, the legacy name-collecting flows are refused (see §7).

### New table `{prefix}lxp_class_members`

Created by `tl_lxp_install_tables()` in [TinyLxp-wp-plugin.php](../TinyLxp-wp-plugin.php), run both from `on_activate()` and from a `tl_lxp_db_version` check on `plugins_loaded` — so **no deactivate/reactivate is needed on production**. Bump `TL_LXP_DB_VERSION` when the schema changes.

```
id, class_id, student_post_id, student_user_id, alias_label,
joined_via('code'|'roster'), status('active'|'removed'),
claim_token_hash, claim_issued_at, claim_last_used,
consent_teacher_id, consent_school_id, created_at
UNIQUE (class_id, alias_label), UNIQUE (claim_token_hash)
```

### Token accounts

| Object | Notable fields |
|---|---|
| WP user | `user_login` = `stu_<12 hex>`, `user_email` = `{login}@students.curriki.local`, `display_name` = alias, role `lxp_student`. **No `first_name` / `last_name`.** Usermeta: `lxp_is_token_student=1`, `lxp_no_marketing=1`, `lxp_class_id` |
| `tl_student` post | `post_title` = alias (never a name), `student_id` meta = the UUID login, `lxp_student_admin_id`, `lxp_student_school_id`, `lxp_teacher_id`, `grades` inherited from the class. **No `lxp_student_password`** |

> `student_id` is now an **opaque internal handle**, not a district SIS number. A real SIS ID is a stable external identifier — anyone with SIS access could re-identify every row — so it is never collected. The meta key is kept because every dashboard, grade and class query resolves students through it.

### Class ↔ student association: two records, on purpose

A class's membership is recorded **twice**, and both are needed:

| | `lxp_student_ids` (post meta) | `lxp_class_members` (table) |
|---|---|---|
| Holds | `tl_student` post IDs | post ID + user ID + alias + claim hash + consent trail |
| Read by | every existing dashboard, widget, grade and assignment query | seats, roster modal, vault |
| Written by | the class modal save | code redemption / seat provisioning |
| Authoritative for | student *visibility* | **seat count** |

The direction of assignment is inverted between the two flows. The old one is **teacher-push** — the student must exist first, then the teacher ticks them in the class modal. Redemption is **student-pull** — nobody pre-creates anything. Same storage, opposite flow.

`provision_member()` writes both, so token students are visible to surfaces that have never heard of the members table.

> **The clobber, and how it's closed.** `Rest_Lxp_Class::create()` rebuilds `lxp_student_ids` wholesale from the modal's checkbox list — `delete_post_meta()` then re-add. A token student missing from that list would be silently evicted from the meta while the table still called them active: seat still consumed, roster still showing them, but gone from the Student Courses widget with no error anywhere.
>
> `Rest_Lxp_Class_Redemption::reconcile_class_student_meta()` now runs at the end of every class save. It re-adds any active member the rebuild missed and drops any the table marks removed. **Students with no row in the members table are left completely untouched** — it only ever speaks for token members.
>
> `TL_Class_Member_Repository::set_removed()` does *not* touch the meta. Any future "remove student" flow must call the reconciler afterwards, or the student lingers in every dashboard.

---

## 5. REST API

All in `Rest_Lxp_Class_Redemption` ([class-redemption.php](../lms/lms-rest-apis/class-redemption.php)), registered in `LMS_REST_API::init()`.

| Route | Auth | Purpose |
|---|---|---|
| `POST /lms/v1/class/redeem` | public | code + alias → provision + enroll + sign in |
| `POST /lms/v1/class/claim` | public | claim token → resume account, no new seat |
| `POST /lms/v1/class/seats` | public | open seat labels for a code (feeds the dropdown) |
| `POST /lms/v1/class/code/settings` | teacher | seats, expiry, revoke, alias mode, regenerate |
| `POST /lms/v1/class/roster` | teacher | roster view |
| `POST /lms/v1/class/roster/provision` | teacher | pre-create N seats (or explicit aliases) |
| `POST /lms/v1/class/member/reissue` | teacher | mint a fresh claim link |

Teacher routes check `can_manage_class()` — `manage_options`, or the current user being the class's `lxp_class_teacher_id` → `lxp_teacher_admin_id`.

### `redeem()` — ordered, fail-closed

1. rate limit → `rate_limited`
2. code resolves to a published `tl_class` → `invalid_code`
3. not revoked → `code_revoked`
4. not expired → `code_expired`
5. active members < `max_seats` → `class_full`
6. alias valid + available → `bad_alias` / `seat_taken`

Then **one atomic unit**: `START TRANSACTION` → WP user → `tl_student` post → meta → membership row → `lxp_student_ids` → enroll in every `lxp_class_course_ids` → `COMMIT`. On failure: `ROLLBACK` **plus explicit compensating deletes**, because some hosts still run LearnPress tables on MyISAM where the transaction alone would not roll the enrollment back.

The client always receives the **same generic message**; the reason is returned as a machine `code` for logging only, so failures cannot be told apart by a guesser.

---

## 6. Returning students

Each token account gets a per-student **claim link** (`?claim=<48 hex>`), shown once at provisioning. Only its SHA-256 is stored (`claim_token_hash`), so the server cannot reproduce a lost link — the teacher re-issues via `/class/member/reissue`, which rotates the secret and invalidates the old link.

The class code alone is **not** sufficient to log back in: classmates share it. Redeeming again would consume a second seat, and in `open` alias mode would mint an unlimited number of duplicate accounts, since nothing ties a browser to a seat it already holds.

### Claim URL shape

```
/student-courses/?claim=<48 hex>&class_code=<6 chars>
```

`class_code` is not decoration: the Student Courses widget is a two-step UI (class picker → that class's courses) and reads `?class_code=` on load to open straight on step 2. A token student is normally in exactly one class, so the picker is pure friction. Both `build_claim_url()` and `landing_url()` append it.

### The ticket screen, and the bookmark hand-off

Because the raw link exists for exactly one moment — the response to `redeem()` — the join widget does **not** redirect straight through it. On success it swaps the form for a *ticket screen* showing the seat label, the link as selectable text (also a real `<a href>`, so browser "Bookmark link" works), a copy button, and a K12-facing instruction to save it.

**Continue then navigates to the claim URL itself, not to `redirect_url`.** That is deliberate and worth not "tidying up": no browser exposes an API to add a bookmark — `window.external.AddFavorite` (IE) and `window.sidebar.addPanel` (Firefox, removed in 23) are both long dead, and a synthesised Ctrl+D `KeyboardEvent` is untrusted so browser chrome ignores it. The only thing that works is the user's own Ctrl+D on the page they are standing on — which means the student has to be *standing on* the claim URL. Sending them to a clean `/student-courses/` would put the wrong URL in the address bar and make any bookmark prompt actively misleading.

On arrival, a **separate** widget — `LXP_Bookmark_Prompt_Widget` ([lxp-bookmark-prompt-widget.php](../includes/widgets/lxp-bookmark-prompt-widget.php), Elementor name `lxp-bookmark-prompt`) — shows the banner: Ctrl+D, swapped to ⌘D on Apple platforms, with an **"I've bookmarked it"** button. That button is a confirmation, not a dismissal — clicking it `history.replaceState()`s the `claim` param out of the URL while **keeping `class_code`**, so the secret leaves both the address bar and that history entry, and the courses widget stays on the student's class. Bookmark first, confirm second; the wording has to keep carrying that order, which is why the widget ships an editor warning against turning it into a close button.

It is a standalone widget rather than part of the join widget so placement and login-state visibility stay with the page author, via Elementor's own display conditions. The one thing it *does* gate on internally is `$_GET['claim']` — that is functional, not a visibility rule: with no token in the URL there is nothing to bookmark and nothing to strip. It renders regardless inside the Elementor editor, so it can be selected and styled.

In practice the banner appears once, right after a join. A *returning* student is signed out, so they take the normal claim path instead: token posted, account resumed, JS redirects to a clean `landing_url()` with no `claim` param left to trigger it.

An earlier build stashed the link in `localStorage` and redirected immediately, which meant a student was never shown the one credential they need to come back — clearing site data or moving to another device stranded them with no self-service recovery. The `localStorage` stash is retained as a same-device fallback, but the visible screen is the actual mechanism.

Its text is Elementor-editable under the widget's **Ticket Screen** section, so schools can reword it for their reading level without touching code.

> A self-service "show me a new link" button for an already-logged-in student is deliberately **not** built. It could only ever rotate the secret (the old one being unrecoverable), so it would silently invalidate any link the student had already saved elsewhere. Recovery stays with the teacher, who can see the whole roster before rotating.

---

## 7. The legacy student flows are gated, not just unused

`import()` and `save_update()` in [students.php](../lms/lms-rest-apis/students.php) write real names into `post_title` and `wp_users.first_name`/`last_name`, plus a **plaintext password** into `lxp_student_password`. Left open, a teacher could undo the entire privacy model by picking the wrong upload button.

Both now call `Rest_Lxp_Student::is_token_mode_school()` and return **403** when the school has `lxp_school_token_mode` set. Schools without the flag are completely unaffected — the existing 4-column CSV import and Student-ID kiosk login behave exactly as before.

Toggle: **Admin → Schools → edit → Student privacy** checkbox (`admin-school-modal.php`, saved via `/shools/save`, read back via `schools.php::get_one()`).

> The existing `access_login()` kiosk flow depends on plaintext `lxp_student_password` and is **unchanged** — it simply never applies to token accounts, which have no such meta.

---

## 8. UI

| Surface | File |
|---|---|
| Join form (student) | [lxp-class-join-widget.php](../includes/widgets/lxp-class-join-widget.php) — Elementor `lxp-class-join` |
| Bookmark prompt (student) | [lxp-bookmark-prompt-widget.php](../includes/widgets/lxp-bookmark-prompt-widget.php) — Elementor `lxp-bookmark-prompt` |
| Roster + claim links (teacher) | [class-roster-modal.php](../lms/templates/tinyLxpTheme/lxp/class-roster-modal.php) |
| Code controls | `teacher-class-modal.php`, `admin-class-modal.php` |
| Seats badge + Roster button | `teacher-classes.php`, `admin-classes.php` |
| Token-mode toggle | `admin-school-modal.php`, `admin-schools.php` |

**The join widget contains no name, email, DOB or password input anywhere in its markup.** That absence is the form-level enforcement the spec requires — it is not a validation rule that can be bypassed. It also handles `?claim=` (resume) and `?class_code=` (deep link) from the URL.

Claim links can only be printed in the session they were minted, since the server holds hashes.

---

## 9. Anti-abuse

- Per-IP: 10 attempts / 10 min. Per-code: 60 / hour. Transient-backed.
- **The client IP is hashed (`wp_hash()`) and used only as a transient key — never stored.** It may itself be a minor's personal data.
- Audit events fire on the `tl_lxp_enrollment_audit` action (`event, class_id, user_id, joined_via, timestamp`) — deliberately **no IP, no device fingerprint**.

---

## 10. Marketing wall — partial by necessity

`noptin` / Mailgun do not exist anywhere in this plugin, so the wall cannot be enforced here. What ships:

- `lxp_no_marketing=1` set atomically with account creation
- `lxp_is_no_marketing_user( $user_id )` and `lxp_get_marketing_excluded_users()` in [lxp/functions.php](../lms/templates/tinyLxpTheme/lxp/functions.php)
- filters `tl_lxp_user_no_marketing`, `tl_lxp_marketing_excluded_users`

**Enforcement in the mailing stack is a separate task on whichever plugin owns it.** This one can only flag.

---

## 11. Not built yet

- **WebAuthn PRF unlock** — the vault ships passphrase-only. PRF is an additive upgrade; see §7 of the Zone B doc.
- Parental notice / opt-out flow (diagram 2), deletion-request routing, DPA text.
- Marketing enforcement in whichever plugin owns noptin/Mailgun (§10).

---

## 12. Gotchas

1. `parent_id` on `learnpress_user_items` is **0**, not the class ID — see §3a. Use `_lxp_class_id` itemmeta.
2. Enrollment goes through LP's `UserCourseModel::save()`, which invalidates its own caches — see §3a-bis. Do not "simplify" it back to a raw `$wpdb->insert()`: that silently leaves LP's per-course student-count caches stale. `flush_lp_caches()` is the pre-4.2.5 fallback only.
3. Claim links are unrecoverable by design. "Print claim slips" only prints links minted in the current modal session.
4. `lxp/functions.php` is **not** loaded in REST context — REST callbacks must use `TL_Class_Member_Repository` directly rather than `lxp_get_class_seats_taken()`.
5. Seat labels auto-grow only for unlimited-seat classes. A capped class that runs out returns `class_full`; raise `lxp_class_max_seats` (which re-syncs the pool) rather than editing labels.
6. `alias_mode = open` still allows a determined student to type something name-like. Only `assigned` is structurally safe — keep it as the default.
7. **Seat-count race:** two students submitting simultaneously against the last seat can over-fill a class by one. The `UNIQUE (class_id, alias_label)` index closes the *alias* race hard, so nobody ever shares a seat label; only the count can drift, and only by one. Accepted rather than serialised behind a lock — over-enrolling one student is far less damaging than blocking a whole class on a lock timeout.
8. Class membership lives in **two** places (`lxp_student_ids` meta + `lxp_class_members`) and that is deliberate, not redundancy — see §4. Any code that rewrites `lxp_student_ids` wholesale must call `Rest_Lxp_Class_Redemption::reconcile_class_student_meta()` afterwards.
9. Rate limiting uses two separate counters: `write` (redeem/claim, 10 per 10 min) and `lookup` (seat reads, 60 per 10 min). The join form calls the lookup as the student types, so it must never consume the write budget — and the widget also dedupes repeat lookups of the same code.
10. **Teacher-only routes need `X-WP-Nonce` explicitly** — `roster`, `roster/provision`, `member/reissue`, all `vault*` routes, and anything else gated by `can_manage_class()`. This codebase's REST routes register `permission_callback => '__return_true'` and check `is_user_logged_in()`/ownership *inside* the callback, so nothing at the route level requires a nonce — but WP core's own `rest_cookie_check_errors()` (`wp-includes/rest-api.php`) still silently demotes any cookie-authenticated request with no `X-WP-Nonce` header to anonymous (`wp_set_current_user(0)`) rather than erroring. `can_manage_class()` then sees a logged-out request and rejects with the generic "not allowed" message — which reads exactly like an ownership bug, not a missing-header one. Fixed for the roster modal and `RosterVault._post()` by threading `wp_create_nonce('wp_rest')` through as `restNonce` / `opts.nonce`; any *new* teacher-only call must do the same. Public routes (`redeem`, `claim`, `seats`) are unaffected — they are meant to work logged-out.
