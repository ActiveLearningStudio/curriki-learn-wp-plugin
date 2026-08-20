<?php

/**
 * Class code redemption — zero-PII student enrollment (Zone A).
 *
 * A student submits a class registration code plus a non-PII alias; this
 * provisions a pseudonymous token account, seats it in the class, enrolls it in
 * the class's LearnPress courses, and starts a session — without collecting or
 * storing any student PII.
 *
 * The teacher's act of issuing the code is the COPPA school-consent gate: a
 * valid code is treated as authorized enrollment. The alias -> real name map
 * (Zone B) is out of scope here; this endpoint never sees a real name.
 *
 * @see docs/student-privacy-zone-a-context.md
 */
class Rest_Lxp_Class_Redemption {

	/** Usermeta/postmeta flag marking an account as a zero-PII token student. */
	const TOKEN_FLAG_META = 'lxp_is_token_student';

	/** Email domain for the system sink address token accounts are given. */
	const SINK_EMAIL_DOMAIN = 'students.curriki.local';

	/** Allowed alias characters + length (used only in `open` alias mode). */
	const ALIAS_PATTERN = '/^[A-Za-z0-9 ._-]{2,32}$/';

	/** Rate limit: write attempts (redeem/claim) per hashed IP per window. */
	const RATE_LIMIT_IP_MAX = 10;

	/** Rate limit: seconds in the per-IP window. */
	const RATE_LIMIT_IP_WINDOW = 600;

	/** Rate limit: attempts per class code per hour. */
	const RATE_LIMIT_CODE_MAX = 60;

	/**
	 * Rate limit for the read-only seat lookup.
	 *
	 * The join form calls this as the student types, so it needs a far larger
	 * budget than redeem/claim — otherwise a student who mistypes their code a
	 * few times locks themselves out before they ever submit. It is kept on a
	 * separate counter so it can never consume the write budget.
	 */
	const RATE_LIMIT_LOOKUP_MAX = 60;

	/** @var TL_Class_Member_Repository|null */
	private static $members = null;

	/** @var TL_Enrollment_Repository|null */
	private static $enrollments = null;

	private static function members() {
		if ( ! self::$members ) {
			self::$members = new TL_Class_Member_Repository();
		}
		return self::$members;
	}

	private static function enrollments() {
		if ( ! self::$enrollments ) {
			self::$enrollments = new TL_Enrollment_Repository();
		}
		return self::$enrollments;
	}

