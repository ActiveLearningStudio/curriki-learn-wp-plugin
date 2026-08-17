<?php

/**
 * Encrypted roster vault (Zone B) — serve and store, never read.
 *
 * The `member_id -> real name` map for a class is encrypted in the teacher's
 * browser and posted here as opaque base64. This endpoint's entire job is
 * access control and storage: it authorises *release* of the blob, and it
 * accepts a replacement blob. It holds no key and performs no crypto.
 *
 * If you ever find yourself adding a decrypt call to this file, the design has
 * been broken.
 *
 * @see docs/student-privacy-zone-b-context.md
 */
class Rest_Lxp_Roster_Vault {

	/** Meta key on tl_district holding the escrow RSA public key (SPKI PEM). */
	const ESCROW_KEY_META = 'lxp_district_escrow_pubkey';

	/** @var TL_Roster_Vault_Repository|null */
	private static $vault = null;

	private static function vault() {
		if ( ! self::$vault ) {
			self::$vault = new TL_Roster_Vault_Repository();
		}
		return self::$vault;
	}

	/**
	 * Register the REST API routes.
	 */
	public static function init() {
		if ( ! function_exists( 'register_rest_route' ) ) {
			return false;
		}

		register_rest_route( 'lms/v1', '/class/vault', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( 'Rest_Lxp_Roster_Vault', 'get_vault' ),
				'permission_callback' => '__return_true',
			),
		) );

		register_rest_route( 'lms/v1', '/class/vault/save', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( 'Rest_Lxp_Roster_Vault', 'save_vault' ),
				'permission_callback' => '__return_true',
			),
		) );

		register_rest_route( 'lms/v1', '/class/vault/delete', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( 'Rest_Lxp_Roster_Vault', 'delete_vault' ),
				'permission_callback' => '__return_true',
			),
		) );

		// Purge the vault when its class goes away — a roster map must never
		// outlive the class it describes.
		add_action( 'before_delete_post', array( 'Rest_Lxp_Roster_Vault', 'on_class_deleted' ), 10, 2 );
	}

	// =========================================================================
	// Endpoints
	// =========================================================================

	/**
	 * Release a class's vault blob to its teacher.
	 *
	 * Also returns the district escrow public key so the browser can wrap a
	 * recovery copy of the DEK when it next saves.
	 */
	public static function get_vault( $request ) {
		$class_id = absint( $request->get_param( 'class_id' ) );

		if ( ! Rest_Lxp_Class_Redemption::can_manage_class( $class_id ) ) {
			return wp_send_json_error( 'You are not allowed to manage this class.', 403 );
		}

		$row = self::vault()->get_by_class( $class_id );

		return wp_send_json_success( array(
			'exists'     => (bool) $row,
			'vault'      => $row ? array(
				'ciphertext'          => $row->ciphertext,
				'iv'                  => $row->iv,
				'wrapped_dek_teacher' => $row->wrapped_dek_teacher,
				'kdf_params'          => $row->kdf_params ? json_decode( $row->kdf_params, true ) : null,
				'version'             => (int) $row->version,
				'updated_at'          => $row->updated_at,
				'has_escrow'          => ! empty( $row->wrapped_dek_escrow ),
			) : null,
			'escrow_public_key' => self::get_escrow_key_for_class( $class_id ),
		) );
	}

	/**
	 * Store a replacement blob.
	 *
	 * `version` is the version the client last read. A mismatch means another
	 * session saved in between, and the write is rejected rather than allowed
	 * to silently discard the other teacher's edit.
	 */
	public static function save_vault( $request ) {
		$class_id = absint( $request->get_param( 'class_id' ) );

		if ( ! Rest_Lxp_Class_Redemption::can_manage_class( $class_id ) ) {
			return wp_send_json_error( 'You are not allowed to manage this class.', 403 );
		}

		$ciphertext = (string) $request->get_param( 'ciphertext' );
		$iv         = (string) $request->get_param( 'iv' );
		$wrapped    = (string) $request->get_param( 'wrapped_dek_teacher' );

		if ( ! self::is_base64( $ciphertext ) || ! self::is_base64( $iv ) || ! self::is_base64( $wrapped ) ) {
			return wp_send_json_error( 'Malformed vault payload.', 400 );
		}

		$escrow = $request->get_param( 'wrapped_dek_escrow' );
		if ( null !== $escrow && '' !== $escrow && ! self::is_base64( (string) $escrow ) ) {
			return wp_send_json_error( 'Malformed vault payload.', 400 );
		}

		$kdf_params = self::sanitize_kdf_params( $request->get_param( 'kdf_params' ) );

		$args = array(
			'class_id'            => $class_id,
			'teacher_user_id'     => get_current_user_id(),
			'ciphertext'          => $ciphertext,
			'iv'                  => $iv,
			'wrapped_dek_teacher' => $wrapped,
		);
		if ( null !== $escrow ) {
			$args['wrapped_dek_escrow'] = (string) $escrow;
		}
		if ( null !== $kdf_params ) {
			$args['kdf_params'] = wp_json_encode( $kdf_params );
		}

		$existing = self::vault()->get_by_class( $class_id );

		if ( ! $existing ) {
			if ( ! self::vault()->create( $args ) ) {
				return wp_send_json_error( 'Could not create the vault.', 500 );
			}
			return wp_send_json_success( array( 'version' => 1 ) );
		}

		$expected = absint( $request->get_param( 'version' ) );
		if ( $expected !== (int) $existing->version ) {
			return wp_send_json_error(
				array(
					'code'            => 'version_conflict',
					'message'         => 'This roster was changed in another session. Reload before saving again.',
					'current_version' => (int) $existing->version,
				),
				409
			);
		}

		$new_version = self::vault()->update( $class_id, $expected, $args );
		if ( false === $new_version ) {
			return wp_send_json_error(
				array(
					'code'    => 'version_conflict',
					'message' => 'This roster was changed in another session. Reload before saving again.',
				),
				409
			);
		}

		return wp_send_json_success( array( 'version' => $new_version ) );
	}

	/**
	 * Destroy a class's vault. Irreversible — the names are unrecoverable after
	 * this, by design.
	 */
	public static function delete_vault( $request ) {
		$class_id = absint( $request->get_param( 'class_id' ) );

		if ( ! Rest_Lxp_Class_Redemption::can_manage_class( $class_id ) ) {
			return wp_send_json_error( 'You are not allowed to manage this class.', 403 );
		}

		self::vault()->delete_by_class( $class_id );

		return wp_send_json_success( array( 'deleted' => true ) );
	}

	// =========================================================================
	// Hooks
	// =========================================================================

	/**
	 * Purge the vault when its class is permanently deleted.
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 */
	public static function on_class_deleted( $post_id, $post = null ) {
		if ( $post && TL_CLASS_CPT !== $post->post_type ) {
			return;
		}
		if ( ! $post && TL_CLASS_CPT !== get_post_type( $post_id ) ) {
			return;
		}

		self::vault()->delete_by_class( $post_id );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * The district escrow public key that applies to a class.
	 *
	 * Walks class -> teacher -> school -> district. Returns '' when the district
	 * has not configured one, in which case the browser saves without a recovery
	 * copy and the UI warns that a forgotten passphrase is unrecoverable.
	 *
	 * @param  int $class_id
	 * @return string SPKI PEM, or ''.
	 */
	public static function get_escrow_key_for_class( $class_id ) {
		$teacher_post_id = (int) get_post_meta( $class_id, 'lxp_class_teacher_id', true );
		if ( ! $teacher_post_id ) {
			return '';
		}

		$school_post_id = (int) get_post_meta( $teacher_post_id, 'lxp_teacher_school_id', true );
		if ( ! $school_post_id ) {
			return '';
		}

		$district_post_id = (int) get_post_meta( $school_post_id, 'lxp_school_district_id', true );
		if ( ! $district_post_id ) {
			return '';
		}

		return trim( (string) get_post_meta( $district_post_id, self::ESCROW_KEY_META, true ) );
	}

	/**
	 * Accept only well-formed base64 for blob columns.
	 *
	 * @param  string $value
	 * @return bool
	 */
	private static function is_base64( $value ) {
		if ( '' === $value || strlen( $value ) > 8 * MB_IN_BYTES ) {
			return false;
		}
		if ( ! preg_match( '#^[A-Za-z0-9+/]+={0,2}$#', $value ) ) {
			return false;
		}

		return false !== base64_decode( $value, true );
	}

	/**
	 * Validate the non-secret key-derivation parameters.
	 *
	 * These are stored so a future release can change KDF without orphaning
	 * existing vaults — `algo` is what makes an Argon2id migration possible
	 * later without breaking anything encrypted today.
	 *
	 * @param  mixed $raw
	 * @return array|null
	 */
	private static function sanitize_kdf_params( $raw ) {
		if ( empty( $raw ) ) {
			return null;
		}

		if ( is_string( $raw ) ) {
			$raw = json_decode( $raw, true );
		}
		if ( ! is_array( $raw ) ) {
			return null;
		}

		$algo = isset( $raw['algo'] ) ? sanitize_text_field( $raw['algo'] ) : '';
		if ( ! in_array( $algo, array( 'PBKDF2-SHA256', 'Argon2id' ), true ) ) {
			return null;
		}

		$salt = isset( $raw['salt'] ) ? (string) $raw['salt'] : '';
		if ( ! self::is_base64( $salt ) ) {
			return null;
		}

		$params = array(
			'algo' => $algo,
			'salt' => $salt,
		);

		if ( isset( $raw['iterations'] ) ) {
			// Floor guards against a downgrade attack: a tampered-with low
			// iteration count would make an offline crack of the passphrase
			// cheap. 100k is well below our 600k default but still a real cost.
			$params['iterations'] = max( 100000, absint( $raw['iterations'] ) );
		}
		if ( isset( $raw['hash'] ) ) {
			$params['hash'] = sanitize_text_field( $raw['hash'] );
		}

		return $params;
	}
}
