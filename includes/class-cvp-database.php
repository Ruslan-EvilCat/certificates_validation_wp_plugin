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
			name varchar(191) NOT NULL,
			surname varchar(191) NOT NULL,
			course varchar(255) NOT NULL,
			hours int(11) NOT NULL DEFAULT 0,
			ects_hours int(11) NOT NULL DEFAULT 0,
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
}
