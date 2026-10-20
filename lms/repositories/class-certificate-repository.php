<?php

/**
 * Course completion certificates — eligibility and the learner's saved details.
 *
 * A course issues a certificate only when an author ticks "Issue certificate"
 * in its Curriki Certificate meta box. A learner is eligible once LearnPress
 * records that course as finished *and* passed.
 *
 * The name / email / school printed on the certificate are what the learner
 * typed on the certificate page, stored per course in user meta
 * (lxp_certificate_{course_id}). They are never copied back into the account's
 * own user_email / display_name. The completion date is not stored — it is
 * always LearnPress's end_time, so it cannot be edited.
 *
 * Used from REST, Elementor widgets and the course page alike, so it must not
 * call the lxp_*() helpers in lxp/functions.php (not loaded in REST context —
 * CLAUDE.md gotcha #13).
 *
 * @see docs/certificate-context.md
 */
class TL_Certificate_Repository {

	const META_ENABLED  = 'lxp_course_issue_certificate';
	const META_SUBTITLE = 'lxp_course_certificate_subtitle';
	const USER_META     = 'lxp_certificate_';
	const OPTION_PAGE   = 'tl_certificate_page_id';

	/** Token-student "sink" mailbox domain — never prefill it as a real email. */
	const SINK_EMAIL_DOMAIN = 'students.curriki.local';

	public function is_course_enabled( $course_id ) {
		return '1' === (string) get_post_meta( absint( $course_id ), self::META_ENABLED, true );
	}

	public function get_subtitle( $course_id ) {
		return (string) get_post_meta( absint( $course_id ), self::META_SUBTITLE, true );
	}

	/**
	 * LearnPress's finish time for a passed course, or null when the user has
	 * not finished it, or finished it without passing.
	 *
	 * @param bool $fresh Bypass LearnPress's cache — needed inside the
	 *                    course-finished hook, where the cached row can still
	 *                    say "enrolled".
	 * @return string|null MySQL datetime (GMT, as LearnPress stores it).
	 */
	public function get_completion( $user_id, $course_id, $fresh = false ) {
		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );
		if ( $user_id <= 0 || $course_id <= 0 || ! class_exists( '\LearnPress\Models\UserItems\UserCourseModel' ) ) {
			return null;
		}

		$user_course = \LearnPress\Models\UserItems\UserCourseModel::find( $user_id, $course_id, ! $fresh );
		if ( ! $user_course ) {
			return null;
		}

		$finished = defined( 'LP_COURSE_FINISHED' ) ? LP_COURSE_FINISHED : 'finished';
		$passed   = defined( 'LP_COURSE_GRADUATION_PASSED' ) ? LP_COURSE_GRADUATION_PASSED : 'passed';
		if ( $user_course->get_status() !== $finished || $user_course->get_graduation() !== $passed ) {
			return null;
		}

		$end_time = $user_course->get_end_time();

		return '' !== $end_time ? $end_time : null;
	}

	public function is_eligible( $user_id, $course_id, $fresh = false ) {
		return $this->is_course_enabled( $course_id ) && null !== $this->get_completion( $user_id, $course_id, $fresh );
	}

	/**
	 * Completion date as printed on the certificate ("Month DD, YYYY").
	 */
	public function format_completion_date( $end_time ) {
		if ( empty( $end_time ) ) {
			return '';
		}
		$timestamp = strtotime( $end_time . ' UTC' );

		return $timestamp ? wp_date( 'F j, Y', $timestamp ) : '';
	}

	/**
	 * The learner's saved details for one course, or null if never saved.
	 *
	 * @return array|null { name, email, school, issued_at, updated_at }
	 */
	public function get( $user_id, $course_id ) {
		$data = get_user_meta( absint( $user_id ), self::USER_META . absint( $course_id ), true );
		if ( ! is_array( $data ) ) {
			return null;
		}

		return wp_parse_args( $data, array(
			'name'       => '',
			'email'      => '',
			'school'     => '',
			'issued_at'  => '',
			'updated_at' => '',
		) );
	}

	/**
	 * Upsert the learner's details. issued_at is set once, on the first save,
	 * and survives every later edit.
	 */
	public function save( $user_id, $course_id, $name, $email, $school ) {
		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );
		$existing  = $this->get( $user_id, $course_id );
		$now       = current_time( 'mysql', true );

		$data = array(
			'name'       => $name,
			'email'      => $email,
			'school'     => $school,
			'issued_at'  => ( $existing && ! empty( $existing['issued_at'] ) ) ? $existing['issued_at'] : $now,
			'updated_at' => $now,
		);

		update_user_meta( $user_id, self::USER_META . $course_id, $data );

		return $data;
	}

	/**
	 * Best-guess values for a learner who has not saved anything yet.
	 *
	 * @return array { name, email, school }
	 */
	public function get_prefill( $user_id ) {
		$user = get_userdata( absint( $user_id ) );
		if ( ! $user ) {
			return array( 'name' => '', 'email' => '', 'school' => '' );
		}

		$name = trim( $user->first_name . ' ' . $user->last_name );
		if ( '' === $name ) {
			$name = (string) $user->display_name;
		}

		$email = (string) $user->user_email;
		if ( '' !== $email && self::SINK_EMAIL_DOMAIN === strtolower( substr( strrchr( $email, '@' ), 1 ) ) ) {
			$email = '';
		}

		return array(
			'name'   => $name,
			'email'  => $email,
			'school' => $this->resolve_school_name( $user->ID ),
		);
	}

	/**
	 * Certificate page URL for a course, or '' when no page is configured.
	 */
	public function get_page_url( $course_id ) {
		$page_id = absint( get_option( self::OPTION_PAGE, 0 ) );
		if ( $page_id <= 0 || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}

		return add_query_arg( 'course_id', absint( $course_id ), get_permalink( $page_id ) );
	}

	/**
	 * School name via the learner's tl_student post, falling back to their
	 * tl_teacher post (teachers taking PD courses).
	 */
	private function resolve_school_name( $user_id ) {
		$lookups = array(
			array( TL_STUDENT_CPT, 'lxp_student_admin_id', 'lxp_student_school_id' ),
			array( TL_TEACHER_CPT, 'lxp_teacher_admin_id', 'lxp_teacher_school_id' ),
		);

		foreach ( $lookups as $lookup ) {
			list( $post_type, $user_key, $school_key ) = $lookup;

			$posts = get_posts( array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array( 'key' => $user_key, 'value' => absint( $user_id ), 'compare' => '=' ),
				),
			) );
			if ( empty( $posts ) ) {
				continue;
			}

			$school_id = absint( get_post_meta( $posts[0], $school_key, true ) );
			if ( $school_id > 0 && get_post( $school_id ) ) {
				return get_the_title( $school_id );
			}
		}

		return '';
	}
}
