<?php
/**
 * Social token refresh: auto-refresh Threads when possible; check Meta tokens and email admin when manual re-auth is required.
 *
 * @package JWW_Theme
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Social_Token_Refresh
 */
class Social_Token_Refresh {

	const CRON_HOOK = 'jww_social_token_refresh';
	const EMAIL_THROTTLE_DAYS = 7;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'maybe_schedule_cron' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_refresh_checks' ) );
	}

	/**
	 * Schedule daily cron if not already scheduled.
	 */
	public function maybe_schedule_cron() {
		if ( ! current_user_can( 'manage_options' ) && ! wp_doing_cron() ) {
			return;
		}
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}
		wp_schedule_event( time(), 'daily', self::CRON_HOOK );
	}

	/**
	 * Run refresh checks for Threads (auto-refresh) and Meta tokens (debug + email if invalid).
	 */
	public function run_refresh_checks() {
		$this->maybe_refresh_threads_token();
		$this->maybe_check_facebook_token();
		$this->maybe_check_instagram_token();
	}

	/**
	 * Refresh Threads long-lived token if it exists and is within 7 days of expiry (or already expired).
	 * On failure (e.g. token expired), send one admin email and throttle repeats.
	 */
	protected function maybe_refresh_threads_token() {
		$token = get_option( 'jww_social_threads_access_token', '' );
		if ( $token === '' ) {
			return;
		}
		$expires_at = (int) get_option( 'jww_social_threads_token_expires_at', 0 );
		$now = time();
		$seven_days = 7 * 24 * 60 * 60;
		// Refresh if we have no expiry stored (legacy), or if token expires within 7 days or is already expired.
		if ( $expires_at > 0 && $expires_at > $now + $seven_days ) {
			return;
		}

		$refresh_url = add_query_arg(
			array(
				'grant_type'   => 'th_refresh_token',
				'access_token' => $token,
			),
			'https://graph.threads.net/refresh_access_token'
		);

		$response = wp_remote_get( $refresh_url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			$this->send_token_email( 'threads', __( 'Threads token refresh failed (network error). Please re-authorize in Settings → Social Sharing.', 'jww-theme' ) );
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 200 && $code <= 299 && ! empty( $data['access_token'] ) ) {
			$new_token = $data['access_token'];
			$expires_in = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : ( 60 * 24 * 60 * 60 );
			update_option( 'jww_social_threads_access_token', $new_token, false );
			update_option( 'jww_social_threads_token_expires_at', time() + $expires_in, false );
			if ( function_exists( 'jww_social_debug_log' ) ) {
				jww_social_debug_log( 'threads', 'Token refreshed successfully via cron.' );
			}
			return;
		}

		// Refresh failed (expired or invalid) — notify admin once per throttle period.
		$this->send_token_email( 'threads', __( 'Threads token could not be refreshed (expired or invalid). Please re-authorize in Settings → Social Sharing.', 'jww-theme' ) );
	}

	/**
	 * Check Facebook Page token via Graph API debug_token. If invalid, send admin email (throttled).
	 */
	protected function maybe_check_facebook_token() {
		$token = get_option( 'jww_social_facebook_page_access_token', '' );
		if ( $token === '' ) {
			return;
		}
		$credentials = function_exists( 'jww_social_get_facebook_app_credentials' ) ? jww_social_get_facebook_app_credentials() : false;
		if ( ! $credentials ) {
			return;
		}
		$app_token = $credentials['app_id'] . '|' . $credentials['app_secret'];
		$url = add_query_arg(
			array(
				'input_token'  => $token,
				'access_token' => $app_token,
			),
			'https://graph.facebook.com/v25.0/debug_token'
		);
		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );
		if ( is_wp_error( $response ) ) {
			return;
		}
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		$valid = isset( $data['data']['is_valid'] ) && $data['data']['is_valid'];
		$expires_at = isset( $data['data']['expires_at'] ) ? (int) $data['data']['expires_at'] : 0;
		if ( $expires_at > 0 && $expires_at < time() ) {
			$valid = false;
		}
		if ( ! $valid ) {
			$this->send_token_email( 'facebook', __( 'Facebook Page token is invalid or expired. Please re-authorize in Settings → Social Sharing.', 'jww-theme' ) );
		}
	}

	/**
	 * Check Instagram (Page) token via Graph API debug_token. If invalid, send admin email (throttled).
	 */
	protected function maybe_check_instagram_token() {
		$token = get_option( 'jww_social_instagram_access_token', '' );
		if ( $token === '' ) {
			return;
		}
		$credentials = function_exists( 'jww_social_get_facebook_app_credentials' ) ? jww_social_get_facebook_app_credentials() : false;
		if ( ! $credentials ) {
			return;
		}
		$app_token = $credentials['app_id'] . '|' . $credentials['app_secret'];
		$url = add_query_arg(
			array(
				'input_token'  => $token,
				'access_token' => $app_token,
			),
			'https://graph.facebook.com/v25.0/debug_token'
		);
		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );
		if ( is_wp_error( $response ) ) {
			return;
		}
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		$valid = isset( $data['data']['is_valid'] ) && $data['data']['is_valid'];
		$expires_at = isset( $data['data']['expires_at'] ) ? (int) $data['data']['expires_at'] : 0;
		if ( $expires_at > 0 && $expires_at < time() ) {
			$valid = false;
		}
		if ( ! $valid ) {
			$this->send_token_email( 'instagram', __( 'Instagram token is invalid or expired. Please re-authorize in Settings → Social Sharing.', 'jww-theme' ) );
		}
	}

	/**
	 * Send one email to admin about token re-auth; throttle per service.
	 *
	 * @param string $service Service key: threads, facebook, instagram.
	 * @param string $message Body message (already translated).
	 */
	protected function send_token_email( $service, $message ) {
		$transient_key = 'jww_social_token_email_' . $service;
		if ( get_transient( $transient_key ) ) {
			return;
		}
		$admin_email = get_option( 'admin_email' );
		if ( ! $admin_email || ! is_email( $admin_email ) ) {
			return;
		}
		$settings_url = admin_url( 'options-general.php?page=jww-social' );
		$site_name = get_bloginfo( 'name' );
		$subject = sprintf(
			/* translators: 1: site name, 2: service name */
			__( '[%1$s] Social Sharing: %2$s token needs re-authorization', 'jww-theme' ),
			$site_name,
			$this->get_service_label( $service )
		);
		$body = $message . "\n\n" . __( 'Re-authorize here:', 'jww-theme' ) . "\n" . $settings_url;
		$sent = wp_mail( $admin_email, $subject, $body );
		if ( $sent ) {
			set_transient( $transient_key, '1', self::EMAIL_THROTTLE_DAYS * DAY_IN_SECONDS );
		}
	}

	/**
	 * Human-readable label for service key.
	 *
	 * @param string $service threads, facebook, instagram.
	 * @return string
	 */
	protected function get_service_label( $service ) {
		$labels = array(
			'threads'   => 'Threads',
			'facebook'  => 'Facebook',
			'instagram' => 'Instagram',
		);
		return isset( $labels[ $service ] ) ? $labels[ $service ] : $service;
	}
}

new Social_Token_Refresh();
