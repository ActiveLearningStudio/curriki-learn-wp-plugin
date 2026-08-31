# Class–Course Association — Feature Context

Session context: Class–Course association feature added (completed June 24, 2026)

---

## Purpose

Allow teachers and admins to directly assign one or more LearnPress courses (`lp_course`) to an LXP Class (`tl_class`). Previously, courses were only linked to a class indirectly — through individual lesson Assignments. This feature adds a first-class, explicit relationship so that a class knows which courses it is working from.

**This is a direct association only.** It does not auto-enroll students in LearnPress or create Assignments — those flows are independent and unchanged.

---

## Key Files

| File | Role |
|---|---|
| `lms/lms-rest-apis/classes.php` | `Rest_Lxp_Class` — 3 new methods + routes; `get_one()` and `create()` updated |
| `lms/templates/tinyLxpTheme/lxp/admin-class-modal.php` | Admin class modal — Courses picker section + `loadAvailableCourses()` JS |
| `lms/templates/tinyLxpTheme/lxp/teacher-class-modal.php` | Teacher class modal — same Courses picker |
| `lms/templates/tinyLxpTheme/lxp/admin-classes.php` | Admin class list — Courses count column (both Classes and Groups tables) |
| `lms/templates/tinyLxpTheme/lxp/teacher-classes.php` | Teacher class list — Courses count column (both Classes and Groups tables) |

---

## Data Storage

**Meta key**: `lxp_class_course_ids` on `tl_class` posts.

Storage uses repeating post meta — one `wp_postmeta` row per assigned LP course ID — identical to how `lxp_student_ids` works:

```
wp_postmeta (post_type = tl_class)
  lxp_class_course_ids = 101   ← lp_course post ID
  lxp_class_course_ids = 204
  lxp_class_course_ids = 388
```

**On save**: all existing `lxp_class_course_ids` entries for the class are deleted first, then one `add_post_meta()` call per selected course ID. This is a full-replace pattern (same as student IDs).

No new database tables were created. No schema migration is needed.

---

## REST Endpoints

All endpoints are in `Rest_Lxp_Class` (`lms/lms-rest-apis/classes.php`) and use `'permission_callback' => '__return_true'` (consistent with all other class endpoints).

### New endpoints

| Method | Route | Params | Returns |
|---|---|---|---|
| `POST` | `/wp-json/lms/v1/class/available-courses` | _(none)_ | All published `lp_course` posts as `{ data: { courses: [{ID, post_title, audience}] } }`, where `audience` is the course's `Students` / `PD` badge labels (possibly empty) |
| `POST` | `/wp-json/lms/v1/class/courses` | `class_id` | Courses assigned to the class as `{ data: { courses: [{ID, post_title, permalink}] } }` |
| `POST` | `/wp-json/lms/v1/class/courses/save` | `class_id`, `course_ids[]` | Replaces the full set of assigned courses; returns `"Courses Saved!"` on success |

### Updated existing endpoints

| Route | What changed |
|---|---|
| `POST /wp-json/lms/v1/classes/save` | Now also accepts optional `course_ids[]` and saves `lxp_class_course_ids` meta after student IDs |
| `POST /wp-json/lms/v1/classes` (get_one) | Response object now includes `lxp_class_course_ids` array |

---

## UI

### Class modal (admin and teacher)

A **Courses** dropdown picker was added to the right panel of both `admin-class-modal.php` and `teacher-class-modal.php`, placed after the Students picker and before the horizontal divider that precedes the Type section.

**How it works:**

1. On page load, `loadAvailableCourses()` fires a `POST` to `/lms/v1/class/available-courses` and populates `#courses-list` inside a Bootstrap dropdown with one checkbox per LP course.
2. Checkboxes use `name="course_ids[]"` — they are picked up automatically by the existing `FormData` form submission to `/classes/save`. No changes to the submit handler were needed.
3. When editing an existing class (`onClassEdit(class_id)`), the AJAX call to `/lms/v1/classes` now returns `lxp_class_course_ids`. The JS pre-checks matching `input.select-course-check` checkboxes and updates the dropdown button counter.
4. When the modal closes (`hide.bs.modal`), all course checkboxes are unchecked and the counter resets to `--- Select ---`.

Relevant JS IDs and classes:

| Selector | Purpose |
|---|---|
| `#coursesDropdownMenu` | Dropdown toggle button; `span` inside shows selected count |
| `#courses-list` | Container for course checkboxes (populated by JS) |
| `.select-course-check` | Class on each course checkbox; `value` = LP course post ID |

### Class list tables

A **Courses** column was added to all four class/group tables across both views:

| File | Tables updated |
|---|---|
| `admin-classes.php` | Classes table, Groups (Other Groups) table |
| `teacher-classes.php` | Classes table, Other Groups table |

The cell value is `count(get_post_meta($class->ID, 'lxp_class_course_ids'))`. If zero, it displays `—`.

---

## Relationship to Other Features

### vs. Assignments

| | Assignments | Class–Course Association |
|---|---|---|
| Granularity | Per-lesson, per-student, with due dates | Per-course, per-class |
| Storage | `tl_assignment` CPT posts with `class_id` and `lxp_lesson_id` meta | `lxp_class_course_ids` post meta on `tl_class` |
| Purpose | Track which students must complete which lessons by when | Indicate which courses a class is working from |
| Effect on LP | None (TinyLxp-only) | None (association only, no LP enrollment) |

Both mechanisms coexist. Assigning a course to a class via this feature does **not** create assignments or enroll students in LearnPress.

### vs. Trek (tl_trek)

Treks are a TinyLxp-specific course-journey wrapper (`tl_trek`) that links to a single `lp_course` via `tl_course_id` meta. The Class–Course Association feature works directly with `lp_course` post IDs — not Trek post IDs.

---

## Known Limitations / Future Considerations

| Topic | Notes |
|---|---|
| Auto-enrollment | Assigning a course to a class does not enroll class students in LearnPress. If auto-enrollment is needed in future, build on `lxp_class_course_ids` + `lxp_student_ids` to call `learn_press_add_user_items()` |
| Course picker load time | `loadAvailableCourses()` fetches all published LP courses on every page load. If the course catalogue is very large, consider adding server-side filtering or lazy loading |
| No unassign-all shortcut | The modal has no "deselect all courses" button (unlike students). Add one mirroring `#select-all-students` if needed |
| REST auth | All three new endpoints use `'permission_callback' => '__return_true'`. Capability checks should be added inside callbacks if these endpoints are ever exposed to untrusted consumers |
