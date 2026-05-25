<?php
/**
 * Plugin Name: ChurchSuite Events
 * Description: Pulls ChurchSuite calendar JSON feed and exposes events for block-based templates.
 * Version: 0.1.2
 * Author: All Saints Wick
 * License: GPL-2.0-or-later
 * Text Domain: churchsuite-events
 *
 * @package ChurchSuiteEvents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CHURCHSUITE_EVENTS_VERSION', '0.1.1' );
define( 'CHURCHSUITE_EVENTS_FILE', __FILE__ );
define( 'CHURCHSUITE_EVENTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'CHURCHSUITE_EVENTS_URL', plugin_dir_url( __FILE__ ) );

// Autoload lightweight class files from includes directory.
spl_autoload_register(
	function ( $class ) {
		if ( 0 !== strpos( $class, 'ChurchSuite_Events_' ) ) {
			return;
		}

		$filename = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
		$filepath = trailingslashit( CHURCHSUITE_EVENTS_PATH . 'includes' ) . $filename;

		if ( file_exists( $filepath ) ) {
			include $filepath;
		}
	}
);

/**
 * Plugin bootstrap.
 *
 * @return void
 */
function churchsuite_events_bootstrap() {
	ChurchSuite_Events_Plugin::instance();
}

/**
 * Register activation hook for rewrites.
 *
 * @return void
 */
function churchsuite_events_activate() {
	ChurchSuite_Events_Plugin::instance()->activate();
}

/**
 * Register deactivation hook for rewrites.
 *
 * @return void
 */
function churchsuite_events_deactivate() {
	ChurchSuite_Events_Plugin::instance()->deactivate();
}

add_action( 'plugins_loaded', 'churchsuite_events_bootstrap' );
register_activation_hook( __FILE__, 'churchsuite_events_activate' );
register_deactivation_hook( __FILE__, 'churchsuite_events_deactivate' );
