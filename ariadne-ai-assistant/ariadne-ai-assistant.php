<?php
/**
 * Plugin Name:       Ariadne AI Assistant
 * Plugin URI:        https://ariadnebot.com/help/wordpress/
 * Description:       Adds the Ariadne AI assistant to your site and connects it to your Ariadne account with one click, with no code to copy.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Ariadne
 * Author URI:        https://ariadnebot.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ariadne-ai-assistant
 * Domain Path:       /languages
 *
 * @package Ariadne_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARIADNE_VERSION', '1.0.1' );
define( 'ARIADNE_PLUGIN_FILE', __FILE__ );
define( 'ARIADNE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/*
 * Our addresses in production. Each one has a filter of the same name in lower case
 * (ariadne_app_url, ariadne_api_url, ariadne_widget_url, ariadne_allowed_hosts) for local
 * testing; a filter needs code on the site, so it gives an attacker nothing they lack.
 */
define( 'ARIADNE_APP_URL', 'https://ariadnebot.com/admin' );
define( 'ARIADNE_API_URL', 'https://api.ariadnebot.com' );
define( 'ARIADNE_WIDGET_URL', 'https://ariadnebot.com/widget' );
define( 'ARIADNE_ALLOWED_HOSTS', array( 'ariadnebot.com', 'api.ariadnebot.com' ) );

require_once ARIADNE_PLUGIN_DIR . 'includes/class-ariadne-options.php';
require_once ARIADNE_PLUGIN_DIR . 'includes/class-ariadne-connect.php';
require_once ARIADNE_PLUGIN_DIR . 'includes/class-ariadne-settings.php';
require_once ARIADNE_PLUGIN_DIR . 'includes/class-ariadne-widget.php';

/**
 * The dashboard address (APP_URL, it already ends in /admin), without a trailing slash.
 *
 * @return string
 */
function ariadne_app_url() {
	return untrailingslashit( (string) apply_filters( 'ariadne_app_url', ARIADNE_APP_URL ) );
}

/**
 * The API address the plugin's server calls for the Connect exchange, without a trailing slash.
 *
 * @return string
 */
function ariadne_api_url() {
	return untrailingslashit( (string) apply_filters( 'ariadne_api_url', ARIADNE_API_URL ) );
}

/**
 * The widget folder, used only with a site key pasted by hand (Connect returns its own).
 *
 * @return string
 */
function ariadne_widget_url() {
	return untrailingslashit( (string) apply_filters( 'ariadne_widget_url', ARIADNE_WIDGET_URL ) );
}

/**
 * The hosts whose addresses the plugin accepts from the Connect exchange, lower case.
 *
 * @return string[]
 */
function ariadne_allowed_hosts() {
	$hosts = apply_filters( 'ariadne_allowed_hosts', ARIADNE_ALLOWED_HOSTS );
	$out   = array();
	foreach ( (array) $hosts as $host ) {
		if ( is_string( $host ) && '' !== $host ) {
			$out[] = strtolower( $host );
		}
	}
	return $out;
}

/**
 * Loads the bundled translations (Greek ships with the plugin).
 */
function ariadne_load_textdomain() {
	// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- the zip from ariadnebot.com carries its own Greek translation.
	load_plugin_textdomain( 'ariadne-ai-assistant', false, dirname( plugin_basename( ARIADNE_PLUGIN_FILE ) ) . '/languages' );
}
add_action( 'init', 'ariadne_load_textdomain' );

/**
 * Activation: the assistant shows once the site is connected, unless the owner turns it off.
 */
function ariadne_activate() {
	add_option( Ariadne_Options::ENABLED, '1' );
}
register_activation_hook( __FILE__, 'ariadne_activate' );

/**
 * Deactivation: the connection stays (uninstalling removes it); only a Connect in progress
 * is dropped.
 */
function ariadne_deactivate() {
	delete_transient( Ariadne_Connect::pending_key( get_current_user_id() ) );
}
register_deactivation_hook( __FILE__, 'ariadne_deactivate' );

Ariadne_Connect::init();
Ariadne_Settings::init();
Ariadne_Widget::init();
