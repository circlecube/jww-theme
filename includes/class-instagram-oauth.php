<?php
/**
 * Instagram OAuth: callback handler and "Re-authorize Instagram" flow.
 * Uses same Meta app as Facebook. Exchanges code for long-lived user token, fetches Page list
 * with instagram_business_account, saves Instagram Account ID and Page access token to options.
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Instagram_OAuth
 */
class Instagram_OAuth {

	const QUERY_VAR = 'instagram_oauth';
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
	 * Add rewrite rule for /instagram-oauth/.
	 */
	public function add_rewrite_rule() {
		add_rewrite_rule( '^instagram-oauth/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
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
	 * Handle OAuth callback: exchange code for tokens, get Page with Instagram, save to options, redirect to admin.
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
			$this->redirect_with_message( 'error', __( 'Facebook App ID and App Secret must be set in .env (JWW_FACEBOOK_APP_ID, JWW_FACEBOOK_APP_SECRET) to use the Instagram OAuth callback.', 'jww-theme' ) );
			return;
		}

		if ( $code === '' ) {
			$this->redirect_with_message( 'error', __( 'No authorization code received. Start from Settings → Social Sharing → Re-authorize Instagram.', 'jww-theme' ) );
			return;
		}

		$redirect_uri = home_url( '/instagram-oauth/', 'https' );
		if ( ! is_ssl() ) {
			$redirect_uri = home_url( '/instagram-oauth/', 'http' );
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
				'grant_type'        => 'fb_exchange_token',
				'client_id'         => $credentials['app_id'],
				'client_secret'     => $credentials['app_secret'],
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

		// Step 3: Get Page list with access_token and instagram_business_account.
		$accounts_url = add_query_arg(
			array(
				'access_token' => $long_lived_user_token,
				'fields'       => 'id,name,access_token,instagram_business_account',
			),
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
			$this->redirect_with_message( 'error', __( 'No Facebook Pages found. Create a Page, link your Instagram to it, and ensure the token has business_management if the Page is in Business Manager.', 'jww-theme' ) );
			return;
		}

		// Prefer the Page already configured for Instagram (same ig_user_id) so re-auth keeps the same account.
		$existing_ig_id = '';
		if ( function_exists( 'jww_social_get_config' ) ) {
			$ig_config = jww_social_get_config( 'instagram' );
			if ( ! empty( $ig_config['ig_user_id'] ) ) {
				$existing_ig_id = $ig_config['ig_user_id'];
			}
		}

		$chosen = null;
		foreach ( $pages as $page ) {
			$ig_account = isset( $page['instagram_business_account']['id'] ) ? (string) $page['instagram_business_account']['id'] : '';
			$ptoken    = isset( $page['access_token'] ) ? $page['access_token'] : '';
			if ( $ig_account === '' || $ptoken === '' ) {
				continue;
			}
			if ( $existing_ig_id !== '' && $ig_account === $existing_ig_id ) {
				$chosen = array( 'ig_user_id' => $ig_account, 'access_token' => $ptoken );
				break;
			}
			if ( $chosen === null ) {
				$chosen = array( 'ig_user_id' => $ig_account, 'access_token' => $ptoken );
			}
		}

		if ( $chosen === null ) {
			$this->redirect_with_message( 'error', __( 'No Page with a linked Instagram Business account found. Link your Instagram (Business/Creator) to your Facebook Page in Meta Business Suite.', 'jww-theme' ) );
			return;
		}

		update_option( 'jww_social_instagram_account_id', $chosen['ig_user_id'], false );
		update_option( 'jww_social_instagram_access_token', $chosen['access_token'], false );
		delete_transient( 'jww_social_token_email_instagram' );

		$this->redirect_with_message( 'success', __( 'Instagram token updated. Saved to WordPress options; if you use .env for Instagram, update JWW_INSTAGRAM_ACCESS_TOKEN there or remove it to use the saved option.', 'jww-theme' ) );
	}

	/**
	 * Redirect to Social Sharing settings page with a transient message.
	 *
	 * @param string $type    'success' or 'error'.
	 * @param string $message Message to show.
	 */
	private function redirect_with_message( $type, $message ) {
		if ( current_user_can( 'manage_options' ) ) {
			set_transient( 'jww_instagram_oauth_message', array( 'type' => $type, 'message' => $message ), 60 );
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=jww-social&instagram_oauth=1' ) );
		exit;
	}

	/**
	 * Build the Instagram OAuth authorize URL for "Re-authorize Instagram" button.
	 * Redirect URI must match exactly what is configured in Meta app (Facebook Login → Valid OAuth Redirect URIs).
	 *
	 * @return string|false URL to send the user to, or false if app credentials not available.
	 */
	public static function get_authorize_url() {
		$credentials = function_exists( 'jww_social_get_facebook_app_credentials' ) ? jww_social_get_facebook_app_credentials() : false;
		if ( ! $credentials ) {
			return false;
		}
		$redirect_uri = home_url( '/instagram-oauth/', 'https' );
		if ( ! is_ssl() ) {
			$redirect_uri = home_url( '/instagram-oauth/', 'http' );
		}
		return add_query_arg(
			array(
				'client_id'     => $credentials['app_id'],
				'redirect_uri'  => $redirect_uri,
				'scope'         => 'pages_show_list,instagram_basic,instagram_content_publish,business_management',
				'response_type' => 'code',
			),
			'https://www.facebook.com/' . self::GRAPH_VERSION . '/dialog/oauth'
		);
	}
}

new Instagram_OAuth();
