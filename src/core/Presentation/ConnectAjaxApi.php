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
	 */
	private static ?ConnectAjaxApi $instance = null;

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
	public static function get_instance(): ConnectAjaxApi {
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
	 * Get OAuth error message from error code.
	 *
	 * @param string $error_code The error code from PagBank.
	 *
	 * @return string The error message.
	 */
	private function get_oauth_error_message( string $error_code ): string {
		$error_messages = array(
			'invalid_request'           => __( 'A requisição é inválida. Por favor, tente novamente. Caso o problema persista, entre em contato com o suporte.', 'pagbank-for-woocommerce' ),
			'unauthorized_client'       => __( 'A aplicação não está autorizada. Por favor, entre em contato com o suporte.', 'pagbank-for-woocommerce' ),
			'access_denied'             => __( 'Acesso negado. Você precisa autorizar o acesso para continuar.', 'pagbank-for-woocommerce' ),
			'unsupported_response_type' => __( 'Tipo de resposta não suportado. Por favor, entre em contato com o suporte.', 'pagbank-for-woocommerce' ),
			'invalid_scope'             => __( 'Escopo inválido. Por favor, entre em contato com o suporte.', 'pagbank-for-woocommerce' ),
			'server_error'              => __( 'Erro no servidor do PagBank. Por favor, tente novamente mais tarde.', 'pagbank-for-woocommerce' ),
			'temporarily_unavailable'   => __( 'O serviço está temporariamente indisponível. Por favor, tente novamente mais tarde.', 'pagbank-for-woocommerce' ),
		);

		return $error_messages[ $error_code ] ?? __( 'Ocorreu um erro durante a autenticação. Por favor, tente novamente.', 'pagbank-for-woocommerce' );
	}

	/**
	 * Output script to close popup and optionally send error to opener.
	 *
	 * @param string|null $error_message The error message to send to opener, or null for success.
	 */
	private function output_oauth_close_script( ?string $error_message = null ): void {
		$message = array(
			'type'    => 'pagbank_oauth_callback',
			'success' => null === $error_message,
		);

		if ( null !== $error_message ) {
			$message['error'] = $error_message;
		}

		$json_message = wp_json_encode( $message );

		echo '<script>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON is safely encoded by wp_json_encode.
		echo 'if (window.opener) { window.opener.postMessage(' . $json_message . ', "*"); }';
		echo 'window.close();';
		echo '</script>';
	}

	/**
	 * Log OAuth error.
	 *
	 * @param string               $message The error message.
	 * @param array<string, mixed> $context Additional context data.
	 */
	private function log_oauth_error( string $message, array $context = array() ): void {
		$logger = wc_get_logger();
		$logger->error(
			$message,
			array_merge(
				array( 'source' => 'pagbank_oauth' ),
				$context
			)
		);
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

		// Handle OAuth errors from PagBank.
		if ( isset( $_GET['error'] ) ) {
			$error_code        = sanitize_text_field( wp_unslash( $_GET['error'] ) );
			$error_description = isset( $_GET['error_description'] ) ? sanitize_text_field( wp_unslash( $_GET['error_description'] ) ) : '';
			$error_message     = $this->get_oauth_error_message( $error_code );

			$this->log_oauth_error(
				'OAuth error from PagBank',
				array(
					'error_code'        => $error_code,
					'error_description' => $error_description,
					'callback_url'      => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
				)
			);

			$this->output_oauth_close_script( $error_message );
			wp_die();
		}

		if ( ! isset( $_GET['code'] ) ) {
			$this->log_oauth_error(
				'OAuth callback missing code parameter',
				array(
					'callback_url' => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
				)
			);

			$this->output_oauth_close_script( __( 'Código de autorização não recebido. Por favor, tente novamente.', 'pagbank-for-woocommerce' ) );
			wp_die();
		}

		$environment = isset( $_GET['environment'] ) && 'production' === $_GET['environment'] ? 'production' : 'sandbox';

		$api     = new Api( $environment );
		$connect = new Connect( $environment );

		$oauth_code   = sanitize_text_field( wp_unslash( $_GET['code'] ) );
		$callback_url = self::get_callback_url( $environment );
		$data         = $api->get_access_token_from_oauth_code( $callback_url, $oauth_code );

		if ( is_wp_error( $data ) ) {
			$this->log_oauth_error(
				'Failed to exchange OAuth code for access token',
				array(
					'error_code'    => $data->get_error_code(),
					'error_message' => $data->get_error_message(),
					'error_data'    => $data->get_error_data(),
				)
			);

			$this->output_oauth_close_script( __( 'Erro ao autorizar a aplicação. Por favor, tente novamente.', 'pagbank-for-woocommerce' ) );
			wp_die();
		}

		$public_key = $api->get_public_key( $data['access_token'] );

		if ( is_wp_error( $public_key ) ) {
			$this->log_oauth_error(
				'Failed to get public key',
				array(
					'error_code'    => $public_key->get_error_code(),
					'error_message' => $public_key->get_error_message(),
					'error_data'    => $public_key->get_error_data(),
				)
			);

			$this->output_oauth_close_script( __( 'Erro ao obter a chave pública. Por favor, tente novamente.', 'pagbank-for-woocommerce' ) );
			wp_die();
		}

		$data['public_key'] = $public_key['public_key'];

		// Fetch account data to store alongside the token.
		if ( ! empty( $data['account_id'] ) ) {
			$account_data = $api->get_account_with_token( $data['account_id'], $data['access_token'] );

			if ( ! is_wp_error( $account_data ) ) {
				$data['account'] = $account_data;
			}
		}

		$connect->save( $data );

		$this->output_oauth_close_script();
		wp_die();
	}
}
