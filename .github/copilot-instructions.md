# VS Code Copilot Instructions

## Project Overview

This repository is **TinyLxp** (`TinyLxp-wp-plugin`), a WordPress plugin (v2.0.3) that turns a WordPress site into an IMS LTI 1.3 Platform host combined with a full Learning Management System (LMS). It enables schools, districts, and teachers to manage Courses, Lessons, Assignments, Students, Teachers, Classes, Groups, Schools, Districts, and Treks (course journeys) — all backed by custom WordPress post types, a REST API, and Elementor widgets.

Key integrations:
- **LTI 1.3** — via the `celtic/lti` Composer library; the plugin acts as an LTI Platform and Tool host.
- **LearnPress** — sections and section items stored in LearnPress DB tables are queried directly.
- **Edlink** — SIS/rostering integration (OAuth, user import, provider sync).
- **Curriki Studio / Tsugi** — H5P activity content and xAPI score tracking.
- **AWS Bedrock** — AI-powered lesson content generation via `BedrockRuntimeClient::converse()` (Anthropic Claude model); credentials resolved automatically from EC2 IAM role via IMDS.

### Root Folders and Files

| Path | Purpose |
|---|---|
| `TinyLxp-wp-plugin.php` | Main plugin entry point (WordPress plugin header, bootstraps the plugin) |
| `tiny-lxp-platform.php` | Duplicate entry point with identical content (legacy) |
| `uninstall.php` | Cleanup hook run on plugin uninstall |
| `composer.json` | PHP dependency manifest (`celtic/lti ^4.7.2`, `aws/aws-sdk-php ^3.337`) |
| `includes/class-aws-bedrock-client.php` | AWS Bedrock SDK wrapper — `TL_AWS_Bedrock_Client::invoke_bedrock()` |
| `includes/` | Core plugin infrastructure: main class, loader, i18n, LTI platform/tool, data connector, Elementor widget registry |
| `admin/` | WordPress admin layer: admin class, LTI tool list table, CSS/JS assets, admin partial templates |
| `public/` | Public/frontend layer: handles LTI request routing on `parse_request` |
| `lms/` | All LMS domain logic: CPT classes, REST API classes, admin menu, constants, templates |
| `lms/lms-rest-apis/` | One file per entity exposing REST endpoints under `/wp-json/lms/v1/` |
| `lms/repositories/` | Repository classes for DB-backed data access (`TL_Grades_Repository`, `TL_Workbook_Submission_Repository`, etc.) |
| `lms/templates/` | PHP templates: single-post overrides, page templates, LXP dashboard partials, Edlink partials |
| `lms/templates/tinyLxpTheme/` | Full theme-like template layer (header, footer, all page-*.php templates, all `lxp/` partials) |
| `includes/widgets/` | Elementor widget classes and their static CSS/JS/font assets |
| `vendor/` | Composer-managed dependencies (`celtic/lti`, `firebase/php-jwt`, `aws/aws-sdk-php`) — gitignored; run `composer install` after clone |
| `languages/` | i18n POT file and language PHP file |
| `tiny-lxp-resource/` | xAPI Activity class and resource loader |

---

## Core Architecture

### Pattern
The plugin follows the **WordPress Plugin Boilerplate (WPPB)** pattern with a hook-based, layered architecture:

- **Bootstrap** (`TinyLxp-wp-plugin.php`) — includes `tiny-lxp-platform.php`, calls `run_tiny_lxp_platform()`.
- **Core orchestrator** (`Tiny_LXP_Platform`) — wires all dependencies via a central loader, creates singletons for all CPT classes, injects the data connector, calls `$loader->run()`.
- **Hook registry** (`Tiny_LXP_Platform_Loader`) — collects `add_action`/`add_filter` registrations and defers them until `run()` is called; do not call `add_action()` directly for admin or public hooks — route through the loader.
- **Admin layer** (`Tiny_LXP_Platform_Admin`) — all admin menu registration, settings pages, LTI tool management, Edlink admin UI, and asset enqueuing.
- **Public layer** (`Tiny_LXP_Platform_Public`) — handles all LTI 1.3 request flows on `parse_request`.
- **LMS CPT layer** (`TL_Post_Type` and subclasses) — TinyLxp-owned CPTs are singleton classes extending `TL_Post_Type`; they register their own hooks directly (bypass the loader).
- **LearnPress extension layer** (`TL_LearnPress_Course_Extension`, `TL_LearnPress_Lesson_Extension`) — extends LearnPress-managed `lp_course` and `lp_lesson` behavior through hooks, metaboxes, and repository-backed metadata.
- **REST API layer** (`LMS_REST_API` + `Rest_Lxp_*` classes) — all endpoints under namespace `lms/v1`; registered on `rest_api_init` → `LMS_REST_API::init()`.
- **Elementor widgets** (`Tiny_LXP_Widget` registry + `Edudeme\Elementor\*` widget classes) — registered via `elementor/widgets/register` hook.
- **LTI library layer** (`Tiny_LXP_Platform_Platform`, `Tiny_LXP_Platform_Tool`, `DataConnector_wp`) — thin WordPress-specific subclasses of the `celtic/lti` library.

### Canonical Path for New Backend Work

```
New CPT entity           →  lms/class-{entity}-post-type.php  (extend TL_Post_Type)
LearnPress lesson work   →  lms/class-learnpress-lesson-extension.php (save_post_lp_lesson, rest_insert_lp_lesson)
LearnPress course work   →  lms/class-learnpress-course-extension.php
New REST endpoint        →  lms/lms-rest-apis/{entity}.php    (extend or add to Rest_Lxp_* class)
Repository data access   →  lms/repositories/class-{domain}-repository.php (prefer over inline SQL)
REST wiring              →  LMS_REST_API::init() in lms/lms-rest-apis/lms-rest-api.php
New admin page           →  admin/class-tiny-lxp-platform-admin.php + admin/partials/
New page template        →  lms/templates/tinyLxpTheme/page-{slug}.php
New dashboard partial    →  lms/templates/tinyLxpTheme/lxp/{role}-{feature}.php
New Elementor widget     →  includes/widgets/lxp-{name}-widget.php  (extend Widget_Base)
Widget registration      →  includes/class-tiny-lxp-platform-widget.php
New LTI flow             →  includes/class-tiny-lxp-platform-platform.php or -public.php
New constants            →  lms/tl-constants.php or lms/xapi-constants.php
AI Bedrock calls         →  includes/class-aws-bedrock-client.php  (TL_AWS_Bedrock_Client::invoke_bedrock)
AI REST endpoints        →  lms/lms-rest-apis/ai-content.php  (Rest_Lxp_AI_Content)
Workbook REST endpoints  →  lms/lms-rest-apis/workbook-submissions.php  (Rest_Lxp_Workbook_Submission)
Workbook repository      →  lms/repositories/class-workbook-submission-repository.php
Workbook frontend JS     →  public/js/lxp-workbook.js  (enqueued by Tiny_LXP_Platform_Public::enqueue_workbook_scripts)
Workbook admin list      →  admin/class-workbook-submission-list-table.php  +  admin/partials/workbook-submission*.php
```

### Architecture Rules
- **Hook routing**: Admin and public hooks must go through `Tiny_LXP_Platform_Loader` (`$this->loader->add_action()`). LMS CPT hooks may register directly in the constructor via `add_action()` following the `TL_Post_Type` pattern.
- **Singletons**: All CPT classes must use the `$_instance` / `instance()` singleton pattern like existing CPT classes.
- **REST auth**: All REST endpoints currently use `'permission_callback' => '__return_true'`. Do not weaken this further; when adding sensitive endpoints, implement proper capability or nonce checks inside the callback.
- **No credentials in code**: Never hardcode API tokens, secrets, or bearer tokens. Use WordPress options (`get_option()`) — follow the `edlink_options` pattern.
- **LearnPress dependency**: When querying LearnPress tables (`learnpress_sections`, `learnpress_section_items`) always use `$wpdb->prefix` — never hardcode table prefixes.
- **Template override**: Lesson and course rendering is extended through LearnPress extension hooks (see `TL_LearnPress_Lesson_Extension` and `TL_LearnPress_Course_Extension`). Page templates reside in `lms/templates/tinyLxpTheme/` and are loaded conditionally by slug.
- **Autoloader**: PHP class autoloading is Composer PSR-4. Do not manually `require` files already covered by `vendor/autoload.php`.

