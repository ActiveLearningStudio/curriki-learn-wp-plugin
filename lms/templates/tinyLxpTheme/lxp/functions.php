<?php

function lxp_login_check()
{
    // Intentionally disabled: this plugin should not run custom login redirects.
}

function lxp_get_active_role($user_id = 0)
{
    $user_id = intval($user_id) > 0 ? $user_id : get_current_user_id();
    $user = get_userdata($user_id);
    $roles = $user ? (array) $user->roles : array();

    if (in_array('lp_teacher', $roles, true) || in_array('lxp_teacher_admin', $roles, true)) {
        return 'lp_teacher';
    }

    return count($roles) > 0 ? array_values($roles)[0] : '';
}

function lxp_is_teacher_user($user_id = 0)
{
    return 'lp_teacher' === lxp_get_active_role($user_id);
}

function lxp_get_user_school_post($user_id = 0)
{
    $user_id = intval($user_id) > 0 ? $user_id : get_current_user_id();
    $school_query = new WP_Query( array( 
        'post_type' => TL_SCHOOL_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,
        'meta_query' => array(
            array('key' => 'lxp_school_admin_id', 'value' => $user_id, 'compare' => '=')
        )
    ) );
    $posts = $school_query->get_posts();
    $school_post = count( $posts ) > 0 ? $posts[0] : null;
    return $school_post;
}

function lxp_get_user_district_post($user_id = 0)
{
    $user_id = intval($user_id) > 0 ? $user_id : get_current_user_id();
    $district_query = new WP_Query( array( 
        'post_type' => TL_DISTRICT_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,
        'meta_query' => array(
            array('key' => 'lxp_district_admin', 'value' => $user_id, 'compare' => '=')
        )
    ) );
    $posts = $district_query->get_posts();
    $district_post = count( $posts ) > 0 ? $posts[0] : null;
    return $district_post;
}

// function lxp_get_district_schools_active($district_id) where settings_active meta key is not set or not equal to false
function lxp_get_district_schools_active($district_id)
{
    $school_query = new WP_Query( array( 
        'post_type' => TL_SCHOOL_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'lxp_school_district_id', 'value' => $district_id, 'compare' => '='),
            array(
                'relation' => 'OR',
                array('key' => 'settings_active', 'compare' => 'NOT EXISTS'),
                array('key' => 'settings_active', 'value' => 'false', 'compare' => '!=')
            )
        )
    ) );
    
    $posts = $school_query->get_posts();
    return $posts;
}

// function lxp_get_district_schools_inactive($district_id) where settings_active meta key is equal to false
function lxp_get_district_schools_inactive($district_id)
{
    $school_query = new WP_Query( array( 
        'post_type' => TL_SCHOOL_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'lxp_school_district_id', 'value' => $district_id, 'compare' => '='),
            array('key' => 'settings_active', 'value' => 'false', 'compare' => '=')
        )
    ) );
    
    $posts = $school_query->get_posts();
    return $posts;
}


function lxp_get_district_schools($district_id)
{
    $school_query = new WP_Query( array( 
        'post_type' => TL_SCHOOL_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'lxp_school_district_id', 'value' => $district_id, 'compare' => '=')
        )
    ) );
    
    $posts = $school_query->get_posts();
    return $posts;
}

// function lxp_get_school_teachers_active($school_id) where settings_active meta key is not set or not equal to false
function lxp_get_school_teachers_active($school_id)
{
    $school_query = new WP_Query( array( 
        'post_type' => TL_TEACHER_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'lxp_teacher_school_id', 'value' => $school_id, 'compare' => '='),
            array(
                'relation' => 'OR',
                array('key' => 'settings_active', 'compare' => 'NOT EXISTS'),
                array('key' => 'settings_active', 'value' => 'false', 'compare' => '!=')
            )
        ),
        'orderby' => 'title',
        'order' => 'ASC'
    ) );
    
    $posts = $school_query->get_posts();
    return $posts;
}

// function lxp_get_school_teachers_inactive($school_id) where settings_active meta key is equal to false
function lxp_get_school_teachers_inactive($school_id)
{
    $school_query = new WP_Query( array( 
        'post_type' => TL_TEACHER_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'lxp_teacher_school_id', 'value' => $school_id, 'compare' => '='),
            array('key' => 'settings_active', 'value' => 'false', 'compare' => '=')
        ),
        'orderby' => 'title',
        'order' => 'ASC'
    ) );
    
    $posts = $school_query->get_posts();
    return $posts;
}

function lxp_get_school_teachers($school_id)
{
    $school_query = new WP_Query( array( 
        'post_type' => TL_TEACHER_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'lxp_teacher_school_id', 'value' => $school_id, 'compare' => '=')
        ),
        'orderby' => 'title',
        'order' => 'ASC'
    ) );
    
    $posts = $school_query->get_posts();
    return $posts;
}

// function lxp_get_school_students_active($school_id) where settings_active meta key is not set or not not equal to false
function lxp_get_school_students_active($school_id)
{
    $school_query = new WP_Query( array( 
        'post_type' => TL_STUDENT_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'lxp_student_school_id', 'value' => $school_id, 'compare' => '='),
            array(
                'relation' => 'OR',
                array('key' => 'settings_active', 'compare' => 'NOT EXISTS'),
                array('key' => 'settings_active', 'value' => 'false', 'compare' => '!=')
            )
        ),
        'orderby' => 'title',
        'order' => 'ASC'
    ) );
    
    $posts = $school_query->get_posts();
    return $posts;
}

// function lxp_get_school_students_inactive($school_id) where settings_active meta key is equal to false
function lxp_get_school_students_inactive($school_id)
{
    $school_query = new WP_Query( array( 
        'post_type' => TL_STUDENT_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'lxp_student_school_id', 'value' => $school_id, 'compare' => '='),
            array('key' => 'settings_active', 'value' => 'false', 'compare' => '=')
        ),
        'orderby' => 'title',
        'order' => 'ASC'
    ) );
    
    $posts = $school_query->get_posts();
    return $posts;
}

function lxp_get_school_students($school_id)
{
    $school_teacher =lxp_get_school_teachers($school_id);
    $all_students = [];
    foreach ($school_teacher as $teacher) {
        $teacher_students = lxp_get_school_teacher_students($teacher->ID);
        $all_students = array_merge($all_students, $teacher_students);
    }
    // $school_query = new WP_Query( array( 
    //     'post_type' => TL_STUDENT_CPT, 
    //     'post_status' => array( 'publish' ),
    //     'posts_per_page'   => -1,        
    //     'meta_query' => array(
    //         array('key' => 'lxp_student_school_id', 'value' => $school_id, 'compare' => '=')
    //     ),
    //     'orderby' => 'title',
    //     'order' => 'ASC'
    // ) );
    
    // $posts = $school_query->get_posts();
    return $all_students;
}

