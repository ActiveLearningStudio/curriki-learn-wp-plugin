<?php

class Rest_Lxp_Student
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

		// /student/settings/update
		register_rest_route('lms/v1', '/student/settings/update', array(
			array(
				'methods' => WP_REST_Server::ALLMETHODS,
				'callback' => array('Rest_Lxp_Student', 'update_settings'),
				'permission_callback' => '__return_true'
			)
		));

		// /student/settings
		register_rest_route('lms/v1', '/student/settings', array(
			array(
				'methods' => WP_REST_Server::ALLMETHODS,
				'callback' => array('Rest_Lxp_Student', 'get_settings'),
				'permission_callback' => '__return_true'
			)
		));

		// /student/access-login — Student ID + class code based login
		register_rest_route('lms/v1', '/student/access-login', array(
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array('Rest_Lxp_Student', 'access_login'),
				'permission_callback' => '__return_true'
			)
		));

		register_rest_route('lms/v1', '/students/save', array(
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array('Rest_Lxp_Student', 'save_update'),
				'permission_callback' => '__return_true',
				'args' => array(
					'lxp_first_name' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'user first name',
						'validate_callback' => function($param, $request, $key) {
							return strlen( $param ) > 0;
						}
					),
					'lxp_last_name' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'user last name',
						'validate_callback' => function($param, $request, $key) {
							return strlen( $param ) > 0;
						}
					),
					'lxp_user_password' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'user login password',
						'validate_callback' => function($param, $request, $key) {
							$student_post_id = intval($request->get_param('student_post_id'));
							if ($student_post_id < 1) {
								return strlen( $param ) > 1;
							} else {
								return true;
							}
						}
					),
					'student_school_id' => array(
						'required' => true,
						'type' => 'integer',
						'description' => 'user school id',
						'validate_callback' => function($param, $request, $key) {
							return strlen( $param ) > 0;
						}
					),
					'teacher_id' => array(
						'required' => true,
						'type' => 'integer',
						'description' => 'teacher id',
						'validate_callback' => function($param, $request, $key) {
							return intval( $param ) > 0;
						}
					),
					'lxp_student_id' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'student id (used as the WP login)',
						'validate_callback' => function($param, $request, $key) {
							return strlen( trim($param) ) > 0;
						}
					),
				)
			),
		));

		register_rest_route('lms/v1', '/edlink/students/save', array(
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array('Rest_Lxp_Student', 'edlink_create'),
				'permission_callback' => '__return_true',
				'args' => array(
					'edlink_students' => array(
					   'required' => true,
					   'type' => 'array',
					   'description' => 'user email name',  
					   'format' => 'email',
					   'validate_callback' => function($param, $request, $key) {							
							return (!empty($param)) ? true : false;
						}
				  	),		
					'edlink_student_school_id' => array(
						'required' => true,
						'type' => 'integer',
						'description' => 'user school id',
						'validate_callback' => function($param, $request, $key) {
							return strlen( $param ) > 0;
						}
					),
					'teacher_id' => array(
						'required' => true,
						'type' => 'integer',
						'description' => 'teacher id',
						'validate_callback' => function($param, $request, $key) {
							return intval( $param ) > 0;
						}
					)
				)
			),
		));

		register_rest_route('lms/v1', '/students', array(
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array('Rest_Lxp_Student', 'get_one'),
				'permission_callback' => '__return_true'
			)
		));
		
		register_rest_route('lms/v1', '/student/assign_grade', array(
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array('Rest_Lxp_Student', 'assign_grade'),
				'permission_callback' => '__return_true'
			)
		));

		register_rest_route('lms/v1', '/students/taken_student', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array('Rest_Lxp_Student', 'get_unassigned_students'),
				'permission_callback' => '__return_true',
				'args' => array(
					'teacher_id' => array(
						'required' => true,
						'type' => 'integer',
						'description' => 'teacher id',
						'validate_callback' => function($param, $request, $key) {
							return intval( $param ) > 0;
						}
					)
				)
			),
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array('Rest_Lxp_Student', 'set_taken_students'),
				'permission_callback' => '__return_true',
				'args' => array(
					'teacher_id' => array(
						'required' => true,
						'type' => 'integer',
						'description' => 'teacher id',
						'validate_callback' => function($param, $request, $key) {
							return intval( $param ) > 0;
						}
					)
				)
			)
		));

		register_rest_route('lms/v1', '/students/list', array(
			array(
				'methods' => WP_REST_Server::ALLMETHODS,
				'callback' => array('Rest_Lxp_Student', 'get_list'),
				'permission_callback' => '__return_true'
			)
		));

		register_rest_route('lms/v1', '/students/import', array(
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array('Rest_Lxp_Student', 'import'),
				'permission_callback' => '__return_true'
			),
		));

		register_rest_route('lms/v1', '/store/student', array(
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array('Rest_Lxp_Student', 'store_student'),
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
				'user_pass' => array(
					'required' => true,
					'type' => 'string',
					'description' => 'user login password',
				),
				'school_id' => array(
					'required' => true,
					'type' => 'integer',
					'description' => 'user school id',
				), 
			   )
			),
		));

        register_rest_route('lms/v1', '/get/student', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array('Rest_Lxp_Student', 'get_student'),
				'permission_callback' => '__return_true',
				'args' => array(
	
				'id' => array(
					'required' => true,
					'type' => 'integer',
					'description' => 'user account id',
				),
				   
			   )
			),
		));
		
		register_rest_route('lms/v1', '/update/student', array(
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array('Rest_Lxp_Student', 'update_student'),
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

	public static function update_settings($request) {
		$entity_post_id = intval($request->get_param('entity_post_id'));
		$active = $request->get_param('active');
		update_post_meta($entity_post_id, 'settings_active', $active);
		return wp_send_json_success( "Settings Saved!" );
	}

	public static function get_settings($request) {
		$entity_post_id = intval($request->get_param('entity_post_id'));
		// get 'settings_active' post metadata and return it as 'active' attribute in response
		$active = get_post_meta($entity_post_id, 'settings_active', true);
		$active = $active && $active === 'false' ? false : true;
		return wp_send_json_success( ["active" => $active] );
	}

	// function to set grade add_post_meta for student with slide number
	public static function assign_grade($request) {
		$student_post_id = $request->get_param('student');
		$slide = $request->get_param('slide');
		$slide = intval($slide);
		$grade = $request->get_param('grade');
		$grade = intval($grade);
		$student_post_id = intval($student_post_id);
		$assignment = $request->get_param('assignment');
		$assignment_grade_key = "assignment_" . $assignment . "_slide_" . $slide . "_grade";
		
		if(get_post_meta($student_post_id, $assignment_grade_key, $grade, true)) {
			update_post_meta($student_post_id, $assignment_grade_key, $grade);
		} else {
			add_post_meta($student_post_id, $assignment_grade_key, $grade, true);
		}
		return wp_send_json_success(array('message' => 'Grade assigned successfully'));
	}

	// function to get grade for student with slid number
	public static function get_grade($request) {
		$student_post_id = $request->get_param('student');
		$assignment = $request->get_param('assignment');
		$assignment_grade_key = "assignment_" . $assignment . "_grade";
		$assignment_slide_key = "assignment_" . $assignment . "_slide";
		$grade = get_post_meta($student_post_id, $assignment_grade_key, true);
		$slid = get_post_meta($student_post_id, $assignment_slide_key, true);
		return wp_send_json_success(array('grade' => $grade, 'slid' => $slid));
	}

	public static function get_unassigned_students( $request ) {
		$teacher_id = intval( $request->get_param( 'teacher_id' ) );
		$school_id  = intval( $request->get_param( 'school_id' ) );

		// === STEP 1: Get ALL student IDs in school (efficient ID-only query) ===
		$student_ids = get_posts( [
			'post_type'   => TL_STUDENT_CPT,
			'post_status' => 'publish',
			'fields'      => 'ids',
			'posts_per_page' => -1,
			// 'meta_query'  => [
			// 	[
			// 		'key'     => 'lxp_student_school_id',
			// 		'value'   => $school_id,
			// 		'compare' => '=',
			// 	],
			// ],
			// 'no_found_rows' => true, // Critical performance boost
			// 'suppress_filters' => true,
		] );

		if ( empty( $student_ids ) ) {
			return wp_send_json_success( [ 'students' => [] ] );
		}

		// === STEP 2: Filter students NOT assigned to teacher (fixes original bug + optimizes) ===
		$unassigned_student_ids = array_filter( $student_ids, function( $student_id ) use ( $teacher_id ) {
			$assigned_teachers = get_post_meta( $student_id, 'lxp_teacher_id', true );
			
			// Handle single ID (int/string)
			if ( is_numeric( $assigned_teachers ) && (int) $assigned_teachers === $teacher_id ) {
				return false;
			}
			
			// Handle array of IDs (WordPress auto-unserializes)
			if ( is_array( $assigned_teachers ) ) {
				$assigned_teachers = array_map( 'intval', $assigned_teachers );
				return ! in_array( $teacher_id, $assigned_teachers, true );
			}
			
			// Handle edge case: serialized string not auto-unserialized (rare)
			if ( is_string( $assigned_teachers ) && strpos( $assigned_teachers, 'a:' ) === 0 ) {
				$unserialized = maybe_unserialize( $assigned_teachers );
				if ( is_array( $unserialized ) ) {
					$unserialized = array_map( 'intval', $unserialized );
					return ! in_array( $teacher_id, $unserialized, true );
				}
			}
			
			// No assignment found → include student
			return true;
		} );

		if ( empty( $unassigned_student_ids ) ) {
			return wp_send_json_success( [ 'students' => [] ] );
		}

		// === STEP 3: Bulk fetch user IDs linked to filtered students ===
		foreach ( $unassigned_student_ids as $student_id ) {
			$user_id = get_post_meta( $student_id, 'lxp_student_admin_id', true );
			
			// Verify user actually exists in wp_users table
			$user_exists = get_userdata( $user_id );
			
			if ( $user_exists ) {
				$valid_students[] = [
					'student_id' => $student_id,
					'user_id'    => $user_id,
					'user_data'  => $user_exists,
				];
			}
		}

		if ( empty( $valid_students ) ) {
			return wp_send_json_success( [ 'students' => [] ] );
		}

		// === STEP 4: Build final student array ===
		$students = array_map( function( $item ) {
			return [
				'id'         => (int) $item['user_id'],
				'first_name' => $item['user_data']->first_name ?: '',
				'last_name'  => $item['user_data']->last_name ?: '',
				'email'      => $item['user_data']->user_email,
			];
		}, $valid_students );

		return wp_send_json_success( [ 'students' => array_values( $students ) ] );
	}

	public static function set_taken_students( $request ) {
		// Step 1: Define inputs
		$student_ids = $request->get_param('student_ids');
		$teacher_id  = intval($request->get_param('teacher_id'));
		$post_type   = TL_STUDENT_CPT;

		if (empty($student_ids) || !is_array($student_ids)) {
			return wp_send_json_error(['message' => 'Invalid or missing student_ids'], 400);
		}

		if (!$teacher_id) {
			return wp_send_json_error(['message' => 'Invalid teacher_id'], 400);
		}

		// Step 2: Loop through each student user ID
		foreach ($student_ids as $student_user_id) {
			$student_user_id = intval($student_user_id);

			// Optional: Validate it's a real user
			if (!get_userdata($student_user_id)) {
				error_log("Invalid student user ID: $student_user_id");
				continue;
			}

			/**
			 * Step 3: Find posts where lxp_student_admin_id = $student_user_id
			 */
			$args = [
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => [
					[
						'key'     => 'lxp_student_admin_id',
						'value'   => $student_user_id,
						'type'    => 'NUMERIC',
						'compare' => '='
					]
				]
			];

			$matched_posts = get_posts($args);

			if (empty($matched_posts)) {
				error_log("No posts found with lxp_student_admin_id = $student_user_id");
				continue;
			}

			/**
			 * Step 4: For each matched post, update lxp_teacher_id as an array
			 */
			foreach ($matched_posts as $post) {
				$post_id = $post->ID;

				// Get current lxp_teacher_id value (could be string, int, array, or false)
				$previous_teachers = get_post_meta($post_id, 'lxp_teacher_id', true);

				// Normalize into an array of integers
				$teachers = [];

				if (is_numeric($previous_teachers)) {
					// Single number: convert to array
					$teachers = [(int)$previous_teachers];
				} elseif (is_array($previous_teachers)) {
					// Already an array: cast all to int
					$teachers = array_map('intval', $previous_teachers);
				}
				// Else: empty or invalid → leave as empty array

				// Remove duplicates
				$teachers = array_unique($teachers);

				// Add new teacher ID if not already present
				if (!in_array($teacher_id, $teachers, true)) {
					$teachers[] = $teacher_id;
				}

				// Save back as a single meta field (replaces any old format)
				$updated = update_post_meta($post_id, 'lxp_teacher_id', $teachers);

				if ($updated) {
					error_log("Updated lxp_teacher_id for post $post_id: " . print_r($teachers, true));
				} else {
					error_log("No change needed or failed to update post $post_id");
				}
			}
		}

		return wp_send_json_success(['message' => 'Students successfully updated with teacher ID.']);
	}

	/**
	 * Whether a school has opted into zero-PII token mode.
	 *
	 * In token mode the name-collecting student flows are closed: students are
	 * provisioned only through the class code-redemption endpoint, which stores
	 * no name, email or plaintext password.
	 *
	 * @see Rest_Lxp_Class_Redemption
	 * @param  int $school_post_id tl_school post ID.
	 * @return bool
	 */
	private static function is_token_mode_school($school_post_id) {
		$school_post_id = absint($school_post_id);
		if (!$school_post_id) {
			return false;
		}
		return (bool) get_post_meta($school_post_id, 'lxp_school_token_mode', true);
	}

	public static function save_update($request) {

		// ============= Student Post =================================
		$school_admin_id = $request->get_param('school_admin_id');
		$student_post_id = intval($request->get_param('student_post_id'));
		$student_description = trim($request->get_param('lxp_about'));

		// Token mode closes this door: it writes first/last name and a plaintext
		// password, which is exactly the PII the zero-knowledge design keeps off
		// the server. Students must come through the class code instead.
		if (self::is_token_mode_school($request->get_param('student_school_id'))) {
			return wp_send_json_error(
				'This school uses privacy-preserving enrollment. Add students by sharing the class code, or create seats from the class roster — names are never stored on the server.',
				403
			);
		}

		// For new students, the Student ID is used as the WP login — ensure it is
		// valid and unique up front (before any post/user is created).
		if ( $student_post_id < 1 ) {
			$check_login = strtolower( sanitize_user( trim($request->get_param('lxp_student_id')), true ) );
			if ( empty($check_login) ) {
				return wp_send_json_error('Please enter a valid Student ID.', 400);
			}
			if ( get_user_by('login', $check_login) ) {
				return wp_send_json_error('A student with this Student ID already exists.', 400);
			}
		}

		$school_post_arg = array(
			'post_title'    => wp_strip_all_tags(trim($request->get_param('lxp_last_name')) . ', ' . trim($request->get_param('lxp_first_name'))),
			'post_content'  => $student_description,
			'post_status'   => 'publish',
			'post_author'   => $school_admin_id,
			'post_type'   => TL_STUDENT_CPT
		);
		if (intval($student_post_id) > 0) {
			$school_post_arg['ID'] = "$student_post_id";
		}
		// Insert / Update
		$student_post_id = wp_insert_post($school_post_arg);
		if(get_post_meta($student_post_id, 'grades', json_encode($request->get_param('grades')))) {
			update_post_meta($student_post_id, 'grades', json_encode($request->get_param('grades')));
		} else {
			add_post_meta($student_post_id, 'grades', json_encode($request->get_param('grades')), true);
		}
		// ============= Profile Picture =============================
		/* $file = $request->get_file_params();
		$profilePicture = isset($file['profile_picture']) ? $file['profile_picture'] : null;
		if ($profilePicture['size'] > 0) {
			$mimes = array(
				'bmp'  => 'image/bmp',
				'gif'  => 'image/gif',
				'jpe'  => 'image/jpeg',
				'jpeg' => 'image/jpeg',
				'jpg'  => 'image/jpeg',
				'png'  => 'image/png',
				'tif'  => 'image/tiff',
				'tiff' => 'image/tiff'
			);
			
			$overrides = array(
				'mimes'     => $mimes,
				'test_form' => false
			);
			 
			$upload = wp_handle_upload( $file['profile_picture'], $overrides );
	
			if ( $upload && !isset( $upload['error'] ) ) {
				// File uploaded successfully. 
				$uploadedFileURL = $upload['url'];
				$uploadedFileName = basename($upload['url']);
				
				// Add Featured Image to Post
				$image_url        = $uploadedFileURL; // Define the image URL here
				$image_name       = $uploadedFileName;
				$upload_dir       = wp_upload_dir(); // Set upload folder
				$image_data       = file_get_contents($image_url); // Get image data
				$unique_file_name = wp_unique_filename( $upload_dir['path'], $image_name ); // Generate unique name
				$filename         = basename( $unique_file_name ); // Create image file name
	
				// Check folder permission and define file location
				if( wp_mkdir_p( $upload_dir['path'] ) ) {
					$file = $upload_dir['path'] . '/' . $filename;
				} else {
					$file = $upload_dir['basedir'] . '/' . $filename;
				}
				
				// Create the image  file on the server
				file_put_contents( $file, $image_data );
	
				// Check image file type
				$wp_filetype = wp_check_filetype( $filename, null );
				
				// Set attachment data
				$attachment = array(
					'post_mime_type' => $wp_filetype['type'],
					'post_title'     => sanitize_file_name( $filename ),
					'post_content'   => '',
					'post_status'    => 'inherit'
				);
				
				// Create the attachment
				$attach_id = wp_insert_attachment( $attachment, $file, $student_post_id );
	
				// Include image.php
				require_once(ABSPATH . 'wp-admin/includes/image.php');
	
				// Define attachment metadata
				$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
	
				// Assign metadata to attachment
				wp_update_attachment_metadata( $attach_id, $attach_data );
	
				// And finally assign featured image to post
				set_post_thumbnail( $student_post_id, $attach_id );	
			}	
		} */
		
		// ========== Student Admin ===========
		$student_admin_data = array(
			'first_name' => trim($request->get_param('lxp_first_name')),
			'last_name' => trim($request->get_param('lxp_last_name')),
			'display_name' =>  trim($request->get_param('lxp_last_name')) . ', ' . trim($request->get_param('lxp_first_name'))
		);
		
		if (trim($request->get_param('lxp_user_password'))) {
			$student_admin_data['user_pass'] = trim($request->get_param('lxp_user_password'));
		}
	
		// The Student ID drives the WP login. It is set once on create and is
		// read-only on edit, so existing logins are never renamed.
		$raw_student_id = trim($request->get_param('lxp_student_id'));
		$student_login  = strtolower( sanitize_user( $raw_student_id, true ) );

		if ( intval($request->get_param('student_post_id')) < 1 ) {
			// create a new student user — student_id becomes the login + email
			$_site_host_s   = wp_parse_url( home_url(), PHP_URL_HOST );
			$_email_domain_s = ( ! empty( $_site_host_s ) && $_site_host_s !== 'localhost' && ! filter_var( $_site_host_s, FILTER_VALIDATE_IP ) )
				? $_site_host_s : 'curriki.org';
			$student_admin_data['user_login']    = $student_login;
			$student_admin_data['user_email']    = $student_login . '@' . $_email_domain_s;
			$student_admin_data['user_nicename'] = $student_login;
			$student_admin_data['role']          = 'lxp_student';
		} else {
			// edit — keep the existing login untouched (Student ID is read-only on edit)
			$student_admin_data['ID'] = intval( get_post_meta($student_post_id, 'lxp_student_admin_id', true) );
		}

		$student_admin_id = wp_insert_user($student_admin_data);
		if ( is_wp_error($student_admin_id) ) {
			return wp_send_json_error('Could not save the student account.', 400);
		}
		
		if (trim($request->get_param('lxp_user_password'))) {
			wp_set_password( trim($request->get_param('lxp_user_password')), $student_admin_id );
		}
		
		if ($student_admin_id) {
			update_post_meta($student_post_id, 'lxp_student_admin_id', $student_admin_id);
			update_post_meta($student_post_id, 'lxp_student_school_id', trim($request->get_param('student_school_id')));
		}
		
		$lxp_teacher_id = $request->get_param('teacher_id');
		// Get current teacher IDs (WordPress auto-unserializes stored arrays)
		$current_teachers = get_post_meta($student_post_id, 'lxp_teacher_id', true);
		$teacher_ids = [];
		// Normalize existing value to clean integer array
		if (is_array($current_teachers)) {
			$teacher_ids = array_map('intval', array_filter($current_teachers));
		} elseif (!empty($current_teachers)) {
			// Handle legacy single-value storage (int/string)
			$single_id = intval($current_teachers);
			if ($single_id > 0) {
				$teacher_ids = [$single_id];
			}
		}
		// Append ONLY if valid and not duplicate
		if ($lxp_teacher_id > 0 && !in_array($lxp_teacher_id, $teacher_ids, true)) {
			$teacher_ids[] = $lxp_teacher_id;
		}
		// Save updated array (WordPress auto-serializes)
		update_post_meta($student_post_id, 'lxp_teacher_id', $teacher_ids);

		$student_id = $request->get_param('lxp_student_id');
		update_post_meta($student_post_id, 'student_id', ($student_id ? $student_id : 0));

		return wp_send_json_success("Student Saved!");
	}

	public static function edlink_create($request) {
		
		// ============= Student Post =================================
		$school_admin_id = $request->get_param('edlink_school_admin_id');
		$student_post_id = intval($request->get_param('edlink_student_post_id'));		
		$edlink_students = $request->get_param('edlink_students');

		foreach ($edlink_students as $user) {
			$user = explode("|", $user);
			$first_name = trim($user[0]);
			$last_name = trim($user[1]);
			$email = trim($user[2]);

			$student_name = $first_name.', '.$last_name;
			$student_description = trim($request->get_param('lxp_about')) ? trim($request->get_param('lxp_about')) : $student_name;

			$school_post_arg = array(
				'post_title'    => wp_strip_all_tags($student_name),
				'post_content'  => $student_description,
				'post_status'   => 'publish',
				'post_author'   => $school_admin_id,
				'post_type'   => TL_STUDENT_CPT
			);
			if (intval($student_post_id) > 0 && trim($request->get_param('lxp_about'))) {
				$school_post_arg['ID'] = "$student_post_id";
			}
			// Insert / Update
			$student_post_id = wp_insert_post($school_post_arg);
			if(get_post_meta($student_post_id, 'grades', json_encode($request->get_param('grades'))) && trim($request->get_param('lxp_about'))) {
				update_post_meta($student_post_id, 'grades', json_encode($request->get_param('grades')));
			} else {
				add_post_meta($student_post_id, 'grades', json_encode($request->get_param('grades')), true);
			}

			// ========== Student Admin ===========
			$student_admin_data = array(
				'user_login' => $email,
				'user_email' => $email,
				'first_name' => $first_name,
				'last_name' => $last_name,
				'display_name' =>  wp_strip_all_tags($student_name),
				'role' => 'lxp_student'
			);
			
			$student_admin_data['user_pass'] = $email;

			$lxp_student_admin_id = get_post_meta($student_post_id, 'lxp_student_admin_id', true);
			if ($lxp_student_admin_id && trim($request->get_param('lxp_about'))) {
				$student_admin_data["ID"] = $lxp_student_admin_id;
				global $wpdb;
				$result = $wpdb->update(
					$wpdb->users,
					array('user_login' => $email),
					array('ID' => $lxp_student_admin_id)
				);
			}

			$student_admin_id = wp_insert_user($student_admin_data);
			wp_set_password($email, $student_admin_id);

			if (!boolval($lxp_student_admin_id) && $student_admin_id) {
				if(get_post_meta($student_post_id, 'lxp_student_admin_id', $student_admin_id) && trim($request->get_param('lxp_about'))) {
					update_post_meta($student_post_id, 'lxp_student_admin_id', $student_admin_id);
				} else {
					add_post_meta($student_post_id, 'lxp_student_admin_id', $student_admin_id, true);
				}
				
				if(get_post_meta($student_post_id, 'lxp_student_school_id', true) && trim($request->get_param('lxp_about'))) {
					update_post_meta($student_post_id, 'lxp_student_school_id', trim($request->get_param('edlink_student_school_id')));
				} else {
					add_post_meta($student_post_id, 'lxp_student_school_id', trim($request->get_param('edlink_student_school_id')), true);
				}
			}
			
			$lxp_teacher_id = $request->get_param('teacher_id');
			update_post_meta($student_post_id, 'lxp_teacher_id', ($lxp_teacher_id ? [$lxp_teacher_id] : 0));
		}

		return wp_send_json_success("Student Saved!");
	}

	public static function get_list($request) {
		$students_ids = $request->get_param("students_ids");
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
		return wp_send_json_success($students);
	}

	public static function get_one($request) {
		$student_id = $request->get_param('lxp_student_id');
		$student = get_post($student_id);
		$student->grades = json_decode(get_post_meta($student_id, 'grades', true));
		$teacher_id = get_post_meta($student_id, 'lxp_teacher_id', true);
		$lxp_student_id = get_post_meta($student_id, 'student_id', true);
		$student->teacher_id = $teacher_id ? $teacher_id : 0;
		$student->student_id = $lxp_student_id ? $lxp_student_id : 0;
		$admin = get_userdata(get_post_meta($student_id, 'lxp_student_admin_id', true));
		$admin->data->first_name = get_user_meta($admin->data->ID, 'first_name', true);
		$admin->data->last_name = get_user_meta($admin->data->ID, 'last_name', true);		
		$adminStudent = array();
		$adminStudent["data"]["ID"] = $admin->data->ID;
		$adminStudent["data"]["user_login"] = $admin->data->user_login;
		$adminStudent["data"]["first_name"] = $admin->data->first_name;
		$adminStudent["data"]["last_name"] = $admin->data->last_name;
		$adminStudent["data"]["user_email"] = $admin->data->user_email;
		$adminStudent["ID"] = $adminStudent["data"]["ID"];
		return wp_send_json_success(array("student" => $student, "admin" => $adminStudent));
	}

	public static function import($request)
	{
		$school_admin_id = $request->get_param('school_admin_id');

		// Token mode closes this door — see save_update() for the rationale. The
		// legacy 4-column CSV carries first/last name and a district student_id,
		// all of which are PII that must not reach the server.
		if (self::is_token_mode_school($request->get_param('student_school_id'))) {
			return wp_send_json_error(
				'This school uses privacy-preserving enrollment. Create seats from the class roster instead — student names are never uploaded.',
				403
			);
		}

		$file = $request->get_file_params();
		$students_csv = isset($file['students']) ? $file['students'] : null;

		// Accept by extension and a small allow-list of CSV MIME variants. Browsers
		// (notably Excel-saved files) report text/csv, application/vnd.ms-excel or
		// application/octet-stream, so a strict text/csv check rejects valid files.
		$allowed_csv_types = array('text/csv', 'application/vnd.ms-excel', 'application/octet-stream', 'text/plain');
		$has_csv_ext = $students_csv && isset($students_csv['name'])
			&& strtolower(pathinfo($students_csv['name'], PATHINFO_EXTENSION)) === 'csv';
		$has_csv_type = $students_csv && in_array($students_csv['type'], $allowed_csv_types, true);

		if ($students_csv && $students_csv['size'] > 0 && ($has_csv_ext || $has_csv_type)) {

			$overrides = array('test_form' => false, 'mimes' => array('csv' => 'text/csv'));
			$upload = wp_handle_upload( $students_csv, $overrides );
			if ( $upload && !isset( $upload['error'] ) ) {
				// Read the local filesystem path, not the public URL: fopen() on a URL
				// depends on allow_url_fopen and the upload being HTTP-reachable, which
				// fails on localhost/XAMPP and locked-down hosts.
				$csv_file_path = $upload["file"];

				$imported   = 0;
				$skipped    = 0;
				$duplicates = 0;

				$configured_password = get_option( 'tl_student_default_password', '' );

				$_site_host   = wp_parse_url( home_url(), PHP_URL_HOST );
				$email_domain = ( ! empty( $_site_host ) && $_site_host !== 'localhost' && ! filter_var( $_site_host, FILTER_VALIDATE_IP ) )
					? $_site_host
					: 'curriki.org';

				if (($handle = fopen($csv_file_path, "r")) !== false) {
					while (($row = fgetcsv($handle, 1000, ",")) !== false) {
						// Skip an optional header row.
						if (isset($row[0]) && strtolower(trim($row[0])) === 'first_name') {
							continue;
						}

						// Need all 4 columns: first_name, last_name, grade, student_id.
						// student_id is used as the WP username and email local-part.
						if (count($row) >= 4) {
							$first_name = trim($row[0]);
							$last_name = trim($row[1]);
							$user_display_name = $last_name . ', ' . $first_name;
							$_grade_ordinals = ['1'=>'1st','2'=>'2nd','3'=>'3rd','4'=>'4th','5'=>'5th',
								                    '6'=>'6th','7'=>'7th','8'=>'8th','9'=>'9th'];
							$grades = [];
							foreach (explode('-', trim($row[2])) as $_g) {
								$_g       = trim($_g);
								$grades[] = isset($_grade_ordinals[$_g]) ? $_grade_ordinals[$_g] : $_g;
							}
							$student_id = trim($row[3]);
							$username = strtolower( sanitize_user( $student_id, true ) );
							$email = $username . '@' . $email_domain;
							$password = ! empty( $configured_password )
								? $configured_password
								: wp_generate_password( 12, false );

							if (!get_user_by('email', $email)) {
								$student_post_arg = array(
									'post_title'    => wp_strip_all_tags($user_display_name),
									'post_content'  => '',
									'post_status'   => 'publish',
									'post_author'   => $school_admin_id,
									'post_type'   => TL_STUDENT_CPT
								);
								// Insert Student post
								$student_post_id = wp_insert_post($student_post_arg);

								// ========== Student Admin ===========
								$student_admin_data = array(
									'user_login' => $username,
									'user_email' => $email,
									'first_name' => $first_name,
									'last_name' => $last_name,
									'display_name' => $user_display_name,
									'user_pass' => $password,
									'role' => 'lxp_student'
								);
								$student_admin_id  = wp_insert_user($student_admin_data);
								if ($student_admin_id && !is_wp_error($student_admin_id)) {
									wp_set_password( $password, $student_admin_id );
									add_post_meta($student_post_id, 'lxp_student_admin_id', $student_admin_id, true);
									add_post_meta($student_post_id, 'lxp_student_school_id', trim($request->get_param('student_school_id')), true);
								}

								$lxp_teacher_id = $request->get_param('teacher_id');
								update_post_meta($student_post_id, 'lxp_teacher_id', ($lxp_teacher_id ? [$lxp_teacher_id] : 0));
								update_post_meta($student_post_id, 'grades', json_encode($grades));
								update_post_meta($student_post_id, 'student_id', ($student_id ? $student_id : 0));
								add_post_meta($student_post_id, 'lxp_student_password', $password, true);
								$imported++;
							} else {
								$duplicates++;
							}
						} else {
							$skipped++;
						}
					}
					fclose($handle);
				}
				return wp_send_json_success(array(
					"message"    => "Students imported successfully.",
					"imported"   => $imported,
					"skipped"    => $skipped,
					"duplicates" => $duplicates,
				));
			} else {
				return  wp_send_json_error("File could not uploaded.", 400);
			}

		} else {
			return  wp_send_json_error("Invalid file . Upload valid CSV file.", 400);
		}

		return wp_send_json_success("");
	}

	/**
	 * Student ID + class code based login (kiosk style).
	 *
	 * Looks the student up by their `student_id` meta (works for students imported
	 * before student_id became the username too), verifies they belong to the class
	 * identified by the URL's class code, then signs them in with the password stored
	 * on the student CPT post.
	 */
	public static function access_login($request)
	{
		$student_id = sanitize_text_field( $request->get_param('student_id') );
		$class_code = sanitize_text_field( $request->get_param('class_code') );

		if ( empty( $student_id ) ) {
			return wp_send_json_error( 'Please enter your Student ID.', 400 );
		}
		if ( empty( $class_code ) ) {
			return wp_send_json_error( 'Invalid or expired class code.', 400 );
		}

		// 1. Find the student CPT post by its student_id meta.
		$student_query = new WP_Query( array(
			'post_type'      => TL_STUDENT_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array( 'key' => 'student_id', 'value' => $student_id, 'compare' => '=' ),
			),
		) );
		$student_posts = $student_query->get_posts();
		if ( empty( $student_posts ) ) {
			return wp_send_json_error( 'Student ID not found.', 404 );
		}
		$student_post = $student_posts[0];

		// 2. Resolve the linked WP user.
		$wp_user_id = get_post_meta( $student_post->ID, 'lxp_student_admin_id', true );
		$user       = $wp_user_id ? get_user_by( 'id', $wp_user_id ) : false;
		if ( ! $user ) {
			return wp_send_json_error( 'Student account not found.', 404 );
		}

		// 3. Validate the class code.
		$class_posts = get_posts( array(
			'post_type'      => TL_CLASS_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => 'lxp_class_code',
			'meta_value'     => $class_code,
		) );
		if ( empty( $class_posts ) ) {
			return wp_send_json_error( 'Invalid or expired class code.', 404 );
		}
		$class = $class_posts[0];

		// 4. Confirm the student belongs to this class.
		$class_student_ids = get_post_meta( $class->ID, 'lxp_student_ids' );
		$class_student_ids = is_array( $class_student_ids ) ? array_map( 'intval', $class_student_ids ) : array();
		if ( ! in_array( (int) $student_post->ID, $class_student_ids, true ) ) {
			return wp_send_json_error( 'You are not enrolled in this class.', 403 );
		}

		// 5. Fetch the stored password and sign in.
		$password = get_post_meta( $student_post->ID, 'lxp_student_password', true );
		if ( empty( $password ) ) {
			return wp_send_json_error( 'Login is not available for this account.', 403 );
		}

		$signon = wp_signon( array(
			'user_login'    => $user->user_login,
			'user_password' => $password,
			'remember'      => true,
		), is_ssl() );

		if ( is_wp_error( $signon ) ) {
			return wp_send_json_error( 'Login failed. Please contact your teacher.', 401 );
		}

		wp_set_current_user( $signon->ID );

		return wp_send_json_success( array( 'message' => 'Logged in' ) );
	}

	public static function store_student()
	{
        $user_data = array(
            'user_login' => $_POST['login_name'],
            'first_name' => $_POST['first_name'],
            'last_name' =>$_POST['last_name'],
            'user_email' =>$_POST['user_email'],
            'display_name' =>$_POST['first_name'] . ' ' .$_POST['last_name'],
            'user_pass' =>$_POST['login_pass'],
            'role' => 'student'
         );
         $user_id  = wp_insert_user($user_data);
		 if(isset( $user_id->errors)){
			return  wp_send_json_error($user_id->errors, 400);
		 }
		 return wp_send_json_success(update_user_meta($user_id , 'lxp_school_id', $_POST['school_id']));
	}

    public static function get_student()
	{
        $users =  get_user_by('id', $_GET['id']
         );
         return wp_send_json_success($users);
	}

    public static function update_student()
	{
        $user_data = array(
            'ID' => $_POST['id'],
            'user_login' => $_POST['login_name'],
            'first_name' => $_POST['first_name'],
            'last_name' =>$_POST['last_name'],
            'user_email' =>$_POST['user_email'],
            'display_name' =>$_POST['first_name'] . ' ' .$_POST['last_name'],
         );
		return wp_send_json_success(wp_update_user($user_data));
	}
}

?>