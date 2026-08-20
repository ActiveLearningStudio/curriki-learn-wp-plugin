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