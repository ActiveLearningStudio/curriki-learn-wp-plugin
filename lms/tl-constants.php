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
 * How a teacher registered, and therefore which course catalogue they see.
 *
 * `k12`  — an educator registering students. Asked which grades they teach.
 * `professional_development` — an administrator registering faculty/staff.
 *          Never asked for grades; their classes carry none.
 *
 * Stored as `teacher_register_type` post meta on the tl_teacher post. Every
 * teacher created before this existed has no meta at all, which resolves to
 * `k12` — see lxp_get_teacher_register_type().
 *
 * Lives here rather than in lxp/functions.php for the same reason as
 * lxp_get_grade_options(): the signup and class REST callbacks need it and
 * functions.php is not loaded in REST context (CLAUDE.md gotcha #13).
 */
const TL_REGISTER_TYPE_K12 = 'k12';
const TL_REGISTER_TYPE_PD  = 'professional_development';

/**
 * The taxonomy holding the two audience terms on LearnPress courses.
 *
 * This is LearnPress's own course category taxonomy, not one this plugin
 * registers — the terms sit alongside subject categories such as Math, and any
 * code that writes them must leave the rest of the course's terms alone.
 */
const TL_COURSE_AUDIENCE_TAXONOMY = 'course_category';

/**
 * The register-type radio options, keyed by stored value.
 *
 * @return array<string,string> value => default label.
 */
if ( ! function_exists( 'lxp_get_register_type_options' ) ) {
    function lxp_get_register_type_options() {
        return array(
            TL_REGISTER_TYPE_K12 => 'I am an educator and I want to register students',
            TL_REGISTER_TYPE_PD  => 'I am an administrator and want to register faculty/staff for Professional Development',
        );
    }
}

/**
 * Coerce any submitted register type into one of the two known values.
 *
 * @param  mixed $value Raw submitted value.
 * @return string       TL_REGISTER_TYPE_K12 or TL_REGISTER_TYPE_PD.
 */
if ( ! function_exists( 'lxp_sanitize_register_type' ) ) {
    function lxp_sanitize_register_type( $value ) {
        $value = is_scalar( $value ) ? (string) $value : '';

        return array_key_exists( $value, lxp_get_register_type_options() )
            ? $value
            : TL_REGISTER_TYPE_K12;
    }
}

/**
 * The register type of a teacher post. Always one of the two known values.
 *
 * The empty-meta fallback lives here so no caller has to remember that every
 * pre-existing teacher is a K-12 educator by default.
 *
 * @param  int $teacher_post_id tl_teacher post ID.
 * @return string
 */
if ( ! function_exists( 'lxp_get_teacher_register_type' ) ) {
    function lxp_get_teacher_register_type( $teacher_post_id ) {
        return lxp_sanitize_register_type(
            get_post_meta( (int) $teacher_post_id, 'teacher_register_type', true )
        );
    }
}

/**
 * The course_category terms that mark a course's audience, keyed by register type.
 *
 * Identity is the slug, never the term ID — an admin renaming "K-12" in
 * LearnPress's Course Categories screen must not break the course filter.
 *
 * @return array<string,array{slug:string,name:string}>
 */
if ( ! function_exists( 'lxp_get_course_audience_terms' ) ) {
    function lxp_get_course_audience_terms() {
        return array(
            TL_REGISTER_TYPE_K12 => array( 'slug' => 'k-12', 'name' => 'K-12' ),
            TL_REGISTER_TYPE_PD  => array( 'slug' => 'professional-development', 'name' => 'Professional Development' ),
        );
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