<?php

class Rest_Lxp_District
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

		// /district/settings/update
		register_rest_route('lms/v1', '/district/settings/update', array(
			array(
				'methods' => WP_REST_Server::ALLMETHODS,
				'callback' => array('Rest_Lxp_District', 'update_settings'),
				'permission_callback' => '__return_true',
			)
		));

		// /district/settings
		register_rest_route('lms/v1', '/district/settings', array(
			array(
				'methods' => WP_REST_Server::ALLMETHODS,
				'callback' => array('Rest_Lxp_District', 'get_settings'),
				'permission_callback' => '__return_true',
			)
		));

        register_rest_route('lms/v1', '/districts', array(
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array('Rest_Lxp_District', 'get_one'),
				'permission_callback' => '__return_true'
			)
		));

        register_rest_route('lms/v1', '/district/save', array(
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array('Rest_Lxp_District', 'create'),
				'permission_callback' => '__return_true',
                'args' => array(
                    'district_name' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'District name',
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
							if ( $user_by_email && intval($request->get_param('district_post_id')) > 0 && $user_by_email->data->user_email !== trim($request->get_param('user_email_default')) ) {
								return false;
							} else if ($request->get_param('district_post_id') == 0) {
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
							$district_post_id = intval($request->get_param('district_post_id'));
							if ($district_post_id < 1) {
								return strlen( $param ) > 1;
							} else {
								return true;
							}
						}
					),
					'district_post_id' => array(
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

		register_rest_route('lms/v1', '/edlink/district/save', array(
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array('Rest_Lxp_District', 'edlink_create'),
				'permission_callback' => '__return_true',
                'args' => array(
                    'edlink_district_name' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'District name',
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
							if ( $user_by_email && intval($request->get_param('edlink_district_post_id')) > 0 && $user_by_email->data->user_email !== trim($request->get_param('edlink_user_email')) ) {
								return false;
							} else if ($request->get_param('edlink_district_post_id') == 0) {
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
					'edlink_district_post_id' => array(
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
		// ============= District Post =================================
		$site_admin_id = $request->get_param('site_admin_id');
		$district_post_id = intval($request->get_param('district_post_id'));
		$district_name = trim($request->get_param('district_name'));
		$district_description = $request->get_param('district_about') ? trim($request->get_param('district_about')) : '';
		
		$shool_post_arg = array(
			'post_title'    => wp_strip_all_tags($district_name),
			'post_content'  => $district_description,
			'post_status'   => 'publish',
			'post_author'   => $site_admin_id,
			'post_type'   => "tl_district"
		);
		if (intval($district_post_id) > 0) {
			$shool_post_arg['ID'] = "$district_post_id";
		}
		// Insert / Update
		$district_post_id = wp_insert_post($shool_post_arg);
		
		// ============= Profile Picture =============================
		/*
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
				$attach_id = wp_insert_attachment( $attachment, $file, $district_post_id );
	
				// Include image.php
				require_once(ABSPATH . 'wp-admin/includes/image.php');
	
				// Define attachment metadata
				$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
	
				// Assign metadata to attachment
				wp_update_attachment_metadata( $attach_id, $attach_data );
	
				// And finally assign featured image to post
				set_post_thumbnail( $district_post_id, $attach_id );	
			}	
		}
		*/

		// ========== District Admin ===========
		$district_admin_data = array(
			'user_login' => trim($request->get_param('user_email')),
			'user_email' => trim($request->get_param('user_email')),
			'first_name' => trim($request->get_param('first_name')),
			'last_name' => trim($request->get_param('last_name')),
			'display_name' => trim($request->get_param('first_name')) . ' ' . trim($request->get_param('last_name')),
			'role' => 'lxp_client_admin'
		);

		if (trim($request->get_param('user_password'))) {
			$district_admin_data['user_pass'] = trim($request->get_param('user_password'));
		}

		$lxp_district_admin_id = get_post_meta($district_post_id, 'lxp_district_admin', true);
		if ($lxp_district_admin_id) {
			$district_admin_data["ID"] = $lxp_district_admin_id;
		}
		$district_admin_id  = wp_insert_user($district_admin_data);

		if (trim($request->get_param('user_password'))) {
			wp_set_password( trim($request->get_param('user_password')), $district_admin_id );
		}

		if (!boolval($lxp_district_admin_id) && $district_admin_id) {
			if(get_post_meta($district_post_id, 'lxp_district_admin', $district_admin_id)) {
				update_post_meta($district_post_id, 'lxp_district_admin', $district_admin_id);
			} else {
				add_post_meta($district_post_id, 'lxp_district_admin', $district_admin_id, true);
			}
		}

		// ===== Roster vault escrow (Zone B recovery) ========================
		// An RSA public key the district publishes so teachers' browsers can
		// wrap a recovery copy of each class DEK. The matching PRIVATE key must
		// live in the district's KMS or offline safe — never here. Without it,
		// a forgotten passphrase means that class's names are gone for good.
		if ( null !== $request->get_param('lxp_district_escrow_pubkey') ) {
			$pem = trim( (string) $request->get_param('lxp_district_escrow_pubkey') );
			if ( '' === $pem ) {
				delete_post_meta($district_post_id, 'lxp_district_escrow_pubkey');
			} else if ( self::is_valid_public_key($pem) ) {
				update_post_meta($district_post_id, 'lxp_district_escrow_pubkey', $pem);
			} else {
				return wp_send_json_error('That does not look like a valid RSA public key (expected a PEM "PUBLIC KEY" block).', 400);
			}
		}

        return wp_send_json_success("District Saved!");
    }

	/**
	 * Validate an escrow public key before storing it.
	 *
	 * A malformed key would fail silently in the browser and leave classes with
	 * no recovery copy, so it is rejected at the door instead. Only the PUBLIC
	 * half is ever accepted — a pasted private key is refused outright.
	 *
	 * @param  string $pem
	 * @return bool
	 */
	private static function is_valid_public_key($pem) {
		if ( false !== strpos($pem, 'PRIVATE KEY') ) {
			return false;
		}
		if ( ! preg_match('/-----BEGIN (RSA )?PUBLIC KEY-----/', $pem) ) {
			return false;
		}
		if ( ! function_exists('openssl_pkey_get_public') ) {
			// No OpenSSL to verify with — the PEM header check is all we have.
			return true;
		}

		$key = openssl_pkey_get_public($pem);
		if ( ! $key ) {
			return false;
		}

		$details = openssl_pkey_get_details($key);

		return isset($details['type']) && OPENSSL_KEYTYPE_RSA === $details['type'];
	}

	public static function edlink_create($request) {
		// ============= District Post =================================
		$site_admin_id = $request->get_param('edlink_site_admin_id');
		$district_post_id = intval($request->get_param('edlink_district_post_id'));
		$district_name = trim($request->get_param('edlink_district_name'));
		$district_description = $request->get_param('edlink_district_about') ? trim($request->get_param('edlink_district_about')) : '';
		$district_type = $request->get_param('district_type');
		$edlink_provider_id = $request->get_param('edlink_provider_id');
		$edlink_provider_name = $request->get_param('edlink_provider_name');
		$edlink_provider_access_token = $request->get_param('edlink_provider_access_token');
		$edlink_district_id = $request->get_param('edlink_district_id');
		$post_name = $edlink_provider_name . '-' . $district_name;

		$shool_post_arg = array(
			'post_title'    => wp_strip_all_tags($district_name),
			'post_name'    => wp_strip_all_tags(strtolower($post_name)),
			'post_content'  => $district_description,
			'post_status'   => 'publish',
			'post_author'   => $site_admin_id,
			'post_type'   => "tl_district"
		);
		if (intval($district_post_id) > 0) {
			$shool_post_arg['ID'] = "$district_post_id";
		}
		// Insert / Update
		$district_post_id = wp_insert_post($shool_post_arg);

		// Save the Edlink meta data
		if(get_post_meta($district_post_id, 'lxp_district_type', $district_type)) {
			update_post_meta($district_post_id, 'lxp_district_type', $district_type);
		} else {
			add_post_meta($district_post_id, 'lxp_district_type', $district_type, true);
		}

		if(get_post_meta($district_post_id, 'lxp_edlink_provider_id', $edlink_provider_id)) {
			update_post_meta($district_post_id, 'lxp_edlink_provider_id', $edlink_provider_id);
		} else if ($edlink_provider_id != '') {
			add_post_meta($district_post_id, 'lxp_edlink_provider_id', $edlink_provider_id, true);
		}

		if(get_post_meta($district_post_id, 'lxp_edlink_provider_name', $edlink_provider_name)) {
			update_post_meta($district_post_id, 'lxp_edlink_provider_name', $edlink_provider_name);
		} else if ($edlink_provider_name != '') {
			add_post_meta($district_post_id, 'lxp_edlink_provider_name', $edlink_provider_name, true);
		}

		if(get_post_meta($district_post_id, 'lxp_edlink_provider_access_token', $edlink_provider_access_token)) {
			update_post_meta($district_post_id, 'lxp_edlink_provider_access_token', $edlink_provider_access_token);
		} else if ($edlink_provider_access_token != '') {
			add_post_meta($district_post_id, 'lxp_edlink_provider_access_token', $edlink_provider_access_token, true);
		}

		if(get_post_meta($district_post_id, 'lxp_edlink_district_id', $edlink_district_id)) {
			update_post_meta($district_post_id, 'lxp_edlink_district_id', $edlink_district_id);
		} else if ($edlink_district_id != '') {
			add_post_meta($district_post_id, 'lxp_edlink_district_id', $edlink_district_id, true);
		}

		// ========== District Admin ===========
		$district_admin_data = array(
			'user_login' => trim($request->get_param('edlink_user_email')),
			'user_email' => trim($request->get_param('edlink_user_email')),
			'first_name' => trim($request->get_param('edlink_first_name')),
			'last_name' => trim($request->get_param('edlink_last_name')),
			'display_name' => trim($request->get_param('edlink_first_name')) . ' ' . trim($request->get_param('edlink_last_name')),
			'role' => 'lxp_client_admin'
		);

		//if (trim($request->get_param('user_password'))) {
			$district_admin_data['user_pass'] = trim($request->get_param('edlink_user_email'));
		//}

		$lxp_district_admin_id = get_post_meta($district_post_id, 'lxp_district_admin', true);
		if ($lxp_district_admin_id) {
			$district_admin_data["ID"] = $lxp_district_admin_id;
			global $wpdb;
			$result = $wpdb->update(
				$wpdb->users,
				array('user_login' => trim($request->get_param('edlink_user_email'))),
				array('ID' => $lxp_district_admin_id)
			);
		}
		$district_admin_id  = wp_insert_user($district_admin_data);

		// if (trim($request->get_param('user_password'))) {
		 	wp_set_password( trim($request->get_param('edlink_user_email')), $district_admin_id );
		// }

		if (!boolval($lxp_district_admin_id) && $district_admin_id) {
			if(get_post_meta($district_post_id, 'lxp_district_admin', $district_admin_id)) {
				update_post_meta($district_post_id, 'lxp_district_admin', $district_admin_id);
			} else {
				add_post_meta($district_post_id, 'lxp_district_admin', $district_admin_id, true);
			}
		}

        return wp_send_json_success("District Saved!");
    }

	public static function get_one($request) {
		$district_id = $request->get_param('district_id');
		$district = get_post($district_id);
		$admin = get_userdata(get_post_meta($district_id, 'lxp_district_admin', true));
		$admin->data->first_name = get_user_meta($admin->ID, 'first_name', true);
		$admin->data->last_name = get_user_meta($admin->ID, 'last_name', true);
		// Roster-vault escrow public key (safe to expose — it is a public key).
		$district->lxp_district_escrow_pubkey = (string) get_post_meta($district_id, 'lxp_district_escrow_pubkey', true);
		return wp_send_json_success(array("district" => $district, "admin" => $admin));
	}
}
?>