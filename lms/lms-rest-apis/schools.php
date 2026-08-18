<?php

class Rest_Lxp_School
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

		// /school/settings/update
		register_rest_route('lms/v1', '/school/settings/update', array(
			array(
				'methods' => WP_REST_Server::ALLMETHODS,
				'callback' => array('Rest_Lxp_School', 'update_settings'),
				'permission_callback' => '__return_true',
			)
		));

		// /school/settings
		register_rest_route('lms/v1', '/school/settings', array(
			array(
				'methods' => WP_REST_Server::ALLMETHODS,
				'callback' => array('Rest_Lxp_School', 'get_settings'),
				'permission_callback' => '__return_true',
			)
		));

        register_rest_route('lms/v1', '/schools', array(
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array('Rest_Lxp_School', 'get_one'),
				'permission_callback' => '__return_true'
			)
		));

        register_rest_route('lms/v1', '/shools/save', array(
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array('Rest_Lxp_School', 'create'),
				'permission_callback' => '__return_true',
                'args' => array(
                    'school_name' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'School name',
						'validate_callback' => function($param, $request, $key) {
							return strlen( $param ) > 1;
						}
					),
                    'user_email' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'user login name',  
						'format' => 'email',
						'validate_callback' => function($param, $request, $key) {
							if (!trim($request->get_param('user_email'))) {
								return false;
							}

							$user_by_email = get_user_by("email", trim($request->get_param('user_email')));
							$user_by_login = get_user_by("login", trim($request->get_param('user_email')));
							if ( $user_by_email && intval($request->get_param('school_post_id')) > 0 && $user_by_email->data->user_email !== trim($request->get_param('user_email_default')) ) {
								return false;
							} else if ($request->get_param('school_post_id') == 0) {
								return ( !($user_by_email || $user_by_login) ? true : false );
							} if ( trim($request->get_param('user_email')) == '' ) {
								return false;
							} else {
								return true;
							}
						}
					),
					'first_name' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'user first name',
						'validate_callback' => function($param, $request, $key) {
							return strlen( $param ) > 0;
						}
					),
					'last_name' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'user last name',
						'validate_callback' => function($param, $request, $key) {
							return strlen( $param ) > 0;
						}
					),
					'user_password' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'user login password',
						'validate_callback' => function($param, $request, $key) {
							$school_post_id = intval($request->get_param('school_post_id'));
							if ($school_post_id < 1) {
								return strlen( $param ) > 1;
							} else {
								return true;
							}
						}
					),
					'district_admin_id' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'user id',
						'validate_callback' => function($param, $request, $key) {
							return strlen( $param ) > 0;
						}
					),
					'school_post_id' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'post id',
						'validate_callback' => function($param, $request, $key) {
							return strlen( $param ) > 0;
						}
					)
                )
            )
        ));

		register_rest_route('lms/v1', '/edlink/schools/save', array(
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array('Rest_Lxp_School', 'edlink_create'),
				'permission_callback' => '__return_true',
                'args' => array(
                    'edlink_school_name' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'School name',
						'validate_callback' => function($param, $request, $key) {
							return strlen( $param ) > 1;
						}
					),
                    'edlink_user_email' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'user login name',  
						'format' => 'email',
						'validate_callback' => function($param, $request, $key) {
							if (!trim($request->get_param('edlink_user_email'))) {
								return false;
							}

							$user_by_email = get_user_by("email", trim($request->get_param('edlink_user_email')));
							$user_by_login = get_user_by("login", trim($request->get_param('edlink_user_email')));
							if ( $user_by_email && intval($request->get_param('edlink_school_post_id')) > 0 && $user_by_email->data->user_email !== trim($request->get_param('edlink_user_email')) ) {
								return false;
							} else if ($request->get_param('edlink_school_post_id') == 0) {
								return ( !($user_by_email || $user_by_login) ? true : false );
							} if ( trim($request->get_param('edlink_user_email')) == '' ) {
								return false;
							} else {
								return true;
							}
						}
					),
					'edlink_first_name' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'user first name',
						'validate_callback' => function($param, $request, $key) {
							return strlen( $param ) > 0;
						}
					),
					'edlink_last_name' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'user last name',
						'validate_callback' => function($param, $request, $key) {
							return strlen( $param ) > 0;
						}
					),					
					'edlink_school_post_id' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'post id',
						'validate_callback' => function($param, $request, $key) {
							return strlen( $param ) > 0;
						}
					)
                )
            )
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

    public static function create($request) {		

		// ============= School Post =================================
		$district_admin_id = $request->get_param('district_admin_id');
		$school_post_id = intval($request->get_param('school_post_id'));
		$school_name = trim($request->get_param('school_name'));
		$school_description = $request->get_param('school_about') ? trim($request->get_param('school_about')) : '';
		
		$shool_post_arg = array(
			'post_title'    => wp_strip_all_tags($school_name),
			'post_content'  => $school_description,
			'post_status'   => 'publish',
			'post_author'   => $district_admin_id,
			'post_type'   => "tl_school"
		);
		if (intval($school_post_id) > 0) {
			$shool_post_arg['ID'] = "$school_post_id";
		}
		// Insert / Update
		$school_post_id = wp_insert_post($shool_post_arg);
		
		// ============= Profile Picture =============================
		$file = $request->get_file_params();
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
				$attach_id = wp_insert_attachment( $attachment, $file, $school_post_id );
	
				// Include image.php
				require_once(ABSPATH . 'wp-admin/includes/image.php');
	
				// Define attachment metadata
				$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
	
				// Assign metadata to attachment
				wp_update_attachment_metadata( $attach_id, $attach_data );
	
				// And finally assign featured image to post
				set_post_thumbnail( $school_post_id, $attach_id );	
			}	
		}
		
		// ========== School Admin ===========
		$school_admin_data = array(
			'user_login' => trim($request->get_param('user_email')),
			'user_email' => trim($request->get_param('user_email')),
			'first_name' => trim($request->get_param('first_name')),
			'last_name' => trim($request->get_param('last_name')),
			'display_name' => trim($request->get_param('first_name')) . ' ' . trim($request->get_param('last_name')),
			'role' => 'lxp_school_admin'
		);

		if (trim($request->get_param('user_password'))) {
			$school_admin_data['user_pass'] = trim($request->get_param('user_password'));
		}

		$lxp_school_admin_id = get_post_meta($school_post_id, 'lxp_school_admin_id', true);
		if ($lxp_school_admin_id) {
			$school_admin_data["ID"] = $lxp_school_admin_id;
		}
		$school_admin_id  = wp_insert_user($school_admin_data);

		if (trim($request->get_param('user_password'))) {
			wp_set_password( trim($request->get_param('user_password')), $school_admin_id );
		}

		if (!boolval($lxp_school_admin_id) && $school_admin_id) {
			if(get_post_meta($school_post_id, 'lxp_school_admin_id', $school_admin_id)) {
				update_post_meta($school_post_id, 'lxp_school_admin_id', $school_admin_id);
			} else {
				add_post_meta($school_post_id, 'lxp_school_admin_id', $school_admin_id, true);
			}
			
			if(get_post_meta($school_post_id, 'lxp_school_district_id', trim($request->get_param('school_district_id')))) {
				update_post_meta($school_post_id, 'lxp_school_district_id', trim($request->get_param('school_district_id')));
			} else {
				add_post_meta($school_post_id, 'lxp_school_district_id', trim($request->get_param('school_district_id')), true);
			}
		}

		// ===== Privacy: zero-PII token mode =================================
		// When on, the name-collecting student flows (CSV import, Manage
		// Students) are refused and students join only via the class code.
		// See Rest_Lxp_Class_Redemption + docs/student-privacy-zone-a-context.md
		if ( $request->get_param('lxp_school_privacy_controls') ) {
			$token_mode = filter_var($request->get_param('lxp_school_token_mode'), FILTER_VALIDATE_BOOLEAN);
			update_post_meta($school_post_id, 'lxp_school_token_mode', $token_mode ? '1' : '');
		}

        return wp_send_json_success("School Saved!");
    }

	public static function edlink_create($request) {
		// ============= School Post =================================
		$district_admin_id = $request->get_param('edlink_district_admin_id');
		$school_post_id = intval($request->get_param('edlink_school_post_id'));
		$school_name = trim($request->get_param('edlink_school_name'));
		$school_description = $request->get_param('edlink_school_about') ? trim($request->get_param('edlink_school_about')) : '';
		$edlink_school_id = $request->get_param('edlink_school_id');

		$shool_post_arg = array(
			'post_title'    => wp_strip_all_tags($school_name),
			'post_content'  => $school_description,
			'post_status'   => 'publish',
			'post_author'   => $district_admin_id,
			'post_type'   => "tl_school"
		);
		if (intval($school_post_id) > 0) {
			$shool_post_arg['ID'] = "$school_post_id";
		}
		// Insert / Update
		$school_post_id = wp_insert_post($shool_post_arg);

		if(get_post_meta($school_post_id, 'lxp_edlink_school_id', $edlink_school_id)) {
			update_post_meta($school_post_id, 'lxp_edlink_school_id', $edlink_school_id);
		} else if ($edlink_school_id != '') {
			add_post_meta($school_post_id, 'lxp_edlink_school_id', $edlink_school_id, true);
		}
		
		// ========== School Admin ===========
		$school_admin_data = array(
			'user_login' => trim($request->get_param('edlink_user_email')),
			'user_email' => trim($request->get_param('edlink_user_email')),
			'first_name' => trim($request->get_param('edlink_first_name')),
			'last_name' => trim($request->get_param('edlink_last_name')),
			'display_name' => trim($request->get_param('edlink_first_name')) . ' ' . trim($request->get_param('edlink_last_name')),
			'role' => 'lxp_school_admin'
		);

		$school_admin_data['user_pass'] = trim($request->get_param('edlink_user_email'));

		$lxp_school_admin_id = get_post_meta($school_post_id, 'lxp_school_admin_id', true);
		if ($lxp_school_admin_id) {
			$school_admin_data["ID"] = $lxp_school_admin_id;
			global $wpdb;
			$result = $wpdb->update(
				$wpdb->users,
				array('user_login' => trim($request->get_param('edlink_user_email'))),
				array('ID' => $lxp_school_admin_id)
			);
		}
		$school_admin_id  = wp_insert_user($school_admin_data);

		wp_set_password( trim($request->get_param('edlink_user_email')), $school_admin_id );

		if (!boolval($lxp_school_admin_id) && $school_admin_id) {
			if(get_post_meta($school_post_id, 'lxp_school_admin_id', $school_admin_id)) {
				update_post_meta($school_post_id, 'lxp_school_admin_id', $school_admin_id);
			} else {
				add_post_meta($school_post_id, 'lxp_school_admin_id', $school_admin_id, true);
			}
			
			if(get_post_meta($school_post_id, 'lxp_school_district_id', trim($request->get_param('edlink_school_district_id')))) {
				update_post_meta($school_post_id, 'lxp_school_district_id', trim($request->get_param('edlink_school_district_id')));
			} else {
				add_post_meta($school_post_id, 'lxp_school_district_id', trim($request->get_param('edlink_school_district_id')), true);
			}
		}

        return wp_send_json_success("School Saved!");
    }

	public static function get_one($request) {
		$school_id = $request->get_param('school_id');
		$school = get_post($school_id);
		$admin = get_userdata(get_post_meta($school_id, 'lxp_school_admin_id', true));
		$admin->data->first_name = get_user_meta($admin->ID, 'first_name', true);
		$admin->data->last_name = get_user_meta($admin->ID, 'last_name', true);
		// Zero-PII enrollment toggle — gates the name-collecting student flows.
		$school->lxp_school_token_mode = (bool) get_post_meta($school_id, 'lxp_school_token_mode', true);
		return wp_send_json_success(array("school" => $school, "admin" => $admin));
	}
}
?>