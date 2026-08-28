# TinyLxp WordPress Plugin

WordPress plugin (v2.0.3) that turns a WP site into an IMS LTI 1.3 Platform + full LMS. Manages Courses, Lessons, Assignments, Students, Teachers, Classes, Groups, Schools, Districts, and Treks.

Full architecture and copilot guidance: [.github/copilot-instructions.md](.github/copilot-instructions.md)  
AI Video feature detail: [docs/ai-video-context.md](docs/ai-video-context.md)  
Class–Course association detail: [docs/class-course-association-context.md](docs/class-course-association-context.md)  
Student identity & access (Student ID login, class code, Student Courses/Access widgets): [docs/student-identity-and-access-context.md](docs/student-identity-and-access-context.md)  
Zero-PII student enrollment (COPPA/FERPA token accounts, code redemption, claim links): [docs/student-privacy-zone-a-context.md](docs/student-privacy-zone-a-context.md)  
Encrypted roster vault (client-side crypto, passphrase KDF, district escrow, CSV import): [docs/student-privacy-zone-b-context.md](docs/student-privacy-zone-b-context.md)  
CSV student import detail: [docs/csv-student-import-context.md](docs/csv-student-import-context.md)

---

## Dev Commands

```bash
composer install          # after clone or library updates — vendor/ is gitignored
php -l path/to/file.php   # syntax check before declaring done
wp rewrite flush          # after any CPT registration change
wp rest route list --namespace=lms/v1 --fields=route,methods
```

> No JS build pipeline. Edit `admin/js/`, `admin/css/`, `includes/widgets/assets/` directly.

---

## Architecture at a Glance

| Layer | Where |
|---|---|
| Plugin entry | `TinyLxp-wp-plugin.php` → `tiny-lxp-platform.php` (identical legacy duplicate — keep both) |
| Core orchestrator | `includes/class-tiny-lxp-platform.php` (`Tiny_LXP_Platform`) |
| Hook registry | `includes/class-tiny-lxp-platform-loader.php` — never call `add_action()` directly for admin/public hooks |
| Admin layer | `admin/class-tiny-lxp-platform-admin.php` + `admin/partials/` |
| LMS domain | `lms/` — CPT classes, REST APIs, repositories, templates |
| REST namespace | `lms/v1` → `/wp-json/lms/v1/` — all registered via `LMS_REST_API::init()` |
| Elementor widgets | `namespace Edudeme\Elementor` in `includes/widgets/` |

### Where to add new things

| Task | Location |
|---|---|
| New CPT | `lms/class-{entity}-post-type.php` (extend `TL_Post_Type`, singleton pattern) |
| New REST endpoint | `lms/lms-rest-apis/{entity}.php` → register in `LMS_REST_API::init()` |
| DB access | `lms/repositories/class-{domain}-repository.php` (prefer over inline SQL) |
| Admin page | `admin/class-tiny-lxp-platform-admin.php` + `admin/partials/` |
| Page template | `lms/templates/tinyLxpTheme/page-{slug}.php` |
| Dashboard partial | `lms/templates/tinyLxpTheme/lxp/{role}-{feature}.php` |
| Elementor widget | `includes/widgets/lxp-{name}-widget.php` → register in `includes/class-tiny-lxp-platform-widget.php` |
| AI Bedrock calls | `includes/class-aws-bedrock-client.php` (`TL_AWS_Bedrock_Client::invoke_bedrock`) |
| AI REST endpoints | `lms/lms-rest-apis/ai-content.php` (`Rest_Lxp_AI_Content`) |
| AI Video endpoints | `lms/lms-rest-apis/ai-video.php` (`Rest_Lxp_AI_Video`) |
| Class–Course association | `lms/lms-rest-apis/classes.php` (`Rest_Lxp_Class`) + class modal templates |

---

## Critical Rules

