<?php
/**
 * The ConnectAjaxApi class is responsible for handling the ajax requests that will happen through admin-ajax.php.
 * The requests are:
 * - pagbank_woocommerce_oauth_status: Returns the oauth status.
 * - pagbank_woocommerce_oauth_url: Returns the oauth url.
 * - pagbank_woocommerce_oauth_callback: Handles the oauth callback.
 *
 * All the ajax requests needs admin authentication and only users with 'manage_options' capability can access it.
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ConnectAjaxApi.
 */
class ConnectAjaxApi {

	/**
	 * Instance.
	 *
	 * @var ConnectAjaxApi
	 */
	private static $instance = null;

	/**
	 * Init.
	 */
	public function __construct() {
		add_action( 'wp_ajax_pagbank_woocommerce_oauth_status', array( $this, 'ajax_get_oauth_status' ) );
		add_action( 'wp_ajax_pagbank_woocommerce_oauth_url', array( $this, 'ajax_get_oauth_url' ) );
		add_action( 'wp_ajax_pagbank_woocommerce_oauth_callback', array( $this, 'ajax_oauth_callback' ) );
	}

	/**
	 * Get instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get callback url to start the oAuth flow.
	 *
	 * @param string $environment The environment.
	 */
	public static function get_callback_url( string $environment ): string {
		$ajax_url = admin_url( 'admin-ajax.php' );
		$url      = add_query_arg(
			array(
				'action'      => 'pagbank_woocommerce_oauth_callback',
				'environment' => $environment === 'production' ? 'production' : 'sandbox',
			),
			$ajax_url
		);

		return $url;
	}

	/**
	 * Get oauth status and account id.
	 */
	public function ajax_get_oauth_status(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}

		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'pagbank_woocommerce_oauth' ) ) {
			wp_die( 'Invalid nonce' );
		}

		$environment = isset( $_GET['environment'] ) && 'production' === $_GET['environment'] ? 'production' : 'sandbox';

		$connect = new Connect( $environment );
		$data    = $connect->get_data();

		if ( ! $data ) {
			wp_send_json(
				array(
					'oauth_status' => 'not_connected',
					'environment'  => $environment,
				)
			);
		} else {
			wp_send_json(
				array(
					'oauth_status' => 'connected',
					'environment'  => $environment,
					'account_id'   => $data['account_id'],
				)
			);
		}

		wp_die();
	}

	/**
	 * Get oauth url ajax response.
	 *
	 * The 'pagbank_woocommerce_oauth' nonce is used to prevent CSRF attacks.
	 * This nonce will be used since the button, until the callback.
	 */
	public function ajax_get_oauth_url(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}

		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'pagbank_woocommerce_oauth' ) ) {
			wp_die( 'Invalid nonce' );
		}

		$application_id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		$environment    = isset( $_GET['environment'] ) && 'production' === $_GET['environment'] ? 'production' : 'sandbox';
		$applications   = Connect::get_connect_applications( $environment );

		if ( ! $application_id || ! array_key_exists( $application_id, $applications ) ) {
			wp_die( 'Invalid application id' );
		}

		$api   = new Api( $environment );
		$nonce = sanitize_text_field( wp_unslash( $_GET['nonce'] ) );

		$oauth_url = $api->get_oauth_url( self::get_callback_url( $environment ), $environment, $nonce, $application_id );

		wp_send_json(
			array(
				'oauth_url' => $oauth_url,
			)
		);
		wp_die();
	}

	/**
	 * Oauth callback response.
	 *
	 * Will output a window.close() script to close the popup.
	 * The 'pagbank_woocommerce_oauth' nonce is used to prevent CSRF attacks.
	 */
	public function ajax_oauth_callback(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}

		if ( ! isset( $_GET['state'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['state'] ) ), 'pagbank_woocommerce_oauth' ) ) {
			wp_die( 'Invalid nonce' );
		}

		if ( ! isset( $_GET['code'] ) ) {
			wp_die( 'Missing code' );
		}

		$environment = isset( $_GET['environment'] ) && 'production' === $_GET['environment'] ? 'production' : 'sandbox';

		$api     = new Api( $environment );
		$connect = new Connect( $environment );

		$oauth_code   = sanitize_text_field( wp_unslash( $_GET['code'] ) );
		$callback_url = self::get_callback_url( $environment );
		$data         = $api->get_access_token_from_oauth_code( $callback_url, $oauth_code );

		if ( is_wp_error( $data ) ) {
			wp_die( esc_html( __( 'Erro ao autorizar a aplicação', 'pagbank-for-woocommerce' ) ) );
			return;
		}

		$public_key = $api->get_public_key( $data['access_token'] );

		if ( is_wp_error( $public_key ) ) {
			wp_die( esc_html( __( 'Erro ao obter a public key', 'pagbank-for-woocommerce' ) ) );
			return;
		}

		$data['public_key'] = $public_key['public_key'];

		$connect->save( $data );

		echo '<script>window.close();</script>';
		wp_die();
	}
}
