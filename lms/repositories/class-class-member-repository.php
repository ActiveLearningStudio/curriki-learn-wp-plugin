<?php

/**
 * Repository for class membership of zero-PII token students.
 *
 * Handles all DB access for {prefix}lxp_class_members — the Zone A record of
 * which token account holds which seat in which class, plus the hashed claim
 * secret that lets a returning student resume that account.
 *
 * Nothing in this table is PII: alias_label is a non-PII display label
 * ("Student 14"), and the claim secret is stored only as a SHA-256 hash.
 */
class TL_Class_Member_Repository {

	/** @var wpdb */
	private $wpdb;

	/** @var string Fully-qualified table name. */
	private $table;

	/**
	 * @param wpdb|null $wpdb_instance Inject a custom wpdb for testing; defaults to global.
	 */
	public function __construct( $wpdb_instance = null ) {
		global $wpdb;
		$this->wpdb  = $wpdb_instance ?? $wpdb;
		$this->table = $this->wpdb->prefix . 'lxp_class_members';
	}

	/**
	 * Hash a raw claim secret for storage/lookup.
	 *
	 * @param  string $raw_token
	 * @return string 64-char hex digest.
	 */
	public static function hash_claim_token( $raw_token ) {
		return hash( 'sha256', (string) $raw_token );
	}

	// -------------------------------------------------------------------------
	// Write
	// -------------------------------------------------------------------------

	/**
	 * Insert a membership row.
	 *
	 * @param  array $args {
	 *     @type int    $class_id
	 *     @type int    $student_post_id
	 *     @type int    $student_user_id
	 *     @type string $alias_label
	 *     @type string $joined_via         'code' | 'roster'
	 *     @type string $claim_token_hash
	 *     @type int    $consent_teacher_id
	 *     @type int    $consent_school_id
	 * }
	 * @return int|false Row ID, or false on failure (including alias collision).
	 */
	public function insert( array $args ) {
		$now = current_time( 'mysql' );

		$joined_via = isset( $args['joined_via'] ) && in_array( $args['joined_via'], array( 'code', 'roster' ), true )
			? $args['joined_via']
			: 'code';

		$inserted = $this->wpdb->insert(
			$this->table,
			array(
				'class_id'           => absint( $args['class_id'] ),
				'student_post_id'    => absint( $args['student_post_id'] ),
				'student_user_id'    => absint( $args['student_user_id'] ),
				'alias_label'        => sanitize_text_field( $args['alias_label'] ),
				'joined_via'         => $joined_via,
				'status'             => 'active',
				'claim_token_hash'   => (string) $args['claim_token_hash'],
				'claim_issued_at'    => $now,
				'consent_teacher_id' => absint( isset( $args['consent_teacher_id'] ) ? $args['consent_teacher_id'] : 0 ),
				'consent_school_id'  => absint( isset( $args['consent_school_id'] ) ? $args['consent_school_id'] : 0 ),
				'created_at'         => $now,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
		);

		return $inserted ? (int) $this->wpdb->insert_id : false;
	}

	/**
	 * Stamp the time a claim link was last exchanged for a session.
	 *
	 * @param  int $id
	 * @return bool
	 */
	public function touch_claim( $id ) {
		return (bool) $this->wpdb->update(
			$this->table,
			array( 'claim_last_used' => current_time( 'mysql' ) ),
			array( 'id' => absint( $id ) ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Replace a member's claim secret (teacher re-issue after a lost link).
	 *
	 * @param  int    $id
	 * @param  string $new_hash
	 * @return bool
	 */
	public function rotate_claim( $id, $new_hash ) {
		return (bool) $this->wpdb->update(
			$this->table,
			array(
				'claim_token_hash' => (string) $new_hash,
				'claim_issued_at'  => current_time( 'mysql' ),
				'claim_last_used'  => null,
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Mark a member removed, freeing their alias-seat for reuse.
	 *
	 * @param  int $id
	 * @return bool
	 */
	public function set_removed( $id ) {
		return (bool) $this->wpdb->update(
			$this->table,
			array( 'status' => 'removed' ),
			array( 'id' => absint( $id ) ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Hard-delete a row. Used only to roll back a failed provision.
	 *
	 * @param  int $id
	 * @return bool
	 */
	public function delete( $id ) {
		return (bool) $this->wpdb->delete(
			$this->table,
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);
	}

	// -------------------------------------------------------------------------
	// Read
	// -------------------------------------------------------------------------

	/**
	 * Count active members of a class (i.e. seats consumed).
	 *
	 * @param  int $class_id
	 * @return int
	 */
	public function count_active( $class_id ) {
		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE class_id = %d AND status = %s",
				absint( $class_id ),
				'active'
			)
		);
	}

	/**
	 * All members of a class, newest last.
	 *
	 * @param  int    $class_id
	 * @param  string $status 'active', 'removed', or 'any'.
	 * @return array  Row objects.
	 */
	public function get_by_class( $class_id, $status = 'active' ) {
		if ( 'any' === $status ) {
			return (array) $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT * FROM {$this->table} WHERE class_id = %d ORDER BY alias_label ASC",
					absint( $class_id )
				)
			);
		}

		return (array) $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE class_id = %d AND status = %s ORDER BY alias_label ASC",
				absint( $class_id ),
				$status
			)
		);
	}

	/**
	 * Alias labels currently taken by active members of a class.
	 *
	 * @param  int $class_id
	 * @return string[]
	 */
	public function get_taken_aliases( $class_id ) {
		$aliases = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT alias_label FROM {$this->table} WHERE class_id = %d AND status = %s",
				absint( $class_id ),
				'active'
			)
		);

		return array_map( 'strval', (array) $aliases );
	}

	/**
	 * Look a member up by the raw claim secret from a claim link.
	 *
	 * @param  string $raw_token
	 * @return object|null Row object, or null when unknown/removed.
	 */
	public function get_by_claim_token( $raw_token ) {
		if ( '' === (string) $raw_token ) {
			return null;
		}

		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE claim_token_hash = %s AND status = %s LIMIT 1",
				self::hash_claim_token( $raw_token ),
				'active'
			)
		);

		return $row ? $row : null;
	}

	/**
	 * Fetch one member row by ID.
	 *
	 * @param  int $id
	 * @return object|null
	 */
	public function get( $id ) {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1", absint( $id ) )
		);

		return $row ? $row : null;
	}

	/**
	 * Find an active member by class + alias.
	 *
	 * @param  int    $class_id
	 * @param  string $alias_label
	 * @return object|null
	 */
	public function get_by_alias( $class_id, $alias_label ) {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE class_id = %d AND alias_label = %s AND status = %s LIMIT 1",
				absint( $class_id ),
				sanitize_text_field( $alias_label ),
				'active'
			)
		);

		return $row ? $row : null;
	}

	/**
	 * Membership rows for a token WP user across all their classes.
	 *
	 * @param  int $user_id
	 * @return array
	 */
	public function get_by_user( $user_id ) {
		return (array) $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE student_user_id = %d AND status = %s",
				absint( $user_id ),
				'active'
			)
		);
	}
}