- **Hook routing**: Admin/public hooks → `Tiny_LXP_Platform_Loader`. CPT-specific hooks → register directly in constructor.
- **Singletons**: All CPT classes must use `$_instance` / `instance()` pattern.
- **REST auth**: All routes currently use `'permission_callback' => '__return_true'`. Always implement `current_user_can()` or nonce inside the callback for write/sensitive ops.
- **No credentials in code**: Use `get_option()` — follow the `edlink_options` pattern.
- **DB**: Always `$wpdb->prefix` for table references. Always `$wpdb->prepare()` for queries. Never interpolate user input.
- **Output**: Always escape (`esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`). Always sanitize input.
- **Namespace in Elementor widgets**: All LP class calls inside `Edudeme\Elementor` must be prefixed with `\` (e.g., `\LP_Global::course_item()`) — bare names resolve to the wrong namespace and cause fatals.
- **LearnPress tables**: Always `$wpdb->prefix` — never hardcode `wp_` prefix.
- **Autoloader**: Don't `require_once` files already in Composer autoload.

---

## Known Gotchas (read before editing)

| # | Issue |
|---|---|
| 1 | `TL_Assingment_Post_Type` — typo (double-s) is intentional, do not fix without full audit |
| 2 | REST route `/shools/save` — missing 'c' is intentional, do not fix without migration plan |
| 3 | `TinyLxp-wp-plugin.php` and `tiny-lxp-platform.php` are duplicates — keep both |
| 4 | LP4 lesson URLs (`/{course}/lessons/{lesson}/`) — `get_queried_object_id()` returns the **course** ID, not lesson ID. Use URL slug extraction as fallback. |
| 5 | `meta_table_html()` is misnamed — it returns Learning Outcomes + Opening Hook, not a metadata table (legacy name, do not rename) |
| 6 | `aws/aws-sdk-php` pinned to `3.337.3` due to PHP version constraint — do not `composer update` without checking server PHP version |
| 7 | `[Capstone Box]` and `[Text Box]` are plain-text sentinels in post content — WP kses strips `<textarea>`. Frontend JS converts them at runtime. Never store form elements in post content. |
| 8 | Standard AI templates (15 total) have **no quiz** — `quiz_html()` returns `''`. Do not add "Check for Understanding" to standard templates. |
| 9 | `lxp-capstone.js` is a single IIFE — any syntax error silently disables all capstone boxes sitewide. Check browser console after edits. |
| 10 | AI Video REST callbacks have `current_user_can()` checks **commented out** — auth not enforced at route level. |
| 11 | AI Video `background_clip` (overlay mode) bypasses Bedrock — injected into the scene JSON in PHP after generation. It makes every `SceneWrap` transparent via `OverlayContext`; new scene components must use `SceneWrap`. Clip is trimmed to the author's M:SS length; Lambda must reach the URL over the public internet (localhost uploads won't render). |
| 12 | `learnpress_user_items.parent_id` is **0** for token-student enrollment, not the class ID — LP owns that column (child items point at the parent course row). The class link lives in `learnpress_user_itemmeta._lxp_class_id`. Enrollment goes through LP's own `UserCourseModel::save()` (LP 4.2.5+), which handles cache invalidation; the direct-`$wpdb` path in `TL_Enrollment_Repository` is a fallback for older LP only. Don't "simplify" it back to a raw INSERT — that leaves LP's per-course student-count caches stale. |
| 13 | `lms/templates/tinyLxpTheme/lxp/functions.php` is **not** loaded in REST context — REST callbacks must not call its `lxp_*()` helpers without an explicit `require_once`. Use the repositories instead. |
| 14 | Class membership is stored **twice on purpose**: `lxp_student_ids` post meta (what every dashboard reads) and `lxp_class_members` (seats, aliases, claim secrets). Any code that rewrites the meta wholesale must call `Rest_Lxp_Class_Redemption::reconcile_class_student_meta($class_id)` afterwards or token students are silently evicted. |
| 15 | Roster vault (Zone B): a **fresh IV per save** is mandatory — reusing a GCM nonce under one key leaks the keystream. `roster-vault.php` must never gain a decrypt call; it only stores and gates. `kdf_params.algo` is what makes a future Argon2id migration possible — do not drop it. Run `node tests/roster-vault.test.js` after touching the crypto. |
| 16 | Any REST call that relies on `is_user_logged_in()` / `current_user_can()` **inside** the callback (the pattern this codebase uses instead of a real `permission_callback`) must send an `X-WP-Nonce: wp_create_nonce('wp_rest')` header from cookie-authenticated JS. Without it, WP core's `rest_cookie_check_errors()` silently force-logs the request out (`wp_set_current_user(0)`) rather than erroring — the callback then rejects with a generic "not allowed" message that looks like an ownership bug. See `class-roster-modal.php`'s `restNonce` / `RosterVault`'s `opts.nonce` for the pattern. |
| 17 | `POST /lms/v1/classes/save` no longer requires `student_ids`, and `Rest_Lxp_Class::create()` only rewrites `lxp_student_ids` when that param is actually present. The class modal has no student picker any more (students arrive by code redemption or the Roster modal), so an unconditional `delete_post_meta()` + rebuild would evict every non-token student on an ordinary save — and `reconcile_class_student_meta()` only restores the *token* ones, making the loss silent and partial. Do not restore the unconditional wipe. |
| 18 | A class stores its grade levels **twice**: `lxp_class_grades` (repeating, the full set inherited from the teacher's signup) and `grade` (single string, the first entry). `grade` cannot be dropped — the class list column reads it and `Rest_Lxp_Class_Redemption` seeds a token student's own `grades` meta from it. Write both via `Rest_Lxp_Class::save_class_grades()`. |
| 19 | `lxp_get_grade_options()` lives in `lms/tl-constants.php`, **not** `lxp/functions.php`, because the teacher-signup REST callback has to validate against it and `functions.php` is not loaded in REST context (gotcha #13). All grade checkbox/select markup should render from it — the old hardcoded lists disagreed (people modals 1st–9th, class modal 1st–12th), which silently truncated a teacher's grades on re-save. |
| 20 | `teacher-classes.php` renders the dashboard's `navbar navbar-expand-lg` header (for the site logo) **minus the search box**, and does **not** render the left `.nav-section` sidebar — every destination it pointed at is off the menu for teachers. Logout lives in `trek/user-profile-block.php`, which that header includes; the heading row has no logout link of its own, so don't add one back, and don't drop the header without restoring one. The empty `.navbar-nav.me-auto` div is a spacer, not leftovers — `me-auto` is what right-aligns the avatar block now the search box is gone. |
| 21 | Students always **type a nickname** to join — the `assigned` seat-label dropdown was removed at the client's request. `resolve_alias()` no longer branches, so classes still carrying `lxp_class_alias_mode = 'assigned'` keep working; that meta is vestigial and nothing reads it. Do **not** confuse this with `get_seat_pool()` / `next_open_seats()` / `lxp_class_seat_labels`, which are alive and belong to the Roster modal, CSV import and claim links. The nickname field's label is doing privacy work now (see `docs/student-privacy-zone-a-context.md` §3b) — don't reword it into "Your name". |
| 22 | In `lxp-class-join-widget.php`, the nickname wrapper's resting state is `display:none` **in the widget's stylesheet**. Reveal it with an explicit `display = 'block'`; assigning `''` only clears the inline declaration and lets the stylesheet rule win again, leaving the field permanently invisible. That was a live bug — and it hid for weeks because a `<select>` auto-selects its first option, so the old dropdown mode kept submitting a valid value from an invisible control. |
| 23 | Never `get_post_meta(…, 'lxp_class_max_seats')` directly. Seats are capped at `TL_CLASS_MAX_SEATS` (150) and `0` is a **legacy "unlimited" sentinel** still stored on pre-cap classes — a raw read gives you `0` and every `$max > 0 &&` guard silently turns back into "no cap". Read via `lxp_get_class_max_seats()`, write via `lxp_clamp_class_max_seats()` (both in `lms/tl-constants.php`, for gotcha #19's reason). There is no migration and none is needed. |
| 24 | `TL_Teacher_Access` (`includes/class-tiny-lxp-teacher-access.php`) registers its hooks **itself**, not through `Tiny_LXP_Platform_Loader` — that is deliberate and contradicts the hook-routing rule above. The loader only runs when `Tiny_LXP_Platform::isOK()` passes, so routing it there would hand teachers wp-admin back the moment an unrelated dependency check failed. It fails **open** for administrators (`manage_options`) on purpose: locking an admin out of wp-admin is not recoverable from the front end. |
| 25 | `class-roster-modal.php` reloads the page on `hidden.bs.modal` — closing the Roster modal (Bootstrap 5's `hide.bs.modal`/`hidden.bs.modal` pair, fired by the X, backdrop click, or Escape, not just an explicit button) always refreshes `/classes` so the class list's seats-used count picks up whatever changed, including students who joined live via code while the modal was open. `hide.bs.modal` is where the existing dirty-name-edit confirm lives; it now also gates the reload via `e.preventDefault()`, so add any *other* "block the close" check there too, not on `hidden.bs.modal` (that one no longer fires if `hide` was prevented). |
| 26 | `TL_LearnPress_Course_Extension::save_course_audience_terms()` writes the `K-12` / `Professional Development` terms with `wp_set_object_terms( …, $append = true )` **plus** a `wp_remove_object_terms()` for the unticked ones. That is not redundant. Those two terms live in LearnPress's shared `course_category` taxonomy alongside subject categories (Math, Science, …), and `wp_set_object_terms()` defaults to `$append = false` — collapsing this into one replacing call silently wipes every other category off the course. The matching filter in `get_available_courses()` is a **strict** one-term match by design: a course tagged with neither audience term is offered to **no** teacher, so an untagged catalogue yields an empty Courses dropdown. That is intended, not a regression — do not "restore" a lenient fallback that shows untagged courses to everyone. |
| 27 | The signup widget's grades block ships **visible** and carries no `display:none` in the stylesheet, because the K-12 radio is checked by default. Its JS toggles it with an explicit `'block'`/`'none'` — the mirror image of gotcha #22, and it must stay that way: give the wrapper a stylesheet `display:none` resting state and the `'block'` assignment still works, but any future `= ''` shorthand would hide it permanently. |
| 28 | `POST /lms/v1/classes/save` rebuilds `lxp_class_course_ids` from `course_ids[]`, but the modal's Courses picker only renders checkboxes for courses **the teacher's audience can see** (gotcha #26). So the posted set describes one slice of the catalogue, not the whole thing, and a straight rebuild silently drops any course assigned outside it — untagged, or tagged for the other audience — on an unrelated save such as a rename. `create()` therefore reads the existing meta first and carries over the assignments the audience filter would hide, via `Rest_Lxp_Class::audience_tax_query()`. Same failure shape as #17; do not "simplify" it back to a bare delete-and-rebuild. `POST /lms/v1/class/courses/save` deliberately has no such guard — it is an explicit replace-the-full-set API with no teacher context, and nothing in the UI calls it. |

---

## Key Integrations

| Service | Config |
|---|---|
| AWS Bedrock (AI) | `TL_AWS_Bedrock_Client::MODEL_ID` / `::REGION` — credentials from EC2 IAM role via IMDSv2 |
| Remotion Lambda (video) | WP options: `tl_remotion_region`, `tl_remotion_function_name`, `tl_remotion_serve_url` |
| Edlink (SIS) | WP option: `edlink_options` array |
| xAPI / Curriki Studio / Tsugi | `lms/xapi-constants.php` — update constants there, never hardcode URLs |
| LearnPress | Direct `$wpdb` queries on `{prefix}learnpress_sections` — LP must be active |

---

## Class–Course Association

A `tl_class` post can be directly linked to one or more LearnPress courses (`lp_course`). Stored as repeating post meta (`lxp_class_course_ids`) — same pattern as `lxp_student_ids`.

**REST endpoints** (all in `Rest_Lxp_Class`, `lms/lms-rest-apis/classes.php`):

| Route | Purpose |
|---|---|
| `POST /lms/v1/class/available-courses` | All published LP courses (for picker) |
| `POST /lms/v1/class/courses` | Courses assigned to a given class |
| `POST /lms/v1/class/courses/save` | Replace full course set for a class |
| `POST /lms/v1/classes/save` | Existing save endpoint — also accepts optional `course_ids[]` |
| `POST /lms/v1/classes` | Existing get-one — now includes `lxp_class_course_ids` |

**UI**: Both `admin-class-modal.php` and `teacher-class-modal.php` include a Courses dropdown picker (loaded via `loadAvailableCourses()` on page load). Both class list tables (`admin-classes.php`, `teacher-classes.php`) show a Courses count column.

**Note**: This is a direct association only. It does not auto-enroll students or create Assignments — those flows are independent.

---

## Teacher Self-Signup

A teacher creates their own account from a public Elementor form, is signed in immediately, and lands on `/classes`.

| Piece | Where |
|---|---|
| REST | `POST /lms/v1/teacher/signup` → `Rest_Lxp_Teacher_Signup` (`lms/lms-rest-apis/teacher-signup.php`) |
| Widget | `LXP Teacher Signup` → `includes/widgets/lxp-teacher-signup-widget.php` |
| Settings | wp-admin → **Curriki Learn → Teacher Signup** (`teacher_signup_settings_page_html()`) |
| Options | `tl_signup_district_id`, `tl_signup_school_id` |

The form collects first/last name, email, password + confirm, and grades — **no school or district field**. Placement comes from `tl_signup_school_id` server-side; letting a public form name its own school would let anyone join any school.

**Prerequisite**: the configured school must have `lxp_school_district_id` set, or signup is refused. `teacher-classes.php` reads the district post's meta, so a district-less school would strand the teacher on their own landing page.

Creates, as one unit with compensating deletes on failure: a WP user (role `lp_teacher`, `user_login` = email) **and** a `tl_teacher` post (`lxp_teacher_admin_id`, `lxp_teacher_school_id`, `grades` JSON, `teacher_register_type`, `settings_active`). Both halves are mandatory — `teacher-dashboard.php` hard-`die()`s for an `lp_teacher` user with no `tl_teacher` post.

### Register type

The form asks **how** the teacher is registering, and the answer drives two things.

| Value | Meaning | Grades | Courses offered |
|---|---|---|---|
| `k12` (default, preselected) | An educator registering students | Asked; at least one required | Only courses tagged `K-12` |
| `professional_development` | An administrator registering faculty/staff | Not asked; forced to `[]` | Only courses tagged `Professional Development` |

The mapping is strict — an untagged course reaches no teacher at all.

Stored as `teacher_register_type` on the `tl_teacher` post. **Never read that meta directly** — every teacher created before this feature has no meta at all. Read via `lxp_get_teacher_register_type()`, validate submitted values with `lxp_sanitize_register_type()` (both in `lms/tl-constants.php`, for gotcha #19's reason); both resolve anything unknown to `k12`.

The course side is two terms in **LearnPress's own `course_category` taxonomy** (slugs `k-12` and `professional-development`, `lxp_get_course_audience_terms()`), created idempotently by `tl_lxp_maybe_install_course_audience_terms()` on `init` priority 20 — not on activation, because LearnPress registers the taxonomy on `init` and the plugin is already active on running sites. Course authors tick them in the **Curriki Audience** meta box on the course edit screen. `Rest_Lxp_Class::get_available_courses()` filters on an optional `teacher_id` param; without it the response is the full unfiltered catalogue, as before.

Distinct from `Rest_Lxp_Teacher::create()` (the admin path), which takes `teacher_school_id` straight from the request with no capability check — never expose that one publicly.

---

## Teacher Front-End Lockdown

`lp_teacher` users never see wp-admin. `TL_Teacher_Access` (`includes/class-tiny-lxp-teacher-access.php`) enforces this with three hooks:

| Hook | Effect |
|---|---|
| `login_redirect` (99) | Teachers land on `/classes/`, ignoring `redirect_to` — that value is usually the wp-admin URL that bounced them to the login form. |
| `admin_init` (1) | Any wp-admin request is redirected back to `/classes/`. Exempts `wp_doing_ajax()` and `DOING_CRON`, which fire `admin_init` without rendering an admin screen. |
| `show_admin_bar` | Hidden — it links only to places they cannot reach. |

Two deliberate deviations, both explained in the file header and in gotchas #23–24: it self-registers rather than using the loader (an access control must not sit behind `isOK()`), and it fails **open** for `manage_options` holders.

Scoped to `lp_teacher` only. `lxp_teacher_admin` (school staff) is untouched.

---

## AI Video Feature (2-step wizard)

1. **Step 1** — author pastes raw text + sets duration (M:SS, 0:30–5:00) → POST `/lms/v1/lesson/ai-video-script` → Bedrock returns `:::layout-name\n...\n:::` block-marker script
2. **Step 2** — author edits script → POST `/lms/v1/lesson/ai-video` → Bedrock returns JSON scene array → Remotion Lambda renders MP4 → client polls GET `/lms/v1/lesson/ai-video` every 5s

19 scene layouts available (see [docs/ai-video-context.md](docs/ai-video-context.md)).  
Remotion deploy (separate from WP): `cd remotion-video-service && npm run deploy-site`  
After any change to `Scenes.tsx`, `theme.ts`, `types.ts`, or `LessonVideo.tsx` → must redeploy Remotion site.

---

## Validation Checklist (before declaring done)

- [ ] Scope matches request exactly — nothing extra added
- [ ] New PHP class: `class-{kebab-name}.php`, singleton if CPT, extends correct base
- [ ] File is autoloaded or explicitly `require_once`'d in the right bootstrap location
- [ ] REST endpoint registered in `LMS_REST_API::init()`; has capability/nonce check in callback
- [ ] No hardcoded secrets or credentials
- [ ] All DB queries use `$wpdb->prepare()`
- [ ] All output escaped; all input sanitized
- [ ] If CPT added: note that `wp rewrite flush` is required
- [ ] PHP syntax verified: `php -l {file}`
