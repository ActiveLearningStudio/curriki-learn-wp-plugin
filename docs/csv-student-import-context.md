# CSV Student Import — Feature Context

Session context: CSV student import improvements (completed June 21, 2026)
Updated June 25, 2026: `student_id` is now the WP username/login (4-column format). See also [student-identity-and-access-context.md](student-identity-and-access-context.md).

> **Disabled for token-mode schools (Aug 2026).** `import()` returns **403** when the target school has `lxp_school_token_mode` set — the 4-column CSV carries first/last name and a district `student_id`, all PII that must not reach the server under the zero-knowledge model. Those schools create seats from the class roster instead, and students self-enroll with a class code. Everything below still applies to every school without the flag. See [student-privacy-zone-a-context.md](student-privacy-zone-a-context.md).

---

## Purpose

Allow school admins to bulk-import students from a CSV file. Each row creates a WordPress user
(`lxp_student` role) + a student CPT post. Course enrollment is handled separately after import.

---

## Key Files

| File | Role |
|------|------|
| `lms/lms-rest-apis/students.php` | REST handler `Rest_Lxp_Student::import()` |
| `lms/templates/tinyLxpTheme/lxp/admin-students.php` | Admin UI: upload form, guide modal |
| `includes/class-tiny-lxp-platform-tool.php` | `lxp_add_user_roles()` — role + capability definitions |
| `lms/templates/tinyLxpTheme/treks-src/assets/sample-students.csv` | Downloadable sample for admins |

---

## CSV Format

Exactly **4 columns**, in this order, no reordering. Passwords are **not** in the CSV — an optional common password can be configured in WP Admin → Settings → Curriki Learn → Student Import Settings. If not set, each student receives a unique random password (via `wp_generate_password(12, false)`) stored in `lxp_student_password` post meta.

> **Changed June 25, 2026**: the old standalone `username` column was removed. `student_id` is now the student's WP login. The username is derived as `strtolower(sanitize_user($student_id, true))` and the email as `{that}@{domain}`. The raw `student_id` value is still stored in the `student_id` post meta (used by the access-login lookup).

| # | Column | Example | Notes |
|---|--------|---------|-------|
| 1 | `first_name` | `Ava` | Student's first name |
| 2 | `last_name` | `Stone` | Student's last name |
| 3 | `grade` | `6` or `3-5` or `3-5-7` | Single grade or multiple grades separated by hyphens (each maps to ordinal: `6`→`6th`, `3-5`→`["3rd","5th"]`) |
| 4 | `student_id` | `S001` | School/SIS identifier. **Becomes the WP login** (`s001`) and email (`s001@{domain}`), and is stored raw in `student_id` post meta |

- **Header row** (`first_name,last_name,...`) is auto-skipped when present.
- Rows with fewer than 4 columns → `$skipped` counter, row silently skipped.
- Rows whose generated email already exists → `$duplicates` counter, skipped (unique `student_id` ⇒ unique email).
- Email domain = the site host (`wp_parse_url(home_url(), PHP_URL_HOST)`); falls back to `curriki.org` on localhost / IP hosts.
- File must be `.csv`; MIME allow-list: `text/csv`, `application/vnd.ms-excel`, `application/octet-stream`, `text/plain` (covers Excel-saved CSVs).
- If `tl_student_default_password` option is empty, each student gets a unique `wp_generate_password(12, false)` password stored in their `lxp_student_password` meta.

Sample file: `lms/templates/tinyLxpTheme/treks-src/assets/sample-students.csv`
```
first_name,last_name,grade,student_id
Ava,Stone,3-5,S001
Liam,Reed,6,S002
```

---

## REST Endpoint

`POST /wp-json/lms/v1/students/import`

**Form-data params:**

| Param | Type | Notes |
|-------|------|-------|
| `students_file` | file | The CSV file |
| `school_admin_id_imp` | int | School admin user ID |
| `student_school_id_imp` | int | School CPT post ID |
| `teacher_id_imp` | int | Teacher user ID (0 if not set) |

**Response JSON:**

```json
{
  "message": "Students imported successfully.",
  "imported": 3,
  "skipped": 1,
  "duplicates": 0
}
```

---

## What Happens Per Row

1. `wp_insert_user()` — creates WP user with `lxp_student` role.
2. `wp_set_password()` — sets the password from `get_option('tl_student_default_password')` or `wp_generate_password(12, false)` (hashed by WP).
3. `wp_insert_post()` — creates `tl_student` CPT post linked to the school.
4. `add_post_meta($id, 'lxp_student_password', $password)` — stores plain-text password on the CPT post for per-student admin visibility.

---

## lxp_student Role Capabilities

Defined in `includes/class-tiny-lxp-platform-tool.php` → `lxp_add_user_roles()` (runs on every
`init`, so changes apply immediately to all existing users).

Key caps added in this session:

```php
$student->add_cap('read');               // base WP cap LearnPress learners need
$student->add_cap('read' . $course_cap); // read LP courses
$student->add_cap('read' . $lesson_cap); // read LP lessons
```

`read` was missing before this session — without it, LP's `current_user_can('read')` checks could
block students from accessing course/lesson pages.

> **Do NOT add `subscriber` as a second role** to imported students. `get_custom_role()` in
> `lms/templates/tinyLxpTheme/lxp/functions.php` keys off `$roles[0]` for dashboard routing —
> a second role doesn't affect WP access but would confuse role-switcher logic.

---

## Admin UI (admin-students.php)

All import controls (upload button, guide link, modal) are inside the `if($school_post)` guard —
they only render when a school is selected.

### CSV format guide modal (`#csvGuideModal`)

Triggered by a link next to the Import button. Contains:
- The 5-column reference table.
- Rules list (column order, header row, file format, duplicates, short rows).
- **"Download sample CSV"** button: `<a download href="<?php echo esc_url($treks_src . 'assets/sample-students.csv'); ?>">`.

`$treks_src` = `TL_PLUGIN_URL . 'lms/templates/tinyLxpTheme/treks-src/'`

---

## Admin: Per-Student Password

The `lxp_student_password` post meta is surfaced as a "Student Password" meta box on the `tl_student` edit screen in WP Admin. Saving a new value there:
- Updates `lxp_student_password` post meta.
- Calls `wp_set_password()` on the linked WP user (`lxp_student_admin_id` meta).

## Admin: Default Password Setting

WP Admin → Settings → Curriki Learn → **Student Import Settings** → *Default Student Password*.
- Option key: `tl_student_default_password`
- `sanitize_text_field` sanitization on save.
- Falls back to `wp_generate_password(12, false)` per student if not configured.

## Known Limitations / Pending Work

| Topic | Status |
|-------|--------|
| **Password handling** | Optional common password from WP Admin settings; falls back to `wp_generate_password(12, false)` per student if not set. Stored plain-text per-student in `lxp_student_password` post meta. No forced-change on first login, no email notification. |
| Runtime test plan | `php -l` passes. Full manual test (role caps, onboard-only, LP cache, dedupe) not yet executed — needs running WP + LP instance. |
| MIME detection | Uses extension + allow-list; if WP's `finfo` is unreliable on the host, could still reject valid CSVs from some Excel versions. |
