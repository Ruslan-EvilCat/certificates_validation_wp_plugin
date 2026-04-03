<?php
/**
 * Handles plugin deactivation.
 *
 * @package CertificateValidationPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deactivation handler.
 */
class CVP_Deactivator {

	/**
	 * Runs deactivation tasks.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Reserved for future cleanup tasks such as transient removal.
	}
}
