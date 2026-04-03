<?php
/**
 * Main plugin bootstrap class.
 *
 * @package CertificateValidationPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CVP_PLUGIN_DIR . 'includes/class-cvp-database.php';
require_once CVP_PLUGIN_DIR . 'includes/class-cvp-certificate-repository.php';
require_once CVP_PLUGIN_DIR . 'admin/class-cvp-admin.php';
require_once CVP_PLUGIN_DIR . 'public/class-cvp-public.php';

/**
 * Main plugin class.
 */
class CVP_Plugin {

	/**
	 * Admin module instance.
	 *
	 * @var CVP_Admin|null
	 */
	protected $admin;

	/**
	 * Public module instance.
	 *
	 * @var CVP_Public|null
	 */
	protected $public;

	/**
	 * Boots plugin modules.
	 *
	 * @return void
	 */
	public function run() {
		CVP_Database::maybe_upgrade();

		if ( is_admin() ) {
			$this->admin = new CVP_Admin();
			$this->admin->init();
		}

		$this->public = new CVP_Public();
		$this->public->init();
	}
}