// function lxp_get_school_teacher_students_active($school_id, $teacher_id) where settings_active meta key is not set or not not equal to false
function lxp_get_school_teacher_students_active($teacher_id)
{
    $all_students = get_posts([ 
        'post_type'      => TL_STUDENT_CPT,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => 'lxp_teacher_id',
                'compare' => 'EXISTS',
            ],
            [
                'relation' => 'OR',
                [
                    'key'     => 'settings_active',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => 'settings_active',
                    'value'   => 'false',
                    'compare' => '!=',
                ]
            ]
        ]
    ]);

    $filtered_posts = array_filter($all_students, function($post) use ($teacher_id) {
        $meta = get_post_meta($post->ID, 'lxp_teacher_id', true);
        
        if (is_numeric($meta) && (int)$meta === (int)$teacher_id) {
            return true;
        }

        if (is_array($meta) && in_array((int)$teacher_id, array_map('intval', $meta))) {
            return true;
        }

        return false;
    });
    // $school_query = new WP_Query( array( 
    //     'post_type' => TL_STUDENT_CPT, 
    //     'post_status' => array( 'publish' ),
    //     'posts_per_page'   => -1,        
    //     'meta_query' => array(
    //         // array('key' => 'lxp_student_school_id', 'value' => $school_id, 'compare' => '='),
    //         array('key' => 'lxp_teacher_id', 'value' => $teacher_id, 'compare' => '='),
    //         array(
    //             'relation'  => 'OR',
    //             array('key' => 'settings_active', 'compare' => 'NOT EXISTS'),
    //             array('key' => 'settings_active', 'compare' => '!=', 'value' => 'false')
    //         )
    //     ),
    //     'orderby' => 'title',
    //     'order' => 'ASC'
    // ) );
    
    // $posts = $school_query->get_posts();
    return $filtered_posts;
}

// function lxp_get_school_teacher_students_inactive($school_id, $teacher_id) where settings_active meta key is equal to false
function lxp_get_school_teacher_students_inactive($teacher_id)
{
    $all_students = get_posts([ 
        'post_type'      => TL_STUDENT_CPT,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => 'lxp_teacher_id',
                'compare' => 'EXISTS',
            ],
            [
                'relation' => 'OR',
                [
                    'key'     => 'settings_active',
                    'value'   => 'false',
                    'compare' => '=',
                ]
            ]
        ]
    ]);

    $filtered_posts = array_filter($all_students, function($post) use ($teacher_id) {
        $meta = get_post_meta($post->ID, 'lxp_teacher_id', true);
        
        if (is_numeric($meta) && (int)$meta === (int)$teacher_id) {
            return true;
        }

        if (is_array($meta) && in_array((int)$teacher_id, array_map('intval', $meta))) {
            return true;
        }

        return false;
    });
    // $school_query = new WP_Query( array( 
    //     'post_type' => TL_STUDENT_CPT, 
    //     'post_status' => array( 'publish' ),
    //     'posts_per_page'   => -1,        
    //     'meta_query' => array(
    //         // array('key' => 'lxp_student_school_id', 'value' => $school_id, 'compare' => '='),
    //         array('key' => 'lxp_teacher_id', 'value' => $teacher_id, 'compare' => '='),
    //         array('key' => 'settings_active', 'value' => 'false', 'compare' => '=')
    //     ),
    //     'orderby' => 'title',
    //     'order' => 'ASC'
    // ) );
    
    // $posts = $school_query->get_posts();
    return $filtered_posts;
}

/**
 * @return all_students_of_given_teacher
 */
function lxp_get_school_teacher_students($teacher_id)
{
    $all_students = get_posts([ 
        'post_type'      => TL_STUDENT_CPT,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => 'lxp_teacher_id',
                'compare' => 'EXISTS',
            ]
        ]
    ]);

    $filtered_posts = array_filter($all_students, function($post) use ($teacher_id) {
        $meta = get_post_meta($post->ID, 'lxp_teacher_id', true);
        
        if (is_numeric($meta) && (int)$meta === (int)$teacher_id) {
            return true;
        }

        if (is_array($meta) && in_array((int)$teacher_id, array_map('intval', $meta))) {
            return true;
        }

        return false;
    });
    // $school_query = new WP_Query( array( 
    //     'post_type' => TL_STUDENT_CPT, 
    //     'post_status' => array( 'publish' ),
    //     'posts_per_page'   => -1,        
    //     'meta_query' => array(
    //         // array('key' => 'lxp_student_school_id', 'value' => $school_id, 'compare' => '='),
    //         array('key' => 'lxp_teacher_id', 'value' => $teacher_id, 'compare' => '=')
    //     ),
    //     'orderby' => 'title',
    //     'order' => 'ASC'
    // ) );
    
    // $posts = $school_query->get_posts();
    return $filtered_posts;
}

// lxp_get_all_schools_active_teachers($school_ids) where settings_active meta key is not set or not not equal to false
function lxp_get_all_schools_active_teachers($school_ids)
{
    if (empty($school_ids)) {
        return array();
    }
    
    $school_query = new WP_Query( array( 
        'post_type' => TL_TEACHER_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            'relation' => 'AND',
            array('key' => 'lxp_teacher_school_id', 'value' => $school_ids, 'compare' => 'IN'),
            array(
                'relation' => 'OR',
                array('key' => 'settings_active', 'compare' => 'NOT EXISTS'),
                array('key' => 'settings_active', 'value' => 'false', 'compare' => '!=')
            )
        ),
        'orderby' => 'title',
        'order' => 'ASC'
    ) );
    
    $posts = $school_query->get_posts();
    return $posts;
}

// lxp_get_all_schools_inactive_teachers($school_ids) where settings_active meta key is equal to false
function lxp_get_all_schools_inactive_teachers($school_ids)
{
    if (empty($school_ids)) {
        return array();
    }
    
    $school_query = new WP_Query( array( 
        'post_type' => TL_TEACHER_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            'relation' => 'AND',
            array('key' => 'lxp_teacher_school_id', 'value' => $school_ids, 'compare' => 'IN'),
            array('key' => 'settings_active', 'value' => 'false', 'compare' => '=')
        ),
        'orderby' => 'title',
        'order' => 'ASC'
    ) );
    
    $posts = $school_query->get_posts();
    return $posts;
}


function lxp_get_all_schools_teachers($school_ids)
{
    if (empty($school_ids)) {
        return array();
    }
    
    $school_query = new WP_Query( array( 
        'post_type' => TL_TEACHER_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'lxp_teacher_school_id', 'value' => $school_ids, 'compare' => 'IN')
        ),
        'orderby' => 'title',
        'order' => 'ASC'
    ) );
    
    $posts = $school_query->get_posts();
    return $posts;
}