	/**
	 * Register the REST API routes.
	 */
	public static function init() {
		if ( ! function_exists( 'register_rest_route' ) ) {
			return false;
		}

		// --- Public (student-facing) -----------------------------------------
		register_rest_route( 'lms/v1', '/class/redeem', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( 'Rest_Lxp_Class_Redemption', 'redeem' ),
				'permission_callback' => '__return_true',
			),
		) );

		register_rest_route( 'lms/v1', '/class/claim', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( 'Rest_Lxp_Class_Redemption', 'claim' ),
				'permission_callback' => '__return_true',
			),
		) );

		register_rest_route( 'lms/v1', '/class/seats', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( 'Rest_Lxp_Class_Redemption', 'get_open_seats' ),
				'permission_callback' => '__return_true',
			),
		) );

		// --- Teacher / admin --------------------------------------------------
		register_rest_route( 'lms/v1', '/class/code/settings', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( 'Rest_Lxp_Class_Redemption', 'save_code_settings' ),
				'permission_callback' => '__return_true',
			),
		) );

		register_rest_route( 'lms/v1', '/class/roster', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( 'Rest_Lxp_Class_Redemption', 'get_roster' ),
				'permission_callback' => '__return_true',
			),
		) );

		register_rest_route( 'lms/v1', '/class/roster/provision', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( 'Rest_Lxp_Class_Redemption', 'provision_roster' ),
				'permission_callback' => '__return_true',
			),
		) );

		register_rest_route( 'lms/v1', '/class/member/reissue', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( 'Rest_Lxp_Class_Redemption', 'reissue_claim' ),
				'permission_callback' => '__return_true',
			),
		) );
	}

	// =========================================================================
	// Public endpoints
	// =========================================================================

	/**
	 * Redeem a class code: provision a token student, enroll, sign in.
	 *
	 * Validation is ordered and fails closed. Only once every check passes are
	 * the writes performed, as one atomic unit.
	 */
	public static function redeem( $request ) {
		$code  = strtoupper( sanitize_text_field( (string) $request->get_param( 'class_code' ) ) );
		$alias = sanitize_text_field( (string) $request->get_param( 'alias_label' ) );

		// 1. Anti-abuse first, so a brute-forcer never reaches the lookups.
		if ( ! self::check_rate_limit( $code ) ) {
			return self::reject( 'rate_limited' );
		}

		// 2-4. Code resolves to a live, unrevoked, unexpired class.
		$class = self::resolve_class_by_code( $code );
		if ( is_string( $class ) ) {
			return self::reject( $class );
		}

		$class_id = (int) $class->ID;

		// 5. Seats.
		$max_seats = (int) get_post_meta( $class_id, 'lxp_class_max_seats', true );
		$taken     = self::members()->count_active( $class_id );
		if ( $max_seats > 0 && $taken >= $max_seats ) {
			return self::reject( 'class_full' );
		}

		// 6. Alias must be non-PII and available.
		$alias = self::resolve_alias( $class_id, $alias );
		if ( is_string( $alias ) && 0 === strpos( $alias, 'ERR:' ) ) {
			return self::reject( substr( $alias, 4 ) );
		}

		$provisioned = self::provision_member( $class_id, $alias, 'code' );
		if ( is_wp_error( $provisioned ) ) {
			return self::reject( 'provision_failed' );
		}

		// Start the session for the freshly-minted token account.
		wp_set_current_user( $provisioned['user_id'] );
		wp_set_auth_cookie( $provisioned['user_id'], true, is_ssl() );

		return wp_send_json_success( array(
			'alias_label' => $provisioned['alias_label'],
			'claim_url'   => $provisioned['claim_url'],
			'redirect_url' => self::landing_url( $class_id ),
		) );
	}

	/**
	 * Resume an existing token account from a claim link.
	 *
	 * Idempotent by design: no new account, no second seat consumed.
	 */
	public static function claim( $request ) {
		$token = sanitize_text_field( (string) $request->get_param( 'claim_token' ) );

		if ( ! self::check_rate_limit( 'claim' ) ) {
			return self::reject( 'rate_limited' );
		}

		if ( '' === $token ) {
			return self::reject( 'invalid_claim' );
		}

		$member = self::members()->get_by_claim_token( $token );
		if ( ! $member ) {
			return self::reject( 'invalid_claim' );
		}

		$user = get_user_by( 'id', (int) $member->student_user_id );
		if ( ! $user ) {
			return self::reject( 'invalid_claim' );
		}

		self::members()->touch_claim( (int) $member->id );

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );

		self::audit( 'claim_resumed', (int) $member->class_id, (int) $user->ID, 'claim' );

		return wp_send_json_success( array(
			'alias_label'  => $member->alias_label,
			'redirect_url' => self::landing_url( (int) $member->class_id ),
		) );
	}

	/**
	 * Look a class code up so the join form can confirm it before submitting.
	 *
	 * Named for what it used to return — a list of free seat labels for a
	 * dropdown. That dropdown is gone (students type a nickname), so this now
	 * answers only "which class is this, and is it full?". The route stays
	 * `/class/seats` because renaming a published endpoint buys nothing.
	 *
	 * Public because the join form is unauthenticated. It discloses only a class
	 * title and a seat count, to a caller who already holds that class's code.
	 */
	public static function get_open_seats( $request ) {
		$code = strtoupper( sanitize_text_field( (string) $request->get_param( 'class_code' ) ) );

		if ( ! self::check_rate_limit( $code, 'lookup' ) ) {
			return self::reject( 'rate_limited' );
		}

		$class = self::resolve_class_by_code( $code );
		if ( is_string( $class ) ) {
			return self::reject( $class );
		}

		$class_id  = (int) $class->ID;
		$max_seats = (int) get_post_meta( $class_id, 'lxp_class_max_seats', true );
		$taken     = self::members()->count_active( $class_id );

		// No seat list any more — students type a nickname. What the join form
		// still needs is confirmation of *which* class a code belongs to, and
		// whether it is full, before the student bothers typing.
		return wp_send_json_success( array(
			'class_name'  => $class->post_title,
			'seats_taken' => $taken,
			'max_seats'   => $max_seats,
			'is_full'     => ( $max_seats > 0 && $taken >= $max_seats ),
		) );
	}

	// =========================================================================
	// Teacher endpoints
	// =========================================================================

	/**
	 * Update the code controls on a class: seats, expiry, revoke, alias mode.
	 * Optionally regenerate the code itself.
	 */
	public static function save_code_settings( $request ) {
		$class_id = absint( $request->get_param( 'class_id' ) );

		if ( ! self::can_manage_class( $class_id ) ) {
			return wp_send_json_error( 'You are not allowed to manage this class.', 403 );
		}

		if ( null !== $request->get_param( 'max_seats' ) ) {
			update_post_meta( $class_id, 'lxp_class_max_seats', absint( $request->get_param( 'max_seats' ) ) );
		}

		if ( null !== $request->get_param( 'code_expires' ) ) {
			$raw = trim( (string) $request->get_param( 'code_expires' ) );
			update_post_meta( $class_id, 'lxp_class_code_expires', $raw ? sanitize_text_field( $raw ) : '' );
		}

		if ( null !== $request->get_param( 'revoked' ) ) {
			$revoked = filter_var( $request->get_param( 'revoked' ), FILTER_VALIDATE_BOOLEAN );
			update_post_meta( $class_id, 'lxp_class_code_revoked', $revoked ? '1' : '' );
		}

		if ( filter_var( $request->get_param( 'regenerate_code' ), FILTER_VALIDATE_BOOLEAN ) ) {
			update_post_meta( $class_id, 'lxp_class_code', self::generate_class_code() );
		}

		// Rebuild the seat pool to match the (possibly new) seat cap.
		self::sync_seat_pool( $class_id );

		return wp_send_json_success( array(
			'class_code'  => get_post_meta( $class_id, 'lxp_class_code', true ),
			'max_seats'   => (int) get_post_meta( $class_id, 'lxp_class_max_seats', true ),
			'expires'     => get_post_meta( $class_id, 'lxp_class_code_expires', true ),
			'revoked'     => (bool) get_post_meta( $class_id, 'lxp_class_code_revoked', true ),
			'seats_taken' => self::members()->count_active( $class_id ),
		) );
	}

	/**
	 * The teacher's roster view: aliases, join method, claim links.
	 *
	 * Claim links are reconstructable only because the teacher owns the class;
	 * the stored value is a hash, so this returns a *re-issued* link on demand
	 * rather than the original secret.
	 */
	public static function get_roster( $request ) {
		$class_id = absint( $request->get_param( 'class_id' ) );

		if ( ! self::can_manage_class( $class_id ) ) {
			return wp_send_json_error( 'You are not allowed to manage this class.', 403 );
		}

		$rows    = self::members()->get_by_class( $class_id, 'active' );
		$roster  = array();

		foreach ( $rows as $row ) {
			$roster[] = array(
				'id'          => (int) $row->id,
				'alias_label' => $row->alias_label,
				'joined_via'  => $row->joined_via,
				'created_at'  => $row->created_at,
				'last_seen'   => $row->claim_last_used,
				'user_id'     => (int) $row->student_user_id,
			);
		}

		$max_seats = (int) get_post_meta( $class_id, 'lxp_class_max_seats', true );

		return wp_send_json_success( array(
			'roster'      => $roster,
			'seats_taken' => count( $roster ),
			'max_seats'   => $max_seats,
			'class_code'  => get_post_meta( $class_id, 'lxp_class_code', true ),
		) );
	}

	/**
	 * Pre-create seats from a roster upload.
	 *
	 * The browser parses the CSV locally and posts only alias labels — student
	 * names never leave the teacher's machine, and no file is written to disk.
	 */
	public static function provision_roster( $request ) {
		$class_id = absint( $request->get_param( 'class_id' ) );

		if ( ! self::can_manage_class( $class_id ) ) {
			return wp_send_json_error( 'You are not allowed to manage this class.', 403 );
		}

		$aliases    = $request->get_param( 'aliases' );
		$seat_count = absint( $request->get_param( 'seat_count' ) );

		if ( ! is_array( $aliases ) || empty( $aliases ) ) {
			if ( ! $seat_count ) {
				return wp_send_json_error( 'No seats to create.', 400 );
			}
			// No explicit labels: take the next N unclaimed labels from the pool.
			$aliases = self::next_open_seats( $class_id, $seat_count );
			if ( empty( $aliases ) ) {
				return wp_send_json_error( 'No seat labels are available. Raise the seat cap first.', 400 );
			}
		}

		$max_seats = (int) get_post_meta( $class_id, 'lxp_class_max_seats', true );
		$taken     = self::members()->count_active( $class_id );

		$created = array();
		$skipped = array();

		foreach ( $aliases as $raw_alias ) {
			$alias = sanitize_text_field( (string) $raw_alias );

			if ( $max_seats > 0 && ( $taken + count( $created ) ) >= $max_seats ) {
				$skipped[] = array( 'alias' => $alias, 'reason' => 'class_full' );
				continue;
			}

			if ( ! preg_match( self::ALIAS_PATTERN, $alias ) || self::looks_like_pii( $alias ) ) {
				$skipped[] = array( 'alias' => $alias, 'reason' => 'bad_alias' );
				continue;
			}

			if ( self::members()->get_by_alias( $class_id, $alias ) ) {
				$skipped[] = array( 'alias' => $alias, 'reason' => 'duplicate' );
				continue;
			}

			$provisioned = self::provision_member( $class_id, $alias, 'roster' );
			if ( is_wp_error( $provisioned ) ) {
				$skipped[] = array( 'alias' => $alias, 'reason' => 'provision_failed' );
				continue;
			}

			$created[] = array(
				'member_id'   => $provisioned['member_id'],
				'alias_label' => $provisioned['alias_label'],
				'claim_url'   => $provisioned['claim_url'],
			);
		}

		return wp_send_json_success( array(
			'created'     => $created,
			'skipped'     => $skipped,
			'seats_taken' => self::members()->count_active( $class_id ),
		) );
	}

	/**
	 * Mint a fresh claim link for one member (lost-link recovery).
	 * The previous link stops working immediately.
	 */
	public static function reissue_claim( $request ) {
		$member_id = absint( $request->get_param( 'member_id' ) );
		$member    = self::members()->get( $member_id );

		if ( ! $member ) {
			return wp_send_json_error( 'Member not found.', 404 );
		}

		if ( ! self::can_manage_class( (int) $member->class_id ) ) {
			return wp_send_json_error( 'You are not allowed to manage this class.', 403 );
		}

		$raw = self::generate_claim_token();
		self::members()->rotate_claim( $member_id, TL_Class_Member_Repository::hash_claim_token( $raw ) );

		self::audit( 'claim_reissued', (int) $member->class_id, (int) $member->student_user_id, $member->joined_via );

		return wp_send_json_success( array(
			'alias_label' => $member->alias_label,
			'claim_url'   => self::build_claim_url( $raw, (int) $member->class_id ),
		) );
	}

	// =========================================================================
	// Provisioning
	// =========================================================================

	/**
	 * Create a token WP user + tl_student post + membership row + enrollments,
	 * as one atomic unit.
	 *
	 * A DB transaction is opened, but explicit compensating deletes also run on
	 * failure: some hosts still run LearnPress tables on MyISAM, where the
	 * transaction alone would not roll the enrollment back.
	 *
	 * @param  int    $class_id
	 * @param  string $alias_label
	 * @param  string $joined_via 'code' | 'roster'
	 * @return array|WP_Error  ['user_id','student_post_id','member_id','alias_label','claim_url']
	 */
	private static function provision_member( $class_id, $alias_label, $joined_via = 'code' ) {
		global $wpdb;

		$teacher_post_id = (int) get_post_meta( $class_id, 'lxp_class_teacher_id', true );
		$school_post_id  = $teacher_post_id
			? (int) get_post_meta( $teacher_post_id, 'lxp_teacher_school_id', true )
			: 0;

		$login     = self::generate_token_login();
		$raw_claim = self::generate_claim_token();

		$user_id         = 0;
		$student_post_id = 0;
		$member_id       = 0;
		$enrolled_ids    = array();

		$wpdb->query( 'START TRANSACTION' );

		try {
			// --- 1. Token WP user: no name, no personal email, no password known
			//        to the student. The claim link is the only credential.
			$user_id = wp_insert_user( array(
				'user_login'    => $login,
				'user_email'    => $login . '@' . self::SINK_EMAIL_DOMAIN,
				'user_nicename' => $login,
				'user_pass'     => wp_generate_password( 32, true, true ),
				'display_name'  => $alias_label,
				'role'          => 'lxp_student',
			) );

			if ( is_wp_error( $user_id ) ) {
				throw new Exception( 'user_insert_failed' );
			}

			update_user_meta( $user_id, self::TOKEN_FLAG_META, 1 );
			update_user_meta( $user_id, 'lxp_no_marketing', 1 );
			update_user_meta( $user_id, 'lxp_class_id', $class_id );

			// --- 2. tl_student post. Title is the alias, never a name. Every
			//        existing dashboard resolves students through this post.
			$student_post_id = wp_insert_post( array(
				'post_title'  => wp_strip_all_tags( $alias_label ),
				'post_status' => 'publish',
				'post_type'   => TL_STUDENT_CPT,
				'post_author' => $user_id,
			), true );

			if ( is_wp_error( $student_post_id ) || ! $student_post_id ) {
				throw new Exception( 'student_post_failed' );
			}

			update_post_meta( $student_post_id, 'lxp_student_admin_id', $user_id );
			update_post_meta( $student_post_id, self::TOKEN_FLAG_META, 1 );
			// student_id is now an opaque internal handle, not a school ID.
			update_post_meta( $student_post_id, 'student_id', $login );
			update_post_meta( $student_post_id, 'lxp_student_school_id', $school_post_id );
			update_post_meta( $student_post_id, 'lxp_teacher_id', $teacher_post_id ? array( $teacher_post_id ) : array() );
			// Grade is inherited from the class rather than collected per student.
			$class_grade = get_post_meta( $class_id, 'grade', true );
			update_post_meta( $student_post_id, 'grades', wp_json_encode( $class_grade ? array( $class_grade ) : array() ) );
			// Deliberately NOT set: lxp_student_password. Token accounts have no
			// plaintext credential; re-entry is via the hashed claim link.

			// --- 3. Membership row (also enforces the alias uniqueness index).
			$member_id = self::members()->insert( array(
				'class_id'           => $class_id,
				'student_post_id'    => $student_post_id,
				'student_user_id'    => $user_id,
				'alias_label'        => $alias_label,
				'joined_via'         => $joined_via,
				'claim_token_hash'   => TL_Class_Member_Repository::hash_claim_token( $raw_claim ),
				'consent_teacher_id' => $teacher_post_id,
				'consent_school_id'  => $school_post_id,
			) );

			if ( ! $member_id ) {
				throw new Exception( 'member_insert_failed' );
			}

			// --- 4. Make the student visible to the existing class UI.
			add_post_meta( $class_id, 'lxp_student_ids', $student_post_id );

			// --- 5. Enroll in every course attached to the class.
			$course_ids = array_map( 'absint', (array) get_post_meta( $class_id, 'lxp_class_course_ids' ) );
			foreach ( array_filter( $course_ids ) as $course_id ) {
				$item_id = self::enrollments()->enroll( $user_id, $course_id, $class_id );
				if ( ! $item_id ) {
					throw new Exception( 'enroll_failed' );
				}
				$enrolled_ids[] = $course_id;
			}

			$wpdb->query( 'COMMIT' );
		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );

			// Compensating deletes — the transaction cannot be relied on when
			// any of these tables is MyISAM.
			foreach ( $enrolled_ids as $course_id ) {
				self::enrollments()->remove( $user_id, $course_id );
			}
			if ( $member_id ) {
				self::members()->delete( $member_id );
			}
			if ( $student_post_id && ! is_wp_error( $student_post_id ) ) {
				delete_post_meta( $class_id, 'lxp_student_ids', $student_post_id );
				wp_delete_post( $student_post_id, true );
			}
			if ( $user_id && ! is_wp_error( $user_id ) ) {
				if ( ! function_exists( 'wp_delete_user' ) ) {
					require_once ABSPATH . 'wp-admin/includes/user.php';
				}
				wp_delete_user( $user_id );
			}

			return new WP_Error( 'provision_failed', $e->getMessage() );
		}

		self::audit( 'provisioned', $class_id, $user_id, $joined_via );

		return array(
			'user_id'         => (int) $user_id,
			'student_post_id' => (int) $student_post_id,
			'member_id'       => (int) $member_id,
			'alias_label'     => $alias_label,
			'claim_url'       => self::build_claim_url( $raw_claim, $class_id ),
		);
	}

	// =========================================================================
	// Membership reconciliation
	// =========================================================================

	/**
	 * Keep the legacy `lxp_student_ids` meta in step with `lxp_class_members`.
	 *
	 * Two records describe the same association on purpose: the repeating post
	 * meta that every existing dashboard, widget and grade query reads, and the
	 * members table that owns seats, aliases, consent provenance and claim
	 * secrets. Provisioning writes both together.
	 *
	 * The gap this closes: `Rest_Lxp_Class::create()` rebuilds the meta wholesale
	 * from the class modal's checkbox list. A token student missing from that
	 * list — different school scoping, an admin editing with a filtered student
	 * list — would be silently dropped from the meta while the table still called
	 * them active. The seat stays consumed and the roster still shows them, but
	 * they vanish from the Student Courses widget and lose their way back in.
	 * Nothing errors, so it would surface weeks later as a confused teacher.
	 *
	 * Students with no row in the table at all (legacy, teacher-assigned) are
	 * left completely untouched — this only ever speaks for token members.
	 *
	 * @param  int $class_id tl_class post ID.
	 * @return int Number of meta rows added or removed.
	 */
	public static function reconcile_class_student_meta( $class_id ) {
		$class_id = absint( $class_id );
		if ( ! $class_id ) {
			return 0;
		}

		$current = array_map( 'intval', (array) get_post_meta( $class_id, 'lxp_student_ids' ) );
		$changed = 0;

		// Every active member must appear in the meta.
		foreach ( self::members()->get_by_class( $class_id, 'active' ) as $member ) {
			$post_id = (int) $member->student_post_id;
			if ( $post_id && ! in_array( $post_id, $current, true ) ) {
				add_post_meta( $class_id, 'lxp_student_ids', $post_id );
				$current[] = $post_id;
				$changed++;
			}
		}

		// Anyone the table marks removed must not.
		foreach ( self::members()->get_by_class( $class_id, 'removed' ) as $member ) {
			$post_id = (int) $member->student_post_id;
			if ( $post_id && in_array( $post_id, $current, true ) ) {
				delete_post_meta( $class_id, 'lxp_student_ids', $post_id );
				$changed++;
			}
		}

		return $changed;
	}

	// =========================================================================
	// Validation helpers
	// =========================================================================

	/**
	 * Resolve a class code to a usable class post.
	 *
	 * @param  string $code
	 * @return WP_Post|string  The post, or an error slug string.
	 */
	private static function resolve_class_by_code( $code ) {
		if ( '' === $code ) {
			return 'invalid_code';
		}

		$posts = get_posts( array(
			'post_type'      => TL_CLASS_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => 'lxp_class_code',
			'meta_value'     => $code,
		) );

		if ( empty( $posts ) ) {
			return 'invalid_code';
		}

		$class    = $posts[0];
		$class_id = (int) $class->ID;

		if ( get_post_meta( $class_id, 'lxp_class_code_revoked', true ) ) {
			return 'code_revoked';
		}

		$expires = trim( (string) get_post_meta( $class_id, 'lxp_class_code_expires', true ) );
		if ( '' !== $expires ) {
			$expires_ts = strtotime( $expires );
			if ( $expires_ts && $expires_ts <= current_time( 'timestamp' ) ) {
				return 'code_expired';
			}
		}

		return $class;
	}

	/**
	 * Validate/normalise the submitted alias for a class.
	 *
	 * Every class works the same way: the student types a nickname, which is
	 * charset-checked, screened by looks_like_pii(), and disambiguated with a
	 * numeric suffix on collision.
	 *
	 * There used to be a second `assigned` mode where the student picked an
	 * unclaimed label from a dropdown instead. It is gone at the client's
	 * request, and the branch had to go with it rather than merely being hidden
	 * in the UI — classes created before the change still carry
	 * `lxp_class_alias_mode = 'assigned'` in post meta, and a typed nickname
	 * would have been rejected as `bad_alias` for not being in the seat pool.
	 *
	 * Note this is *not* the same thing as the seat pool itself
	 * (`get_seat_pool()` / `lxp_class_seat_labels`), which is still very much
	 * alive: it belongs to the Roster modal and CSV import, where the teacher
	 * pre-creates seats and hands out claim links. That flow never comes
	 * through here.
	 *
	 * @param  int    $class_id
	 * @param  string $alias
	 * @return string Alias on success, or "ERR:<slug>" on failure.
	 */
	private static function resolve_alias( $class_id, $alias ) {
		$alias = trim( $alias );

		if ( '' === $alias ) {
			return 'ERR:bad_alias';
		}

		if ( ! preg_match( self::ALIAS_PATTERN, $alias ) || self::looks_like_pii( $alias ) ) {
			return 'ERR:bad_alias';
		}

		// Disambiguate a collision rather than rejecting the student.
		$candidate = $alias;
		$suffix    = 2;
		while ( self::members()->get_by_alias( $class_id, $candidate ) ) {
			$candidate = $alias . ' ' . $suffix;
			$suffix++;
			if ( $suffix > 99 ) {
				return 'ERR:bad_alias';
			}
		}

		return $candidate;
	}

	/**
	 * Reject alias values that look like personal data.
	 *
	 * This is a backstop for `open` mode only — `assigned` mode is the real
	 * enforcement, because it offers no free-text field in the first place.
	 *
	 * @param  string $alias
	 * @return bool
	 */
	private static function looks_like_pii( $alias ) {
		// Email-shaped.
		if ( false !== strpos( $alias, '@' ) || is_email( $alias ) ) {
			return true;
		}
		// Phone-shaped: 7+ digits once separators are stripped.
		$digits = preg_replace( '/\D/', '', $alias );
		if ( strlen( $digits ) >= 7 ) {
			return true;
		}
		return false;
	}

	/**
	 * The teacher-defined alias pool for a class.
	 *
	 * Falls back to generating `Student 01..NN` from the seat cap so a class is
	 * never left with an empty dropdown.
	 *
	 * @param  int $class_id
	 * @return string[]
	 */
	private static function get_seat_pool( $class_id ) {
		$pool = array_values( array_filter( array_map(
			'strval',
			(array) get_post_meta( $class_id, 'lxp_class_seat_labels' )
		) ) );

		if ( ! empty( $pool ) ) {
			return $pool;
		}

		return self::sync_seat_pool( $class_id );
	}

	/**
	 * Rebuild the seat pool to match the class's seat cap, preserving any
	 * labels already claimed by an active member.
	 *
	 * @param  int $class_id
	 * @return string[] The resulting pool.
	 */
	private static function sync_seat_pool( $class_id ) {
		$max_seats = (int) get_post_meta( $class_id, 'lxp_class_max_seats', true );
		$taken     = self::members()->get_taken_aliases( $class_id );

		// Unlimited seats: keep whatever exists, topped up to cover current use.
		$target = $max_seats > 0 ? $max_seats : max( count( $taken ) + 10, 30 );

		$pool = array();
		for ( $i = 1; $i <= $target; $i++ ) {
			$pool[] = sprintf( 'Student %02d', $i );
		}

		// Never drop a label somebody is actively using.
		foreach ( $taken as $label ) {
			if ( ! in_array( $label, $pool, true ) ) {
				$pool[] = $label;
			}
		}

		delete_post_meta( $class_id, 'lxp_class_seat_labels' );
		foreach ( $pool as $label ) {
			add_post_meta( $class_id, 'lxp_class_seat_labels', $label );
		}

		return $pool;
	}

	/**
	 * The next N unclaimed seat labels for a class, growing the pool if needed.
	 *
	 * @param  int $class_id
	 * @param  int $count
	 * @return string[]
	 */
	private static function next_open_seats( $class_id, $count ) {
		$count = max( 0, min( 100, absint( $count ) ) );
		if ( ! $count ) {
			return array();
		}

		$pool  = self::get_seat_pool( $class_id );
		$taken = self::members()->get_taken_aliases( $class_id );
		$open  = array_values( array_diff( $pool, $taken ) );

		// Unlimited-seat classes should never be blocked by a short pool.
		$max_seats = (int) get_post_meta( $class_id, 'lxp_class_max_seats', true );
		if ( count( $open ) < $count && 0 === $max_seats ) {
			$next = count( $pool ) + 1;
			while ( count( $open ) < $count ) {
				$label = sprintf( 'Student %02d', $next );
				if ( ! in_array( $label, $pool, true ) ) {
					add_post_meta( $class_id, 'lxp_class_seat_labels', $label );
					$pool[] = $label;
					$open[] = $label;
				}
				$next++;
			}
		}

		return array_slice( $open, 0, $count );
	}

	// =========================================================================
	// Anti-abuse, auth, audit
	// =========================================================================

	/**
	 * Per-IP and per-code rate limiting.
	 *
	 * The client IP is hashed before use and never stored — it may itself be a
	 * minor's personal data, so it is used as a transient key only.
	 *
	 * @param  string $code   Class code being attempted ('' to skip the code counter).
	 * @param  string $bucket 'write' for redeem/claim, 'lookup' for seat reads.
	 * @return bool   False when the caller should be turned away.
	 */
	private static function check_rate_limit( $code, $bucket = 'write' ) {
		$ip_hash = substr( wp_hash( self::client_ip() ), 0, 32 );
		$is_read = 'lookup' === $bucket;

		$ip_key   = 'lxp_rl_' . $bucket . '_' . $ip_hash;
		$ip_max   = $is_read ? self::RATE_LIMIT_LOOKUP_MAX : self::RATE_LIMIT_IP_MAX;
		$ip_count = (int) get_transient( $ip_key );

		if ( $ip_count >= $ip_max ) {
			return false;
		}
		set_transient( $ip_key, $ip_count + 1, self::RATE_LIMIT_IP_WINDOW );

		// The per-code cap exists to blunt distributed guessing at one class.
		// Read-only lookups don't consume it — they create nothing.
		if ( ! $is_read && '' !== $code ) {
			$code_key   = 'lxp_rl_code_' . md5( $code );
			$code_count = (int) get_transient( $code_key );
			if ( $code_count >= self::RATE_LIMIT_CODE_MAX ) {
				return false;
			}
			set_transient( $code_key, $code_count + 1, HOUR_IN_SECONDS );
		}

		return true;
	}

	/**
	 * Best-effort client IP, used only as rate-limit key material.
	 *
	 * @return string
	 */
	private static function client_ip() {
		$candidates = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		foreach ( $candidates as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$value = explode( ',', (string) $_SERVER[ $key ] );
				$ip    = trim( $value[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return 'unknown';
	}

	/**
	 * Whether the current user may administer this class.
	 *
	 * Public because Rest_Lxp_Roster_Vault gates on exactly the same rule —
	 * one definition of "owns this class" beats two that can drift apart.
	 *
	 * @param  int $class_id
	 * @return bool
	 */
	public static function can_manage_class( $class_id ) {
		if ( ! $class_id || ! is_user_logged_in() ) {
			return false;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$class = get_post( $class_id );
		if ( ! $class || TL_CLASS_CPT !== $class->post_type ) {
			return false;
		}

		$teacher_post_id = (int) get_post_meta( $class_id, 'lxp_class_teacher_id', true );
		if ( ! $teacher_post_id ) {
			return false;
		}

		$teacher_user_id = (int) get_post_meta( $teacher_post_id, 'lxp_teacher_admin_id', true );

		return $teacher_user_id && get_current_user_id() === $teacher_user_id;
	}

	/**
	 * Minimal provisioning audit for the teacher/school.
	 *
	 * Deliberately records no IP address and no device fingerprint.
	 *
	 * @param string $event
	 * @param int    $class_id
	 * @param int    $user_id
	 * @param string $joined_via
	 */
	private static function audit( $event, $class_id, $user_id, $joined_via ) {
		/**
		 * Fires on each token-student provisioning event.
		 *
		 * @param string $event      'provisioned' | 'claim_resumed' | 'claim_reissued'
		 * @param int    $class_id
		 * @param int    $user_id    Token WP user ID.
		 * @param string $joined_via 'code' | 'roster'
		 * @param string $timestamp  MySQL datetime.
		 */
		do_action( 'tl_lxp_enrollment_audit', $event, $class_id, $user_id, $joined_via, current_time( 'mysql' ) );
	}

	// =========================================================================
	// Small helpers
	// =========================================================================

	/**
	 * Failure response.
	 *
	 * Reasons that concern **the code itself** all share one generic message, so
	 * a guesser cannot tell "no such code" from "revoked" from "expired" — that
	 * indistinguishability is the point and must not be optimised away for
	 * friendlier copy. The specific reason still travels as a machine `code`
	 * for logging.
	 *
	 * Reasons that concern what the student typed, or the state of a class they
	 * demonstrably already have the code for, leak nothing and so say what is
	 * actually wrong. Without this a rejected nickname reported "That class code
	 * could not be used", sending the student to fix the one field that was fine.
	 *
	 * @param  string $reason
	 */
	private static function reject( $reason ) {
		$status = 'rate_limited' === $reason ? 429 : 400;

		$specific = array(
			'bad_alias'    => __( 'Please pick a different nickname — 2 to 32 letters or numbers, and no email addresses or phone numbers.', 'tinylxp' ),
			'seat_taken'   => __( 'Somebody just took that spot. Please try another nickname.', 'tinylxp' ),
			'class_full'   => __( 'This class is full. Please check with your teacher.', 'tinylxp' ),
			'rate_limited' => __( 'Too many tries. Please wait a few minutes and try again.', 'tinylxp' ),
		);

		$message = isset( $specific[ $reason ] )
			? $specific[ $reason ]
			: __( 'That class code could not be used. Please check it with your teacher.', 'tinylxp' );

		return wp_send_json_error(
			array(
				'code'    => $reason,
				'message' => $message,
			),
			$status
		);
	}

	/**
	 * Opaque, collision-resistant login for a token account.
	 *
	 * @return string
	 */
	private static function generate_token_login() {
		do {
			$login = 'stu_' . bin2hex( random_bytes( 6 ) );
		} while ( get_user_by( 'login', $login ) );

		return $login;
	}

	/**
	 * A per-student claim secret. Only its SHA-256 is persisted.
	 *
	 * @return string
	 */
	private static function generate_claim_token() {
		return bin2hex( random_bytes( 24 ) );
	}

	/**
	 * Build the student-facing claim URL.
	 *
	 * Carries `class_code` alongside `claim` so the Student Courses widget can
	 * open straight on this student's class instead of its class-picker step
	 * (see its `getParam()` / `showStep2()` deep-link handling). A token student
	 * is normally in exactly one class, so the picker is pure friction.
	 *
	 * @param  string $raw_token
	 * @param  int    $class_id
	 * @return string
	 */
	private static function build_claim_url( $raw_token, $class_id = 0 ) {
		/**
		 * Filter the page a claim link points at.
		 *
		 * @param string $base Default: the /student-courses/ landing page.
		 */
		$base = apply_filters( 'tl_lxp_claim_base_url', home_url( '/student-courses/' ) );

		$args = array( 'claim' => $raw_token );

		$code = $class_id ? get_post_meta( absint( $class_id ), 'lxp_class_code', true ) : '';
		if ( $code ) {
			$args['class_code'] = $code;
		}

		return add_query_arg( $args, $base );
	}

	/**
	 * Where to send a student once their session starts.
	 *
	 * The Student Courses landing page, not straight into a course — a fresh
	 * token student has no context yet for a single course; landing on the page
	 * that lists everything they're enrolled in is the same pattern the Student
	 * Courses widget already uses for regular students. `class_code` deep-links
	 * past that widget's class picker.
	 *
	 * @param  int $class_id
	 * @return string
	 */
	private static function landing_url( $class_id ) {
		$base = home_url( '/student-courses/' );

		$code = get_post_meta( absint( $class_id ), 'lxp_class_code', true );
		if ( $code ) {
			$base = add_query_arg( 'class_code', $code, $base );
		}

		/**
		 * Filter the page a student lands on after redeeming a code or
		 * resuming a claim link. Mirrors 'tl_lxp_claim_base_url'.
		 *
		 * @param string $url      Default: /student-courses/?class_code=…
		 * @param int    $class_id
		 */
		return apply_filters( 'tl_lxp_redirect_after_join_url', $base, $class_id );
	}

	/**
	 * Generate a unique 6-char class code.
	 *
	 * Mirrors Rest_Lxp_Class::generate_class_code(), which is private there;
	 * the alphabet excludes visually ambiguous characters (0/O, 1/I).
	 *
	 * @return string
	 */
	private static function generate_class_code() {
		$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		do {
			$code = '';
			for ( $i = 0; $i < 6; $i++ ) {
				$code .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
			}
			$existing = get_posts( array(
				'post_type'      => TL_CLASS_CPT,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => 'lxp_class_code',
				'meta_value'     => $code,
			) );
		} while ( ! empty( $existing ) );

		return $code;
	}
}
