<?php
/**
 * Plugin Name:       Aluleth Forms
 * Description:       Fast, beautiful forms that match your site. Entries, contacts and email notifications included, with no payment fees and nothing locked.
 * Version:           0.19.6
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Author:            Aluleth
 * Author URI:        https://profiles.wordpress.org/aluleth/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aluleth-forms
 * Domain Path:       /languages
 *
 * @package Aluleth\Forms
 */

defined( 'ABSPATH' ) || exit;

// Already running (the copy inside the complete Aluleth Forms Pro): nothing to load twice.
if ( defined( 'ALULETH_FORMS_VERSION' ) ) {
	return;
}

define( 'ALULETH_FORMS_VERSION', '0.19.6' );
define( 'ALULETH_FORMS_FILE', __FILE__ );
define( 'ALULETH_FORMS_DIR', __DIR__ );
define( 'ALULETH_FORMS_CORE_MIN', '0.18.0' );

require_once __DIR__ . '/core/loader.php';

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'Aluleth\\Forms\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = ALULETH_FORMS_DIR . '/includes/class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * Starts the plugin once the core is loaded.
 *
 * @return void
 */
function aluleth_forms_boot() {
	if ( ! function_exists( 'aluleth_core' ) || ! aluleth_core()->at_least( ALULETH_FORMS_CORE_MIN ) ) {
		add_action( 'admin_notices', 'aluleth_forms_core_notice' );
		return;
	}
	\Aluleth\Forms\Plugin::instance()->boot();
}
add_action( 'plugins_loaded', 'aluleth_forms_boot', 10 );

/**
 * Notice shown when another Aluleth plugin ships an older core.
 *
 * @return void
 */
function aluleth_forms_core_notice() {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'Aluleth Forms needs a newer version of your other Aluleth plugins. Please update them.', 'aluleth-forms' ) . '</p></div>';
}

/**
 * Activation: tables and a first contact form.
 *
 * @return void
 */
function aluleth_forms_activate() {
	aluleth_core_elect();
	if ( ! function_exists( 'aluleth_core' ) ) {
		return;
	}
	\Aluleth\Core\Installer::install();
	\Aluleth\Forms\Installer::install();
	\Aluleth\Forms\Plugin::instance()->register_post_type();
	\Aluleth\Forms\Installer::create_first_form();
}
register_activation_hook( __FILE__, 'aluleth_forms_activate' );

/**
 * Deactivation: stops the scheduled tasks (no data is deleted).
 *
 * @return void
 */
function aluleth_forms_deactivate() {
	require_once __DIR__ . '/core/uninstall.php';
	wp_clear_scheduled_hook( 'aluleth_forms_daily' );
	aluleth_core_deactivate( __DIR__ );
}
register_deactivation_hook( __FILE__, 'aluleth_forms_deactivate' );
