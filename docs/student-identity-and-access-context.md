# Student Identity & Access — Feature Context

Session context: completed June 25, 2026.

Covers four connected changes built on top of the [Class–Course Association](class-course-association-context.md):

1. **Class Code** — a shareable 6-char code per `tl_class`.
2. **`LXP Student Courses`** — a 2-step student-facing Elementor widget (classes → that class's courses).
3. **`LXP Student Access`** — a kiosk-style Student-ID login widget.
4. **Student identity = Student ID** — `student_id` is now the WP username, applied across CSV import and the Manage Students UI.

See also: [csv-student-import-context.md](csv-student-import-context.md).

> **Superseded for token-mode schools (Aug 2026).** Everything below describes the *named-student* model, which is still the default. Schools with `lxp_school_token_mode` set on their `tl_school` post use zero-PII enrollment instead: students are provisioned only via class-code redemption, `student_id` is an opaque `stu_<hex>` handle rather than a school ID, and there is no `lxp_student_password`. `import()` and `save_update()` return 403 for those schools. See [student-privacy-zone-a-context.md](student-privacy-zone-a-context.md).

---

## 1. Student Identity Model

```
WP user (role lxp_student)
  ▲  user_login = sanitized student_id (e.g. "s001"),  user_email = {login}@{domain}
  │
  │  lxp_student_admin_id  (meta on the CPT post → WP user ID)
  ▼
tl_student CPT post
  • student_id            (meta — RAW value as typed, e.g. "S001")  ← identity key
  • lxp_student_password  (meta — plaintext, used by access-login + admin visibility)
  • grades, lxp_teacher_id, lxp_student_school_id, ...
  ▲
  │  lxp_student_ids  (repeating meta on tl_class → tl_student POST IDs, NOT user IDs)
  ▼
tl_class CPT post
  • lxp_class_code        (meta — 6-char shareable code)
  • lxp_class_course_ids  (repeating meta → lp_course IDs)
```

**Key rules**
- The **Student ID** (`student_id` meta, raw) is the single human identifier. The WP `user_login` is the sanitized/lowercased form (`strtolower(sanitize_user($student_id, true))`).
- `lxp_student_ids` on a class stores **tl_student post IDs**, not WP user IDs.
- Login lookups go **by `student_id` meta** (robust for legacy students whose `user_login` ≠ `student_id`), then resolve the WP user via `lxp_student_admin_id`.
- The Student ID is **set once on create** and is **read-only on edit** — WP logins are never renamed.

---

## 2. Class Code (`lxp_class_code`)

Meta key `lxp_class_code` on `tl_class` posts. Single value, **6 uppercase chars**, alphabet `ABCDEFGHJKLMNPQRSTUVWXYZ23456789` (no ambiguous `0 O 1 I`).

| Where | Detail |
|---|---|
| Generation on save | `Rest_Lxp_Class::generate_class_code()` (private) in [classes.php](../lms/lms-rest-apis/classes.php); called inside `create()` (behind `POST /lms/v1/classes/save`) only if no code exists yet — existing classes get one on their next save, no migration needed |
| On-demand generation | `lxp_get_or_create_class_code($class_id)` in [functions.php](../lms/templates/tinyLxpTheme/lxp/functions.php) — returns the code or creates+saves one; used by the Courses widget so cards always have a non-empty code |
| Exposed in REST | `get_one()` adds `lxp_class_code`; `GET /lms/v1/class/by-code?class_code=XYZ` (`get_by_code()`) returns `{ID, post_title, lxp_class_code, lxp_class_course_ids}` |

> Gotcha (fixed): the widget's JS click handler does `if (!code) return;`. A class with empty `lxp_class_code` (created before this feature) rendered `data-class-code=""` and was unclickable — hence `lxp_get_or_create_class_code()`.

---

## 3. `LXP Student Courses` widget

File: [includes/widgets/lxp-student-courses-widget.php](../includes/widgets/lxp-student-courses-widget.php) — `LXP_Student_Courses_Widget`, namespace `Edudeme\Elementor`, `get_name()` `lxp-student-courses`, category `general`. Registered in [class-tiny-lxp-platform-widget.php](../includes/class-tiny-lxp-platform-widget.php).

**Behavior** — Google-Classroom-style card grid, 2 steps in one PHP render (no AJAX):
- **Step 1**: every class the student is in (`lxp_get_student_all_classes($student_post->ID)`), each card showing the course count. ALL classes shown, including 0-course ones.
- **Step 2**: clicking a card appends `?class_code=XYZ` to the URL (`history.pushState`) and reveals that class's course cards (each links to `get_permalink($course_id)`). Back button + browser back/forward (`popstate`) supported; deep-linking with `?class_code=` opens Step 2 directly.

Data: gathers each class's `lxp_class_course_ids` + code, batch-fetches all `lp_course` posts once. Lesson counts via `learn_press_get_course()->count_items(LP_LESSON_CPT)`.

**Controls**: columns (2/3/4), color cycle (7-hue palette) or fixed header color, card/body/meta/button colors, labels (`open_label`, `back_label`, `empty_message`). All CSS/JS inline + scoped to `#lxp-scw-{widget_id}`.

---

## 4. Share Class (teacher/admin class lists)

Files: [admin-classes.php](../lms/templates/tinyLxpTheme/lxp/admin-classes.php), [teacher-classes.php](../lms/templates/tinyLxpTheme/lxp/teacher-classes.php) (both Classes and Other-Groups tables).

A **Code** column shows the class code plus two clipboard buttons:
- `.lxp-copy-code` → copies the raw code.
- `.lxp-copy-link` → copies `{origin}/student-courses/?class_code={code}`.

Both flash ✓ for 1.5s. Empty code shows `—` (auto-fills on next save). The share-link base path `/student-courses/` is a default; if the widget page uses a different slug, the link must be adjusted manually.

---

## 5. `LXP Student Access` widget (kiosk login)

File: [includes/widgets/lxp-student-access-widget.php](../includes/widgets/lxp-student-access-widget.php) — `LXP_Student_Access_Widget`, namespace `Edudeme\Elementor`, `get_name()` `lxp-student-access`, category `general`.

**UX**: single "Student ID" field + Enter button. Reads `?class_code=` from the page URL, POSTs to the login endpoint, reloads on success (so a co-located `LXP Student Courses` widget shows that class), shows the error message on failure. Renders "You are signed in." when already logged in. Controls: heading / field / button labels + box/text/button colors.

### Login endpoint

`POST /wp-json/lms/v1/student/access-login` → `Rest_Lxp_Student::access_login()` in [students.php](../lms/lms-rest-apis/students.php). `permission_callback => '__return_true'`; all checks inside the callback:

1. Require `student_id` + `class_code`.
2. Find `tl_student` by `student_id` meta → resolve WP user via `lxp_student_admin_id`.
3. Validate class by `lxp_class_code`.
4. Confirm the student post ID is in the class's `lxp_student_ids`.
5. Read `lxp_student_password` meta → `wp_signon()` (sets the auth cookie).

Distinct error messages for: missing input, unknown Student ID, missing account, invalid class code, not enrolled, login failure.

> **Security (by design)**: this is kiosk/shared-device login gated only by *knowing a Student ID* + *being on a page with a valid class code where that student is enrolled*. It depends on the plaintext `lxp_student_password` meta. Intended for trusted-channel class links — not a substitute for password auth.

---

## 6. Manage Students consistency (student_id = username)

Because `student_id` is now the login, the Manage Students UI was consolidated. The Edlink import modal (`edlink/student-modal.php`, separate `/edlink/students/save` route) was **left as-is**.

### Backend — [students.php](../lms/lms-rest-apis/students.php)
- **`import()`**: 4-column CSV (see [csv doc](csv-student-import-context.md)); login + email derived from `student_id`.
- **`/students/save` validation**: removed the `lxp_username` arg; `lxp_student_id` is now **required**.
- **`save_update()`**:
  - New-student duplicate guard up front (rejects an already-used Student ID before creating anything).
  - On create: `user_login` / email / nicename derived from `student_id` (mirrors import). On edit: login untouched (`ID` only), so logins are never renamed; removed the legacy `lxp_username_default` email-upgrade `$wpdb->update(user_login)` path.
  - `WP_Error` guard after `wp_insert_user()`.

### UI
- **Listings** — "Username" column removed (header + `user_login` cell); "ID" column (student_id) kept:
  `admin-students.php`, `teacher-students.php`, `school-students.php`, `client-students.php`, `school-dashboard-students-tab.php`.
- **Modals** (`admin-`, `teacher-`, `school-student-modal.php`) — the separate "Username" field (`lxp_username` + hidden `lxp_username_default`) removed; the field is now a single **"Student ID"** (`name="lxp_student_id"`, `#idStudent`, placeholder `S001`). `onStudentEdit()` sets it **read-only**; `hide.bs.modal` clears it and restores editable for the next Add.

> Any external caller of `/students/save` must now send `lxp_student_id` (no longer `lxp_username`).

---

## Files Touched (quick index)

| Area | Files |
|---|---|
| Class code + by-code | `lms/lms-rest-apis/classes.php`, `lms/templates/tinyLxpTheme/lxp/functions.php` |
| Courses widget | `includes/widgets/lxp-student-courses-widget.php`, `includes/class-tiny-lxp-platform-widget.php` |
| Share class | `lms/templates/tinyLxpTheme/lxp/admin-classes.php`, `teacher-classes.php` |
| Access widget + login | `includes/widgets/lxp-student-access-widget.php`, `lms/lms-rest-apis/students.php` |
| Identity / Manage Students | `lms/lms-rest-apis/students.php`, the 5 `*-students*.php` listings, the 3 `*-student-modal.php` files, `treks-src/assets/sample-students.csv`, `admin-students.php` (guide modal) |
