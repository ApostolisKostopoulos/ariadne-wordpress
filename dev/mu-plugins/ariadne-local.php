<?php
/**
 * Local testing only: docker-compose.yml mounts this folder as wp-content/mu-plugins.
 * It is never part of the plugin or its zip.
 *
 * Points the plugin at the Ariadne dashboard, API and widget running on this computer
 * (the private repository's `pnpm dev`, with VITE_API_MODE=real for the dashboard).
 *
 * @package Ariadne_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The browser opens the Connect page here.
add_filter(
	'ariadne_app_url',
	function () {
		return 'http://localhost:5173/admin';
	}
);

// The exchange is a call from PHP inside the container, where "localhost" is the container
// itself; Docker Desktop's host.docker.internal is this computer, where the API listens on 3000.
add_filter(
	'ariadne_api_url',
	function () {
		return 'http://host.docker.internal:3000';
	}
);

// Only for "paste a site key by hand"; Connect gets the widget address from the exchange.
add_filter(
	'ariadne_widget_url',
	function () {
		return 'http://localhost:5174';
	}
);

// The exchange answers with http://localhost:3000, http://localhost:5174 and
// http://localhost:5173/admin/s/<id>; the plugin accepts http only for localhost.
add_filter(
	'ariadne_allowed_hosts',
	function () {
		return array( 'localhost' );
	}
);
