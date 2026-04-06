<?php
/**
 * Handles plugin activation.
 *
 * @package CertificateValidationPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CVP_PLUGIN_DIR . 'includes/class-cvp-database.php';

/**
 * Activation handler.
 */
class CVP_Activator {

	/**
	 * Runs activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		CVP_Database::install();
		add_option( CVP_OPTION_FRONTEND_DISPLAY_LANGUAGE, 'en' );
	}
}
