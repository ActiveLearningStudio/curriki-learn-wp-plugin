<?php

/**
 * REST endpoint for saving a learner's course-certificate details.
 *
 * Route (namespace lms/v1):
 *   POST /certificate/save — store name / email / school for one course.
 *
 * There is no download route: the PDF is captured in the browser from the
 * rendered certificate (LXP Certificate widget) so it matches the client's
 * design pixel for pixel. This endpoint only persists what gets printed.
 *
 * Cookie-authenticated callers must send X-WP-Nonce (CLAUDE.md gotcha #16).
 *
 * @see lms/repositories/class-certificate-repository.php
 */
class Rest_Lxp_Certificate {

	const NAME_MAX   = 100;
	const SCHOOL_MAX = 150;

	public static function init() {
		register_rest_route(
			'lms/v1',
			'/certificate/save',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( 'Rest_Lxp_Certificate', 'save' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	public static function save( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to save your certificate.', 'tinylxp' ) ), 401 );
		}

		$user_id   = get_current_user_id();
		$course_id = absint( $request->get_param( 'course_id' ) );
		$repo      = new TL_Certificate_Repository();

		if ( $course_id <= 0 || ! $repo->is_eligible( $user_id, $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'A certificate is not available for this course yet.', 'tinylxp' ) ), 403 );
		}

		$name      = sanitize_text_field( wp_unslash( (string) $request->get_param( 'name' ) ) );
		$raw_email = trim( wp_unslash( (string) $request->get_param( 'email' ) ) );
		$email     = sanitize_email( $raw_email );
		$school    = sanitize_text_field( wp_unslash( (string) $request->get_param( 'school' ) ) );

		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Please type your full name.', 'tinylxp' ) ), 400 );
		}
		if ( mb_strlen( $name ) > self::NAME_MAX ) {
			wp_send_json_error( array( 'message' => sprintf( __( 'Your name must be %d characters or fewer.', 'tinylxp' ), self::NAME_MAX ) ), 400 );
		}
		if ( '' !== $raw_email && ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address, or leave it blank.', 'tinylxp' ) ), 400 );
		}
		if ( mb_strlen( $school ) > self::SCHOOL_MAX ) {
			wp_send_json_error( array( 'message' => sprintf( __( 'School name must be %d characters or fewer.', 'tinylxp' ), self::SCHOOL_MAX ) ), 400 );
		}

		$saved = $repo->save( $user_id, $course_id, $name, $email, $school );

		wp_send_json_success( array(
			'name'   => $saved['name'],
			'email'  => $saved['email'],
			'school' => $saved['school'],
		) );
	}
}
