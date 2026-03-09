<?php
/**
 * Facebook OAuth: callback handler and "Re-authorize Facebook" flow.
 * Exchanges authorization code for long-lived user token, fetches Page list, saves Page ID and Page access token to options.
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Facebook_OAuth
 */
class Facebook_OAuth {

	const QUERY_VAR = 'facebook_oauth';
	const GRAPH_VERSION = 'v18.0';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'handle_callback' ), 1 );
	}

	/**
	 * Add rewrite rule for /facebook-oauth/.
	 */
	public function add_rewrite_rule() {
		add_rewrite_rule( '^facebook-oauth/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
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
	 * Handle OAuth callback: exchange code for tokens, get Page, save to options, redirect to admin.
	 */
	public function handle_callback() {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		if ( $code !== '' && strpos( $code, '#' ) !== false ) {
			$code = strtok( $code, '#' );
		}

		$credentials = function_exists( 'jww_social_get_facebook_app_credentials' ) ? jww_social_get_facebook_app_credentials() : false;
		if ( ! $credentials ) {
			$this->redirect_with_message( 'error', __( 'Facebook App ID and App Secret must be set in .env (JWW_FACEBOOK_APP_ID, JWW_FACEBOOK_APP_SECRET) to use the OAuth callback.', 'jww-theme' ) );
			return;
		}

		if ( $code === '' ) {
			$this->redirect_with_message( 'error', __( 'No authorization code received. Start from Settings → Social Sharing → Re-authorize Facebook.', 'jww-theme' ) );
			return;
		}

		$redirect_uri = home_url( '/facebook-oauth/', 'https' );
		if ( ! is_ssl() ) {
			$redirect_uri = home_url( '/facebook-oauth/', 'http' );
		}

		// Step 1: Exchange code for short-lived user access token.
		$exchange_url = add_query_arg(
			array(
				'client_id'     => $credentials['app_id'],
				'client_secret' => $credentials['app_secret'],
				'redirect_uri'  => $redirect_uri,
				'code'          => $code,
			),
			'https://graph.facebook.com/' . self::GRAPH_VERSION . '/oauth/access_token'
		);

		$response = wp_remote_get( $exchange_url, array( 'timeout' => 15 ) );

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
		if ( $short_lived_token === '' ) {
			$this->redirect_with_message( 'error', __( 'Token exchange did not return an access token.', 'jww-theme' ) );
			return;
		}

		// Step 2: Exchange short-lived user token for long-lived user token.
		$long_lived_url = add_query_arg(
			array(
				'grant_type'       => 'fb_exchange_token',
				'client_id'        => $credentials['app_id'],
				'client_secret'    => $credentials['app_secret'],
				'fb_exchange_token' => $short_lived_token,
			),
			'https://graph.facebook.com/' . self::GRAPH_VERSION . '/oauth/access_token'
		);

		$long_response = wp_remote_get( $long_lived_url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $long_response ) ) {
			$this->redirect_with_message( 'error', $long_response->get_error_message() );
			return;
		}

		$long_code = wp_remote_retrieve_response_code( $long_response );
		$long_body = wp_remote_retrieve_body( $long_response );
		$long_data = json_decode( $long_body, true );

		if ( $long_code < 200 || $long_code > 299 ) {
			$message = isset( $long_data['error']['message'] ) ? $long_data['error']['message'] : sprintf( __( 'Long-lived token exchange failed (HTTP %d)', 'jww-theme' ), $long_code );
			$this->redirect_with_message( 'error', $message );
			return;
		}

		$long_lived_user_token = isset( $long_data['access_token'] ) ? $long_data['access_token'] : '';
		if ( $long_lived_user_token === '' ) {
			$this->redirect_with_message( 'error', __( 'Long-lived token exchange did not return an access token.', 'jww-theme' ) );
			return;
		}

		// Step 3: Get Page list and pick the page to use.
		$accounts_url = add_query_arg(
			array( 'access_token' => $long_lived_user_token ),
			'https://graph.facebook.com/' . self::GRAPH_VERSION . '/me/accounts'
		);

		$accounts_response = wp_remote_get( $accounts_url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $accounts_response ) ) {
			$this->redirect_with_message( 'error', $accounts_response->get_error_message() );
			return;
		}

		$acc_code = wp_remote_retrieve_response_code( $accounts_response );
		$acc_body = wp_remote_retrieve_body( $accounts_response );
		$acc_data = json_decode( $acc_body, true );

		if ( $acc_code < 200 || $acc_code > 299 ) {
			$message = isset( $acc_data['error']['message'] ) ? $acc_data['error']['message'] : sprintf( __( 'Could not load Pages (HTTP %d)', 'jww-theme' ), $acc_code );
			$this->redirect_with_message( 'error', $message );
			return;
		}

		$pages = isset( $acc_data['data'] ) && is_array( $acc_data['data'] ) ? $acc_data['data'] : array();
		if ( empty( $pages ) ) {
			$this->redirect_with_message( 'error', __( 'No Facebook Pages found for this account. Create a Page or use an account that manages a Page.', 'jww-theme' ) );
			return;
		}

		// Prefer the Page ID already configured (e.g. from .env) so re-auth keeps the same page.
		$existing_page_id = '';
		if ( function_exists( 'jww_social_get_config' ) ) {
			$fb_config = jww_social_get_config( 'facebook' );
			if ( ! empty( $fb_config['page_id'] ) ) {
				$existing_page_id = $fb_config['page_id'];
			}
		}

		$chosen = null;
		foreach ( $pages as $page ) {
			$pid = isset( $page['id'] ) ? (string) $page['id'] : '';
			$ptoken = isset( $page['access_token'] ) ? $page['access_token'] : '';
			if ( $pid !== '' && $ptoken !== '' ) {
				if ( $existing_page_id !== '' && $pid === $existing_page_id ) {
					$chosen = array( 'id' => $pid, 'access_token' => $ptoken );
					break;
				}
				if ( $chosen === null ) {
					$chosen = array( 'id' => $pid, 'access_token' => $ptoken );
				}
			}
		}

		if ( $chosen === null ) {
			$this->redirect_with_message( 'error', __( 'No valid Page access token in the response.', 'jww-theme' ) );
			return;
		}

		update_option( 'jww_social_facebook_page_id', $chosen['id'], false );
		update_option( 'jww_social_facebook_page_access_token', $chosen['access_token'], false );

		$this->redirect_with_message( 'success', __( 'Facebook Page token updated. Saved to WordPress options; if you use .env for Facebook, update JWW_FACEBOOK_PAGE_ACCESS_TOKEN there or remove it to use the saved option.', 'jww-theme' ) );
	}

	/**
	 * Redirect to Social Sharing settings page with a transient message.
	 *
	 * @param string $type    'success' or 'error'.
	 * @param string $message Message to show.
	 */
	private function redirect_with_message( $type, $message ) {
		if ( current_user_can( 'manage_options' ) ) {
			set_transient( 'jww_facebook_oauth_message', array( 'type' => $type, 'message' => $message ), 60 );
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=jww-social&facebook_oauth=1' ) );
		exit;
	}

	/**
	 * Build the Facebook OAuth authorize URL for "Re-authorize Facebook" button.
	 * Redirect URI must match exactly what is configured in Meta app (Facebook Login → Settings → Valid OAuth Redirect URIs).
	 *
	 * @return string|false URL to send the user to, or false if app credentials not available.
	 */
	public static function get_authorize_url() {
		$credentials = function_exists( 'jww_social_get_facebook_app_credentials' ) ? jww_social_get_facebook_app_credentials() : false;
		if ( ! $credentials ) {
			return false;
		}
		$redirect_uri = home_url( '/facebook-oauth/', 'https' );
		if ( ! is_ssl() ) {
			$redirect_uri = home_url( '/facebook-oauth/', 'http' );
		}
		return add_query_arg(
			array(
				'client_id'     => $credentials['app_id'],
				'redirect_uri'  => $redirect_uri,
				'scope'         => 'pages_show_list,pages_read_engagement,pages_manage_posts',
				'response_type' => 'code',
			),
			'https://www.facebook.com/' . self::GRAPH_VERSION . '/dialog/oauth'
		);
	}
}

new Facebook_OAuth();
