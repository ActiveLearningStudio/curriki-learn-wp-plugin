<?php

/**
 * Repository for LearnPress course enrollment.
 *
 * This plugin historically only ever *read* enrollment (via LP_User_Items_DB);
 * this is the first writer. It inserts course-level rows into
 * {prefix}learnpress_user_items so a token student provisioned by the class
 * code-redemption flow lands in the course without going through LP's own
 * front-end enroll button.
 *
 * NOTE on parent_id: the privacy spec asked for `parent_id = class post ID`.
 * LP4 already owns that column — for a course row it is 0, and for lesson/quiz
 * rows it holds the parent course's user_item_id. Writing a class post ID there
 * would collide with LP's child-item lookups. The class association is stored
 * in learnpress_user_itemmeta (`_lxp_class_id`) and in lxp_class_members
 * instead; parent_id stays 0.
 */
class TL_Enrollment_Repository {

	/** @var wpdb */
	private $wpdb;

	/** @var string Fully-qualified LearnPress user items table. */
	private $table;

	/**
	 * @param wpdb|null $wpdb_instance Inject a custom wpdb for testing; defaults to global.
	 */
	public function __construct( $wpdb_instance = null ) {
		global $wpdb;
		$this->wpdb  = $wpdb_instance ?? $wpdb;
		$this->table = $this->wpdb->prefix . 'learnpress_user_items';
	}

	// -------------------------------------------------------------------------
	// Write
	// -------------------------------------------------------------------------

	/**
	 * Enrol a user in a course, tying the enrollment to a class.
	 *
	 * Idempotent — if the user already has a course row, the existing
	 * user_item_id is returned and no second row is created.
	 *
	 * @param  int $user_id   WordPress user ID (token student).
	 * @param  int $course_id lp_course post ID.
	 * @param  int $class_id  tl_class post ID (consent + grouping provenance).
	 * @return int|false      user_item_id on success, false on failure.
	 */
	public function enroll( $user_id, $course_id, $class_id = 0 ) {
		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );
		$class_id  = absint( $class_id );

		if ( ! $user_id || ! $course_id ) {
			return false;
		}

		$existing = $this->get_course_item_id( $user_id, $course_id );
		if ( $existing ) {
			// Already enrolled — make sure the class link is present, then bail.
			if ( $class_id ) {
				$this->set_class_meta( $existing, $class_id );
			}
			return $existing;
		}

		$inserted = $this->wpdb->insert(
			$this->table,
			array(
				'user_id'      => $user_id,
				'item_id'      => $course_id,
				'item_type'    => 'lp_course',
				'status'       => 'enrolled',
				'graduation'   => 'in-progress',
				'access_level' => 50,
				'start_time'   => current_time( 'mysql' ),
				'parent_id'    => 0,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d' )
		);

		if ( ! $inserted ) {
			return false;
		}

		$user_item_id = (int) $this->wpdb->insert_id;

		if ( $class_id ) {
			$this->set_class_meta( $user_item_id, $class_id );
		}

		$this->flush_lp_caches( $user_id );

		return $user_item_id;
	}

	/**
	 * Enrol a user in several courses at once.
	 *
	 * @param  int   $user_id
	 * @param  int[] $course_ids
	 * @param  int   $class_id
	 * @return array Map of course_id => user_item_id|false.
	 */
	public function enroll_many( $user_id, array $course_ids, $class_id = 0 ) {
		$results = array();
		foreach ( $course_ids as $course_id ) {
			$course_id             = absint( $course_id );
			$results[ $course_id ] = $this->enroll( $user_id, $course_id, $class_id );
		}
		return $results;
	}

	/**
	 * Remove a user's course row. Used only for rollback of a failed provision.
	 *
	 * @param  int $user_id
	 * @param  int $course_id
	 * @return bool
	 */
	public function remove( $user_id, $course_id ) {
		$user_item_id = $this->get_course_item_id( $user_id, $course_id );
		if ( ! $user_item_id ) {
			return false;
		}

		if ( function_exists( 'learn_press_delete_user_item_meta' ) ) {
			learn_press_delete_user_item_meta( $user_item_id, '_lxp_class_id' );
		}

		$deleted = $this->wpdb->delete(
			$this->table,
			array( 'user_item_id' => $user_item_id ),
			array( '%d' )
		);

		$this->flush_lp_caches( $user_id );

		return (bool) $deleted;
	}

