<?php
/**
 * Connect: links this WordPress site to an Ariadne site with no copy-paste.
 *
 * The flow and every check follow docs/connect.md in the plugin repository.
 *
 * @package Ariadne_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Starts Connect (a form POST from the settings page) and handles the return from the
 * Ariadne dashboard.
 */
class Ariadne_Connect {

	const START_ACTION  = 'ariadne_connect_start';
	const RETURN_ACTION = 'ariadne_connect_return';

	/** The one-time code the dashboard sends back: 32 bytes in base64url. */
	const CODE_PATTERN = '/^[A-Za-z0-9_-]{43}$/';

	/** The longest site title sent to the Connect page, in UTF-16 units like the page counts. */
	const NAME_MAX = 80;

	/**
	 * Hooks. The return is hooked for signed-in users only: never admin_post_nopriv_.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::START_ACTION, array( __CLASS__, 'start' ) );
		add_action( 'admin_post_' . self::RETURN_ACTION, array( __CLASS__, 'finish' ) );
		// "Cancel" on the Connect page goes back to admin-post.php with no action at all.
		add_action( 'admin_post', array( __CLASS__, 'cancelled' ) );
	}

	/**
	 * The transient that keeps one user's state and verifier while Connect runs.
	 *
	 * @param int $user_id WordPress user id.
	 * @return string
	 */
	public static function pending_key( $user_id ) {
		return 'ariadne_connect_' . absint( $user_id );
	}

	/**
	 * The PKCE challenge: base64url(sha256(verifier)) without padding, 43 characters.
	 *
	 * @param string $verifier The verifier.
	 * @return string
	 */
	public static function challenge( $verifier ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- the PKCE challenge is base64url by definition.
		return rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
	}

	/**
	 * Step 1 to 3: makes state and verifier, keeps them 10 minutes, sends the browser to the
	 * Connect page of the Ariadne dashboard.
	 */
	public static function start() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do this.', 'ariadne-ai-assistant' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::START_ACTION, self::START_ACTION . '_nonce' );

		$state    = wp_generate_password( 32, false );
		$verifier = wp_generate_password( 64, false );
		set_transient(
			self::pending_key( get_current_user_id() ),
			array(
				'state'    => $state,
				'verifier' => $verifier,
			),
			10 * MINUTE_IN_SECONDS
		);

