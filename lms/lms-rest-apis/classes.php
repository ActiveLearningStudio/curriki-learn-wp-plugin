<?php

class Rest_Lxp_Class
{
	/**
	 * Register the REST API routes.
	 */
	public static function init()
	{
		if (!function_exists('register_rest_route')) {
			// The REST API wasn't integrated into core until 4.4, and we support 4.0+ (for now).
			return false;
		}

		register_rest_route('lms/v1', '/class/students', array(
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array('Rest_Lxp_Class', 'get_students'),
				'permission_callback' => '__return_true'
			)
		));

		register_rest_route('lms/v1', '/classes', array(
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array('Rest_Lxp_Class', 'get_one'),
				'permission_callback' => '__return_true'
			)
		));
		
		register_rest_route('lms/v1', '/classes/save', array(
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array('Rest_Lxp_Class', 'create'),
				'permission_callback' => '__return_true',
				'args' => array(
					'class_name' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'class name',
						'validate_callback' => function($param, $request, $key) {
							return strlen( $param ) > 1;
						}
					),					
					// 'class_description' => array(
					// 	'required' => true,
					// 	'type' => 'string',
					// 	'description' => 'class description',
					// 	'validate_callback' => function($param, $request, $key) {
					// 		return strlen( $param ) > 1;
					// 	}
					// ),					
					// 'schedule' => array(
					// 	'required' => true,
					// 	'description' => 'class schedule',
					// 	'validate_callback' => function($param, $request, $key) {
					// 		$ok = true;
					// 		if (count( $param ) === 0) {
					// 			$ok = false;
					// 		}
					// 		foreach ($param as $day) {
					// 			$start = $request->get_param($day . '-sd');
					// 			$end = $request->get_param($day . '-ed');
					// 			if ( !(boolval(strlen($start)) || boolval(strlen($end))) )
					// 			{
					// 				$ok = false;
					// 			}
					// 		}
					// 		return $ok;
					// 	}
					// ),
					// 'grade' => array(
					// 	'required' => true,
					// 	'type' => 'string',
					// 	'description' => 'class grade',
					// 	'validate_callback' => function($param, $request, $key) {
					// 		return strlen( $param ) > 1;
					// 	}
					// ),
					// Deliberately NOT required. The class modal no longer carries a
					// student picker — students reach a class by redeeming its code or
					// through the Roster modal — so most saves omit this entirely.
					// create() only rewrites lxp_student_ids when it is actually sent.
					'student_ids' => array(
						'required' => false,
						'description' => 'class students',
						'validate_callback' => function($param, $request, $key) {
							return is_array( $param );
						}
					),
					'class_teacher_id' => array(
						'required' => true,
						'type' => 'integer',
						'description' => 'class teacher id',
						'validate_callback' => function($param, $request, $key) {
							return intval( $param ) > 0;
						}
					),
					'class_post_id' => array(
						'required' => true,
						'type' => 'integer',
						'description' => 'class post id',
						'validate_callback' => function($param, $request, $key) {
							return strlen( $param ) > 0;
						}
					)
			   )
			),
		));

		register_rest_route('lms/v1', '/class/courses', array(
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array('Rest_Lxp_Class', 'get_class_courses'),
				'permission_callback' => '__return_true'
			)
		));

		register_rest_route('lms/v1', '/class/courses/save', array(
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array('Rest_Lxp_Class', 'save_class_courses'),
				'permission_callback' => '__return_true'
			)
		));

		register_rest_route('lms/v1', '/class/available-courses', array(
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array('Rest_Lxp_Class', 'get_available_courses'),
				'permission_callback' => '__return_true'
			)
		));

		register_rest_route('lms/v1', '/class/by-code', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array('Rest_Lxp_Class', 'get_by_code'),
				'permission_callback' => '__return_true'
			)
		));

		register_rest_route('lms/v1', '/update/class', array(
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array('Rest_Lxp_Class', 'update_class'),
				'permission_callback' => '__return_true',
				'args' => array(
					'user_email' => array(
					   'required' => true,
					   'type' => 'string',
					   'description' => 'user login name',  
					   'format' => 'email'
				   ),
				   'login_name' => array(
					'required' => true,
					'type' => 'string',
					'description' => 'user login name name'
				),
				'first_name' => array(
					'required' => true,
					'type' => 'string',
					'description' => 'user first name',
				),
				'last_name' => array(
					'required' => true,
					'type' => 'string',
					'description' => 'user last name',
				),
				'id' => array(
					'required' => true,
					'type' => 'integer',
					'description' => 'user account id',
				),
				   
			   )
			),
		));
		
	}

	public static function create($request) {		
		// ============= Class Post =================================
		$class_teacher_id = $request->get_param('class_teacher_id');
		$class_post_id = intval($request->get_param('class_post_id'));
		$class_name = trim($request->get_param('class_name'));
		$class_description = trim($request->get_param('class_description'));
		$edlink_class_sec_id = trim($request->get_param('edlink_class_sec_id') ?? '');
		
		$class_post_arg = array(
			'post_title'    => wp_strip_all_tags($class_name),
			'post_content'  => $class_description,
			'post_status'   => 'publish',
			'post_author'   => $class_teacher_id,
			'post_type'   => TL_CLASS_CPT
		);
		if (intval($class_post_id) > 0) {
			$class_post_arg['ID'] = "$class_post_id";
		}
		
		// Insert / Update
		$class_post_id = wp_insert_post($class_post_arg);

		if ( ! get_post_meta( $class_post_id, 'lxp_class_code', true ) ) {
			update_post_meta( $class_post_id, 'lxp_class_code', self::generate_class_code() );
		}

		// ===== Registration-code controls (zero-PII enrollment) ==============
		// Seat cap, expiry, revoke and alias mode gate the code-redemption
		// endpoint. See Rest_Lxp_Class_Redemption + docs/student-privacy-zone-a-context.md
		// Clamped, not absint()'d: seats are capped at TL_CLASS_MAX_SEATS and the
		// modal's max= attribute is a hint only. A class that never posts the
		// field simply has no meta, which reads back as the cap.
		if ( null !== $request->get_param('lxp_class_max_seats') ) {
			update_post_meta($class_post_id, 'lxp_class_max_seats', lxp_clamp_class_max_seats($request->get_param('lxp_class_max_seats')));
		}

		if ( null !== $request->get_param('lxp_class_code_expires') ) {
			$expires = trim( (string) $request->get_param('lxp_class_code_expires') );
			update_post_meta($class_post_id, 'lxp_class_code_expires', $expires ? sanitize_text_field($expires) : '');
		}

		// Checkboxes only post when checked, so treat an absent value as unchecked
		// whenever the modal declares it submitted the code-controls block.
		//
		// There is no alias-mode choice any more: students always type a nickname.
		// See Rest_Lxp_Class_Redemption::resolve_alias(). `lxp_class_alias_mode`
		// survives as dead meta on classes created before that change; nothing
		// reads it.
		if ( $request->get_param('lxp_class_code_controls') ) {
			$revoked = filter_var($request->get_param('lxp_class_code_revoked'), FILTER_VALIDATE_BOOLEAN);
			update_post_meta($class_post_id, 'lxp_class_code_revoked', $revoked ? '1' : '');
		}

		// ===== Grades ========================================================
		// The class modal no longer asks for a grade — a class inherits the set
		// the teacher chose at signup. `grade` (singular) is legacy and still
		// read by the class list and by Rest_Lxp_Class_Redemption when it seeds
		// a token student's grades, so it is kept in sync with the first entry.
		self::save_class_grades($class_post_id, $class_teacher_id, $request);

		if(get_post_meta($class_post_id, 'lxp_class_teacher_id', true)) {
			update_post_meta($class_post_id, 'lxp_class_teacher_id', $class_teacher_id);
		} else {
			add_post_meta($class_post_id, 'lxp_class_teacher_id', $class_teacher_id, true);
		}

		if(get_post_meta($class_post_id, 'edlink_class_sec_id', true)) {
			update_post_meta($class_post_id, 'edlink_class_sec_id', $edlink_class_sec_id);
		} else {
			add_post_meta($class_post_id, 'edlink_class_sec_id', $edlink_class_sec_id, true);
		}
		// Only rebuild membership when the caller actually submitted a roster.
		// The teacher class modal no longer has a student picker, so an
		// unconditional wipe here would evict every non-token student on the
		// class on every ordinary save (renaming it, editing its schedule...) —
		// and reconcile_class_student_meta() below would only bring the token
		// ones back, making the loss silent and partial.
		$student_ids = $request->get_param('student_ids');
		if (is_array($student_ids)) {
			delete_post_meta($class_post_id, 'lxp_student_ids');
			foreach ($student_ids as $student_id) {
				add_post_meta($class_post_id, 'lxp_student_ids', $student_id);
			}
		}

		// Token students who joined by code are not in the modal's checkbox set,
		// so re-add anyone lxp_class_members still counts as an active member.
		// Runs unconditionally: cheap, idempotent, and the safety net for any
		// other path that rewrites the meta wholesale.
		// See Rest_Lxp_Class_Redemption::reconcile_class_student_meta().
		if (class_exists('Rest_Lxp_Class_Redemption')) {
			Rest_Lxp_Class_Redemption::reconcile_class_student_meta($class_post_id);
		}

		delete_post_meta($class_post_id, 'lxp_class_course_ids');
		$course_ids = $request->get_param('course_ids');
		if (is_array($course_ids)) {
			foreach ($course_ids as $course_id) {
				add_post_meta($class_post_id, 'lxp_class_course_ids', absint($course_id));
			}
		}

		$schedule = array();
		if (is_array($request->get_param('schedule'))) {
			foreach ($request->get_param('schedule') as $day) {
				$start = $request->get_param($day . '-sd');
				$end = $request->get_param($day . '-ed');
				$schedule[$day] = array("start" => $start, "end" => $end);
			}
		}
		
		if(get_post_meta($class_post_id, 'schedule', true)) {
			update_post_meta($class_post_id, 'schedule', json_encode($schedule));
		} else {
			add_post_meta($class_post_id, 'schedule', json_encode($schedule), true);
		}

		if(get_post_meta($class_post_id, 'lxp_class_type', true)) {
			update_post_meta($class_post_id, 'lxp_class_type', $request->get_param('type'));
		} else {
			add_post_meta($class_post_id, 'lxp_class_type', $request->get_param('type'), true);
		}

        return wp_send_json_success("Class Saved!");
    }

	/**
	 * Persist a class's grade levels.
	 *
	 * Two keys are written on purpose:
	 *   - lxp_class_grades — repeating meta, the full set (new).
	 *   - grade            — single string, the first entry (legacy).
	 *
	 * The legacy key cannot simply be dropped: the class list column reads it,
	 * and Rest_Lxp_Class_Redemption seeds a token student's own `grades` meta
	 * from it when they redeem a seat.
	 *
	 * Precedence: an explicitly submitted grade/grades always wins (that is how
	 * admin-class-modal.php and any future picker stay in control). Only when
	 * nothing was submitted AND the class has no grades yet do we inherit the
	 * teacher's signup selection — so re-saving a class never silently
	 * overwrites a grade someone set by hand.
	 *
	 * @param int             $class_post_id    tl_class post ID.
	 * @param int             $class_teacher_id tl_teacher post ID (not a user ID).
	 * @param WP_REST_Request $request
	 */
	private static function save_class_grades($class_post_id, $class_teacher_id, $request) {
		$grades = array();

		// 1. Explicit multi-value submission.
		$submitted_grades = $request->get_param('grades');
		if (is_array($submitted_grades)) {
			$grades = $submitted_grades;
		}

		// 2. Explicit single-value submission (legacy modal field, '0' = none).
		if (empty($grades)) {
			$submitted_grade = $request->get_param('grade');
			if ($submitted_grade && '0' !== (string) $submitted_grade) {
				$grades = array($submitted_grade);
			}
		}

		// 3. Inherit from the teacher, but only for a class that has no grades at
		// all yet. Checking the legacy singular key too matters: classes created
		// before lxp_class_grades existed have only `grade`, and inheriting over
		// them would silently replace a value someone chose by hand.
		$has_grades = get_post_meta($class_post_id, 'lxp_class_grades')
			|| '' !== (string) get_post_meta($class_post_id, 'grade', true);

		if (empty($grades) && !$has_grades) {
			$teacher_grades = json_decode(get_post_meta(absint($class_teacher_id), 'grades', true));
			if (is_array($teacher_grades)) {
				$grades = $teacher_grades;
			}
		}

		// Nothing to write and nothing submitted — leave existing values alone.
		if (empty($grades) && null === $request->get_param('grade') && null === $request->get_param('grades')) {
			return;
		}

		$allowed = function_exists('lxp_get_grade_options') ? lxp_get_grade_options() : array();
		$grades  = array_values(array_unique(array_filter(array_map(function($grade) use ($allowed) {
			$grade = sanitize_text_field((string) $grade);
			return (empty($allowed) || in_array($grade, $allowed, true)) ? $grade : '';
		}, $grades))));

		delete_post_meta($class_post_id, 'lxp_class_grades');
		foreach ($grades as $grade) {
			add_post_meta($class_post_id, 'lxp_class_grades', $grade);
		}

		update_post_meta($class_post_id, 'grade', isset($grades[0]) ? $grades[0] : '');
	}

    public static function get_students($request) {
		$class_id = $request->get_param('class_id');
		$lxp_student_ids = get_post_meta($class_id, 'lxp_student_ids');
		// fetch student posts using WP_Query which includes ids form $lxp_student_ids
		$students = new WP_Query(array(
			'post_type' => TL_STUDENT_CPT,
			'post__in' => $lxp_student_ids,
			'posts_per_page' => -1,
			'orderby' => 'title',
			'order' => 'ASC'
		));
		$student_posts = $students->posts;
		$student_posts_result = array_map(function($post) { 
			$user_data = get_userdata(get_post_meta($post->ID, 'lxp_student_admin_id', true))->data;
			$user = ["ID" => $user_data->ID, "display_name" => $user_data->display_name, "user_email" => $user_data->user_email, "user_login" => $user_data->user_login];
			return array('post' => $post, 'user' => $user);
		} , $student_posts);

		return wp_send_json_success(array("students" => $student_posts_result));
	}

    public static function get_one($request) {
		$class_id = $request->get_param('class_id');
		$class = get_post($class_id);
		$class->grade = get_post_meta($class_id, 'grade', true);
		// Full grade set (new); `grade` above is the first entry, kept for legacy readers.
		$class->lxp_class_grades = get_post_meta($class_id, 'lxp_class_grades');
		$class->lxp_class_teacher_id = get_post_meta($class_id, 'lxp_class_teacher_id', true);
		$class->lxp_student_ids = get_post_meta($class_id, 'lxp_student_ids');
		$class->schedule = json_decode(get_post_meta($class_id, 'schedule', true));
		$class->lxp_class_type = get_post_meta($class_id, 'lxp_class_type', true);
		$class->edlink_class_sec_id = get_post_meta($class_id, 'edlink_class_sec_id', true);
		$class->lxp_class_course_ids = get_post_meta($class_id, 'lxp_class_course_ids');
		$class->lxp_class_code = get_post_meta($class_id, 'lxp_class_code', true);
		// Registration-code controls used by the code-redemption flow.
		$class->lxp_class_max_seats = lxp_get_class_max_seats($class_id);
		$class->lxp_class_code_expires = get_post_meta($class_id, 'lxp_class_code_expires', true);
		$class->lxp_class_code_revoked = (bool) get_post_meta($class_id, 'lxp_class_code_revoked', true);
		$members = new TL_Class_Member_Repository();
		$class->lxp_class_seats_taken = $members->count_active($class_id);
		return wp_send_json_success(array("class" => $class));
	}

    public static function update_class()
	{
        $user_data = array(
            'ID' => $_POST['id'],
            'user_login' => $_POST['login_name'],
            'first_name' => $_POST['first_name'],
            'last_name' =>$_POST['last_name'],
            'user_email' =>$_POST['user_email'],
            'display_name' =>$_POST['first_name'] . ' ' .$_POST['last_name'],
            'user_pass' =>$_POST['login_pass']
         );
         wp_send_json_success (wp_update_user($user_data));
	}

	public static function get_class_courses($request) {
		$class_id = absint($request->get_param('class_id'));
		$course_ids = get_post_meta($class_id, 'lxp_class_course_ids');
		if (empty($course_ids)) {
			return wp_send_json_success(array('courses' => array()));
		}
		$courses = get_posts(array(
			'post_type'      => TL_COURSE_CPT,
			'post__in'       => $course_ids,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		));
		$result = array_map(function($c) {
			return array('ID' => $c->ID, 'post_title' => $c->post_title, 'permalink' => get_permalink($c->ID));
		}, $courses);
		return wp_send_json_success(array('courses' => $result));
	}

	public static function save_class_courses($request) {
		$class_id  = absint($request->get_param('class_id'));
		$course_ids = $request->get_param('course_ids');
		$course_ids = is_array($course_ids) ? $course_ids : array();
		delete_post_meta($class_id, 'lxp_class_course_ids');
		foreach ($course_ids as $course_id) {
			add_post_meta($class_id, 'lxp_class_course_ids', absint($course_id));
		}
		return wp_send_json_success('Courses Saved!');
	}

	/**
	 * Courses offerable to a teacher, filtered by how that teacher registered.
	 *
	 * `teacher_id` is optional. Without it the response is every published
	 * course, exactly as before this filter existed.
	 */
	public static function get_available_courses($request) {
		$args = array(
			'post_type'      => TL_COURSE_CPT,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		$teacher_id = absint($request->get_param('teacher_id'));

		if ($teacher_id > 0 && taxonomy_exists(TL_COURSE_AUDIENCE_TAXONOMY)) {
			$register_type = lxp_get_teacher_register_type($teacher_id);
			$terms         = lxp_get_course_audience_terms();
			$mine          = $terms[$register_type]['slug'];
			$other         = (TL_REGISTER_TYPE_K12 === $register_type)
				? $terms[TL_REGISTER_TYPE_PD]['slug']
				: $terms[TL_REGISTER_TYPE_K12]['slug'];

			// Show the course if it carries MY audience term, or if it simply does
			// not carry the OTHER one. A course with neither term is therefore
			// offered to both teacher types, which is what keeps the pre-existing
			// untagged catalogue visible.
			//
			// NOT EXISTS would be wrong here: it tests for having no
			// course_category term at all, so a course tagged only with a subject
			// category such as Math would vanish.
			$args['tax_query'] = array(
				'relation' => 'OR',
				array(
					'taxonomy' => TL_COURSE_AUDIENCE_TAXONOMY,
					'field'    => 'slug',
					'terms'    => $mine,
				),
				array(
					'taxonomy' => TL_COURSE_AUDIENCE_TAXONOMY,
					'field'    => 'slug',
					'terms'    => $other,
					'operator' => 'NOT IN',
				),
			);
		}

		$courses = get_posts($args);
		$result = array_map(function($c) {
			return array('ID' => $c->ID, 'post_title' => $c->post_title);
		}, $courses);
		return wp_send_json_success(array('courses' => $result));
	}

	public static function get_by_code($request) {
		$code = sanitize_text_field( $request->get_param('class_code') );
		if ( empty( $code ) ) {
			return wp_send_json_error('class_code is required', 400);
		}
		$posts = get_posts(array(
			'post_type'      => TL_CLASS_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => 'lxp_class_code',
			'meta_value'     => $code,
		));
		if ( empty( $posts ) ) {
			return wp_send_json_error('Class not found', 404);
		}
		$class           = $posts[0];
		$class_id        = $class->ID;
		$result          = array(
			'ID'                  => $class_id,
			'post_title'          => $class->post_title,
			'lxp_class_code'      => $code,
			'lxp_class_course_ids' => get_post_meta($class_id, 'lxp_class_course_ids'),
		);
		return wp_send_json_success(array('class' => $result));
	}

	private static function generate_class_code() {
		$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		do {
			$code = '';
			for ( $i = 0; $i < 6; $i++ ) {
				$code .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
			}
			$existing = get_posts(array(
				'post_type'      => TL_CLASS_CPT,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => 'lxp_class_code',
				'meta_value'     => $code,
			));
		} while ( ! empty( $existing ) );
		return $code;
	}

}
