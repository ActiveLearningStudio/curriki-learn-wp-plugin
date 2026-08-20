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