function lxp_get_all_schools_students($school_ids)
{

    if (empty($school_ids)) {
        return array();
    }
    $all_students = [];
    foreach ($school_ids as $school_id) {
        $school_teacher =lxp_get_school_teachers($school_id);
        foreach ($school_teacher as $teacher) {
            $teacher_students = lxp_get_school_teacher_students($teacher->ID);
            $all_students = array_merge($all_students, $teacher_students);
        }
    }
    // $school_query = new WP_Query( array( 
    //     'post_type' => TL_STUDENT_CPT, 
    //     'post_status' => array( 'publish' ),
    //     'posts_per_page'   => -1,        
    //     'meta_query' => array(
    //         array('key' => 'lxp_student_school_id', 'value' => $school_ids, 'compare' => 'IN')
    //     )
    // ) );
    
    // $posts = $school_query->get_posts();
    return $all_students;
}

function lxp_get_teacher_post($lxp_teacher_admin_id)
{
    $school_query = new WP_Query( array( 
        'post_type' => TL_TEACHER_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'lxp_teacher_admin_id', 'value' => $lxp_teacher_admin_id, 'compare' => '=')
        )
    ) );
    
    $posts = $school_query->get_posts();
    return ( count($posts) > 0 ? $posts[0] : null );
}

function lxp_get_teacher_classes($lxp_class_teacher_id)
{
    $school_query = new WP_Query( array( 
        'post_type' => TL_CLASS_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'lxp_class_teacher_id', 'value' => $lxp_class_teacher_id, 'compare' => '=')
        )
    ) );
    return $school_query->get_posts();
}

function lxp_get_student_all_classes($student_id)
{
    $school_query = new WP_Query( array( 
        'post_type' => TL_CLASS_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'lxp_student_ids', 'value' => $student_id, 'compare' => '=')
        )
    ) );
    return $school_query->get_posts();
}

function lxp_get_all_teachers_classes($teachers_ids)
{
    if (empty($teachers_ids)) {
        return array();
    }

    $school_query = new WP_Query( array( 
        'post_type' => TL_CLASS_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'lxp_class_teacher_id', 'value' => $teachers_ids, 'compare' => 'IN')
        )
    ) );
    return $school_query->get_posts();
}

// function get_course_section_by_id($section_id)
// {
//     global $wpdb;
//     return $wpdb->get_row("SELECT * FROM {$wpdb->prefix}learnpress_course_sections WHERE id={$section_id}");
// }

function lxp_get_class_assignments($class_id)
{
    $school_query = new WP_Query( array( 
        'post_type' => TL_ASSIGNMENT_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'class_id', 'value' => $class_id, 'compare' => '=')
        )
    ) );
    return $school_query->get_posts();
}

function lxp_get_all_teachers_assignments($teachers_ids)
{

    if (empty($teachers_ids)) {
        return array();
    }

    $school_query = new WP_Query( array( 
        'post_type' => TL_ASSIGNMENT_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'lxp_assignment_teacher_id', 'value' => $teachers_ids, 'compare' => 'IN')
        )
    ) );
    return $school_query->get_posts();
}

function lxp_get_teacher_assignments($teacher_id, $count = -1)
{
    $school_query = new WP_Query( array( 
        'post_type' => TL_ASSIGNMENT_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => $count,        
        'meta_query' => array(
            array('key' => 'lxp_assignment_teacher_id', 'value' => $teacher_id, 'compare' => 'IN')
        )
    ) );
    return $school_query->get_posts();
}

function lxp_get_course_segment_assignment($course_id,  $course_section_id, $lxp_assignment_teacher_id)
{
    $assignments_query = new WP_Query( array( 
        'post_type' => TL_ASSIGNMENT_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'trek_id', 'value' => $course_id, 'compare' => '='),
            array('key' => 'trek_section_id', 'value' => $course_section_id, 'compare' => '='),
            array('key' => 'lxp_assignment_teacher_id', 'value' => $lxp_assignment_teacher_id, 'compare' => '=')
        )
    ) );
    return $assignments_query->get_posts();
}

function lxp_get_assignment($assignment_id) {
    $assignment = get_post($assignment_id);
    $assignment->grade = get_post_meta($assignment_id, 'grade', true);
    $assignment->lxp_assignment_teacher_id = get_post_meta($assignment_id, 'lxp_assignment_teacher_id', true);
    $assignment->lxp_student_ids = get_post_meta($assignment_id, 'lxp_student_ids');
    $assignment->trek_section_id = get_post_meta($assignment_id, 'trek_section_id', true);
    $assignment->trek_id = get_post_meta($assignment_id, 'trek_id', true);
    $assignment->start_date = get_post_meta($assignment_id, 'start_date', true);
    $assignment->schedule = json_decode(get_post_meta($assignment_id, 'schedule', true));
    return $assignment;
}

function lxp_get_students($students_ids) {
    $students = array_map(function ($student_id)
    {
        $student = get_post($student_id);
        $student->grades = get_post_meta($student_id, 'grades', true);
        $admin = get_userdata(get_post_meta($student_id, 'lxp_student_admin_id', true));
        $student->admin_first_name = get_user_meta($admin->ID, 'first_name', true);
        $student->admin_last_name = get_user_meta($admin->ID, 'last_name', true);
        $student->name = $admin->data->display_name;
        $student->status = "In progress";
        $student->score = "0%";
        $student->progress = "0/0";
        return $student;
    }, $students_ids);
    return $students;
}

function lxp_get_course_digital_journals($course_id) {
    $journal_query = new WP_Query( array( 
        'post_type' => TL_LESSON_CPT,
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'tl_course_id', 'value' => $course_id, 'compare' => '=')
        )
    ) );
    return $journal_query->get_posts();
}

// function lxp_get_student_assignment_grade to get grade for student with slid number
function lxp_get_student_assignment_grade($student_post_id, $assignment_post_id, $slide) {
    $assignment_grade_key = "assignment_" . $assignment_post_id . "_slide_" . $slide . "_grade";
    return get_post_meta($student_post_id, $assignment_grade_key, true);
}

function lxp_get_student_post($student_id)
{
    $query = new WP_Query( array( 
        'post_type' => TL_STUDENT_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'lxp_student_admin_id', 'value' => $student_id, 'compare' => '=')
        )
    ) );
    
    $posts = $query->get_posts();
    return count($posts) > 0 ? $posts[0] : null;
}

