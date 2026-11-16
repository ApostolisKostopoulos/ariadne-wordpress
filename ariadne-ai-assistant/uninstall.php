<?php
/**
 * Uninstall: deletes every ariadne_* option and transient the plugin wrote.
 *
 * The plugin's own files are not loaded here, so the option names are listed again; keep
 * them in step with Ariadne_Options::ALL.
 *
 * @package Ariadne_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Deletes the plugin's options and transients on the current site.
 */
function ariadne_uninstall_current_site() {
	$options = array(
		'ariadne_site_key',
		'ariadne_api_url',
		'ariadne_widget_url',
		'ariadne_site_name',
		'ariadne_dashboard_url',
		'ariadne_enabled',
		'ariadne_source',
	);
	foreach ( $options as $name ) {
		delete_option( $name );
	}

	// Per-user transients: a Connect in progress (10 minutes) and a pending notice (5 minutes).
	// Only users who can manage options ever get one.
	$user_ids   = get_users(
		array(
			'capability' => 'manage_options',
			'fields'     => 'ID',
		)
	);
	$user_ids[] = get_current_user_id();
	foreach ( array_unique( array_map( 'absint', $user_ids ) ) as $user_id ) {
		delete_transient( 'ariadne_connect_' . $user_id );
		delete_transient( 'ariadne_notice_' . $user_id );
	}
}

if ( is_multisite() ) {
	$ariadne_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $ariadne_site_ids as $ariadne_site_id ) {
		switch_to_blog( $ariadne_site_id );
		ariadne_uninstall_current_site();
		restore_current_blog();
	}
} else {
	ariadne_uninstall_current_site();
}
