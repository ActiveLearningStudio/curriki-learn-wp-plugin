<?php
// Define constants for custom post types.
const TL_COURSE_CPT   = 'lp_course';
const TL_LESSON_CPT   = 'lp_lesson';
// const TL_TREK_CPT   = 'tl_course';
const TL_DISTRICT_CPT   = 'tl_district';
const TL_SCHOOL_CPT   = 'tl_school';
const TL_ASSIGNMENT_CPT   = 'tl_assignment';
const TL_TEACHER_CPT   = 'tl_teacher';
const TL_STUDENT_CPT   = 'tl_student';
const TL_CLASS_CPT   = 'tl_class';
const TL_ASSIGNMENT_SUBMISSION_CPT   = 'tl_submission';
const TL_GROUP_CPT   = 'tl_group';

// Configure WordPress subdirectory path used by inline JS API URL builders.
const WORDPRESS_HOST = '/tinylxp';

/**
 * The canonical grade-level list.
 *
 * Grades are neither a taxonomy nor an option — they were hardcoded as HTML in
 * five separate templates, and in two different ranges (people modals offered
 * 1st-9th, the class modal 1st-12th). That mismatch silently truncated a
 * teacher's grades on re-save. This is the single source both now render from.
 *
 * Lives in tl-constants.php rather than lxp/functions.php on purpose: that file
 * is not loaded in REST context (see CLAUDE.md gotcha #13), and the teacher
 * signup endpoint has to validate against this list.
 *
 * @return string[] Ordinal grade labels, in order.
 */
if ( ! function_exists( 'lxp_get_grade_options' ) ) {
    function lxp_get_grade_options() {
        return array( '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th' );
    }
}

/**
 * Hard ceiling on how many students one class may hold.
 *
 * Seats used to be uncapped: `lxp_class_max_seats = 0` meant "unlimited" and the
 * seat pool grew on demand forever. There is now a real ceiling, so `0` is no
 * longer a special value — it resolves to the ceiling like any other
 * out-of-range number.
 *
 * Existing classes still have `0` stored and are deliberately not migrated:
 * every read goes through lxp_get_class_max_seats(), so a legacy `0` and a
 * fresh `150` behave identically. Never read the meta directly.
 *
 * Lives here rather than in lxp/functions.php for the same reason as
 * lxp_get_grade_options() — REST callbacks need it and functions.php is not
 * loaded in REST context (CLAUDE.md gotcha #13).
 */
const TL_CLASS_MAX_SEATS = 150;

/**
 * Coerce any submitted seat cap into the allowed range.
 *
 * @param  mixed $value Raw submitted value.
 * @return int          Between 1 and TL_CLASS_MAX_SEATS.
 */
if ( ! function_exists( 'lxp_clamp_class_max_seats' ) ) {
    function lxp_clamp_class_max_seats( $value ) {
        $value = (int) $value;

        // < 1 covers both "unset" and the legacy 0-means-unlimited sentinel.
        if ( $value < 1 ) {
            return TL_CLASS_MAX_SEATS;
        }

        return min( $value, TL_CLASS_MAX_SEATS );
    }
}

/**
 * The effective seat cap for a class. Always a positive integer.
 *
 * @param  int $class_id
 * @return int
 */
if ( ! function_exists( 'lxp_get_class_max_seats' ) ) {
    function lxp_get_class_max_seats( $class_id ) {
        return lxp_clamp_class_max_seats( get_post_meta( (int) $class_id, 'lxp_class_max_seats', true ) );
    }
}

/**
 * The two audiences a course can be labelled for.
 *
 * These are array keys for lxp_get_course_audience_terms(), nothing more. They
 * used to be a teacher's stored "register type", which gated which courses that
 * teacher could see; that gate is gone. Every teacher now sees every published
 * course, and these only decide whether a course carries a Student or PD badge
 * in the class modal's Courses picker.
 */
const TL_COURSE_AUDIENCE_K12 = 'k12';
const TL_COURSE_AUDIENCE_PD  = 'professional_development';

/**
 * The taxonomy holding the two audience terms on LearnPress courses.
 *
 * This is LearnPress's own course category taxonomy, not one this plugin
 * registers — the terms sit alongside subject categories such as Math, and any
 * code that writes them must leave the rest of the course's terms alone.
 */
const TL_COURSE_AUDIENCE_TAXONOMY = 'course_category';

/**
 * The course_category terms that mark a course's audience.
 *
 * Identity is the slug, never the term ID — an admin renaming "K-12" in
 * LearnPress's Course Categories screen must not break the badge lookup.
 *
 * `short` is the badge text shown beside the course title in the picker. It
 * lives here, next to the slug it belongs to, so the two cannot drift apart.
 *
 * Lives in this file rather than lxp/functions.php for the same reason as
 * lxp_get_grade_options(): REST callbacks need it and functions.php is not
 * loaded in REST context (CLAUDE.md gotcha #13).
 *
 * @return array<string,array{slug:string,name:string,short:string}>
 */
if ( ! function_exists( 'lxp_get_course_audience_terms' ) ) {
    function lxp_get_course_audience_terms() {
        return array(
            TL_COURSE_AUDIENCE_K12 => array( 'slug' => 'k-12', 'name' => 'K-12', 'short' => 'Student' ),
            TL_COURSE_AUDIENCE_PD  => array( 'slug' => 'professional-development', 'name' => 'Professional Development', 'short' => 'PD' ),
        );
    }
}

/**
 * Whether a teacher ticked Professional Development at signup.
 *
 * Independent of `grades`: a teacher may have both, either, or — for accounts
 * created before this checkbox existed — neither. Nothing gates on it yet; it
 * records what the teacher told us about themselves.
 *
 * @param  int $teacher_post_id tl_teacher post ID.
 * @return bool
 */
if ( ! function_exists( 'lxp_get_teacher_pd_flag' ) ) {
    function lxp_get_teacher_pd_flag( $teacher_post_id ) {
        return '1' === (string) get_post_meta( (int) $teacher_post_id, 'teacher_professional_development', true );
    }
}

const Allowed_Activity_types = [
            'Course Presentation',
            'Crossroads',
            'Drag and Drop',
            'Drag the Words',
            'Essay',
            'Fill in the Blanks',
            'Find the Hotspot',
            'Free Text Question',
            'Interactive Video',
            'Mark the Words',
            'Memory Game',
            'Multiple Choice',
            'Open Ended Question',
            'Questionnaire',
            'Question Set',
            'Simple Multi Choice',
            'Single Choice Set',
            'Summary',
            'Statements',
            'True/False Question'
        ];