// function lxp_get_student_assignments to get all student assignments using WPQuery object and return array of assignments
function lxp_get_student_assignments($student_post_id)
{
    $school_query = new WP_Query( array( 
        'post_type' => TL_ASSIGNMENT_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'lxp_student_ids', 'value' => $student_post_id, 'compare' => 'IN')
        )
    ) );
    return $school_query->get_posts();
}

function lxp_get_assignments_courses($assignments)
{
    // $courses = array_map(function ($assignment) { return get_post($assignment->course_id)->ID; }, $assignments); abid comment it 6May2024 undefined id error was showing
    $courses = array_map(function ($assignment) { return $assignment->course_id; }, $assignments);
    $query = new WP_Query( array( 'post_type' => LP_COURSE_CPT , 'posts_per_page'   => -1, 'post_status' => array( 'publish' ), 'post__in' => array_values(array_unique($courses)), 'orderby' => 'meta_value_num', 'order' => 'ASC' ) );
    return $query->get_posts();
}

function lxp_assignment_stats($assignment_id) {
    $students_ids = get_post_meta($assignment_id, 'lxp_student_ids');
    $q = new WP_Query( array( "post_type" => TL_STUDENT_CPT, 'posts_per_page'   => -1, "post__in" => $students_ids ) );
    $students_posts = $q->get_posts();
    $students = array_map(function ($student) use ($assignment_id) {
        $attempted = lxp_user_assignment_attempted($assignment_id, $student->ID);
        $submission = lxp_get_assignment_submissions($assignment_id, $student->ID);
        /* 
        if ($attempted && is_null($submission)) {
            $status = 'In Progress';
        }else if ($attempted && !is_null($submission)) {
            $status = 'Completed';
        } else {
            $status = 'To Do';
        }
        */
        $status = 'To Do';
        if ($attempted && !is_null($submission) && !$submission['lti_user_id'] && !$submission['submission_id']) {
            $status = 'In Progress';
        } else if ($attempted && !is_null($submission) && $submission['lti_user_id'] && $submission['submission_id']) {
            $status = 'Completed';
        }
        $lxp_student_admin_id = get_post_meta($student->ID, 'lxp_student_admin_id', true);
        $userdata = get_userdata($lxp_student_admin_id);
        $progress = $submission && $submission['score_raw'] && $submission['score_max'] ? $submission['score_raw'] .'/'. $submission['score_max'] : '---';
        $score = $submission && $submission['score_scaled'] ? round(($submission['score_scaled'] * 100), 2) . '%' : '---';
        $data = array("ID" => $student->ID, "name" => $userdata->data->display_name, "status" => $status, "progress" => $progress, "score" => $score);
        return $data;
    } , $students_posts);
    return $students;
}

function lxp_get_teacher_saved_courses($args, $courses_saved_ids, $strand = '', $sort='', $search='')
{
    if (count($courses_saved_ids) > 0 && is_array($courses_saved_ids)) {
        $args = array( 
            'post_type' => LP_COURSE_CPT , 
            'posts_per_page'   => -1, 
            'post_status' => array( 'publish' ), 
            'post__in' => array_values(array_unique($courses_saved_ids)), 
            // 'meta_key' => 'sort', 
            'orderby' => 'meta_value_num', 
            'order' => 'ASC' );
        if(!($strand === '' || $strand === 'none')) {
            $args['meta_query'] = array('key' => 'strands', 'value' => $strand, 'compare' => '=');
        }

        if(!($sort === '' || $sort === 'none')) {
            $args['order'] = $sort;
        }

        if(!($search === '' || $search === 'none')) {
            $args['s'] = $search;
        }
        
        $query = new WP_Query( $args );
        return $query->get_posts();
    } else {
        return array();
    }
}

// function to get assignment submission post type by assignment id using WPQuery object which returns array of posts.
function lxp_get_assignment_submissions($assignment_id, $student_post_id)
{
    $query = new WP_Query( array( 
        'post_type' => TL_ASSIGNMENT_SUBMISSION_CPT , 
        'posts_per_page'   => -1, 
        'post_status' => array( 'publish' ), 
            'meta_query' => array(
                array('key' => 'lxp_assignment_id', 'value' => $assignment_id, 'compare' => '='),
                array('key' => 'lxp_student_id', 'value' => $student_post_id, 'compare' => '=')
            )
        )
    );
    $assignment_submission_posts = $query->get_posts();

    if ($assignment_submission_posts) {
        $lesson_id = get_post_meta($assignment_id, 'lxp_lesson_id', true);
        $assignment_submission_post = $assignment_submission_posts[0];
        $assignment_submission_post_data = array(
            'ID' => $assignment_submission_post->ID,
            'local_user_id' => $assignment_submission_post->post_author,
            'lxp_assignment_id' => get_post_meta($assignment_submission_post->ID, 'lxp_assignment_id', true),
            'lxp_student_id' => get_post_meta($assignment_submission_post->ID, 'lxp_student_id', true),
            'lti_user_id' => get_post_meta($assignment_submission_post->ID, 'lti_user_id', true),
            'h5p_content_id' => get_post_meta($lesson_id, 'h5p_content_id', true),
            'activity_id' => get_post_meta($lesson_id, 'lti_custom_attr', true),
            'submission_id' => get_post_meta($assignment_submission_post->ID, 'submission_id', true),
            'score_min' => get_post_meta($assignment_submission_post->ID, 'score_min', true),
            'score_max' => get_post_meta($assignment_submission_post->ID, 'score_max', true),
            'score_raw' => get_post_meta($assignment_submission_post->ID, 'score_raw', true),
            'score_scaled' => get_post_meta($assignment_submission_post->ID, 'score_scaled', true),
            'completion' => boolval(get_post_meta($assignment_submission_post->ID, 'completion', true)),
            'duration' => get_post_meta($assignment_submission_post->ID, 'duration', true)
        );
        return $assignment_submission_post_data;
    } else {
        return null;
    }
}

function lxp_user_assignment_attempted($assignment_id, $user_id) {
    $query = new WP_Query( array( 'post_type' => TL_ASSIGNMENT_CPT ,
                                  'posts_per_page'   => -1,
                                  'post_status' => array( 'publish' ),
                                  'p' => $assignment_id,
                                  'meta_query' => array(
                                    array('key' => 'attempted_students', 'value' => $user_id, 'compare' => 'IN')
                                ) ) );
    $assignment_posts = $query->get_posts();
    return count($assignment_posts) > 0 ? true : false;
}