---

## Custom Post Types Reference

All CPT slugs and their constants are defined in [lms/tl-constants.php](lms/tl-constants.php):

| Constant | CPT Slug | Class | Notes |
|---|---|---|---|
| `TL_COURSE_CPT` | `lp_course` | `TL_LearnPress_Course_Extension` | LearnPress-managed course type; TinyLxp extends behavior via hooks and REST handlers |
| `TL_LESSON_CPT` | `lp_lesson` | `TL_LearnPress_Lesson_Extension` | LearnPress-managed lesson type; custom URL and LTI metadata/launch behavior come from extension hooks |
| `TL_ASSIGNMENT_CPT` | `tl_assignment` | `TL_Assingment_Post_Type` *(typo in class name)* | |
| `TL_ASSIGNMENT_SUBMISSION_CPT` | `tl_submission` | `TL_Assignment_Submission_Post_Type` | |
| `TL_STUDENT_CPT` | `tl_student` | `TL_Student_Post_Type` | |
| `TL_TEACHER_CPT` | `tl_teacher` | `TL_Teacher_Post_Type` | |
| `TL_CLASS_CPT` | `tl_class` | `TL_Class_Post_Type` | Meta: `lxp_class_teacher_id`, `lxp_student_ids` (multi), `grade`, `schedule` (JSON), `lxp_class_type`, `edlink_class_sec_id`, `lxp_class_course_ids` (multi — LP course IDs assigned to this class) |
| `TL_DISTRICT_CPT` | `tl_district` | `TL_District_Post_Type` | |
| `TL_SCHOOL_CPT` | `tl_school` | `TL_School_Post_Type` | |
| `TL_GROUP_CPT` | `tl_group` | `TL_Group_Post_Type` | |
| *(no constant)* | `tl_trek` | `TL_Trek_Post_Type` | Trek = course journey |

**Meta key convention**: `lxp_{entity}_{field}` (e.g., `lxp_student_school_id`, `lxp_assignment_teacher_id`, `lxp_course_outcome`, `lxp_lesson_tagline`).  
**User role convention**: `lxp_{role}` (e.g., `lxp_teacher`, `lxp_student`, `lxp_student_admin`, `lxp_teacher_admin`).

---

## REST API Reference

All endpoints use namespace `lms/v1` → base URL `/wp-json/lms/v1/`. Entry point: [lms/lms-rest-apis/lms-rest-api.php](lms/lms-rest-apis/lms-rest-api.php).

| File | Class | Entity Coverage |
|---|---|---|
| `lms-rest-api.php` | `LMS_REST_API` | Scores, tokens, playlists, Trek sections/events, student assignment helpers, course search |
| `workbook-submissions.php` | `Rest_Lxp_Workbook_Submission` | Upsert and fetch student workbook answers (`POST/GET /workbook/submission`) |
| `courses.php` | `Rest_Lxp_Course` | LXP course sections, section lessons |
| `students.php` | `Rest_Lxp_Student` | CRUD students, settings, grade assignment, bulk import, Edlink sync |
| `teachers.php` | `Rest_Lxp_Teacher` | CRUD teachers, settings, student list, course restrictions |
| `assignments.php` | `Rest_Lxp_Assignment` | CRUD assignments, calendar events, stats, interactions |
| `assignment-submissions.php` | `Rest_Lxp_Assignment_Submission` | Submission tracking, feedback, grading |
| `classes.php` | `Rest_Lxp_Class` | Class CRUD, class students, LP course assignment (`lxp_class_course_ids`) |
| `districts.php` | `Rest_Lxp_District` | District CRUD, settings |
| `schools.php` | `Rest_Lxp_School` | School CRUD, settings |
| `groups.php` | `Rest_Lxp_Group` | Group CRUD, class groups, group students |
| `edlink-apis.php` | `Rest_Lxp_Edlink` | OAuth token exchange, provider/district/people sync |
| `ai-content.php` | `Rest_Lxp_AI_Content` | AI lesson generation via Bedrock; auto-detects standard vs workbook type; original content backup/restore |

**Security note**: All routes use `'permission_callback' => '__return_true'`. Access control is enforced inside callbacks; always verify `current_user_can()` or nonce for write operations.

---

## Elementor Widget Token Reference

