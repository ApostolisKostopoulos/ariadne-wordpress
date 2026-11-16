<?php
/**
 * The plugin's saved options, their checks, and the one-time notices of the settings page.
 *
 * @package Ariadne_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option names and the checks every saved value passes.
 */
class Ariadne_Options {

	const SITE_KEY      = 'ariadne_site_key';
	const API_URL       = 'ariadne_api_url';
	const WIDGET_URL    = 'ariadne_widget_url';
	const SITE_NAME     = 'ariadne_site_name';
	const DASHBOARD_URL = 'ariadne_dashboard_url';
	const ENABLED       = 'ariadne_enabled';
	const SOURCE        = 'ariadne_source';

	/** Every option the plugin writes; uninstall.php deletes the same list. */
	const ALL = array(
		self::SITE_KEY,
		self::API_URL,
		self::WIDGET_URL,
		self::SITE_NAME,
		self::DASHBOARD_URL,
		self::ENABLED,
		self::SOURCE,
	);

	/** A site key, as the Ariadne API makes them: pk_ and 24 letters or digits. */
	const SITE_KEY_PATTERN = '/^pk_[A-Za-z0-9]{24}$/';

	/**
	 * Whether a value is a site key.
	 *
	 * @param mixed $value The value to check.
	 * @return bool
	 */
	public static function is_site_key( $value ) {
		return is_string( $value ) && 1 === preg_match( self::SITE_KEY_PATTERN, $value );
	}

	/**
	 * Whether a URL may be saved: https (http only for localhost, which the allowed hosts
	 * hold only when a local filter adds it), a host in the allowed hosts, no user name or
	 * password, at most 2048 characters.
	 *
	 * @param mixed $url The URL to check.
	 * @return bool
	 */
	public static function is_allowed_url( $url ) {
		if ( ! is_string( $url ) || '' === $url || strlen( $url ) > 2048 ) {
			return false;
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return false;
		}
		$host   = strtolower( $parts['host'] );
		$scheme = strtolower( $parts['scheme'] );
		if ( 'https' !== $scheme && ! ( 'http' === $scheme && 'localhost' === $host ) ) {
			return false;
		}
		return in_array( $host, ariadne_allowed_hosts(), true );
	}

	/**
	 * Whether the site is connected (a valid site key and widget address are saved).
	 *
	 * @return bool
	 */
	public static function is_connected() {
		return self::is_site_key( get_option( self::SITE_KEY ) )
			&& self::is_allowed_url( get_option( self::WIDGET_URL ) )
			&& self::is_allowed_url( get_option( self::API_URL ) );
	}

	/**
	 * Whether the owner wants the assistant on the site (on unless switched off).
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return '0' !== (string) get_option( self::ENABLED, '1' );
	}

	/**
	 * Saves a connection. Every value has been checked by the caller.
	 *
	 * @param array $values site_key, api_url, widget_url, site_name, dashboard_url, source.
	 */
	public static function save_connection( array $values ) {
		update_option( self::SITE_KEY, $values['site_key'] );
		update_option( self::API_URL, $values['api_url'] );
		update_option( self::WIDGET_URL, $values['widget_url'] );
		update_option( self::SITE_NAME, $values['site_name'], false );
		update_option( self::DASHBOARD_URL, $values['dashboard_url'], false );
		update_option( self::SOURCE, $values['source'], false );
		update_option( self::ENABLED, '1' );
	}

	/**
	 * Forgets the connection. The on/off switch keeps its place.
	 */
	public static function delete_connection() {
		foreach ( array( self::SITE_KEY, self::API_URL, self::WIDGET_URL, self::SITE_NAME, self::DASHBOARD_URL, self::SOURCE ) as $name ) {
			delete_option( $name );
		}
	}

	/**
	 * The transient that carries a notice to the next view of the settings page.
	 *
	 * @param int $user_id WordPress user id.
	 * @return string
	 */
	public static function notice_key( $user_id ) {
		return 'ariadne_notice_' . absint( $user_id );
	}

	/**
	 * Leaves a notice for the current user's next view of the settings page.
	 *
	 * @param string $code One of the codes Ariadne_Settings::notice_text() knows.
	 */
	public static function set_notice( $code ) {
		set_transient( self::notice_key( get_current_user_id() ), $code, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Takes the current user's notice, once.
	 *
	 * @return string The notice code, or '' when there is none.
	 */
	public static function take_notice() {
		$key  = self::notice_key( get_current_user_id() );
		$code = get_transient( $key );
		if ( false === $code ) {
			return '';
		}
		delete_transient( $key );
		return is_string( $code ) ? $code : '';
	}
}