function assignments_submissions($assignments, $student_post)
{
    $assignments_submission = array_map(function($assignment) use ($student_post) {
        $attempted = lxp_user_assignment_attempted($assignment->ID, $student_post->ID);
        $submission = lxp_get_assignment_submissions($assignment->ID, $student_post->ID);
        $status = 'To Do';
        if ($attempted && !is_null($submission) && !$submission['lti_user_id'] && !$submission['submission_id']) {
            $status = 'In Progress';
        } else if ($attempted && !is_null($submission) && $submission['lti_user_id'] && $submission['submission_id']) {
            $status = 'Completed';
        }
        return array( $assignment->ID => array('status' => $status, 'submission' => $submission) );
    }, $assignments);   
    return $assignments_submission;
}

function get_assignment_lesson_slides($assignment_post_id) {
    $course = get_post(get_post_meta($assignment_post_id, 'course_id', true));
    $lxp_lesson_post = get_post(get_post_meta($assignment_post_id, 'lxp_lesson_id', true));
    $lesson_query = new WP_Query( array( 
        'post_type' => TL_LESSON_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'tl_course_id', 'value' => $course->ID, 'compare' => '=')
        )
    ) );
    $activity_id = '47048';
    // foreach ($lesson_query->get_posts() as $lesson) {
    //     if ( $lesson->ID == $lxp_lesson_post->ID ) {
    //         $tool_url_parts = parse_url(get_post_meta($lesson->ID, 'lti_tool_url', true));
    //         if (isset($tool_url_parts['query'])) {
    //             $q = [];
    //             parse_str($tool_url_parts['query'], $q);
    //             $activity_id = isset($q['activity']) ? $q['activity'] : 0;
    //         }
    //     }        
    // }

    $response = wp_remote_get(CURRIKI_STUDIO_HOST . 'api/api/v1/activities/' . $activity_id . '/h5p/cp');
    $code = wp_remote_retrieve_response_code($response);
    $data =  array();
    if ($code === 200) {
        $data = json_decode(wp_remote_retrieve_body($response));
        $data->slides = array_filter($data->slides, function($item) {
            return strtolower($item->title) !== 'you did it!';
        });
    }
    return $data;
}

function lxp_check_assignment_submission($assignment_id, $student_post_id) {

    $assignment_post = get_post($assignment_id);
    $user_post = get_post($student_post_id);
    $userId = get_post_meta($user_post->ID, 'lxp_student_admin_id', true);
    
    $assignment_submission_get_query = new WP_Query( array( 'post_type' => TL_ASSIGNMENT_SUBMISSION_CPT , 'posts_per_page'   => -1, 'post_status' => array( 'publish' ), 
            'meta_query' => array(
                array('key' => 'lxp_assignment_id', 'value' => $assignment_id, 'compare' => '='),
                array('key' => 'lxp_student_id', 'value' => $student_post_id, 'compare' => '=')
            )
        )
    );
    $assignment_submission_posts = $assignment_submission_get_query->get_posts();
    if (!count($assignment_submission_posts)) {
        $assignment_submission_post_title = $user_post->post_title . ' | ' . $assignment_post->post_title;
        $assignment_submission_post_arg = array(
            'post_title'    => wp_strip_all_tags($assignment_submission_post_title),
            'post_content'  => $assignment_submission_post_title,
            'post_status'   => 'publish',
            'post_author'   => $userId,
            'post_type'   => TL_ASSIGNMENT_SUBMISSION_CPT
        );
        $assignment_submission_post_id = wp_insert_post($assignment_submission_post_arg);
        if ($assignment_submission_post_id) {
            update_post_meta($assignment_submission_post_id, 'lxp_assignment_id', $assignment_post->ID);
            update_post_meta($assignment_submission_post_id, 'lxp_student_id', $user_post->ID);
        }
        return $assignment_submission_post_id ? true : false;
    }
}

function lxp_assignment_submission_auto_score($assignment_submission_id, $slide) {
    $sub_content_ids = get_post_meta($assignment_submission_id, "subContentIds");
    $slide_result_keys = array_map(function($sub_content_id) use ($slide) {
        return "slide_{$slide}_subContentId_{$sub_content_id}_result";
    }, $sub_content_ids);
    $slide_contents_result = array_filter(get_post_meta($assignment_submission_id), function($key) use ($slide_result_keys) {
        return in_array($key, $slide_result_keys);
    }, ARRAY_FILTER_USE_KEY);
    
    $score = array_reduce($slide_contents_result, function($carry, $item) {
        $carry += json_decode($item[0])->score->raw;
        return $carry;
    }, 0);
    
    $max = array_reduce($slide_contents_result, function($carry, $item) {
        $carry += json_decode($item[0])->score->max;
        return $carry;
    }, 0);
    return array(
        'score' => $score,
        'max' => $max
    );
}

function lxp_get_teacher_group_by_type($lxp_class_teacher_id, $type)
{
    $query = new WP_Query( array( 
        'post_type' => TL_CLASS_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array(
                'key' => 'lxp_class_teacher_id', 
                'value' => $lxp_class_teacher_id, 'compare' => '='
            ),
            array(
                'key' => 'lxp_class_type', 
                'value' => $type, 'compare' => '='
            )
        )
    ) );
    return $query->get_posts();
}

function lxp_get_teacher_default_classes($lxp_class_teacher_id)
{
    $query = new WP_Query( array( 
        'post_type' => TL_CLASS_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array(
                'key' => 'lxp_class_teacher_id', 
                'value' => $lxp_class_teacher_id, 'compare' => '='
            ),
            array(
             'key' => 'lxp_class_type',
             'compare' => 'NOT EXISTS'
            )
        )
    ) );
    return $query->get_posts();
}

function lxp_get_teacher_groups($lxp_group_teacher_id)
{
    $query = new WP_Query( array( 
        'post_type' => TL_GROUP_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array(
                'key' => 'lxp_group_teacher_id', 
                'value' => $lxp_group_teacher_id, 'compare' => '='
            )
        )
    ) );
    return $query->get_posts();
}

function lxp_get_class_group($class_id)
{
    $query = new WP_Query( array( 
        'post_type' => TL_GROUP_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'lxp_class_group_id', 'value' => $class_id, 'compare' => '=')
        )
    ) );
    return $query->get_posts();
}

function lxp_get_student_class_group_by_type($student_id, $type)
{
    $query = new WP_Query( array( 
        'post_type' => TL_CLASS_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(            
            array('key' => 'lxp_student_ids', 'value' => $student_id, 'compare' => '='),
            array(
                'key' => 'lxp_class_type', 
                'value' => $type, 'compare' => '='
            )
        )
    ) );
    return $query->get_posts();
}

function lxp_get_all_teacher_groups($teachers_ids)
{
    if (empty($teachers_ids)) {
        return array();
    }

    $query = new WP_Query( array( 
        'post_type' => TL_GROUP_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'lxp_group_teacher_id', 'value' => $teachers_ids, 'compare' => 'IN')
        )
    ) );
    return $query->get_posts();
}