All widgets live in `namespace Edudeme\Elementor`, registered via `Tiny_LXP_Widget::register_elementor_widgets()`. **Always prefix global LP class calls with `\`** inside this namespace (e.g., `\LP_Global::course_item()`, `\LP_Datetime::get_string_plural_duration()`) — bare names resolve to `Edudeme\Elementor\ClassName` and will throw a fatal.

### `LXP_Course_HTML_Widget` (`includes/widgets/lxp-course-html-widget.php`)

Designed for Elementor Theme Builder **single `lp_course` templates**. Resolves tokens against `learn_press_get_course( get_queried_object_id() )`.

| Token | Source |
|---|---|
| `{{lp_course_title}}` | `LP_Course::get_title()` |
| `{{lp_course_excerpt}}` | `get_the_excerpt()` |
| `{{lp_course_image_url}}` | `LP_Course::get_image_url('full')` |
| `{{lp_course_level}}` | `_lp_level` post meta |
| `{{lp_course_duration}}` | `_lp_duration` meta → `LP_Datetime::get_string_plural_duration()` |
| `{{lp_course_lesson_count}}` | `LP_Course::count_items(LP_LESSON_CPT)` |
| `{{lp_course_student_count}}` | `LP_Course::count_students()` |
| `{{lp_course_button}}` | `do_action('learn-press/course-buttons')` output |
| `{{lp_course_tags}}` | Comma-separated `course_tag` taxonomy terms |
| `{{lp_course_outcome}}` | `lxp_course_outcome` post meta |
| `{{lp_course_description}}` | `post_content` via `apply_filters('the_content', ...)` with `<style>` block protection |
| `{{#lp_course_tags}}...{{lp_course_tag}}...{{/lp_course_tags}}` | Per-tag repeat loop |
| `{{#lp_course_sections}}...{{/lp_course_sections}}` | Per-section repeat loop |

Section loop inner tokens: `{{lp_section_number}}` (zero-padded), `{{lp_section_index}}`, `{{lp_section_title}}`, `{{lp_section_first_lesson_title}}`, `{{lp_section_first_lesson_excerpt}}`, `{{lp_section_first_lesson_url}}`. Conditionals: `{{#lp_section_is_last}}`, `{{#lp_section_is_not_last}}`.

Backwards-compatible aliases: `{{lp_title}}`, `{{lp_excerpt}}`, `{{lp_image_url}}`, `{{lp_level}}`, `{{lp_duration}}`, `{{lp_lesson_count}}`, `{{lp_student_count}}`, `{{lp_enroll_button}}`.

Lesson URLs in section loop use `LP_Course::get_item_link($lesson_id)` — **not** `get_permalink()` — to produce the correct LP4 nested URL `/{course-slug}/lessons/{lesson-slug}/`.

### `LXP_Lesson_HTML_Widget` (`includes/widgets/lxp-lesson-html-widget.php`)

Designed for Elementor Theme Builder **single `lp_lesson` templates**. LP4 lesson URLs are `/{course}/lessons/{lesson-slug}/`; the queried object is the **course**, not the lesson. Resolution cascade:

1. `\LP_Global::course_item()` — LP sets this when processing lesson requests.
2. `get_queried_object_id()` — fallback if queried object type is `lp_lesson`.
3. `get_the_ID()` — global `$post` last resort.
4. Slug extraction from `$_SERVER['REQUEST_URI']` + `get_page_by_path()` against `lp_lesson` — **primary working path** when Elementor renders before LP_Global is set.

| Token | Source |
|---|---|
| `{{lp_lesson_title}}` | `WP_Post::post_title` |
| `{{lp_lesson_tagline}}` | `lxp_lesson_tagline` post meta |
| `{{lp_lesson_duration}}` | `_lp_duration` meta → `LP_Datetime::get_string_plural_duration()` |
| `{{lp_lesson_number}}` | SQL COUNT of preceding items (by `section_order` / `item_order`) |
| `{{lp_lesson_total}}` | `LP_Course::count_items(LP_LESSON_CPT)` |
| `{{lp_lesson_module_label}}` | Pre-composed `"Module X of Y"` |
| `{{lp_lesson_section_name}}` | `learnpress_sections.section_name` for this lesson's section |
| `{{lp_lesson_section_number}}` | `learnpress_sections.section_order` |
| `{{lp_course_title}}` | `LP_Course::get_title()` |
| `{{lp_course_image_url}}` | `LP_Course::get_image_url('full')` |

---

## AI Content Generation (AWS Bedrock)

### Overview

The AI Content Gen feature allows lesson authors to transform raw lesson text into a richly structured HTML page using AWS Bedrock (Claude model). It is surfaced as an "AI Content Gen" metabox on the `lp_lesson` admin edit screen. Two lesson types are supported: **standard** (two-pass classify+generate against one of 15 structural templates) and **workbook** (9-section interactive workbook activity). The type is auto-detected from the lesson title and original content.

### Files

| File | Class | Role |
|---|---|---|
| [includes/class-aws-bedrock-client.php](includes/class-aws-bedrock-client.php) | `TL_AWS_Bedrock_Client` | SDK wrapper — single entry point for all Bedrock calls |
| [lms/lms-rest-apis/ai-content.php](lms/lms-rest-apis/ai-content.php) | `Rest_Lxp_AI_Content` | REST endpoints; prompt builder; lesson type detection; original content backup |
| [lms/class-learnpress-lesson-extension.php](lms/class-learnpress-lesson-extension.php) | `TL_LearnPress_Lesson_Extension` | Registers the "AI Content Gen" side metabox on `lp_lesson` |
| [admin/js/tiny-lxp-platform-post.js](admin/js/tiny-lxp-platform-post.js) | — | Generate + Reset button click handlers; `tinyLxpSetEditorContent()` helper |
| [admin/css/tiny-lxp-platform-post.css](admin/css/tiny-lxp-platform-post.css) | — | Button layout + status message styles |

### REST Endpoints

| Method | Route | Callback | Purpose |
|---|---|---|---|
| `POST` | `/wp-json/lms/v1/lesson/ai-content` | `Rest_Lxp_AI_Content::generate_lesson_content()` | Sends lesson content to Bedrock; auto-detects lesson type; backs up original on first call; returns AI HTML + `lesson_type` (+ `template_id` for standard) |
| `GET` | `/wp-json/lms/v1/lesson/original-content` | `Rest_Lxp_AI_Content::get_original_content()` | Returns the pre-generation backup stored in `lxp_lesson_original_content` meta |

**Request params** (`POST`): `post_id` (int), `lesson_content` (string — current editor content).  
**Request params** (`GET`): `post_id` (int).  
Both callbacks enforce `current_user_can('edit_post', $post_id)`.  
**Response** (`POST`) includes `content` (HTML string), `lesson_type` (`'standard'` or `'workbook'`), and — for standard lessons only — `template_id` (`'01'`–`'15'`). The admin JS displays `"(Template XX applied)"` in the status message when `template_id` is present.

### `TL_AWS_Bedrock_Client`

```php
TL_AWS_Bedrock_Client::invoke_bedrock( $user_message, $system_prompt = '' )
// Returns string (generated HTML) or WP_Error on failure.
```

- Uses `BedrockRuntimeClient::converse()` from `aws/aws-sdk-php`.
- Model: `TL_AWS_Bedrock_Client::MODEL_ID` (default `anthropic.claude-sonnet-4-6:0`).
- Region: `TL_AWS_Bedrock_Client::REGION` (default `us-east-1`).
- `inferenceConfig`: `maxTokens = 4096`, `temperature = 0.3`.
- Credentials: resolved automatically by the SDK from the EC2 instance IAM role (no manual config needed on EC2). On non-EC2 environments use environment variables or `~/.aws/credentials`.
- The client instance is cached in a static property for the lifetime of the PHP request.

### Prompt Architecture

The generation uses a **split system/user prompt** and auto-detects the lesson type:

**Lesson type detection** (`Rest_Lxp_AI_Content::detect_lesson_type($post_id, $lesson_content)`):
- Returns `'workbook'` when the lesson **title** contains "workbook" (case-insensitive) OR the **pre-AI content** contains the phrase "Workbook Entry".
- Otherwise returns `'standard'`.
- Detection always runs on the **original (pre-AI) content** passed in the request — not on previously-generated HTML.

**Standard lesson — two-pass flow**:
- **Pass 1 — Template classification** (`classify_template($lesson_title, $lesson_content)`):
  - Sends lesson title + content + the 15-entry `$template_manifest` to Bedrock with a tightly constrained system prompt.
  - Bedrock returns a two-digit ID (`'01'`–`'15'`). Falls back to `'01'` on error or invalid response.
  - The manifest is structural (not subject-specific): each entry describes the HTML layout pattern for that template.
- **Pass 2 — Content generation** (`get_template($id)` → `build_template_system_prompt()` → `build_template_user_message()`):
  - `get_template()` returns the full HTML template heredoc for the classified ID via a switch statement calling `_tpl_01()` through `_tpl_15()`.
  - Every template shares the same shell: **Hero Header → Learning Outcomes → Opening Hook → template-specific core sections → Capstone Activity**.
  - Shared helper methods: `hero_html()`, `meta_table_html()` (now returns Learning Outcomes + Opening Hook), `capstone_html()`, `quiz_html()` (retired — returns `''`).
  - **No quiz or "Check for Understanding" section** appears in standard templates — the lessons end with the Capstone Activity only.
  - `build_template_system_prompt()` instructs Claude to: fill `[OUTCOME_1]`–`[OUTCOME_4]` with actionable outcomes, fill `[OPENING_HOOK_STATEMENT]` with a framing statement, preserve `[Capstone Box]` exactly, and **never add a quiz section**.

**15-template manifest** (structural patterns — topic-agnostic):

| ID | Structural Description |
|---|---|
| 01 | Stats/evidence grid + numbered consequence cards + myth-vs-reality two-column |
| 02 | Goal-alignment cards + strategy-to-mission mapping |
| 03 | Role-split two-column + opportunity/boundary table |
| 04 | Compliance rule cards + do/never list + violation/risk two-column |
| 05 | Criteria-based evaluation grid + tier/access decision cards |
| 06 | Adoption-stats block + supportive/problematic contrast + gap warning dark block |
| 07 | Failure-reason icon grid + alert block + alternatives row |
| 08 | Curated option cards + access/tier grid |
| 09 | Audience-split cards + domain grid + stability/flexibility dark block |
| 10 | Three-tier framework cards + implementation icon cards + goal-matching |
| 11 | Standards-definition block + proper/improper conduct comparison |
| 12 | Cognitive-framing card + augments/bypasses contrast + visible-thinking row |
| 13 | Audience-specific message cards + communication-channel grid |
| 14 | Bridge-mapping grid + alignment checklist |
| 15 | Numbered component grid + writing-guidelines dark block + living-document cycle |

**Workbook lesson**:
- **System prompt** (`build_workbook_system_prompt($lesson_title='')`): Instructs Claude to produce a 9-section interactive workbook page; mandates that every `[Text Box]` sentinel div is preserved verbatim.
- **User message** (`build_workbook_user_message($lesson_content, $lesson_title='')`): Contains a 9-section HTML template:
  1. Hero Header
  2. Activity Overview (with metadata table)
  3. Why This Matters
  4. Reflection Prompt
  5. Workbook Entry (field blocks with `[Text Box]` sentinel divs)
  6. Example
  7. Important Note
  8. Next Step
  9. Check for Understanding (multiple-choice quiz)

**`[Text Box]` sentinel pattern**: Workbook Entry field blocks store `[Text Box]` as plain text inside a styled `<div>`. This text is safe to save in WP post content (kses-safe). The frontend JS in `public/js/lxp-workbook.js` converts each sentinel div to a `<textarea>` at runtime — never store `<textarea>` or `<input>` in post content.

### Original Content Backup

- Meta key: `lxp_lesson_original_content`
- Written **once** when `generate_lesson_content()` is called and no backup exists yet.
- Subsequent AI generations do **not** overwrite it — the true original is always preserved.
- The "Reset to Original" JS button calls `GET /wp-json/lms/v1/lesson/original-content` and restores the editor via `tinyLxpSetEditorContent()`.
- Lesson type detection also uses this original content (passed as `lesson_content` in the POST body), not the AI-generated HTML.

### JS Editor Integration

`tinyLxpSetEditorContent(html)` in `admin/js/tiny-lxp-platform-post.js` handles all three WP editor modes:
1. Block editor: `wp.data.dispatch('core/editor').editPost({ content: html })`
2. Classic (TinyMCE): `tinymce.get('content').setContent(html)`
3. Fallback: `document.getElementById('content').value = html`

### Wiring / Load Order

`lms/lms-rest-apis/lms-rest-api.php` requires both new files before `LMS_REST_API::init()`:
```php
require_once( LMS__PLUGIN_DIR . '../includes/class-aws-bedrock-client.php' );
require_once( LMS__PLUGIN_DIR . 'lms-rest-apis/ai-content.php' );
```
`Rest_Lxp_AI_Content::init()` is called inside `LMS_REST_API::init()`.

### Extending or Modifying AI Generation

- To change the model: update `TL_AWS_Bedrock_Client::MODEL_ID`. The model must be enabled in the AWS account's Bedrock Model Access settings.
- To change the region: update `TL_AWS_Bedrock_Client::REGION`.
- To change a standard template's core sections: edit the corresponding `_tpl_01()` through `_tpl_15()` private method in `Rest_Lxp_AI_Content`. Each template's shell (Learning Outcomes + Opening Hook + Capstone) is shared via helper methods.
- To change the shared Learning Outcomes + Opening Hook sections: edit `Rest_Lxp_AI_Content::meta_table_html()` (the method was repurposed; the name is legacy).
- To change the Capstone Activity section: edit `Rest_Lxp_AI_Content::capstone_html()`.
- To add a new standard template (16th, etc.): add a new `_tpl_16()` method, add a case to `get_template()`, and add an entry to `$template_manifest`.
- To change the template system prompt: edit `Rest_Lxp_AI_Content::build_template_system_prompt()`. The no-quiz rule and LO requirement are enforced here.
- To change the template user message: edit `Rest_Lxp_AI_Content::build_template_user_message()`.
- To change the workbook template: edit `Rest_Lxp_AI_Content::build_workbook_user_message()`. The `<<<'HTML'` heredoc holds the 9-section workbook template.
- To change workbook system-level behaviour: edit `Rest_Lxp_AI_Content::build_workbook_system_prompt()`.
- To change the lesson type detection logic: edit `Rest_Lxp_AI_Content::detect_lesson_type()`.
- To adjust token budget or temperature: edit the `inferenceConfig` array in `TL_AWS_Bedrock_Client::invoke_bedrock()`.

---

## Capstone Submissions

### Overview

Capstone submissions persist a student's written response to the `[Capstone Box]` field in any AI-generated standard lesson. One row per (student, lesson) pair — resubmission is an upsert. Storage uses a **custom DB table** created on plugin activation.

### Files

| File | Class | Role |
|---|---|---|
| [lms/repositories/class-capstone-submission-repository.php](lms/repositories/class-capstone-submission-repository.php) | `TL_Capstone_Submission_Repository` | All DB access for the `{prefix}lxp_capstone_submissions` table |
| [lms/lms-rest-apis/capstone-submissions.php](lms/lms-rest-apis/capstone-submissions.php) | `Rest_Lxp_Capstone_Submission` | REST endpoints — upsert and fetch submission |
| [public/js/lxp-capstone.js](public/js/lxp-capstone.js) | — | Frontend: finds `.lxp-capstone-box` (with legacy text fallback), converts `[Capstone Box]` sentinel to `<textarea>`, pre-fills, Save button |
| [public/class-tiny-lxp-platform-public.php](public/class-tiny-lxp-platform-public.php) | `Tiny_LXP_Platform_Public` | `enqueue_capstone_scripts()` — enqueues JS on LP4 lesson URLs using a lesson-ID resolver helper |
| [admin/class-capstone-submission-list-table.php](admin/class-capstone-submission-list-table.php) | `TL_Capstone_Submission_List_Table` | Admin list table with course/lesson/user filters |
| [admin/partials/capstone-submissions-admin.php](admin/partials/capstone-submissions-admin.php) | — | Admin list page partial |
| [admin/partials/capstone-submission-detail-admin.php](admin/partials/capstone-submission-detail-admin.php) | — | Admin detail view partial |
| [lms/templates/tinyLxpTheme/page-capstone-journal.php](lms/templates/tinyLxpTheme/page-capstone-journal.php) | — | Student-facing Capstone Journal page |

### Database Table

Table: `{prefix}lxp_capstone_submissions`  
Created by `on_activate()` in `TinyLxp-wp-plugin.php`.

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint unsigned AUTO_INCREMENT` | Primary key |
| `lesson_id` | `bigint unsigned NOT NULL` | `lp_lesson` post ID |
| `course_id` | `bigint unsigned NOT NULL` | Parent `lp_course` post ID |
| `user_id` | `bigint unsigned NOT NULL` | WordPress user ID |
| `response` | `longtext NOT NULL` | Student's written capstone response |
| `submitted_at` | `datetime NOT NULL` | Set on first insert |
| `updated_at` | `datetime NOT NULL` | Updated on every upsert |

Indexes: `UNIQUE KEY lesson_user (lesson_id, user_id)`, `KEY course_id`, `KEY user_id`.

### REST Endpoints

| Method | Route | Callback | Auth | Purpose |
|---|---|---|---|---|
| `POST` | `/wp-json/lms/v1/lesson/capstone-submission` | `Rest_Lxp_Capstone_Submission::upsert_submission()` | `is_user_logged_in()` | Save/update capstone response |
| `GET` | `/wp-json/lms/v1/lesson/capstone-submission` | `Rest_Lxp_Capstone_Submission::get_submission()` | `is_user_logged_in()` | Fetch current user's response |
| `GET` | `/wp-json/lms/v1/course/capstone-submissions` | `Rest_Lxp_Capstone_Submission::get_course_submissions()` | `is_user_logged_in()` | All lessons in a course with the user's responses (journal) |

**POST params**: `lesson_id` (int), `response` (string). `course_id` is derived server-side via `TL_LearnPress_Section_Repository::get_course_id_by_item_id()` — do not pass it from the client.  
**GET params**: `lesson_id` (int) for `/lesson/capstone-submission`; `course_id` (int) for `/course/capstone-submissions`.  
All return 401 if the user is not logged in.

### Frontend JS (`lxp-capstone.js`)

Enqueued on LP4 lesson URLs via `Tiny_LXP_Platform_Public::enqueue_capstone_scripts()`.  
Localised as `lxp_capstone_vars`: `{ rest_url, nonce, lesson_id, course_id }`.

1. Resolves the current lesson ID on LP4 lesson URLs (`/{course}/lessons/{lesson-slug}/`) before localising script vars.
2. Finds the capstone sentinel inside `.lp-ai-lesson-template` by querying `.lxp-capstone-box`.
3. Falls back to text-matching leaf divs containing `[Capstone Box]` for legacy lessons generated before the class was added.
4. Replaces the sentinel div with a `<textarea>`.
5. On load: GETs `/lesson/capstone-submission?lesson_id=N` and pre-fills any saved response.
6. Injects a Save button at the bottom of the capstone section.
7. On Save: POSTs `{ lesson_id, response }` to `/lesson/capstone-submission`. (`course_id` is resolved server-side.)

### `[Capstone Box]` sentinel pattern

The Capstone Activity section in AI-generated HTML contains `[Capstone Box]` as plain text inside a styled `<div class="lxp-capstone-box">`. WP kses strips `<textarea>` from post content, so the sentinel is used instead. The frontend JS converts it to an interactive `<textarea>` at runtime. Never store `<textarea>` or `<input>` in post content. Keep the `lxp-capstone-box` class on new standard templates; `lxp-capstone.js` only text-matches as a backwards-compatibility fallback for older lessons already stored in the database.

### Admin Menu

| Menu | Slug | Position |
|---|---|---|
| Curriki Learn (top-level) | `curriki-learn` | 30 |
| Capstone Submissions (submenu) | `curriki-learn-capstone-submissions` | under Curriki Learn |

`capstone_submissions_page()` branches on `$_GET['view']`: `'detail'` shows a single submission; default shows the filterable list.

---

## Workbook Submissions

### Overview

Workbook submissions persist a student's answers to the `[Text Box]` fields in a workbook-type AI lesson. One row per (student, lesson) pair — resubmission is an upsert. Storage uses a **custom DB table** (not a CPT) created on plugin activation.

### Files

| File | Class | Role |
|---|---|---|
| [lms/repositories/class-workbook-submission-repository.php](lms/repositories/class-workbook-submission-repository.php) | `TL_Workbook_Submission_Repository` | All DB access for the `{prefix}lxp_workbook_submissions` table |
| [lms/lms-rest-apis/workbook-submissions.php](lms/lms-rest-apis/workbook-submissions.php) | `Rest_Lxp_Workbook_Submission` | REST endpoints — upsert and fetch submission |
| [public/js/lxp-workbook.js](public/js/lxp-workbook.js) | — | Frontend: converts `[Text Box]` to `<textarea>`, pre-fills, Save button |
| [public/class-tiny-lxp-platform-public.php](public/class-tiny-lxp-platform-public.php) | `Tiny_LXP_Platform_Public` | `enqueue_workbook_scripts()` — enqueues JS on `lp_lesson` pages |
| [admin/class-workbook-submission-list-table.php](admin/class-workbook-submission-list-table.php) | `TL_Workbook_Submission_List_Table` | Admin list table with course/lesson/user filters |
| [admin/partials/workbook-submissions-admin.php](admin/partials/workbook-submissions-admin.php) | — | Admin list page partial |
| [admin/partials/workbook-submission-detail-admin.php](admin/partials/workbook-submission-detail-admin.php) | — | Admin detail view partial |
| [admin/class-tiny-lxp-platform-admin.php](admin/class-tiny-lxp-platform-admin.php) | `Tiny_LXP_Platform_Admin` | `register_curriki_learn_menu()` + `workbook_submissions_page()` |

### Database Table

Table: `{prefix}lxp_workbook_submissions`  
Created by `on_activate()` in `TinyLxp-wp-plugin.php`.

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint unsigned AUTO_INCREMENT` | Primary key |
| `lesson_id` | `bigint unsigned NOT NULL` | `lp_lesson` post ID |
| `course_id` | `bigint unsigned NOT NULL` | Parent `lp_course` post ID |
| `user_id` | `bigint unsigned NOT NULL` | WordPress user ID |
| `fields` | `longtext NOT NULL` | JSON-encoded `{ label: answer }` pairs |
| `submitted_at` | `datetime NOT NULL` | Set on first insert |
| `updated_at` | `datetime NOT NULL` | Updated on every upsert |

Indexes: `UNIQUE KEY lesson_user (lesson_id, user_id)`, `KEY course_id`, `KEY user_id`.

### REST Endpoints

| Method | Route | Callback | Auth | Purpose |
|---|---|---|---|---|
| `POST` | `/wp-json/lms/v1/workbook/submission` | `Rest_Lxp_Workbook_Submission::upsert_submission()` | `is_user_logged_in()` | Save/update answers |
| `GET` | `/wp-json/lms/v1/workbook/submission` | `Rest_Lxp_Workbook_Submission::get_submission()` | `is_user_logged_in()` | Fetch current user's answers |

**POST params**: `lesson_id` (int), `course_id` (int), `fields` (assoc array `{ label: answer }`).  
**GET params**: `lesson_id` (int).  
Both return 401 if the user is not logged in. Field labels and values are sanitized with `sanitize_text_field()` / `sanitize_textarea_field()`.

### Frontend JS (`lxp-workbook.js`)

Enqueued on every `lp_lesson` singular page via `Tiny_LXP_Platform_Public::enqueue_workbook_scripts()`.  
Localised as `lxp_workbook_vars`: `{ rest_url, nonce, lesson_id, course_id }`.

1. Finds the section with `<h3>Workbook Entry</h3>` inside `.lp-ai-lesson-template`.
2. Replaces every `[Text Box]` sentinel div with a `<textarea>`; derives the label from the preceding `<strong>` element.
3. On load: GETs `/workbook/submission?lesson_id=N` and pre-fills saved answers.
4. Injects a Save button at the bottom of the Workbook Entry section.
5. On Save: POSTs `{ lesson_id, course_id, fields }` to `/workbook/submission`.

### Admin Menu

| Menu | Slug | Position |
|---|---|---|
| Curriki Learn (top-level) | `curriki-learn` | 30 |
| Workbook Submissions (submenu) | `curriki-learn-workbook-submissions` | under Curriki Learn |

Registered by `Tiny_LXP_Platform_Admin::register_curriki_learn_menu()`, hooked to `admin_menu` via the loader.  
`workbook_submissions_page()` branches on `$_GET['view']`: `'detail'` shows a single submission with all fields; default shows the filterable list.

---

## Class–Course Association

### Overview

A `tl_class` post can be directly associated with one or more LearnPress courses (`lp_course`). This is a first-class relationship separate from the indirect course-class link created through Assignments. The association is stored as repeating post meta on the class post — identical to the `lxp_student_ids` pattern.

**Meta key**: `lxp_class_course_ids` on `tl_class` posts. Multiple entries, one per assigned `lp_course` post ID.

### Files

| File | Role |
|---|---|
| [lms/lms-rest-apis/classes.php](lms/lms-rest-apis/classes.php) | `Rest_Lxp_Class` — three new methods and routes; `get_one()` and `create()` updated |
| [lms/templates/tinyLxpTheme/lxp/admin-class-modal.php](lms/templates/tinyLxpTheme/lxp/admin-class-modal.php) | Admin class modal — Courses picker section + `loadAvailableCourses()` JS |
| [lms/templates/tinyLxpTheme/lxp/teacher-class-modal.php](lms/templates/tinyLxpTheme/lxp/teacher-class-modal.php) | Teacher class modal — same Courses picker |
| [lms/templates/tinyLxpTheme/lxp/admin-classes.php](lms/templates/tinyLxpTheme/lxp/admin-classes.php) | Admin class list — Courses count column |
| [lms/templates/tinyLxpTheme/lxp/teacher-classes.php](lms/templates/tinyLxpTheme/lxp/teacher-classes.php) | Teacher class list — Courses count column |

### REST Endpoints

All in `Rest_Lxp_Class` (`lms/lms-rest-apis/classes.php`):

| Method | Route | Callback | Purpose |
|---|---|---|---|
| `POST` | `/wp-json/lms/v1/class/available-courses` | `get_available_courses()` | Returns all published `lp_course` posts (`{ID, post_title, audience}`) for the modal picker; `audience` holds the `Students` / `PD` badge labels |
| `POST` | `/wp-json/lms/v1/class/courses` | `get_class_courses()` | Returns courses currently assigned to a class (`{ID, post_title, permalink}`) |
| `POST` | `/wp-json/lms/v1/class/courses/save` | `save_class_courses()` | Replaces all assigned courses for a class; params: `class_id`, `course_ids[]` |

The existing `POST /wp-json/lms/v1/classes/save` endpoint also accepts an optional `course_ids[]` array, saving course associations as part of the normal class create/edit flow.

`GET /wp-json/lms/v1/classes` (`get_one()`) now includes `lxp_class_course_ids` in its response.

### Data Storage

```
wp_postmeta (post_type = tl_class)
  lxp_class_course_ids = 101   ← one row per assigned lp_course ID
  lxp_class_course_ids = 204
```

On save: all existing `lxp_class_course_ids` meta entries are deleted, then one `add_post_meta()` call per selected course — identical to how `lxp_student_ids` is saved.

### UI

- The class modal (both admin and teacher variants) renders a **Courses dropdown picker** after the Students picker. Courses are loaded once on page load via `loadAvailableCourses()` → `POST /class/available-courses`.
- When editing an existing class, `onClassEdit()` reads `class_record.lxp_class_course_ids` from `GET /classes` and pre-checks the matching course checkboxes.
- `course_ids[]` checkboxes are picked up automatically by the existing `FormData` form submission to `/classes/save`.
- Both the Classes table and the Groups/Other-Groups table in the admin and teacher class list views show a **Courses** count column.

### Relationship to Assignments

Course–class association via `lxp_class_course_ids` is a **direct link** (which courses are available or assigned to this class). The existing **Assignment** flow (which lessons from a course are due for which students by when) is independent and unchanged. The two mechanisms coexist — assigning a course to a class does not auto-enroll students or create assignments.

---

## Admin UI Reference

| Menu/Page | Slug | Location |
|---|---|---|
| LXP Dashboard | `../dashboard` | Top-level WP menu (position 25) |
| Curriki Learn | `curriki-learn` | Top-level WP menu (position 30) |
| Capstone Submissions | `curriki-learn-capstone-submissions` | Under Curriki Learn |
| Workbook Submissions | `curriki-learn-workbook-submissions` | Under Curriki Learn |
| Edlink Settings | `edlink_options` | Top-level WP menu |
| Tiny LXP Tools (LTI tool list) | `lti-platform` | Settings submenu |
| Add Tiny LXP Tool | `lti-platform-edit` | Hidden submenu |
| Tiny LXP Platform Settings | `lti-platform-settings` | Hidden submenu |

**Settings option key**: `edlink_options` (array with `edlink_application_id`, `edlink_application_secrets`, `edlink_sso_enable`).

**Activation auto-pages**: On activation, the plugin creates WP pages for: `Assignment`, `Assignments`, `Calendar`, `Classes`, `Courses`, `Dashboard`, `Districts`, `Grade Assignment`, `Grade Summary`, `Grades`, `Groups`, `Lessons`, and more.

---

## Finding Related Code

Use this sequence before editing:

1. Check constants in [lms/tl-constants.php](lms/tl-constants.php) for CPT slugs and [lms/xapi-constants.php](lms/xapi-constants.php) for external service URLs.
2. For CPT behavior: open the matching `lms/class-{entity}-post-type.php` and its parent `lms/class-abstract-tl-post-type.php`. For `lp_course`/`lp_lesson`, inspect `lms/class-learnpress-course-extension.php` and `lms/class-learnpress-lesson-extension.php`.
3. For repository-backed data access: inspect `lms/repositories/` (`class-grades-repository.php`, `class-trek-event-repository.php`, `class-learnpress-section-repository.php`, `class-lti-metadata-repository.php`) and prefer these over adding inline SQL in REST handlers.
4. For REST endpoints: open `lms/lms-rest-apis/{entity}.php` and trace registration in `LMS_REST_API::init()`.
5. For AI content generation: `includes/class-aws-bedrock-client.php` (SDK wrapper) → `lms/lms-rest-apis/ai-content.php` (REST routes + two-pass classifier + 15 templates + lesson type detection) → `lms/class-learnpress-lesson-extension.php` (admin metabox) → `admin/js/tiny-lxp-platform-post.js` (button handlers).
6. For capstone submissions: `lms/repositories/class-capstone-submission-repository.php` (DB) → `lms/lms-rest-apis/capstone-submissions.php` (REST) → `public/js/lxp-capstone.js` (frontend) → `admin/class-capstone-submission-list-table.php` + `admin/partials/capstone-submission*.php` (admin UI) → `lms/templates/tinyLxpTheme/page-capstone-journal.php` (student journal page).
7. For workbook submissions: `lms/repositories/class-workbook-submission-repository.php` (DB) → `lms/lms-rest-apis/workbook-submissions.php` (REST) → `public/js/lxp-workbook.js` (frontend) → `admin/class-workbook-submission-list-table.php` + `admin/partials/workbook-submission*.php` (admin UI).
8. For class–course associations: `lms/lms-rest-apis/classes.php` (`get_available_courses`, `get_class_courses`, `save_class_courses`, updated `get_one`, updated `create`) → `lms/templates/tinyLxpTheme/lxp/admin-class-modal.php` + `teacher-class-modal.php` (picker UI + `loadAvailableCourses` JS) → `lms/templates/tinyLxpTheme/lxp/admin-classes.php` + `teacher-classes.php` (Courses count column). Meta key: `lxp_class_course_ids` on `tl_class` posts.
9. For admin UI: trace `admin/class-tiny-lxp-platform-admin.php` → `admin/partials/`.
9. For page rendering: check `lms/templates/tinyLxpTheme/page-{slug}.php`, then partials under `lms/templates/tinyLxpTheme/lxp/`.
10. For LTI flows: trace `public/class-tiny-lxp-platform-public.php::parse_request()` → `includes/class-tiny-lxp-platform-platform.php`.
11. For Elementor widgets: `includes/class-tiny-lxp-platform-widget.php` (registry) → `includes/widgets/lxp-{name}-widget.php`.
12. For hook registration: `includes/class-tiny-lxp-platform.php::define_admin_hooks()` / `define_public_hooks()` → `includes/class-tiny-lxp-platform-loader.php::run()`.

**Search strategy**:
- Use semantic search for concept-level discovery (e.g., "student grade assignment REST").
- Use grep for exact hook names, CPT slugs, option keys, or REST route paths.
- When an endpoint behaves unexpectedly, grep for the route path string in `lms/lms-rest-apis/`.

---

## Validating Changes

MANDATORY: validate in this order before declaring a task complete.

1. Verify that any new PHP class file is correctly named (`class-{kebab-name}.php`) and follows the singleton pattern if it is a CPT class.
2. Confirm the file is either autoloaded (via Composer classmap/PSR-4) or explicitly `require_once`'d in the appropriate bootstrap location.
3. If a REST endpoint was added or changed, test it using a REST client (`GET /wp-json/lms/v1/` shows all registered routes).
4. If a CPT was registered or changed, flush rewrite rules: go to **Settings > Permalinks** and save, or call `flush_rewrite_rules()` during activation.
5. Check for PHP syntax errors: `php -l {file}`.
6. If template files were added, verify the page slug matches the `page-{slug}.php` filename convention and that the template is correctly loaded.

---

## Runtime and Dev Commands

```bash
# Install PHP dependencies (required after clone or library updates)
composer install

# Check for outdated dependencies
composer outdated

# Check PHP syntax on a file
php -l path/to/file.php

# Flush WordPress rewrite rules after CPT changes (WP-CLI)
wp rewrite flush

# Activate the plugin (WP-CLI)
wp plugin activate TinyLxp-wp-plugin

# Deactivate the plugin (WP-CLI)
wp plugin deactivate TinyLxp-wp-plugin

# Check registered REST routes (WP-CLI)
wp rest route list --namespace=lms/v1 --fields=route,methods

# Run DB migrations / create required tables (handled on plugin activation)
# Re-trigger by deactivating and reactivating the plugin

# Dump all registered hooks (WP-CLI, useful for debugging)
wp hook list
```

> There is no JavaScript build pipeline. JS and CSS files in `admin/js/`, `admin/css/`, and `includes/widgets/assets/` are hand-written / pre-compiled. Edit them directly.

---

## Coding Guidelines

### Scope and Diff Discipline
- Implement only what was explicitly requested.
- Keep diffs minimal — do not touch unrelated hooks, templates, or option keys.
- Do not rename CPT slugs or meta keys without a full audit of templates, queries, and REST callbacks that reference them.
- Do not move files or change class names without updating all references and the Composer classmap.

### PHP Style and Naming
- Follow existing WordPress coding standards (tabs for indentation, `snake_case` for functions and variables, `PascalCase` for classes).
- New CPT classes: extend `TL_Post_Type`, use the `$_instance` / `instance()` singleton, name the file `class-{entity}-post-type.php` in `lms/`.
- New REST classes: name them `Rest_Lxp_{Entity}`, file `{entity}.php` in `lms/lms-rest-apis/`, register via `LMS_REST_API::init()`.
- New Elementor widgets: extend `\Elementor\Widget_Base` under namespace `Edudeme\Elementor`, file `lxp-{name}-widget.php` in `includes/widgets/`, register in `Tiny_LXP_Widget`.
- Use `$wpdb->prefix` for all database table references — never hardcode prefixes.
- Escape all output: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` as appropriate.
- Sanitize all input: `sanitize_text_field()`, `absint()`, `sanitize_email()`, etc.

### WordPress Hook Guidelines
- **Admin/public hooks** (enqueue scripts, admin menus, settings): register through `Tiny_LXP_Platform_Loader` in `Tiny_LXP_Platform::define_admin_hooks()` / `define_public_hooks()`.
- **CPT-specific hooks** (init, save_post, add_meta_boxes, rest_api_init): register directly in the CPT constructor following the `TL_Post_Type::__construct()` pattern.
- **LearnPress lesson save hooks**: use post-type-specific `save_post_lp_lesson` for lesson metabox persistence (registered in `Tiny_LXP_Platform::define_public_hooks()`), not generic `save_post`.
- **LearnPress lesson REST save**: use `rest_insert_lp_lesson` mapped to `TL_LearnPress_Lesson_Extension::insert_post_api()`.
- **Lesson metabox nonces**: LTI save uses `lesson_lti_nonce` / `save_lesson_lti_options`; tagline save uses `lxp_lesson_tagline_nonce` / `save_lxp_lesson_tagline`. Both handlers are wired to `save_post_lp_lesson` — LTI at priority 10 (`save_tl_post`), tagline at priority 20 (`save_lesson_tagline_meta`).
- **REST routes**: register inside a static `init()` method called from `add_action('rest_api_init', ...)` in `LMS_REST_API::init()`.
- Never call `add_action()` or `add_filter()` at the global file scope in new files.

### Security Rules
- **REST callbacks** must validate user capabilities for any write or sensitive read operations using `current_user_can()`.
- **Nonces**: use `wp_verify_nonce()` for admin form submissions; use `check_ajax_referer()` for AJAX. Localize nonces to JavaScript via `wp_localize_script()`.
- **No hardcoded secrets**: store API tokens and credentials in WordPress options; expose to JavaScript only what is needed, never secrets.
- **SQL queries**: always use `$wpdb->prepare()` for parameterized queries. Never interpolate user input directly into SQL strings.
- **Output**: always escape before echoing. Never use `echo $_GET[...]` or `echo $_POST[...]` directly.

### Error Handling
- Return `WP_Error` objects from REST callbacks on failure; WordPress REST infrastructure converts them to proper JSON error responses.
- Use `wp_die()` only in admin contexts where appropriate.
- Propagate errors up; do not silently swallow them with empty `catch` blocks.

### Imports and Dependencies
- Do not add new Composer packages without updating `composer.json` and running `composer install`.
- Do not `require_once` files that are already autoloaded via Composer.
- Add new plugin-level `require_once` calls in `includes/class-tiny-lxp-platform.php::load_dependencies()`.

---

## Template Conventions

- **Page templates**: `lms/templates/tinyLxpTheme/page-{page-slug}.php`. The slug must match the WordPress page slug exactly.
- **Dashboard partials**: `lms/templates/tinyLxpTheme/lxp/{role}-{feature}.php` (e.g., `teacher-dashboard.php`, `student-grades.php`).
- **Modal partials**: `lms/templates/tinyLxpTheme/lxp/admin-{entity}-modal.php`.
- **Edlink partials**: `lms/templates/tinyLxpTheme/lxp/edlink/`.
- **Single CPT templates**: override via `single_template` filter in the CPT class; fallback to `lms/templates/tinyLxpTheme/single-{cpt}.php`.
- Include sub-partials with `include` (not `require_once`) unless the file is absolutely required to exist.

---

## External Service Configuration

| Service | Config Location | Constant/Option |
|---|---|---|
| Edlink | `edlink_options` WP option | `edlink_application_id`, `edlink_application_secrets` |
| xAPI LRS | [lms/xapi-constants.php](lms/xapi-constants.php) | `XAPI_HOST` |
| Curriki Studio | [lms/xapi-constants.php](lms/xapi-constants.php) | `CURRIKI_STUDIO_HOST` |
| Tsugi | [lms/xapi-constants.php](lms/xapi-constants.php) | `TSUGI_HOST` |
| LTI settings | WordPress options via `Tiny_LXP_Platform::get_settings_name()` | — |
| AWS Bedrock | [includes/class-aws-bedrock-client.php](includes/class-aws-bedrock-client.php) | `TL_AWS_Bedrock_Client::REGION`, `TL_AWS_Bedrock_Client::MODEL_ID` |

When changing external service hosts, update constants in [lms/xapi-constants.php](lms/xapi-constants.php) — do not hardcode URLs elsewhere.

**AWS Bedrock configuration**: Region and model ID are class constants in `TL_AWS_Bedrock_Client`. Credentials are **never stored in code or options** — the SDK resolves them automatically from the EC2 instance IAM role via IMDSv2. The plugin will not work in non-EC2 environments unless AWS credentials are configured by another SDK-supported method (environment variables, `~/.aws/credentials`).

---

## Known Issues / Watch-outs

1. **CPT slug ambiguity**: `TL_COURSE_CPT = 'lp_course'` and `TL_LESSON_CPT = 'lp_lesson'` are LearnPress-managed types extended by TinyLxp hooks, while most `tl_*` entities are plugin-owned CPT registrations. Verify which slug family a query targets.
2. **Typo in class name**: `TL_Assingment_Post_Type` (double-s). Do not "fix" this spelling without updating all references.
3. **Typo in REST route**: `/shools/save` (missing 'c'). Do not change without a migration plan for existing API consumers.
4. **Duplicate main file**: `TinyLxp-wp-plugin.php` and `tiny-lxp-platform.php` are identical. WordPress uses the file matching the directory name as plugin entry. Do not delete either without testing.
5. **LearnPress dependency**: Code in `Rest_Lxp_Course` directly queries `{prefix}learnpress_sections` — LearnPress must be active or those queries will fail silently.
6. **REST auth**: `'permission_callback' => '__return_true'` on all routes. Every new endpoint must implement its own authorization logic inside the callback.
7. **Lesson extension path**: `lp_lesson` behavior is handled through `TL_LearnPress_Lesson_Extension` hooks; do not add a new `class-lesson-post-type.php` registration path unless explicitly doing an architecture migration.
8. **LP4 lesson URL context**: On LP4 lesson pages (`/{course}/lessons/{lesson}/`), `get_queried_object_id()` returns the **course** post ID, not the lesson ID. `LP_Global::course_item()` is also null when Elementor renders before LP processes its routing. Use URL slug extraction as the reliable fallback: `basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))` + `get_page_by_path($slug, OBJECT, LP_LESSON_CPT)`. See `LXP_Lesson_HTML_Widget::get_current_lesson_and_course()`.
9. **Namespace backslash for LP globals in widgets**: All Elementor widgets live in `namespace Edudeme\Elementor`. Any bare LP class reference (`LP_Global`, `LP_Datetime`, `LP_Section_DB`, etc.) resolves to `Edudeme\Elementor\LP_*` and causes a fatal. Always use the global-namespace prefix: `\LP_Global::course_item()`, `\LP_Datetime::get_string_plural_duration()`, etc.
10. **AWS SDK PHP version constraint**: `aws/aws-sdk-php` latest (≥3.338) requires PHP ≥8.1. The server runs an older PHP version; Composer resolved to `3.337.3`. Do not run `composer update aws/aws-sdk-php` without verifying the server's PHP version first.
11. **AI content meta key**: The original lesson content backup is stored under `lxp_lesson_original_content` post meta. It is written only once (on first AI generation) and is never overwritten by subsequent generations. The "Reset to Original" button reads this meta via `GET /wp-json/lms/v1/lesson/original-content`.
12. **Bedrock model ID**: Currently `anthropic.claude-sonnet-4-6:0` in `TL_AWS_Bedrock_Client::MODEL_ID`. If Bedrock returns a 400 error, update this constant — the model ID must match an enabled model in the AWS account's Bedrock model access settings.
13. **vendor/ is gitignored**: The `vendor/` directory is excluded from version control (`.gitignore`). After cloning or pulling on a new server, run `composer install` before the plugin will function.
14. **Workbook type detection uses original content**: `detect_lesson_type()` runs on the `lesson_content` parameter of the `POST /lesson/ai-content` request — this is the current editor state before AI generation, not previously-generated HTML. Lesson title is also checked.
15. **`[Text Box]` sentinel in workbook HTML**: Workbook Entry field blocks in the generated HTML contain `[Text Box]` as plain text inside a styled `<div>`. This is intentional — WP kses would strip `<textarea>` from post content. The frontend JS converts sentinels to interactive `<textarea>` elements at runtime. Never replace sentinels with actual form elements in the PHP template.
16. **Workbook DB table not created by `dbDelta()`**: The `lxp_workbook_submissions` table is created with a raw `$wpdb->query("CREATE TABLE IF NOT EXISTS ...")` call in `on_activate()`, matching the existing `tiny_lms_grades` pattern. Re-trigger creation by deactivating and reactivating the plugin.
17. **Workbook admin page is in `admin/` not `lms/`**: `TL_Workbook_Submission_List_Table` lives in `admin/class-workbook-submission-list-table.php`; the repository it depends on lives in `lms/repositories/`. The admin page callback does its own `require_once` of both files so they are not double-loaded on every request.
18. **AI standard templates have no quiz**: Standard (15-template) AI lessons end with a Capstone Activity only. `quiz_html()` is retired and returns `''`. Never add a "Check for Understanding" quiz to standard templates — the no-quiz rule is enforced in `build_template_system_prompt()`.
19. **`[Capstone Box]` sentinel in standard templates**: Capstone Activity sections contain `[Capstone Box]` as plain text inside a styled `<div class="lxp-capstone-box">` — never replace it with form elements in PHP. `lxp-capstone.js` converts it to a `<textarea>` at runtime. Keep the class on newly-generated lessons; the JS only text-matches old lessons as a legacy fallback. Same pattern as `[Text Box]` sentinels in workbook templates.
20. **LP4 lesson frontend context**: `enqueue_capstone_scripts()` and `enqueue_workbook_scripts()` cannot rely on `is_singular('lp_lesson')` alone. LP4 lesson URLs are usually routed in `lp_course` context, so the public layer resolves the lesson ID from the URL when needed before localising script vars.
21. **`meta_table_html()` method name is legacy**: This shared helper was originally the Lesson Overview metadata table. It was repurposed to return Learning Outcomes + Opening Hook sections. Do not re-add Lesson Overview metadata table logic here without updating all 15 template functions.
22. **Capstone DB table follows workbook pattern**: `lxp_capstone_submissions` is created with `$wpdb->query("CREATE TABLE IF NOT EXISTS ...")` in `on_activate()`. Re-trigger creation by deactivating and reactivating the plugin. Schema: `(id, lesson_id, course_id, user_id, response, submitted_at, updated_at)` with `UNIQUE KEY lesson_user (lesson_id, user_id)`.
23. **`lxp-capstone.js` is a single IIFE — a syntax error disables all capstone boxes sitewide**: A JS syntax error anywhere in the file prevents it from parsing entirely, silently leaving every `[Capstone Box]` sentinel on every lesson as a plain non-interactive div. Always verify the browser console shows no errors after editing this file.
24. **Policy PDF is an end-to-end feature, not a single-file tweak**: Keep `policy-document.php` (PDF render + `Content-Disposition` name), repository `module_order` data, and journal/template module labels in sync. The Blob download in `public/js/lxp-policy-document.js` must use the response filename, not a hardcoded `policy-document.pdf`. Policy filtering (`lxp_lesson_include_in_policy`) and content fallback order (workbook -> capstone -> placeholders) must stay intact in both page and PDF behavior.

---

## Out of Scope by Default

Unless explicitly requested:
- No architecture migrations (e.g., converting hook-based registration to a DI container).
- No renaming of CPT slugs, meta keys, or user roles (breaks existing content).
- No changes to the Composer vendor directory.
- No introduction of new JavaScript frameworks or build tools.
- No global PHP namespace changes.
- No removal of the duplicate main plugin file.

---

## Copilot Delivery Checklist

Before finalizing any task:
- [ ] Request scope is fully implemented and nothing extra was added.
- [ ] Diff is minimal; unrelated files are untouched.
- [ ] New PHP class files follow the `class-{kebab-name}.php` naming convention.
- [ ] New TinyLxp-owned CPT classes extend `TL_Post_Type` and use the singleton pattern; LearnPress-managed `lp_course`/`lp_lesson` changes should use extension hooks.
- [ ] New REST callbacks implement proper capability/nonce checks.
- [ ] No credentials, tokens, or secrets are hardcoded.
- [ ] All DB queries use `$wpdb->prepare()`.
- [ ] All output is escaped; all input is sanitized.
- [ ] New REST routes are registered in `LMS_REST_API::init()`.
- [ ] If lesson admin save logic changed, verify `save_post_lp_lesson` and `lesson_lti_nonce` checks are still correct.
- [ ] Template files follow the `page-{slug}.php` / `{role}-{feature}.php` naming convention.
- [ ] If a new CPT was registered, flush rewrite rules was noted as required.
- [ ] PHP syntax was verified (`php -l`).
- [ ] Risks, typos-in-existing-code, and follow-up items are noted briefly.
