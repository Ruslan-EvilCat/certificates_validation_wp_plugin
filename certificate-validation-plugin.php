<?php
/**
 * Plugin Name:       Certificate Validation Plugin
 * Plugin URI:        https://example.com/
 * Description:       Validate certificates on the frontend and manage certificates in the WordPress admin area.
 * Version:           0.1.1
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Placeholder
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       certificate-validation-plugin
 * Domain Path:       /languages
 *
 * @package CertificateValidationPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CVP_VERSION', '0.1.1' );
define( 'CVP_DB_VERSION', '1.1.0' );
define( 'CVP_OPTION_FRONTEND_DISPLAY_LANGUAGE', 'cvp_frontend_display_language' );
define( 'CVP_PLUGIN_FILE', __FILE__ );
define( 'CVP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CVP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once CVP_PLUGIN_DIR . 'includes/class-cvp-activator.php';
require_once CVP_PLUGIN_DIR . 'includes/class-cvp-deactivator.php';
require_once CVP_PLUGIN_DIR . 'includes/class-cvp-plugin.php';

/**
 * Runs plugin activation tasks.
 *
 * @return void
 */
function cvp_activate_plugin() {
	CVP_Activator::activate();
}

register_activation_hook( __FILE__, 'cvp_activate_plugin' );

/**
 * Runs plugin deactivation tasks.
 *
 * @return void
 */
function cvp_deactivate_plugin() {
	CVP_Deactivator::deactivate();
}

register_deactivation_hook( __FILE__, 'cvp_deactivate_plugin' );

/**
 * Boots the plugin.
 *
 * @return void
 */
function cvp_run_plugin() {
	$plugin = new CVP_Plugin();
	$plugin->run();
}

cvp_run_plugin();