function lxp_get_all_teachers_group_by_type($teachers_ids, $type)
{
    if (empty($teachers_ids)) {
        return array();
    }

    $query = new WP_Query( array( 
        'post_type' => TL_CLASS_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'lxp_class_teacher_id', 'value' => $teachers_ids, 'compare' => 'IN'),
            array(
                'key' => 'lxp_class_type', 
                'value' => $type, 'compare' => '='
            )
        )
    ) );
    return $query->get_posts();
}

function lxp_get_teacher_all_default_classes($teachers_ids)
{
    if (empty($teachers_ids)) {
        return array();
    }

    $query = new WP_Query( array( 
        'post_type' => TL_CLASS_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'lxp_class_teacher_id', 'value' => $teachers_ids, 'compare' => 'IN'),
            array(
             'key' => 'lxp_class_type',
             'compare' => 'NOT EXISTS'
            )
        )
    ) );
    return $query->get_posts();
}

function lxp_get_all_teachers_groups($teachers_ids)
{
    if (empty($teachers_ids)) {
        return array();
    }

    $query = new WP_Query( array( 
        'post_type' => TL_GROUP_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,        
        'meta_query' => array(
            array('key' => 'lxp_group_teacher_id', 'value' => $teachers_ids, 'compare' => 'IN')
        )
    ) );
    return $query->get_posts();
}

function lxp_get_courses()
{
    $courses_query = new WP_Query( array( 
        'post_type' => TL_COURSE_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,
        'order' => 'asc'
    ) );
    return $courses_query->get_posts();
}

function lxp_get_lessons_by_course($course_id)
{
    $lessons_query = new WP_Query( array( 
        'post_type' => TL_LESSON_CPT, 
        'post_status' => array( 'publish' ),
        'posts_per_page'   => -1,
        'order' => 'asc',
        'meta_query' => [
            [
              'key' => 'tl_course_id', 
              'value' => $course_id,
              'compare' => '='
            ]
        ]
    ) );
    return $lessons_query->get_posts();
}

/**
 * @param   assignment_submission_id
 * @since   10-01-2025
 * @see     Abid
 */
function get_interactions_by_activity($assignment_submission) : array {
    $string = $assignment_submission['activity_id'];
    // $h5p_content_id = $assignment_submission['h5p_content_id'];\
    $h5p_content_id = '40439';
    // Split the string by '=' to get an array
    $parts = explode('=', $string);
    // The value you want is the last element in the array
    // $activityId = end($parts);
    $activityId = '48225';
    $curriki_studio_host = CURRIKI_STUDIO_HOST.'api/api/v1/google-classroom/activities/'.$activityId.'/h5p-resource-settings';
    // var_dump($curriki_studio_host); die;
    $response = wp_remote_get($curriki_studio_host, ['timeout' => 180]);

    // $jsonFile = 'D:\xampp7\htdocs\tsgportal.local\wp-content\plugins\TinyLxp-wp-plugin\lms\templates\json_decoded.txt';
    // // Read the JSON content from the file
    // $jsonData = file_get_contents($jsonFile);
    // // Convert JSON to PHP array
    // $phpArray = json_decode($jsonData, true);
    // return ['data'=>json_decode($jsonData, true), 'type'=>'interactive_book'];
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    $content_data = $data ? $data['h5p']['settings']['contents']['cid-'.$h5p_content_id.'']['jsonContent'] : '';
    $interactions_array = json_decode($content_data, true);
    

    $interactions_array = isset($interactions_array['content']) ? $interactions_array['content'][0]['content']['params'] : $interactions_array;
    // var_dump($response); exit;    
    //  check for interactive-Video   or  interactive-Book
    if ( isset($interactions_array['interactiveVideo']) ) {
        return filterInteractiveVideoInteractions($interactions_array['interactiveVideo']);
    } elseif ( isset($interactions_array['chapters']) ) {
        return filterInteractiveBookInteractions($interactions_array['chapters']);
    } elseif ( isset($interactions_array['questions']) ) {
        return filterQuestionSetInteractions($interactions_array['questions']);
    } elseif ( isset($interactions_array['presentation']) ) {
        return filterCoursePresentationInteractions($interactions_array['presentation']['slides']);
    } else {
        return ['data'=>[], 'type'=>''];
    }
}

/**
 * @param   assignment_id,verb
 */
function get_submitted_xapi_data($assignment_submission, $verb=null) : array {
    $interactions_data = [];
    if (isset($assignment_submission['lti_user_id']) && isset($assignment_submission['h5p_content_id'])) {
        $lti_user_id = $assignment_submission['lti_user_id'];
        $h5p_content_id = $assignment_submission['h5p_content_id'];
        $sub_content_id = isset($assignment_submission['sub_content_id']) ? '?subContentId='.$assignment_submission['sub_content_id'] : '';
        // live sqlrs path
        $curriki_studio_host = XAPI_HOST.'/api/v1/xapi/statements?limit=1000&related_activities=1';
        $curriki_studio_host .= '&agent={"objectType":"Agent","account":{"homePage":"'.TSUGI_HOST.'","name":"'.$lti_user_id.'"}}';
        if ($verb) {
            $curriki_studio_host .= '&verb=http://adlnet.gov/expapi/verbs/'.$verb;
        }
        $curriki_studio_host .= '&activity='.CURRIKI_STUDIO_HOST.'h5p/embed/'.$h5p_content_id.$sub_content_id;
        // $curriki_studio_host .= '&ascending=1';
        // var_dump($curriki_studio_host); exit;
        $response = wp_remote_get($curriki_studio_host, ['timeout' => 60]);
        $code = wp_remote_retrieve_response_code($response);
        if ($code == 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($data['statements'])) {
                $interactions_data = array_reduce( $data['statements'], function ($carry, $statement) {
                    static $seenCombinations = [];
                    
                    $query_string = parse_url($statement['object']['id'], PHP_URL_QUERY);
                    $verb_id = $statement['verb']['display']['en-US'];
                    parse_str($query_string, $params);
                    $sub_content_id = $params['subContentId'] ?? null;
                    
                    $combinationKey = $sub_content_id . '|' . $verb_id;
                    // && isset($statement['result']['score']) 
                    if (!isset($seenCombinations[$combinationKey]) ) {
                        $seenCombinations[$combinationKey] = true;
                        $statement['object']['definition']['extensions']['http://h5p.org/x-api/h5p-subContentId'] = $sub_content_id;
                        $carry[] = $statement;
                    }
                    return $carry;
                }, [] );
            }
        }
    }
    return $interactions_data;
}

/**
 * @param   time_sting,working,on,both,cases,like,interaction_time,and,xapi_time
 */
