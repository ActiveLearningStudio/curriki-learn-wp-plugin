# Course Completion Certificates — Feature Context

Session context: course completion certificates added (October 20, 2026)

---

## Purpose

A learner who finishes **and passes** a course that an author has flagged **Issue certificate** is sent to a certificate page. There they type their name, email and school onto the certificate and download it as a PDF. The details are saved per course, so the learner can come back at any time, edit them and download again.

---

## Flow

1. An author ticks **Issue certificate** in the **Curriki Certificate** meta box on the course edit screen. The same box has an optional **Certificate subtitle**.
   - The box is **ticked by default on a new course**, meaning an `auto-draft` that has never been saved.
   - Existing courses show what is actually stored. Missing meta still means "off", so older courses never start issuing certificates until someone saves them with the box ticked.
2. An admin picks the certificate page under **Curriki Learn → Certificates**, and places the **LXP Certificate** Elementor widget on that page.
3. The learner presses LearnPress's **Finish** button and lands on `/{certificate-page}/?course_id=X`.
4. The widget renders the certificate, prefilled with the learner's saved details or with best guesses from their profile.
5. **Download PDF** first saves through `POST /lms/v1/certificate/save`, then captures the certificate in the browser.

Every widget state, including the notices, ends with a **help note**: "Having trouble getting your certificate? Send an email to grants@curriki.org", with the address as a `mailto:` link.
- Both the text and the email are widget controls. Leaving both empty hides the note.
- The note sits outside `.cert`, so it never appears in the PDF.

Two links lead back to the certificate page later:
- a **Certificate** link on the course's card in the *LXP Student Courses* widget
- a **Get Certificate** button among the course page buttons (`learn-press/course-buttons`). This one also covers teachers taking PD courses.

---

## Key Files

| File | Role |
|---|---|
| `lms/repositories/class-certificate-repository.php` | `TL_Certificate_Repository`: eligibility, saved details, prefill, page URL. Safe in REST context. |
| `lms/lms-rest-apis/certificate.php` | `Rest_Lxp_Certificate`: `POST /lms/v1/certificate/save` |
| `includes/widgets/lxp-certificate-widget.php` | The *LXP Certificate* Elementor widget. Its markup is the design file's markup. |
| `includes/widgets/assets/style/lxp-certificate.css` | The design's CSS, scoped under `.lxp-cert-wrap .cert` |
| `includes/widgets/assets/js/lxp-certificate.js` | Scale-to-fit, save, and html2canvas → jsPDF capture |
| `includes/widgets/assets/js/lib/` | html2canvas 1.4.1 and jsPDF 2.5.1, committed (no CDN). Not `js/vendor/`: `.gitignore`'s bare `vendor/` rule would drop them. |
| `includes/widgets/assets/fonts/montserrat-*.woff` | The design's four embedded Montserrat weights, decoded |
| `includes/widgets/assets/img/curriki-ai-grants-logo.svg` | The design's logo, unchanged |
| `lms/class-learnpress-course-extension.php` | Meta box, finish redirect, Get Certificate button |
| `admin/class-tiny-lxp-platform-admin.php` | `certificate_settings_page_html()` |

---

## Data

| Where | Key | Value |
|---|---|---|
| `lp_course` post meta | `lxp_course_issue_certificate` | `'1'` when flagged. Deleted when unticked. |
| `lp_course` post meta | `lxp_course_certificate_subtitle` | Line printed under the course title. Falls back to the widget's *Default Course Subtitle*. |
| user meta | `lxp_certificate_{course_id}` | `{ name, email, school, issued_at, updated_at }`. `issued_at` is set on the first save and kept through later edits. Times are GMT. |
| option | `tl_certificate_page_id` | The certificate page. Settings group `tl-certificate-settings`. |

**Eligibility.** The course is flagged **and** LearnPress's `UserCourseModel` for the user has `status = finished` and `graduation = passed`.

**Completion date.** This is **not stored**. It is always LearnPress's `end_time`, formatted as `F j, Y`, and it renders as a read-only field, so a learner cannot change it.

**Personal details.** The certificate's name and email are what the learner typed. They are never written back to the account's `user_email` or `display_name`. Prefill skips the token-student sink address (`@students.curriki.local`), so a zero-PII account never sees a fake email on its certificate.

The settings page has **its own settings group** on purpose. `options.php` resets every option in the submitted group that the form left out, so sharing the `tiny-lxp-platform` group with the Teacher Signup page would let either page wipe the other's values.

---

## The redirect trick

LearnPress's "redirect after finish" is a single global setting (`course_finish_redirect`). It is also cached inside `LP_Settings`, so filtering the option is unreliable. Instead:

- `on_course_finished()` on `learn-press/user-course-finished` stores the certificate URL in a static, but only for an eligible, flagged course.
- `filter_finish_redirect()` on `wp_redirect` swaps that URL into the redirect `LP_AJAX::finish_course()` makes right after `handle_finish()`. It is one-shot and clears itself.

Unflagged courses never set the static, so they keep LearnPress's normal redirect. The eligibility check inside the hook reads LearnPress **fresh** (`is_eligible(..., true)`), because the cached `UserCourseModel` can still say `enrolled` at that point.

---

## Design source of truth

**`docs/Curriki-Grant-Application-Course-Certificate.html` is the master design.** The client asked for it to be followed strictly.

- The widget's `render_certificate()` markup is that file's `<main class="cert">`, copied verbatim, with three changes only:
  - `<main>` became a `<div>`, because the page already has a `<main>`.
  - The inline logo SVG moved to `assets/img/` and is shown with an `<img>`. Its viewBox was extended from 204 to 210 units tall: the design draws the "g" descender past the viewBox using `overflow:visible`, which an `<img>` clips. The scale and position are unchanged.
  - The seal `<svg>` is wrapped in `<div class="seal">`, and the design's `.seal` rule now positions that wrapper. **html2canvas silently drops an absolutely positioned inline `<svg>` from the capture.** It still shows on screen, which is why this only showed up in the PDF. Any new decorative SVG needs the same wrapper treatment.
  - Dynamic text is substituted in: band label, tagline and URL; pill; course title; subtitle; input values.
- **Course title.** The title comes from `post_title` run through `the_title`, not from `get_the_title()`. That function prepends "Private:" or "Protected:" for non-public courses, which must never appear on a certificate.
- **Long course titles.** The design's course card is a fixed 132px tall, sized for a short title.
  - `fitCourseTitle()` in `lxp-certificate.js` steps the `h2` font size down from the design's 30px, to a minimum of 14px, until the title and subtitle fit inside the card with 12px to spare above and below.
  - The size is set inline, so the PDF capture clone inherits it.
  - It runs again after the font loads.
  - Short titles never shrink.
  - The only CSS this adds outside the design is `.card-text { min-width:0; overflow-wrap:anywhere }`.
- `lxp-certificate.css` copies the design's values unchanged. Only the selectors are scoped. Its extra theme-proofing resets give back the defaults a bare `<body>` would provide.
- The font family is renamed `LXP Cert Montserrat` so it cannot collide with a theme's Montserrat.

**If the design changes**, copy the change into the widget markup, the CSS and/or the logo SVG. Do not hand-tune values.

---

## PDF capture

The PDF is generated **in the browser**, not with dompdf. dompdf cannot render the design's flexbox, CSS variables, gradients, shadows or masked inline SVG.

`lxp-certificate.js` does the following:

1. Saves the details. A failed save aborts the download.
2. Waits for the four font weights to load.
3. Clones `.cert` into an off-screen, **unscaled** `.lxp-cert-capture-host`. Each `<input>` in the clone is replaced by a `.lxp-cert-value` div holding the live value, because html2canvas draws input values unreliably. The CSS gives those divs the inputs' exact box.
4. Adds `.lxp-cert--capture`, which mirrors the design's `@media print` rules: no shadow, no corner radius.
5. Captures at 3× with `html2canvas`, then writes the result as a JPEG onto an 11×8.5 in landscape jsPDF page and saves it as `Certificate - {Course Title}.pdf`.
   - The title keeps its original case, spaces and dashes.
   - Characters that aren't allowed in Windows or macOS file names (`\ / : * ? " < > |`) are replaced with `_`.
   - The title is capped at 150 characters.
   - The name is built in `LXP_Certificate_Widget::certificate_filename()`.

The PDF is an image, so its text is not selectable. That is the cost of exact fidelity.

On screen, the 1056×816 artboard is scaled to the container width with `transform: scale()`. The capture always uses the unscaled clone, so the PDF is identical on every device.

---

## Testing checklist

- [ ] With the course flagged and passed, pressing Finish lands on the certificate page with the fields prefilled and the date read-only.
- [ ] An unflagged course keeps LearnPress's normal redirect.
- [ ] The downloaded PDF is 11×8.5 landscape and matches the design file side by side, with no shadow or rounded corners.
- [ ] After editing the school and downloading again, the PDF shows the new school with the same date.
- [ ] Logged out, a login link is shown. With an unfinished course, a "Complete this course" message is shown. With an unflagged course, "No certificate" is shown.
- [ ] A `POST /certificate/save` with no nonce, or from an ineligible user, is rejected and nothing is saved.
- [ ] At phone width the certificate scales down and the page does not scroll sideways.
