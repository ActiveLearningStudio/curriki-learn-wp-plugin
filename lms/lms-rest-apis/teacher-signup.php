<?php

/**
 * Teacher self-signup.
 *
 * A teacher creates their own account from a public form and is signed in and
 * dropped onto /classes. Every account lands in one preconfigured school, set
 * by an administrator under Curriki Learn -> Teacher Signup — the form never
 * asks which school, because a public form that lets the caller choose their
 * own school is a public form that lets anyone join any school.
 *
 * This is deliberately separate from Rest_Lxp_Teacher::create(), which is the
 * admin-driven path: it takes school_admin_id and teacher_school_id straight
 * from the request and performs no capability check at all. Reusing it here
 * would expose that to the internet.
 *
 * @see admin/class-tiny-lxp-platform-admin.php  Teacher Signup settings page
 * @see lms/lms-rest-apis/class-redemption.php   The other public provisioner
 */
class Rest_Lxp_Teacher_Signup {

	/** WP option holding the tl_school post ID every signup is attached to. */
	const SCHOOL_OPTION = 'tl_signup_school_id';

	/** WP option holding the tl_district post ID that scopes the school picker. */
	const DISTRICT_OPTION = 'tl_signup_district_id';

	/** Minimum password length accepted. */
	const MIN_PASSWORD_LENGTH = 8;

	/** Rate limit: signup attempts per hashed IP per window. */
	const RATE_LIMIT_IP_MAX = 5;

	/** Rate limit: seconds in the per-IP window. */
	const RATE_LIMIT_IP_WINDOW = 900;