	// -------------------------------------------------------------------------
	// Read
	// -------------------------------------------------------------------------

	/**
	 * Get the user_item_id of a user's course-level enrollment row.
	 *
	 * @param  int $user_id
	 * @param  int $course_id
	 * @return int 0 when not enrolled.
	 */
	public function get_course_item_id( $user_id, $course_id ) {
		$row_id = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT user_item_id FROM {$this->table}
				 WHERE user_id = %d AND item_id = %d AND item_type = %s
				 ORDER BY user_item_id DESC LIMIT 1",
				absint( $user_id ),
				absint( $course_id ),
				'lp_course'
			)
		);

		return $row_id ? (int) $row_id : 0;
	}

	/**
	 * Whether a user already has a course-level row for this course.
	 *
	 * @param  int $user_id
	 * @param  int $course_id
	 * @return bool
	 */
	public function is_enrolled( $user_id, $course_id ) {
		return $this->get_course_item_id( $user_id, $course_id ) > 0;
	}

	/**
	 * Course IDs a user is enrolled in through a given class.
	 *
	 * @param  int $user_id
	 * @param  int $class_id
	 * @return int[]
	 */
	public function get_courses_for_class( $user_id, $class_id ) {
		$meta_table = $this->wpdb->prefix . 'learnpress_user_itemmeta';

		$ids = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT ui.item_id
				 FROM {$this->table} ui
				 INNER JOIN {$meta_table} um
				         ON um.learnpress_user_item_id = ui.user_item_id
				 WHERE ui.user_id = %d
				   AND ui.item_type = %s
				   AND um.meta_key = %s
				   AND um.meta_value = %s",
				absint( $user_id ),
				'lp_course',
				'_lxp_class_id',
				(string) absint( $class_id )
			)
		);

		return array_map( 'absint', (array) $ids );
	}

	// -------------------------------------------------------------------------
	// Internal
	// -------------------------------------------------------------------------

	/**
	 * Record the originating class on the enrollment row.
	 *
	 * @param int $user_item_id
	 * @param int $class_id
	 */
	private function set_class_meta( $user_item_id, $class_id ) {
		if ( function_exists( 'learn_press_update_user_item_meta' ) ) {
			learn_press_update_user_item_meta( $user_item_id, '_lxp_class_id', absint( $class_id ) );
			return;
		}

		// LearnPress helper unavailable — write the meta row directly.
		$meta_table = $this->wpdb->prefix . 'learnpress_user_itemmeta';

		$existing = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT meta_id FROM {$meta_table}
				 WHERE learnpress_user_item_id = %d AND meta_key = %s LIMIT 1",
				absint( $user_item_id ),
				'_lxp_class_id'
			)
		);

		if ( $existing ) {
			$this->wpdb->update(
				$meta_table,
				array( 'meta_value' => (string) absint( $class_id ) ),
				array( 'meta_id' => (int) $existing ),
				array( '%s' ),
				array( '%d' )
			);
			return;
		}

		$this->wpdb->insert(
			$meta_table,
			array(
				'learnpress_user_item_id' => absint( $user_item_id ),
				'meta_key'                => '_lxp_class_id',
				'meta_value'              => (string) absint( $class_id ),
			),
			array( '%d', '%s', '%s' )
		);
	}

	/**
	 * Drop LearnPress' cached user/course state so has_enrolled_course() sees
	 * the row we just inserted behind LP's back.
	 *
	 * Guarded throughout — LP's cache API has moved between 3.x and 4.x.
	 *
	 * @param int $user_id
	 */
	private function flush_lp_caches( $user_id ) {
		$user_id = absint( $user_id );

		if ( function_exists( 'learn_press_reset_user_cache' ) ) {
			learn_press_reset_user_cache( $user_id );
		}

		if ( class_exists( 'LP_User_Items_Cache' ) && method_exists( 'LP_User_Items_Cache', 'instance' ) ) {
			$cache = LP_User_Items_Cache::instance();
			if ( method_exists( $cache, 'clean_user_items' ) ) {
				$cache->clean_user_items( $user_id );
			}
		}

		if ( class_exists( 'LP_Cache' ) && method_exists( 'LP_Cache', 'cleanCache' ) ) {
			// Static helper in newer LP4 builds; harmless when the group is absent.
			LP_Cache::cleanCache( 'lp/user/course/%' );
		}

		wp_cache_delete( $user_id, 'learn-press/user-items' );
	}
}