		$args = array(
			'site_url'  => rawurlencode( home_url() ),
			'return'    => rawurlencode( admin_url( 'admin-post.php' ) ),
			'state'     => rawurlencode( $state ),
			'challenge' => rawurlencode( self::challenge( $verifier ) ),
			'woo'       => class_exists( 'WooCommerce' ) ? '1' : '0',
		);
		$name = self::site_name();
		if ( '' !== $name ) {
			$args['name'] = rawurlencode( $name );
		}

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- our own Connect page, a fixed address; wp_safe_redirect() would refuse its host.
		wp_redirect( add_query_arg( $args, ariadne_app_url() . '/connect/wordpress' ) );
		exit;
	}

	/**
	 * Step 4 to 7: the browser comes back from the Connect page with a one-time code. Checks
	 * the state, swaps the code for the site key server to server, saves what came back.
	 */
	public static function finish() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do this.', 'ariadne-ai-assistant' ), '', array( 'response' => 403 ) );
		}

		// Single use: the pending state goes before anything else is looked at.
		$key     = self::pending_key( get_current_user_id() );
		$pending = get_transient( $key );
		delete_transient( $key );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- this request arrives from the Ariadne dashboard, so it carries no nonce; `state` does that job.
		$has_code = isset( $_GET['code'] );
		$code     = $has_code ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$state    = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! $has_code ) {
			self::back( 'cancelled' );
		}

		$valid = is_array( $pending )
			&& isset( $pending['state'], $pending['verifier'] )
			&& is_string( $pending['state'] )
			&& is_string( $pending['verifier'] )
			&& '' !== $state
			&& 1 === preg_match( self::CODE_PATTERN, $code )
			&& hash_equals( $pending['state'], $state );
		if ( ! $valid ) {
			self::back( 'expired' );
		}

		$result = self::exchange( $code, $pending['verifier'] );
		if ( null === $result ) {
			self::back( 'failed' );
		}

		Ariadne_Options::save_connection(
			array(
				'site_key'      => $result['site_key'],
				'api_url'       => $result['api_url'],
				'widget_url'    => $result['widget_url'],
				'site_name'     => $result['site_name'],
				'dashboard_url' => $result['dashboard_url'],
				'source'        => 'connect',
			)
		);
		self::back( $result['preview_mode'] ? 'connected_preview' : 'connected' );
	}

	/**
	 * "Cancel" on the Connect page: a GET to admin-post.php with no action. Acts only for a
	 * user with a Connect in progress, so every other use of admin-post.php is left alone.
	 */
	public static function cancelled() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'GET' !== $method ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$key = self::pending_key( get_current_user_id() );
		if ( false === get_transient( $key ) ) {
			return;
		}
		delete_transient( $key );
		self::back( 'cancelled' );
	}

	/**
	 * Swaps the code for the site's details. Returns null on any failure: a network error,
	 * an answer other than 200, bad JSON, or a value that fails its check.
	 *
	 * @param string $code     The one-time code.
	 * @param string $verifier The verifier kept since the start.
	 * @return array|null
	 */
	private static function exchange( $code, $verifier ) {
		$response = wp_remote_post(
			ariadne_api_url() . '/v1/connect/wordpress/exchange',
			array(
				'timeout'             => 15,
				'redirection'         => 0,
				'limit_response_size' => 65536,
				'headers'             => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'                => wp_json_encode(
					array(
						'code'     => $code,
						'verifier' => $verifier,
					)
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return null;
		}

		$site_key      = isset( $data['siteKey'] ) ? $data['siteKey'] : null;
		$api_url       = isset( $data['apiUrl'] ) ? $data['apiUrl'] : null;
		$widget_url    = isset( $data['widgetUrl'] ) ? $data['widgetUrl'] : null;
		$dashboard_url = isset( $data['dashboardUrl'] ) ? $data['dashboardUrl'] : null;
		$site_name     = isset( $data['siteName'] ) ? $data['siteName'] : null;
		$preview_mode  = isset( $data['previewMode'] ) ? $data['previewMode'] : null;

		if ( ! Ariadne_Options::is_site_key( $site_key )
			|| ! Ariadne_Options::is_allowed_url( $api_url )
			|| ! Ariadne_Options::is_allowed_url( $widget_url )
			|| ! Ariadne_Options::is_allowed_url( $dashboard_url )
			|| ! is_string( $site_name )
			|| ! is_bool( $preview_mode ) ) {
			return null;
		}

		return array(
			'site_key'      => $site_key,
			'api_url'       => untrailingslashit( $api_url ),
			'widget_url'    => untrailingslashit( $widget_url ),
			'dashboard_url' => $dashboard_url,
			'site_name'     => mb_substr( sanitize_text_field( $site_name ), 0, 200 ),
			'preview_mode'  => $preview_mode,
		);
	}

	/**
	 * The site title for the Connect page: decoded, trimmed, at most NAME_MAX UTF-16 units
	 * (the page checks the length in JavaScript, where an emoji counts twice).
	 *
	 * @return string
	 */
	private static function site_name() {
		$name  = trim( wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );
		$chars = preg_split( '//u', $name, -1, PREG_SPLIT_NO_EMPTY );
		if ( false === $chars ) {
			return '';
		}
		$out   = '';
		$units = 0;
		foreach ( $chars as $char ) {
			$units += strlen( $char ) >= 4 ? 2 : 1;
			if ( $units > self::NAME_MAX ) {
				break;
			}
			$out .= $char;
		}
		return trim( $out );
	}

	/**
	 * Back to the settings page with a notice, and stop.
	 *
	 * @param string $notice Notice code.
	 */
	private static function back( $notice ) {
		Ariadne_Options::set_notice( $notice );
		wp_safe_redirect( Ariadne_Settings::page_url() );
		exit;
	}
}