	/**
	 * Register the REST API routes.
	 */
	public static function init() {
		if ( ! function_exists( 'register_rest_route' ) ) {
			return false;
		}

		// Public by necessity — the caller has no account yet. Hardened inside
		// the callback (rate limit, validation, server-chosen school), which is
		// this codebase's convention rather than a real permission_callback.
		register_rest_route( 'lms/v1', '/teacher/signup', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( 'Rest_Lxp_Teacher_Signup', 'signup' ),
				'permission_callback' => '__return_true',
			),
		) );

		return true;
	}

	/**
	 * Create a teacher account, sign it in, and hand back where to go next.
	 *
	 * @param  WP_REST_Request $request
	 * @return void  Responds via wp_send_json_*.
	 */
	public static function signup( $request ) {
		if ( ! self::check_rate_limit() ) {
			return self::reject( 'rate_limited', __( 'Too many attempts. Please wait a few minutes and try again.', 'tinylxp' ), 429 );
		}

		if ( is_user_logged_in() ) {
			return self::reject( 'already_logged_in', __( 'You are already signed in.', 'tinylxp' ) );
		}

		$first  = sanitize_text_field( (string) $request->get_param( 'lxp_first_name' ) );
		$last   = sanitize_text_field( (string) $request->get_param( 'lxp_last_name' ) );
		$email  = sanitize_email( (string) $request->get_param( 'lxp_user_email' ) );
		$pass   = (string) $request->get_param( 'lxp_user_password' );
		$pass2  = (string) $request->get_param( 'lxp_user_password_confirm' );
		$grades = $request->get_param( 'grades' );

		// A plain opt-in tick. Absent (an older client) reads as unticked.
		$pd = ( '1' === (string) $request->get_param( 'teacher_professional_development' ) );

		if ( '' === trim( $first ) || '' === trim( $last ) ) {
			return self::reject( 'bad_name', __( 'Please enter both your first and last name.', 'tinylxp' ) );
		}

		if ( ! is_email( $email ) ) {
			return self::reject( 'bad_email', __( 'Please enter a valid email address.', 'tinylxp' ) );
		}

		// A signup form has to say "that email is taken" to be usable at all.
		// It is a mild account-enumeration surface, accepted knowingly here —
		// unlike the student redemption endpoint, where generic failures are a
		// deliberate anti-guessing measure.
		if ( email_exists( $email ) || username_exists( $email ) ) {
			return self::reject( 'email_taken', __( 'An account already exists for that email address. Try signing in instead.', 'tinylxp' ) );
		}

		if ( strlen( $pass ) < self::MIN_PASSWORD_LENGTH ) {
			return self::reject(
				'weak_password',
				sprintf(
					/* translators: %d: minimum number of characters. */
					__( 'Please choose a password of at least %d characters.', 'tinylxp' ),
					self::MIN_PASSWORD_LENGTH
				)
			);
		}

		if ( $pass !== $pass2 ) {
			return self::reject( 'password_mismatch', __( 'The two passwords do not match.', 'tinylxp' ) );
		}

		$grades = self::sanitize_grades( $grades );

		// The teacher has to tell us something about what they teach, but the PD
		// tick counts on its own — an administrator registering faculty has no
		// grade to claim. Only a wholly empty selection is refused.
		if ( empty( $grades ) && ! $pd ) {
			return self::reject( 'bad_grades', __( 'Please choose at least one grade you teach, or tick Professional Development.', 'tinylxp' ) );
		}

		// --- Server-side placement -------------------------------------------
		$school_id = (int) get_option( self::SCHOOL_OPTION, 0 );
		$school    = $school_id ? get_post( $school_id ) : null;

		if ( ! $school || TL_SCHOOL_CPT !== $school->post_type ) {
			return self::reject( 'not_configured', __( 'Teacher signup is not configured yet. Please contact the site administrator.', 'tinylxp' ) );
		}

		// A school with no district makes /classes fatal downstream — that page
		// reads the district post's meta without a null guard. Refuse to create
		// an account that cannot reach its own landing page.
		if ( ! get_post_meta( $school->ID, 'lxp_school_district_id', true ) ) {
			return self::reject( 'school_no_district', __( 'Teacher signup is not configured correctly. Please contact the site administrator.', 'tinylxp' ) );
		}

		$result = self::provision_teacher( $first, $last, $email, $pass, $grades, (int) $school->ID, $pd );

		if ( is_wp_error( $result ) ) {
			return self::reject( 'create_failed', __( 'We could not create your account. Please try again.', 'tinylxp' ) );
		}

		// Start the session. wp_set_current_user() too, so anything hooked later
		// in this request sees the new user rather than an anonymous one.
		wp_set_auth_cookie( $result['user_id'], true );
		wp_set_current_user( $result['user_id'] );

		/**
		 * Fires after a teacher provisions their own account.
		 *
		 * @param int $user_id    New WP user ID (role lp_teacher).
		 * @param int $teacher_id New tl_teacher post ID.
		 * @param int $school_id  tl_school post the account was attached to.
		 */
		do_action( 'tl_lxp_teacher_signup', $result['user_id'], $result['teacher_id'], (int) $school->ID );

		return wp_send_json_success( array(
			'redirect_url' => apply_filters( 'tl_lxp_teacher_signup_redirect_url', home_url( '/classes/' ), $result['user_id'] ),
		) );
	}

	// =========================================================================
	// Provisioning
	// =========================================================================

	/**
	 * Create the WP user and its tl_teacher post as one unit.
	 *
	 * Both halves are mandatory: teacher-dashboard.php hard-die()s for an
	 * lp_teacher user with no tl_teacher post, and lxp_get_teacher_post() is how
	 * every teacher screen finds its data. A half-created account is an
	 * unrecoverable dead end for the person who just signed up, so a failure
	 * anywhere rolls the whole thing back.
	 *
	 * Compensating deletes rather than a DB transaction: wp_insert_user() and
	 * wp_insert_post() fire hooks that other plugins may act on outside our
	 * transaction scope, so an explicit unwind is the honest mechanism.
	 *
	 * @param  string   $first
	 * @param  string   $last
	 * @param  string   $email
	 * @param  string   $pass
	 * @param  string[] $grades
	 * @param  int      $school_id
	 * @param  bool     $pd Whether Professional Development was ticked.
	 * @return array{user_id:int,teacher_id:int}|WP_Error
	 */
	private static function provision_teacher( $first, $last, $email, $pass, $grades, $school_id, $pd = false ) {
		// "Last, First" matches Rest_Lxp_Teacher::create() so both paths produce
		// records that sort and read identically in the teacher lists.
		$display_name = wp_strip_all_tags( $last . ', ' . $first );

		$user_id = wp_insert_user( array(
			'user_login'   => $email,
			'user_email'   => $email,
			'user_pass'    => $pass,
			'first_name'   => $first,
			'last_name'    => $last,
			'display_name' => $display_name,
			'role'         => 'lp_teacher',
		) );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$teacher_post_id = wp_insert_post( array(
			'post_title'   => $display_name,
			'post_content' => $display_name,
			'post_status'  => 'publish',
			'post_author'  => $user_id,
			'post_type'    => TL_TEACHER_CPT,
		), true );

		if ( is_wp_error( $teacher_post_id ) || ! $teacher_post_id ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user_id );

			return is_wp_error( $teacher_post_id ) ? $teacher_post_id : new WP_Error( 'tl_teacher_post_failed', 'Could not create teacher post.' );
		}

		update_post_meta( $teacher_post_id, 'lxp_teacher_admin_id', $user_id );
		update_post_meta( $teacher_post_id, 'lxp_teacher_school_id', $school_id );
		// Still JSON-encoded when empty, so this reads "[]" rather than "".
		// Rest_Lxp_Class::save_class_grades() json_decode()s this meta; "[]"
		// decodes to an empty array where "" would decode to null.
		update_post_meta( $teacher_post_id, 'grades', wp_json_encode( $grades ) );
		update_post_meta( $teacher_post_id, 'teacher_professional_development', $pd ? '1' : '0' );
		update_post_meta( $teacher_post_id, 'settings_active', '1' );

		return array(
			'user_id'    => (int) $user_id,
			'teacher_id' => (int) $teacher_post_id,
		);
	}

	// =========================================================================
	// Validation helpers
	// =========================================================================

	/**
	 * Keep only values from the canonical grade list.
	 *
	 * @param  mixed $grades
	 * @return string[]
	 */
	private static function sanitize_grades( $grades ) {
		if ( ! is_array( $grades ) ) {
			return array();
		}

		// functions.php is not loaded in REST context (CLAUDE.md gotcha #13),
		// but lxp_get_grade_options() lives in tl-constants.php precisely so
		// this endpoint can reach it.
		$allowed = function_exists( 'lxp_get_grade_options' ) ? lxp_get_grade_options() : array();

		$clean = array();
		foreach ( $grades as $grade ) {
			$grade = sanitize_text_field( (string) $grade );
			if ( '' !== $grade && ( empty( $allowed ) || in_array( $grade, $allowed, true ) ) ) {
				$clean[] = $grade;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Per-IP signup rate limiting.
	 *
	 * The IP is hashed before use and never stored — it is transient key
	 * material only, matching the treatment in Rest_Lxp_Class_Redemption.
	 *
	 * @return bool False when the caller should be turned away.
	 */
	private static function check_rate_limit() {
		$key   = 'lxp_rl_signup_' . substr( wp_hash( self::client_ip() ), 0, 32 );
		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT_IP_MAX ) {
			return false;
		}

		set_transient( $key, $count + 1, self::RATE_LIMIT_IP_WINDOW );

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
	 * Uniform failure response.
	 *
	 * Unlike the student redemption endpoint, messages here are specific: the
	 * person filling in this form needs to know what to fix, and none of these
	 * reasons leak anything about another user's data.
	 *
	 * @param  string $code
	 * @param  string $message
	 * @param  int    $status
	 * @return void
	 */
	private static function reject( $code, $message, $status = 400 ) {
		return wp_send_json_error(
			array(
				'code'    => $code,
				'message' => $message,
			),
			$status
		);
	}
}
