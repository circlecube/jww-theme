<?php
/**
 * Threads OAuth: callback handler and "Re-authorize Threads" flow. Exchanges authorization code for long-lived token and saves to options.
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Threads_OAuth
 */
class Threads_OAuth {

	const QUERY_VAR = 'threads_oauth';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'handle_callback' ), 1 );
	}

	/**
	 * Add rewrite rule for /threads-oauth/.
	 */
	public function add_rewrite_rule() {
		add_rewrite_rule( '^threads-oauth/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Register query var.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Handle OAuth callback: exchange code for token, save, redirect to admin.
	 */
	public function handle_callback() {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		// Code may be in URL with fragment (#_) - ensure we only have the code.
		if ( $code !== '' && strpos( $code, '#' ) !== false ) {
			$code = strtok( $code, '#' );
		}

		$credentials = function_exists( 'jww_social_get_threads_app_credentials' ) ? jww_social_get_threads_app_credentials() : false;
		if ( ! $credentials ) {
			$this->redirect_with_message( 'error', __( 'Threads App ID and App Secret must be set in .env (JWW_THREADS_APP_ID, JWW_THREADS_APP_SECRET) or in options to use the OAuth callback.', 'jww-theme' ) );
			return;
		}

		if ( $code === '' ) {
			$this->redirect_with_message( 'error', __( 'No authorization code received. Start from Settings → Social Sharing → Re-authorize Threads.', 'jww-theme' ) );
			return;
		}

		$redirect_uri = home_url( '/threads-oauth/', 'https' );
		if ( is_ssl() === false ) {
			$redirect_uri = home_url( '/threads-oauth/', 'http' );
		}

		// Step 1: Exchange code for short-lived access_token and user_id.
		$exchange_url = 'https://graph.threads.net/oauth/access_token';
		$exchange_body = array(
			'client_id'     => $credentials['app_id'],
			'client_secret' => $credentials['app_secret'],
			'grant_type'    => 'authorization_code',
			'redirect_uri'  => $redirect_uri,
			'code'          => $code,
		);

		$response = wp_remote_post(
			$exchange_url,
			array(
				'body'    => $exchange_body,
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->redirect_with_message( 'error', $response->get_error_message() );
			return;
		}

		$code_http = wp_remote_retrieve_response_code( $response );
		$body     = wp_remote_retrieve_body( $response );
		$data     = json_decode( $body, true );

		if ( $code_http < 200 || $code_http > 299 ) {
			$message = isset( $data['error']['message'] ) ? $data['error']['message'] : sprintf( __( 'Token exchange failed (HTTP %d)', 'jww-theme' ), $code_http );
			$this->redirect_with_message( 'error', $message );
			return;
		}

		$short_lived_token = isset( $data['access_token'] ) ? $data['access_token'] : '';
		$user_id          = isset( $data['user_id'] ) ? $data['user_id'] : '';

		if ( $short_lived_token === '' || $user_id === '' ) {
			$this->redirect_with_message( 'error', __( 'Token exchange did not return access_token or user_id.', 'jww-theme' ) );
			return;
		}

		// Step 2: Exchange short-lived token for long-lived (~60 days).
		$long_lived_url = add_query_arg(
			array(
				'grant_type'    => 'th_exchange_token',
				'client_secret' => $credentials['app_secret'],
				'access_token'  => $short_lived_token,
			),
			'https://graph.threads.net/access_token'
		);

		$long_response = wp_remote_get( $long_lived_url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $long_response ) ) {
			$this->redirect_with_message( 'error', $long_response->get_error_message() );
			return;
		}

		$long_body = wp_remote_retrieve_body( $long_response );
		$long_data = json_decode( $long_body, true );
		$long_code = wp_remote_retrieve_response_code( $long_response );

		if ( $long_code < 200 || $long_code > 299 ) {
			$message = isset( $long_data['error']['message'] ) ? $long_data['error']['message'] : sprintf( __( 'Long-lived token exchange failed (HTTP %d)', 'jww-theme' ), $long_code );
			$this->redirect_with_message( 'error', $message );
			return;
		}

		$long_lived_token = isset( $long_data['access_token'] ) ? $long_data['access_token'] : '';
		if ( $long_lived_token === '' ) {
			$this->redirect_with_message( 'error', __( 'Long-lived token exchange did not return an access token.', 'jww-theme' ) );
			return;
		}

		$expires_in = isset( $long_data['expires_in'] ) ? (int) $long_data['expires_in'] : ( 60 * 24 * 60 * 60 ); // Default 60 days in seconds.
		$expires_at = time() + $expires_in;

		// Save to options (theme config reads options first, then .env).
		update_option( 'jww_social_threads_user_id', $user_id, false );
		update_option( 'jww_social_threads_access_token', $long_lived_token, false );
		update_option( 'jww_social_threads_token_expires_at', $expires_at, false );
		delete_transient( 'jww_social_token_email_threads' );

		$this->redirect_with_message( 'success', __( 'Threads token updated. Saved to WordPress options; if you use .env for Threads, update JWW_THREADS_ACCESS_TOKEN there or remove it to use the saved option.', 'jww-theme' ) );
	}

	/**
	 * Redirect to Social Sharing settings page with a transient message.
	 *
	 * @param string $type    'success' or 'error'.
	 * @param string $message Message to show.
	 */
	private function redirect_with_message( $type, $message ) {
		if ( current_user_can( 'manage_options' ) ) {
			set_transient( 'jww_threads_oauth_message', array( 'type' => $type, 'message' => $message ), 60 );
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=jww-social&threads_oauth=1' ) );
		exit;
	}

	/**
	 * Build the Threads OAuth authorize URL for "Re-authorize Threads" button.
	 * Redirect URI must match exactly what is configured in Meta app (e.g. https://jessewellesworld.com/threads-oauth/).
	 *
	 * @return string|false URL to send the user to, or false if app credentials not available.
	 */
	public static function get_authorize_url() {
		$credentials = function_exists( 'jww_social_get_threads_app_credentials' ) ? jww_social_get_threads_app_credentials() : false;
		if ( ! $credentials ) {
			return false;
		}
		$redirect_uri = home_url( '/threads-oauth/', 'https' );
		if ( ! is_ssl() ) {
			$redirect_uri = home_url( '/threads-oauth/', 'http' );
		}
		return add_query_arg(
			array(
				'client_id'     => $credentials['app_id'],
				'redirect_uri'  => $redirect_uri,
				'scope'         => 'threads_basic,threads_content_publish',
				'response_type' => 'code',
			),
			'https://threads.net/oauth/authorize'
		);
	}
}

new Threads_OAuth();
