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
 * Writes go through LearnPress' own data layer wherever it is available:
 * `LearnPress\Models\UserItems\UserCourseModel::save()` (LP 4.2.5+). That is the
 * same path LP's admin "Assign courses to users" tool uses — see
 * LP_REST_Admin_Tools_Controller::assign_courses_to_users() — so we inherit LP's
 * exact cache invalidation, including the course student-count caches that a raw
 * INSERT leaves stale. A direct $wpdb write remains as a fallback for older LP.
 *
 * NOTE on parent_id: the privacy spec asked for `parent_id = class post ID`.
 * LP4 already owns that column — for a course row it is 0, and for lesson/quiz
 * rows it holds the parent course's user_item_id. Writing a class post ID there
 * would collide with LP's child-item lookups. LP's own assign tool likewise
 * leaves it at 0. The class association is stored in learnpress_user_itemmeta
 * (`_lxp_class_id`) and in lxp_class_members instead; parent_id stays 0.
 *
 * NOTE on ref_type: UserCourseModel defaults it to LP_ORDER_CPT. A redeemed
 * class seat has no order behind it, so we clear it — again matching LP's
 * assign tool. Times are stored in UTC (gmdate), which is LP's convention.
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
	 * This deliberately differs from LP's own assign tool, which calls
	 * delete_user_items_old() first and so wipes progress on every re-assign.
	 * A returning student resuming a claim link must keep their progress, so we
	 * no-op instead of recreating.
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

		$user_item_id = $this->insert_enrollment( $user_id, $course_id );

		if ( ! $user_item_id ) {
			return false;
		}

		if ( $class_id ) {
			$this->set_class_meta( $user_item_id, $class_id );
		}

		return $user_item_id;
	}

	/**
	 * Create the course-level enrollment row.
	 *
	 * Prefers LP's own model; falls back to a direct write only when that model
	 * is unavailable (LP older than 4.2.5, or LP not loaded).
	 *
	 * @param  int $user_id
	 * @param  int $course_id
	 * @return int user_item_id, or 0 on failure.
	 */
	private function insert_enrollment( $user_id, $course_id ) {
		if ( class_exists( '\LearnPress\Models\UserItems\UserCourseModel' ) ) {
			$user_item_id = $this->insert_via_lp_model( $user_id, $course_id );

			if ( $user_item_id ) {
				return $user_item_id;
			}

			// The model path failed. It may still have written a row before
			// throwing — check before inserting one ourselves, or we duplicate.
			$stray = $this->get_course_item_id( $user_id, $course_id );
			if ( $stray ) {
				return $stray;
			}
		}

		return $this->insert_direct( $user_id, $course_id );
	}

	/**
	 * Enrol through LearnPress' UserCourseModel (LP 4.2.5+).
	 *
	 * save() runs LP's own clean_caches(), which clears the user-item caches
	 * *and* the per-course student-count caches — the latter being what a raw
	 * INSERT silently leaves stale.
	 *
	 * @param  int $user_id
	 * @param  int $course_id
	 * @return int user_item_id, or 0 when the model path could not complete.
	 */
	private function insert_via_lp_model( $user_id, $course_id ) {
		try {
			$user_course = new \LearnPress\Models\UserItems\UserCourseModel();

			$user_course->user_id    = $user_id;
			$user_course->item_id    = $course_id;
			$user_course->item_type  = LP_COURSE_CPT;
			$user_course->ref_type   = ''; // No order behind a redeemed class seat.
			$user_course->status     = LP_COURSE_ENROLLED;
			$user_course->graduation = LP_COURSE_GRADUATION_IN_PROGRESS;
			$user_course->start_time = gmdate( 'Y-m-d H:i:s', time() );

			$user_course->save();

			$user_item_id = (int) $user_course->get_user_item_id();

			if ( ! $user_item_id ) {
				return 0;
			}

			/**
			 * Same signal LP fires from its admin "Assign courses to users" tool,
			 * so listeners treat a redeemed seat like any other assigned seat.
			 *
			 * Note we deliberately do NOT fire 'learnpress/user/course-enrolled':
			 * that one is the purchase path, expects an order ID we do not have,
			 * and triggers enrollment email to what is a sink address for a
			 * token student.
			 *
			 * Caveat: Rest_Lxp_Class_Redemption::provision_member() calls this
			 * inside a transaction, so listeners run before the commit — as they
			 * do in LP's own tool. If provisioning then fails, remove() undoes
			 * the rows but cannot undo a listener's side effects.
			 */
			do_action( 'learn-press/assigned-course-to-user', $user_course );

			return $user_item_id;
		} catch ( \Throwable $e ) {
			error_log( 'TL_Enrollment_Repository: LP model enrol failed — ' . $e->getMessage() );
			return 0;
		}
	}

	/**
	 * Fallback: write the enrollment row directly.
	 *
	 * Column set and UTC time mirror what LP's model would have written.
	 * `access_level` is omitted — the column defaults to 50, and LP's own model
	 * does not write it either.
	 *
	 * @param  int $user_id
	 * @param  int $course_id
	 * @return int user_item_id, or 0 on failure.
	 */
	private function insert_direct( $user_id, $course_id ) {
		$inserted = $this->wpdb->insert(
			$this->table,
			array(
				'user_id'    => $user_id,
				'item_id'    => $course_id,
				'item_type'  => 'lp_course',
				'status'     => 'enrolled',
				'graduation' => 'in-progress',
				'ref_type'   => '',
				'start_time' => gmdate( 'Y-m-d H:i:s', time() ),
				'parent_id'  => 0,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( ! $inserted ) {
			return 0;
		}

		$user_item_id = (int) $this->wpdb->insert_id;

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
	 * Prefers LP's model, whose delete() drops the row, its itemmeta and any
	 * child items, then clears the same caches its save() does.
	 *
	 * @param  int $user_id
	 * @param  int $course_id
	 * @return bool
	 */
	public function remove( $user_id, $course_id ) {
		$user_id      = absint( $user_id );
		$course_id    = absint( $course_id );
		$user_item_id = $this->get_course_item_id( $user_id, $course_id );

		if ( ! $user_item_id ) {
			return false;
		}

		if ( class_exists( '\LearnPress\Models\UserItems\UserCourseModel' ) ) {
			try {
				$user_course = \LearnPress\Models\UserItems\UserCourseModel::find( $user_id, $course_id, false );

				if ( $user_course instanceof \LearnPress\Models\UserItems\UserCourseModel ) {
					$user_course->delete();
					return true;
				}
			} catch ( \Throwable $e ) {
				error_log( 'TL_Enrollment_Repository: LP model delete failed — ' . $e->getMessage() );
				// Fall through to the direct delete below.
			}
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
	 * Best-effort cache flush for the direct-write fallback only.
	 *
	 * On LP 4.2.5+ this is not used for inserts: UserCourseModel::save() runs
	 * LP's own clean_caches(), which is both precise and more complete than
	 * anything we can do from outside (it also clears the per-course
	 * student-count caches). This remains for older LP, where the cache API
	 * differs — hence every call below is guarded.
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
