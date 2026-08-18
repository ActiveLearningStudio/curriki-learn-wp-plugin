<?php

/**
 * Repository for the encrypted roster vault (Zone B).
 *
 * One ciphertext blob per class, holding the `member_id -> real name` map.
 * The server stores it, serves it, and deletes it — it can never read it.
 * Every value handled here is either opaque ciphertext or non-secret key
 * derivation parameters (salt, iteration count), all base64-encoded text.
 *
 * Envelope encryption, performed entirely in the teacher's browser:
 *
 *   DEK  random 32 bytes, encrypts the roster JSON (AES-256-GCM)
 *   KEK  PBKDF2(passphrase, salt, 600k, SHA-256), encrypts the DEK
 *        + a second DEK copy wrapped with the district's RSA-OAEP public key
 *
 * @see docs/student-privacy-zone-b-context.md
 */
class TL_Roster_Vault_Repository {

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
		$this->table = $this->wpdb->prefix . 'lxp_roster_vault';
	}

	// -------------------------------------------------------------------------
	// Read
	// -------------------------------------------------------------------------

	/**
	 * Fetch a class's vault.
	 *
	 * @param  int $class_id tl_class post ID.
	 * @return object|null Row object, or null when no vault exists yet.
	 */
	public function get_by_class( $class_id ) {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE class_id = %d LIMIT 1",
				absint( $class_id )
			)
		);

		return $row ? $row : null;
	}

	/**
	 * Whether a class has a vault yet.
	 *
	 * @param  int $class_id
	 * @return bool
	 */
	public function exists( $class_id ) {
		return (bool) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT id FROM {$this->table} WHERE class_id = %d LIMIT 1",
				absint( $class_id )
			)
		);
	}

	/**
	 * Current version number of a class's vault (0 when absent).
	 *
	 * Used for optimistic concurrency — two teachers editing the same roster
	 * must not silently clobber each other.
	 *
	 * @param  int $class_id
	 * @return int
	 */
	public function get_version( $class_id ) {
		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT version FROM {$this->table} WHERE class_id = %d LIMIT 1",
				absint( $class_id )
			)
		);
	}

	// -------------------------------------------------------------------------
	// Write
	// -------------------------------------------------------------------------

	/**
	 * Create a class's vault for the first time.
	 *
	 * @param  array $args {
	 *     @type int    $class_id
	 *     @type int    $teacher_user_id
	 *     @type string $ciphertext           base64
	 *     @type string $iv                   base64, 12 bytes
	 *     @type string $wrapped_dek_teacher  base64, iv||ciphertext
	 *     @type string $wrapped_dek_escrow   base64, or '' when no escrow key
	 *     @type string $kdf_params           JSON string
	 * }
	 * @return int|false Row ID, or false on failure (including a duplicate class).
	 */
	public function create( array $args ) {
		$inserted = $this->wpdb->insert(
			$this->table,
			array(
				'class_id'            => absint( $args['class_id'] ),
				'teacher_user_id'     => absint( $args['teacher_user_id'] ),
				'ciphertext'          => (string) $args['ciphertext'],
				'iv'                  => (string) $args['iv'],
				'wrapped_dek_teacher' => (string) $args['wrapped_dek_teacher'],
				'wrapped_dek_escrow'  => isset( $args['wrapped_dek_escrow'] ) ? (string) $args['wrapped_dek_escrow'] : null,
				'kdf_params'          => isset( $args['kdf_params'] ) ? (string) $args['kdf_params'] : null,
				'version'             => 1,
				'updated_at'          => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return $inserted ? (int) $this->wpdb->insert_id : false;
	}

	/**
	 * Replace a class's vault contents, guarded by the expected version.
	 *
	 * @param  int   $class_id
	 * @param  int   $expected_version Version the caller last read.
	 * @param  array $args             Same shape as create(), minus class_id.
	 * @return int|false New version number, or false on a version conflict.
	 */
	public function update( $class_id, $expected_version, array $args ) {
		$data = array(
			'ciphertext'          => (string) $args['ciphertext'],
			'iv'                  => (string) $args['iv'],
			'wrapped_dek_teacher' => (string) $args['wrapped_dek_teacher'],
			'version'             => absint( $expected_version ) + 1,
			'updated_at'          => current_time( 'mysql' ),
		);
		$formats = array( '%s', '%s', '%s', '%d', '%s' );

		// Escrow + KDF params only change on a passphrase/escrow-key rotation.
		if ( isset( $args['wrapped_dek_escrow'] ) ) {
			$data['wrapped_dek_escrow'] = (string) $args['wrapped_dek_escrow'];
			$formats[]                  = '%s';
		}
		if ( isset( $args['kdf_params'] ) ) {
			$data['kdf_params'] = (string) $args['kdf_params'];
			$formats[]          = '%s';
		}
		if ( isset( $args['teacher_user_id'] ) ) {
			$data['teacher_user_id'] = absint( $args['teacher_user_id'] );
			$formats[]               = '%d';
		}

		$updated = $this->wpdb->update(
			$this->table,
			$data,
			array(
				'class_id' => absint( $class_id ),
				'version'  => absint( $expected_version ),
			),
			$formats,
			array( '%d', '%d' )
		);

		// 0 rows means the version moved under us — someone else saved first.
		if ( ! $updated ) {
			return false;
		}

		return absint( $expected_version ) + 1;
	}

	/**
	 * Delete a class's vault.
	 *
	 * Called when a class is deleted: the roster map must not outlive the class
	 * it describes.
	 *
	 * @param  int $class_id
	 * @return bool
	 */
	public function delete_by_class( $class_id ) {
		return (bool) $this->wpdb->delete(
			$this->table,
			array( 'class_id' => absint( $class_id ) ),
			array( '%d' )
		);
	}
}
