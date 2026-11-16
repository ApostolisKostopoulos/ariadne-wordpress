<?php
/**
 * Settings › Ariadne: the connection, Connect and Disconnect, the on/off switch, and a
 * site key field for sites that cannot use Connect.
 *
 * @package Ariadne_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The settings page and its forms. Every form posts to admin-post.php with a nonce and is
 * handled only for users who can manage_options.
 */
class Ariadne_Settings {

	const PAGE              = 'ariadne-ai-assistant';
	const SAVE_ACTION       = 'ariadne_save_settings';
	const KEY_ACTION        = 'ariadne_save_site_key';
	const DISCONNECT_ACTION = 'ariadne_disconnect';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( __CLASS__, 'save' ) );
		add_action( 'admin_post_' . self::KEY_ACTION, array( __CLASS__, 'save_key' ) );
		add_action( 'admin_post_' . self::DISCONNECT_ACTION, array( __CLASS__, 'disconnect' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ARIADNE_PLUGIN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * The settings page's address.
	 *
	 * @return string
	 */
	public static function page_url() {
		return admin_url( 'options-general.php?page=' . self::PAGE );
	}

	/**
	 * Settings › Ariadne.
	 */
	public static function menu() {
		add_options_page(
			__( 'Ariadne AI Assistant', 'ariadne-ai-assistant' ),
			'Ariadne',
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * A "Settings" link on the Plugins screen.
	 *
	 * @param string[] $links The plugin's links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( self::page_url() ), esc_html__( 'Settings', 'ariadne-ai-assistant' ) )
		);
		return $links;
	}

	/**
	 * The help page on ariadnebot.com, in Greek for a Greek admin.
	 *
	 * @return string
	 */
	private static function help_url() {
		return 0 === strpos( get_user_locale(), 'el' )
			? 'https://ariadnebot.com/el/help/wordpress/'
			: 'https://ariadnebot.com/help/wordpress/';
	}

	/**
	 * Where "Open the Ariadne dashboard" goes: the connected site's page, else the dashboard.
	 *
	 * @return string
	 */
	private static function dashboard_url() {
		$url = get_option( Ariadne_Options::DASHBOARD_URL );
		return Ariadne_Options::is_allowed_url( $url ) ? $url : ariadne_app_url();
	}

	/**
	 * The text and kind of a one-time notice.
	 *
	 * @param string $code Notice code.
	 * @return array|null array( kind, text, with dashboard button ), or null for an unknown code.
	 */
	private static function notice_text( $code ) {
		switch ( $code ) {
			case 'connected_preview':
				return array( 'success', __( 'Connected to Ariadne. For now only you can see the assistant. Open the Ariadne dashboard to try it and show it to everyone.', 'ariadne-ai-assistant' ), true );
			case 'connected':
				return array( 'success', __( 'Connected to Ariadne. The assistant now shows on your site.', 'ariadne-ai-assistant' ), true );
			case 'failed':
				return array( 'error', __( "Connecting didn't finish. Please try again.", 'ariadne-ai-assistant' ), false );
			case 'expired':
				return array( 'error', __( 'This connect link has expired or was already used. Press "Connect to Ariadne" again.', 'ariadne-ai-assistant' ), false );
			case 'cancelled':
				return array( 'info', __( 'Connecting was cancelled. Nothing changed.', 'ariadne-ai-assistant' ), false );
			case 'disconnected':
				return array( 'success', __( 'Disconnected. The assistant no longer shows on your site.', 'ariadne-ai-assistant' ), false );
			case 'saved':
				return array( 'success', __( 'Settings saved.', 'ariadne-ai-assistant' ), false );
			case 'key_saved':
				return array( 'success', __( 'Site key saved.', 'ariadne-ai-assistant' ), false );
			case 'key_invalid':
				return array( 'error', __( "That doesn't look like a site key. It starts with pk_ followed by 24 letters and digits.", 'ariadne-ai-assistant' ), false );
		}
		return null;
	}

	/**
	 * Prints this page's notice, if there is one. Notices show on this page only.
	 *
	 * @param string $code Notice code.
	 */
	private static function render_notice( $code ) {
		$notice = self::notice_text( $code );
		if ( null === $notice ) {
			return;
		}
		list( $kind, $text, $with_button ) = $notice;
		?>
		<div class="notice notice-<?php echo esc_attr( $kind ); ?>">
			<p><?php echo esc_html( $text ); ?></p>
			<?php if ( $with_button ) : ?>
				<p><a class="button button-primary" href="<?php echo esc_url( self::dashboard_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open the Ariadne dashboard', 'ariadne-ai-assistant' ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * A form that posts one action to admin-post.php with its nonce.
	 *
	 * @param string $action The admin-post action.
	 */
	private static function form_open( $action ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
			<?php wp_nonce_field( $action, $action . '_nonce' ); ?>
		<?php
	}

	/**
	 * The page.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$notice    = Ariadne_Options::take_notice();
		$connected = Ariadne_Options::is_connected();
		$source    = (string) get_option( Ariadne_Options::SOURCE );
		$site_name = (string) get_option( Ariadne_Options::SITE_NAME );
		$site_key  = (string) get_option( Ariadne_Options::SITE_KEY );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Ariadne AI Assistant', 'ariadne-ai-assistant' ); ?></h1>
			<?php self::render_notice( $notice ); ?>

			<h2><?php esc_html_e( 'Status', 'ariadne-ai-assistant' ); ?></h2>
			<?php if ( $connected ) : ?>
				<p>
					<?php
					if ( 'manual' === $source ) {
						esc_html_e( 'Connected with a site key you pasted by hand.', 'ariadne-ai-assistant' );
					} elseif ( '' !== $site_name ) {
						/* translators: %s: the name of the site in Ariadne. */
						echo esc_html( sprintf( __( 'Connected to Ariadne as "%s".', 'ariadne-ai-assistant' ), $site_name ) );
					} else {
						esc_html_e( 'Connected to Ariadne.', 'ariadne-ai-assistant' );
					}
					?>
				</p>
				<p><?php esc_html_e( 'Site key:', 'ariadne-ai-assistant' ); ?> <code><?php echo esc_html( $site_key ); ?></code></p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( self::dashboard_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open the Ariadne dashboard', 'ariadne-ai-assistant' ); ?></a>
				</p>
				<p class="description"><?php esc_html_e( 'While preview mode is on in the Ariadne dashboard, only you and your team see the assistant.', 'ariadne-ai-assistant' ); ?></p>

				<h2><?php esc_html_e( 'On your site', 'ariadne-ai-assistant' ); ?></h2>
				<?php self::form_open( self::SAVE_ACTION ); ?>
					<p>
						<label>
							<input type="checkbox" name="ariadne_enabled" value="1" <?php checked( Ariadne_Options::is_enabled() ); ?>>
							<?php esc_html_e( 'Show the assistant on my site', 'ariadne-ai-assistant' ); ?>
						</label>
					</p>
					<p class="description"><?php esc_html_e( 'While this is off, the assistant does not show on your site, even for you.', 'ariadne-ai-assistant' ); ?></p>
					<?php submit_button( __( 'Save', 'ariadne-ai-assistant' ), 'secondary', 'ariadne_save' ); ?>
				</form>

				<h2><?php esc_html_e( 'Disconnect', 'ariadne-ai-assistant' ); ?></h2>
				<p><?php esc_html_e( 'Disconnecting removes the assistant from this site. Your Ariadne account and its data stay as they are.', 'ariadne-ai-assistant' ); ?></p>
				<?php self::form_open( self::DISCONNECT_ACTION ); ?>
					<?php submit_button( __( 'Disconnect', 'ariadne-ai-assistant' ), 'secondary', 'ariadne_disconnect', false ); ?>
				</form>
			<?php else : ?>
				<p><?php esc_html_e( 'Not connected yet.', 'ariadne-ai-assistant' ); ?></p>
				<p><?php esc_html_e( 'Press "Connect to Ariadne", log in or create an account, and the assistant appears on your site with no code to copy.', 'ariadne-ai-assistant' ); ?></p>
				<?php self::form_open( Ariadne_Connect::START_ACTION ); ?>
					<?php submit_button( __( 'Connect to Ariadne', 'ariadne-ai-assistant' ), 'primary', 'ariadne_connect', false ); ?>
				</form>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Advanced', 'ariadne-ai-assistant' ); ?></h2>
			<details<?php echo ( $connected && 'manual' === $source ) ? ' open' : ''; ?>>
				<summary><?php esc_html_e( 'Paste a site key by hand', 'ariadne-ai-assistant' ); ?></summary>
				<p class="description"><?php esc_html_e( 'Use this only if Connect does not work on your site. Copy the site key from the Install page of the Ariadne dashboard.', 'ariadne-ai-assistant' ); ?></p>
				<?php self::form_open( self::KEY_ACTION ); ?>
					<p>
						<label for="ariadne-site-key"><?php esc_html_e( 'Site key', 'ariadne-ai-assistant' ); ?></label><br>
						<input type="text" id="ariadne-site-key" name="ariadne_site_key" class="regular-text code" maxlength="27" autocomplete="off" spellcheck="false" placeholder="pk_…" value="<?php echo esc_attr( 'manual' === $source ? $site_key : '' ); ?>">
					</p>
					<?php submit_button( __( 'Save site key', 'ariadne-ai-assistant' ), 'secondary', 'ariadne_save_key', false ); ?>
				</form>
			</details>

			<p><a href="<?php echo esc_url( self::help_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Need help? See how to connect', 'ariadne-ai-assistant' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Stops anyone who cannot manage options. Each handler checks its nonce right after.
	 */
	private static function require_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do this.', 'ariadne-ai-assistant' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Back to the settings page with a notice, and stop.
	 *
	 * @param string $notice Notice code.
	 */
	private static function back( $notice ) {
		Ariadne_Options::set_notice( $notice );
		wp_safe_redirect( self::page_url() );
		exit;
	}

	/**
	 * Saves the on/off switch.
	 */
	public static function save() {
		self::require_admin();
		check_admin_referer( self::SAVE_ACTION, self::SAVE_ACTION . '_nonce' );
		$enabled = isset( $_POST['ariadne_enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['ariadne_enabled'] ) );
		update_option( Ariadne_Options::ENABLED, $enabled ? '1' : '0' );
		self::back( 'saved' );
	}

	/**
	 * Saves a site key pasted by hand, with our own production addresses.
	 */
	public static function save_key() {
		self::require_admin();
		check_admin_referer( self::KEY_ACTION, self::KEY_ACTION . '_nonce' );
		$key = isset( $_POST['ariadne_site_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['ariadne_site_key'] ) ) ) : '';
		if ( ! Ariadne_Options::is_site_key( $key ) ) {
			self::back( 'key_invalid' );
		}
		$api_url    = ariadne_api_url();
		$widget_url = ariadne_widget_url();
		if ( ! Ariadne_Options::is_allowed_url( $api_url ) || ! Ariadne_Options::is_allowed_url( $widget_url ) ) {
			self::back( 'failed' );
		}
		Ariadne_Options::save_connection(
			array(
				'site_key'      => $key,
				'api_url'       => $api_url,
				'widget_url'    => $widget_url,
				'site_name'     => '',
				'dashboard_url' => '',
				'source'        => 'manual',
			)
		);
		self::back( 'key_saved' );
	}

	/**
	 * Forgets the connection. No call to Ariadne in v1: the site stays in the account.
	 */
	public static function disconnect() {
		self::require_admin();
		check_admin_referer( self::DISCONNECT_ACTION, self::DISCONNECT_ACTION . '_nonce' );
		Ariadne_Options::delete_connection();
		self::back( 'disconnected' );
	}
}
