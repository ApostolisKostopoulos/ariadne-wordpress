<?php
/**
 * Prints the Ariadne loader on the front end, with the same attributes as the snippet on the
 * Install page of the Ariadne dashboard, so the install check finds it.
 *
 * @package Ariadne_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The loader tag.
 */
class Ariadne_Widget {

	const HANDLE = 'ariadne-loader';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'script_loader_tag', array( __CLASS__, 'tag' ), 10, 2 );
	}

	/**
	 * Whether this request gets the loader: a front-end page of a connected site with the
	 * switch on. Never admin pages, the login page, feeds, embeds, REST or AJAX answers, cron,
	 * or AMP pages.
	 *
	 * @return bool
	 */
	public static function should_print() {
		if ( is_admin() || is_feed() || is_embed() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( function_exists( 'is_login' ) && is_login() ) {
			return false;
		}
		if ( function_exists( 'amp_is_request' ) && amp_is_request() ) {
			return false;
		}
		if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
			return false;
		}
		return Ariadne_Options::is_connected() && Ariadne_Options::is_enabled();
	}

	/**
	 * Enqueues {widget}/loader.js in the footer, with no ?ver=.
	 */
	public static function enqueue() {
		if ( ! self::should_print() ) {
			return;
		}
		$src = get_option( Ariadne_Options::WIDGET_URL ) . '/loader.js';
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the loader keeps one stable address; our side caches it for 5 minutes.
		wp_enqueue_script( self::HANDLE, $src, array(), null, true );
	}

	/**
	 * Adds the loader's attributes before src=. Also the opt-outs of speed plugins: the
	 * loader finds its own tag through document.currentScript, which inlining, combining,
	 * delaying or deferring the tag breaks (Cloudflare Rocket Loader, LiteSpeed, Autoptimize,
	 * PageSpeed, WP Rocket).
	 *
	 * @param string $tag    The script tag WordPress made.
	 * @param string $handle The script's handle.
	 * @return string
	 */
	public static function tag( $tag, $handle ) {
		if ( self::HANDLE !== $handle ) {
			return $tag;
		}
		$at = strpos( $tag, ' src=' );
		if ( false === $at ) {
			return $tag;
		}
		$attributes = sprintf(
			' async data-site-key="%s" data-api="%s" data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-pagespeed-no-defer nowprocket',
			esc_attr( (string) get_option( Ariadne_Options::SITE_KEY ) ),
			esc_attr( (string) get_option( Ariadne_Options::API_URL ) )
		);
		return substr( $tag, 0, $at ) . $attributes . substr( $tag, $at );
	}
}