function convetToInterectiveVideoTimeFormate($timeString, $check = 0) {
    // Extract the integer part
    $seconds = ($check == 0) ? substr($timeString, 2, -1) : $timeString;
    $seconds = intval($seconds);
    // Calculate minutes and seconds
    $minutes = floor($seconds / 60);
    $remainingSeconds = $seconds % 60;
    // Format the output
    $formattedTime[] = sprintf("%d:%02d", $minutes, $remainingSeconds);
    $formattedTime[] = $seconds;
    return $formattedTime;
}

/**
 * @param   time_sting,working,on,both,cases,like,interaction_time,and,xapi_time
 */
function filterInteractiveVideoInteractions($interactions_array_fetch) : array {
    // $interactions_array_fetch = isset($interactions_array) ? $interactions_array['interactiveVideo']['assets']['interactions'] : [];
    
    $filtered_interactions = array_filter($interactions_array_fetch['assets']['interactions'], function($single_interaction) {
        return in_array($single_interaction['libraryTitle'], Allowed_Activity_types);
    });
    // Custom comparison function to sort by duration.from
    usort($filtered_interactions, function($a, $b) {
        return $a['duration']['from'] <=> $b['duration']['from'];
    });

    // check if last summary exist or not of interactive video activity
    if (isset($interactions_array_fetch['summary']['task']['params']['summaries'][0]['summary'])) {
        $summary_task['action']['metadata']['contentType'] = 'Summary';
        $summary_task['action']['metadata']['title'] = $interactions_array_fetch['summary']['task']['metadata']['title'];
        $summary_task['action']['subContentId'] = $interactions_array_fetch['summary']['task']['params']['summaries'][0]['subContentId'];
        $summary_task['duration']['from'] = '';
        $summary_task['action']['params']['summaries'] = $interactions_array_fetch['summary']['task']['params']['summaries'];
        $filtered_interactions[] = $summary_task;
    }
    // return $filtered_interactions;
    $response = [];
    $response['data'] = $filtered_interactions;
    $response['type'] = 'interactive_video';
    return $response;
}

/**
 * @param   time_sting,working,on,both,cases,like,interaction_time,and,xapi_time
 */
function filterInteractiveBookInteractions($interactions_array_fetch) : array {
    $response = [];
    $response['data'] = $interactions_array_fetch;
    $response['type'] = 'interactive_book';
    return $response;
}

/**
 * @param   time_sting,working,on,both,cases,like,interaction_time,and,xapi_time
 */
function filterQuestionSetInteractions($interactions_array_fetch) : array {
    $response = [];
    $response['data'] = $interactions_array_fetch;
    $response['type'] = 'question_set';
    return $response;
}

/**
 * @param   time_sting,working,on,both,cases,like,interaction_time,and,xapi_time
 */
function filterCoursePresentationInteractions($interactions_array_fetch) : array {
    $response = [];
    $response['data'] = $interactions_array_fetch;
    $response['type'] = 'course_presentation';
    return $response;
}

/**
 * @param      Sub_content_id-,-Xapi_statements_of_interactions
 * @return     array
 * throws      No_error
 * @var         allowed_types
 * deprecated,  No
 * @since       19-05-2025
 * @see         Abid
 */
function getScoresByGivenXapiStatements($sub_content_id_array, $interaction_xapis) : array {
    // var_dump($sub_content_id_array); die;
    $scores = array_map(function($single_sub_content_id) use ($interaction_xapis) {
        $filtered = array_filter($interaction_xapis, function($statement) use ($single_sub_content_id) {
            $xapi_sub_content_id = $statement['object']['definition']['extensions']['http://h5p.org/x-api/h5p-subContentId'];
            return ( $single_sub_content_id == $xapi_sub_content_id && ( $statement['verb']['display']['en-US'] == 'completed' || $statement['verb']['display']['en-US'] == 'answered' ) );
        });
        if ($filtered) {
            $xapi = reset($filtered);
            return [
                'score' => $xapi['result']['score'] ?? ''
            ];
        }
        return [ 'score' => '' ];
    }, $sub_content_id_array);
    return $scores;
}

/**
 * @param      submission_id-,-interactions_data-,-xapi_array
 */
function get_cp_interactions_score($assignment_submission, $slides, $interaction_xapis) {
    $score_raw = 0;
    $score_max = 0;
    $sub_content_id_array = []; // do not change the place of this code
    foreach ($slides as $single_slide) {
        if (isset($single_slide['elements'])) { // this if for empty slide or slide have data
            foreach ($single_slide['elements'] as $question_per_slide) {
                $cp_intrect_type_name = $question_per_slide['action']['metadata']['contentType'];
                if ( in_array($cp_intrect_type_name, Allowed_Activity_types) ) {
                    $sub_content_id_array = []; // do not change the place of this code
                    if ($cp_intrect_type_name == 'Essay') {
                        $sub_content_id_array[] = $question_per_slide['action']['subContentId'];
                        $i_grade = get_post_meta($assignment_submission['assignment_submission_id'], 'slide_'.$sub_content_id_array[0].'_grade', true);
                        $score_raw += $i_grade == '' ? '0' : $i_grade;
                        $score_max += '7';
                    } else {
                        if ( $cp_intrect_type_name == 'Statements' || $cp_intrect_type_name == 'Summary' ) {
                            $sub_content_id_array = array_column( $question_per_slide['action']['params']['summaries'], 'subContentId' );
                        } elseif ($cp_intrect_type_name == 'Single Choice Set') {
                            $sub_content_id_array = array_column( $question_per_slide['action']['params']['choices'], 'subContentId');
                        } else {
                            $sub_content_id_array[] = $question_per_slide['action']['subContentId'];
                        }
                        $scores_array = getScoresByGivenXapiStatements($sub_content_id_array, $interaction_xapis);
                        $scores = array_column($scores_array, 'score');
                        $score_raw += array_sum(array_column($scores, 'raw'));
                        $score_max += array_sum(array_column($scores, 'max'));
                    }
                }
            }
        }
    }
    return [ 'sub_content_id_array' => $sub_content_id_array, 'score_raw' => $score_raw, 'score_max' => $score_max ];
}

/**
 * @param      submission_id-,-interactions_data-,-xapi_array
 */
