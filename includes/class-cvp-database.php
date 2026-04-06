<?php
/**
 * Database helper for the certificate table.
 *
 * @package CertificateValidationPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database helper.
 */
class CVP_Database {

	/**
	 * Database schema version option name.
	 *
	 * @var string
	 */
	const OPTION_DB_VERSION = 'cvp_db_version';

	/**
	 * Returns the certificate table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'certificates';
	}

	/**
	 * Creates or updates the certificate table.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			code varchar(191) NOT NULL,
			full_name varchar(255) NOT NULL,
			course varchar(255) NOT NULL,
			hours decimal(10,2) NOT NULL DEFAULT 0.00,
			ects_hours decimal(10,2) NOT NULL DEFAULT 0.00,
			issued_date date NOT NULL,
			course_link text NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY code_unique (code),
			KEY code_index (code)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Creates or upgrades the plugin database schema and stores the schema version.
	 *
	 * @return void
	 */
	public static function install() {
		self::create_table();
		self::migrate_legacy_name_columns();
		self::migrate_hour_columns_to_decimal();
		self::update_schema_version();
	}

	/**
	 * Runs schema updates when the stored schema version is outdated.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed_version = self::get_installed_schema_version();

		if ( CVP_DB_VERSION === $installed_version ) {
			return;
		}

		self::install();
	}

	/**
	 * Returns the installed database schema version.
	 *
	 * @return string
	 */
	public static function get_installed_schema_version() {
		$installed_version = get_option( self::OPTION_DB_VERSION, '' );

		if ( ! is_string( $installed_version ) ) {
			return '';
		}

		return $installed_version;
	}

	/**
	 * Stores the current database schema version.
	 *
	 * @return void
	 */
	public static function update_schema_version() {
		update_option( self::OPTION_DB_VERSION, CVP_DB_VERSION );
	}

	/**
	 * Migrates legacy name and surname columns into full_name.
	 *
	 * @return void
	 */
	protected static function migrate_legacy_name_columns() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$has_full_name   = self::column_exists( $table_name, 'full_name' );
		$has_name        = self::column_exists( $table_name, 'name' );
		$has_surname     = self::column_exists( $table_name, 'surname' );

		if ( ! $has_full_name ) {
			return;
		}

		if ( $has_name || $has_surname ) {
			$name_sql    = $has_name ? 'name' : "''";
			$surname_sql = $has_surname ? 'surname' : "''";

			$wpdb->query(
				"UPDATE {$table_name}
				SET full_name = TRIM(CONCAT_WS(' ', {$name_sql}, {$surname_sql}))
				WHERE (full_name = '' OR full_name IS NULL)"
			);
		}

		if ( $has_name ) {
			$wpdb->query( "ALTER TABLE {$table_name} DROP COLUMN name" );
		}

		if ( $has_surname ) {
			$wpdb->query( "ALTER TABLE {$table_name} DROP COLUMN surname" );
		}
	}

	/**
	 * Ensures hours columns support decimal values.
	 *
	 * @return void
	 */
	protected static function migrate_hour_columns_to_decimal() {
		global $wpdb;

		$table_name = self::get_table_name();

		$wpdb->query(
			"ALTER TABLE {$table_name}
			MODIFY COLUMN hours decimal(10,2) NOT NULL DEFAULT 0.00,
			MODIFY COLUMN ects_hours decimal(10,2) NOT NULL DEFAULT 0.00"
		);
	}

	/**
	 * Determines whether a table column exists.
	 *
	 * @param string $table_name Table name.
	 * @param string $column_name Column name.
	 * @return bool
	 */
	protected static function column_exists( $table_name, $column_name ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			'SHOW COLUMNS FROM `' . esc_sql( $table_name ) . '` LIKE %s',
			$column_name
		);

		return null !== $wpdb->get_var( $sql );
	}
}