function get_iv_interactions_score($assignment_submission, $iv_data, $interaction_xapis) {
    $iv_questions = filterInteractiveVideoInteractions($iv_data);
    $score_raw = 0;
    $score_max = 0;
    foreach ($iv_questions['data'] as $question_per_second) {
        $iv_intrect_type_name = $question_per_second['action']['metadata']['contentType'];
        if ( isset($iv_intrect_type_name) && in_array($iv_intrect_type_name, Allowed_Activity_types) ) {
            $sub_content_id_array = []; // do not change the place of this code
            if ($iv_intrect_type_name == 'Free Text Question') {
                $sub_content_id_array[] = $question_per_second['action']['subContentId'];
                $i_grade = get_post_meta($assignment_submission['assignment_submission_id'], 'slide_'.$sub_content_id_array[0].'_grade', true);
                $score_raw += $i_grade == '' ? '0' : $i_grade;
                $score_max += '7';
            } else {
                if ( $iv_intrect_type_name == 'Statements' || $iv_intrect_type_name == 'Summary' ) {
                    $sub_content_id_array = array_column( $question_per_second['action']['params']['summaries'], 'subContentId' );
                } elseif ($iv_intrect_type_name == 'Single Choice Set') {
                    $sub_content_id_array = array_column( $question_per_second['action']['params']['choices'], 'subContentId');
                } else {
                    $sub_content_id_array[] = $question_per_second['action']['subContentId'];
                }
                $scores_array = getScoresByGivenXapiStatements($sub_content_id_array, $interaction_xapis);
                $scores = array_column($scores_array, 'score');
                $score_raw += array_sum(array_column($scores, 'raw'));
                $score_max += array_sum(array_column($scores, 'max'));
            }
        }
    }
    return [ 'sub_content_id_array' => $sub_content_id_array, 'score_raw' => $score_raw, 'score_max' => $score_max ];
}

/**
 * @param      submission_id-,-interactions_data-,-xapi_array
 */
function get_question_set_interactions_score($assignment_submission, $qs_data, $interaction_xapis) {
    $score_raw = 0;
    $score_max = 0;

    foreach ($qs_data as $single_question) {
        $iv_intrect_type_name = $single_question['metadata']['contentType'];
        if ( isset($iv_intrect_type_name) && in_array($iv_intrect_type_name, Allowed_Activity_types) ) {
            $sub_content_id_array = []; // do not change the place of this code
            if ($iv_intrect_type_name == 'Free Text Question') {
                $sub_content_id_array[] = $single_question['subContentId'];
                $i_grade = get_post_meta($assignment_submission['assignment_submission_id'], 'slide_'.$sub_content_id_array[0].'_grade', true);
                $score_raw += $i_grade == '' ? '0' : $i_grade;
                $score_max += '7';
            } else {
                if ( $iv_intrect_type_name == 'Statements' || $iv_intrect_type_name == 'Summary' ) {
                    $sub_content_id_array = array_column( $single_question['params']['summaries'], 'subContentId' );
                } elseif ($iv_intrect_type_name == 'Single Choice Set') {
                    $sub_content_id_array = array_column( $single_question['params']['choices'], 'subContentId');
                } else {
                    $sub_content_id_array[] = $single_question['subContentId'];
                }
                $scores_array = getScoresByGivenXapiStatements($sub_content_id_array, $interaction_xapis);
                $scores = array_column($scores_array, 'score');
                $score_raw += array_sum(array_column($scores, 'raw'));
                $score_max += array_sum(array_column($scores, 'max'));
            }
        }
    }
    return [ 'sub_content_id_array' => $sub_content_id_array, 'score_raw' => $score_raw, 'score_max' => $score_max ];
}

/**
 * @return      array-of-roles-of-current-user      
 */
if (! function_exists('get_custom_role')) {
    function get_custom_role() : string {
        $current_user = wp_get_current_user();
        $roles = $current_user->roles;

        // Get current active role from transient
        $active_role = get_transient('active_role_' . $current_user->ID);
        // If not set, default to first available role
        if (!$active_role && !empty($roles)) {
            $active_role = $roles[0];
            set_transient('active_role_' . $current_user->ID, $active_role, 3600);
        }

        // Handle form submit to switch role
        if (isset($_POST['switch_role'])) {
            $switch_role = sanitize_text_field($_POST['switch_role']);
            if (in_array($switch_role, $roles)) {
                $active_role = $switch_role;
                set_transient('active_role_' . $current_user->ID, $active_role, 3600);
            }
        }
        if ($active_role === 'lp_teacher' || $active_role === 'lxp_teacher_admin') {
            return 'lp_teacher';
        }

        return $active_role;
    }
}

// =============================================================================
// Zero-PII token students (Zone A)
// See docs/student-privacy-zone-a-context.md
// =============================================================================

/**
 * Seats currently consumed on a class (active token members).
 *
 * @param  int $class_id tl_class post ID.
 * @return int
 */
function lxp_get_class_seats_taken($class_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'lxp_class_members';

    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE class_id = %d AND status = %s",
        absint($class_id),
        'active'
    ));
}

/**
 * Whether a WP user is a pseudonymous token student (no PII on file).
 *
 * @param  int $user_id
 * @return bool
 */
function lxp_is_token_student($user_id = 0)
{
    $user_id = $user_id ? absint($user_id) : get_current_user_id();

    return $user_id && (bool) get_user_meta($user_id, 'lxp_is_token_student', true);
}

/**
 * Whether a school has opted into token mode.
 *
 * When on, the legacy name-collecting student flows (CSV import, Manage
 * Students) refuse to write first/last name or a plaintext password.
 *
 * @param  int $school_post_id tl_school post ID.
 * @return bool
 */
function lxp_school_is_token_mode($school_post_id)
{
    return (bool) get_post_meta(absint($school_post_id), 'lxp_school_token_mode', true);
}

/**
 * Whether a user must be excluded from every marketing list.
 *
 * Token accounts are flagged `lxp_no_marketing` at creation. Note that no
 * mailing integration lives in this plugin — this helper (and the
 * `tl_lxp_marketing_excluded_users` filter) is the contract that whichever
 * plugin owns noptin/Mailgun must honour.
 *
 * @param  int $user_id
 * @return bool
 */
function lxp_is_no_marketing_user($user_id)
{
    $user_id = absint($user_id);
    $blocked = (bool) get_user_meta($user_id, 'lxp_no_marketing', true);

    /**
     * Filter whether a user is walled off from marketing.
     *
     * @param bool $blocked
     * @param int  $user_id
     */
    return (bool) apply_filters('tl_lxp_user_no_marketing', $blocked, $user_id);
}

/**
 * All user IDs that must never receive marketing email.
 *
 * @return int[]
 */
function lxp_get_marketing_excluded_users()
{
    $ids = get_users(array(
        'meta_key'   => 'lxp_no_marketing',
        'meta_value' => 1,
        'fields'     => 'ID',
        'number'     => -1,
    ));

    /**
     * Filter the marketing exclusion list.
     *
     * @param int[] $ids
     */
    return array_map('absint', (array) apply_filters('tl_lxp_marketing_excluded_users', $ids));
}

?>