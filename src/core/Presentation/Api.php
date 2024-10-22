<?php
/**
 * The Api class is responsible for making requests to the PagBank Rest API.
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Carbon\Carbon;
use WC_Logger;
use Wilkques\PKCE\Generator;
use WP_Error;

/**
 * Class Api.
 */
class Api {

	/**
	 * The Connect instance.
	 *
	 * @var Connect
	 */
	private $connect;

	/**
	 * The log ID.
	 *
	 * @var string|null;
	 */
	private $log_id;

	/**
	 * The logger instance.
	 *
	 * @var WC_Logger;
	 */
	private $logger;

	/**
	 * Whether to use the sandbox environment.
	 *
	 * @var bool
	 */
	private $is_sandbox;

	/**
	 * Api constructor.
	 *
	 * @param string      $environment The environment to use.
	 * @param string|null $log_id The log ID.
	 */
	public function __construct( string $environment, string $log_id = null ) {
		$this->connect    = new Connect( $environment );
		$this->is_sandbox = $environment === 'sandbox';
		$this->log_id     = $log_id;
		$this->logger     = new WC_Logger();
	}

	/**
	 * Get the API URL.
	 *
	 * @param string $path The path to append to the API URL.
	 *
	 * @return string The API URL.
	 */
	public function get_api_url( string $path = '' ): string {
		return "https://{$this->get_environment()}api.pagseguro.com/$path";
	}

	/**
	 * Get the OAuth API URL.
	 *
	 * @param string $path The path to append to the API URL.
	 *
	 * @return string The API URL.
	 */
	public function get_oauth_api_url( string $path = '' ): string {
		return "https://connect.{$this->get_environment()}pagseguro.uol.com.br/$path";
	}

	/**
	 * Get the environment.
	 *
	 * @return string The environment.
	 */
	private function get_environment() {
		return $this->is_sandbox ? 'sandbox.' : '';
	}

	/**
	 * Get the URL for the OAuth flow.
	 *
	 * This will open a new window for the user to authenticate with PagSeguro. The state will contain the encrypted code verifier.
	 *
	 * @param string $callback_url The URL to redirect the user to after authentication.
	 * @param string $environment The environment to use.
	 * @param string $nonce The nonce to use for the state.
	 * @param string $application_id The application ID.
	 *
	 * @return string The URL to redirect the user to.
	 */
	public function get_oauth_url( string $callback_url, string $environment, string $nonce, string $application_id ): string {
		$code_challenge = $this->generate_code_challenge();

		set_transient( 'pagbank_oauth_code_verifier', $code_challenge['code_verifier'], 15 * MINUTE_IN_SECONDS );
		set_transient( 'pagbank_oauth_application_id', $application_id, 15 * MINUTE_IN_SECONDS );
		set_transient( 'pagbank_oauth_environment', $environment, 15 * MINUTE_IN_SECONDS );

		$url = http_build_url(
			$this->get_oauth_api_url( 'oauth2/authorize' ),
			array(
				'query' => implode(
					'&',
					array(
						'scope=' . implode( '+', array( 'payments.read', 'payments.create', 'payments.refund' ) ),
						'response_type=code',
						'client_id=' . $application_id,
						'redirect_uri=' . rawurlencode( $callback_url ),
						'state=' . $nonce,
						'code_challenge=' . $code_challenge['code_challenge'],
						'code_challenge_method=S256',
					)
				),
			)
		);

		$this->log( 'OAUTH_URL: ' . $url, 'pagbank_oauth' );
		$this->log( 'OAUTH CODE VERIFIER: ' . $code_challenge['code_verifier'], 'pagbank_oauth' );

		return $url;
	}

	/**
	 * Get the access token from the OAuth code.
	 *
	 * This will exchange the code for an access token.
	 *
	 * @param string $callback_url The URL to redirect the user to after authentication. Needs to be the same as used to generate the OAuth URL.
	 * @param string $oauth_code The OAuth code.
	 *
	 * @return array|WP_Error The access token, the token expiration time (in seconds), the account ID and the refresh token.
	 */
	public function get_access_token_from_oauth_code( string $callback_url, string $oauth_code ) {
		$url = $this->get_api_url( 'oauth2/token' );

		$code_verifier  = get_transient( 'pagbank_oauth_code_verifier' );
		$application_id = get_transient( 'pagbank_oauth_application_id' );
		$environment    = get_transient( 'pagbank_oauth_environment' );

		delete_transient( 'pagbank_oauth_code_verifier' );
		delete_transient( 'pagbank_oauth_application_id' );
		delete_transient( 'pagbank_oauth_environment' );

		if ( ! $environment ) {
			return new WP_Error( 'pagbank_oauth_invalid_environment', __( 'O ambiente é inválido.', 'pagbank-for-woocommerce' ) );
		}

		if ( ! $code_verifier ) {
			return new WP_Error( 'pagbank_oauth_invalid_code_verifier', __( 'O código de verificação é inválido.', 'pagbank-for-woocommerce' ) );
		}

		$applications = Connect::get_connect_applications( $environment );

		if ( ! $application_id || ! array_key_exists( $application_id, $applications ) ) {
			return new WP_Error( 'pagbank_oauth_invalid_application_id', __( 'ID da aplicação inválida.', 'pagbank-for-woocommerce' ) );
		}

		$body = $this->json_encode(
			array(
				'grant_type'    => 'authorization_code',
				'code'          => $oauth_code,
				'redirect_uri'  => $callback_url,
				'code_verifier' => $code_verifier,
			)
		);

		if ( defined( 'PAGBANK_LOG_OAUTH_REQUEST' ) && PAGBANK_LOG_OAUTH_REQUEST ) {
			$this->log_request_begin( $url, $body, 'pagbank_oauth' );
		}

		$response = $this->request(
			$url,
			array(
				'method'  => 'POST',
				'headers' => array(
					'Authorization' => 'Pub ' . $applications[ $application_id ]['access_token'],
					'Content-Type'  => 'application/json',
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( defined( 'PAGBANK_LOG_OAUTH_REQUEST' ) && PAGBANK_LOG_OAUTH_REQUEST ) {
				$this->log_request_error( $response, 'pagbank_oauth' );
			}

			return $response;
		}

		$response_code         = wp_remote_retrieve_response_code( $response );
		$response_body         = wp_remote_retrieve_body( $response );
		$decoded_response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( defined( 'PAGBANK_LOG_OAUTH_REQUEST' ) && PAGBANK_LOG_OAUTH_REQUEST ) {
			$this->log_request_ends( $response_code, $response_body, 'pagbank_oauth' );
		}

		if ( 200 !== $response_code ) {
			return new WP_Error( 'pagbank_request_error', __( 'Status HTTP inválido.', 'pagbank-for-woocommerce' ) );
		}

		return array(
			'application_id'  => $application_id,
			'environment'     => $environment,
			'token_type'      => $decoded_response_body['token_type'],
			'access_token'    => $decoded_response_body['access_token'],
			'expiration_date' => Carbon::now()->addSeconds( $decoded_response_body['expires_in'] )->toISOString(),
			'refresh_token'   => $decoded_response_body['refresh_token'],
			'scope'           => $decoded_response_body['scope'],
			'account_id'      => $decoded_response_body['account_id'],
		);
	}

	/**
	 * Refresh the access token.
	 *
	 * @param string $refresh_token The refresh token.
	 * @param string $environment The environment.
	 * @param string $application_id The application ID.
	 *
	 * @return array|WP_Error The access token, the token expiration time (in seconds), the account ID and the refresh token.
	 */
	public function refresh_access_token( string $refresh_token, string $environment, string $application_id ) {
		$url = $this->get_api_url( 'oauth2/refresh' );

		$applications = Connect::get_connect_applications( $environment );

		if ( ! $application_id || ! array_key_exists( $application_id, $applications ) ) {
			return new WP_Error( 'pagbank_oauth_invalid_application_id', __( 'O ID da aplicação é inválido.', 'pagbank-for-woocommerce' ) );
		}

		$body = $this->json_encode(
			array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => $refresh_token,
			)
		);
		$this->log_request_begin( $url, $body, 'pagbank_oauth' );

		$response = $this->request(
			$url,
			array(
				'method'  => 'POST',
				'headers' => array(
					'Authorization' => 'Pub ' . $applications[ $application_id ]['access_token'],
					'Content-Type'  => 'application/json',
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_request_error( $response, 'pagbank_oauth' );

			return $response;
		}

		$response_code         = wp_remote_retrieve_response_code( $response );
		$response_body         = wp_remote_retrieve_body( $response );
		$decoded_response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		$this->log_request_ends( $response_code, $response_body, 'pagbank_oauth' );

		if ( 200 !== $response_code ) {
			return new WP_Error( 'pagbank_request_error', __( 'Status HTTP inválido.', 'pagbank-for-woocommerce' ) );
		}

		return array(
			'application_id'  => $application_id,
			'environment'     => $environment,
			'token_type'      => $decoded_response_body['token_type'],
			'access_token'    => $decoded_response_body['access_token'],
			'expiration_date' => Carbon::now()->addSeconds( $decoded_response_body['expires_in'] )->toISOString(),
			'refresh_token'   => $decoded_response_body['refresh_token'],
			'scope'           => $decoded_response_body['scope'],
			'account_id'      => $decoded_response_body['account_id'],
		);
	}

	/**
	 * Generate a code challenge.
	 *
	 * @return array The code verifier and the code challenge.
	 */
	public function generate_code_challenge() {
		$pkce = Generator::generate();

		return array(
			'code_verifier'  => $pkce->getCodeVerifier(),
			'code_challenge' => $pkce->getCodeChallenge(),
		);
	}

	/**
	 * Create order.
	 *
	 * @param array $data The order data.
	 *
	 * @return array|WP_Error The order data.
	 */
	public function create_order( $data ) {
		$url = $this->get_api_url( 'orders' );

		$body = $this->json_encode( $data );

		$this->log_request_begin( $url, $body );

		$response = $this->request(
			$url,
			array(
				'method'  => 'POST',
				'headers' => array(
					'Authorization' => $this->connect->get_access_token(),
					'Content-Type'  => 'application/json',
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_request_error( $response );

			return $response;
		}

		$response_code         = wp_remote_retrieve_response_code( $response );
		$response_body         = wp_remote_retrieve_body( $response );
		$decoded_response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		$this->log_request_ends( $response_code, $response_body );

		if ( 201 !== $response_code ) {
			return new WP_Error( 'pagbank_order_creation_failed', 'PagBank order creation failed', $decoded_response_body );
		}

		return $decoded_response_body;
	}

	/**
	 * Refund an order.
	 *
	 * @param string $charge_id     The order ID.
	 * @param float  $amount The amount to be refunded.
	 *
	 * @return array|WP_Error The refund data.
	 */
	public function refund( string $charge_id, float $amount ) {
		$url = $this->get_api_url( 'charges/' . $charge_id . '/cancel' );

		$body = $this->json_encode(
			array(
				'amount' => array(
					'value' => Helpers::format_money_cents( $amount ),
				),
			)
		);

		$this->log_request_begin( $url, $body );

		$response = $this->request(
			$url,
			array(
				'method'  => 'POST',
				'headers' => array(
					'Authorization' => $this->connect->get_access_token(),
					'Content-Type'  => 'application/json',
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_request_error( $response );

			return $response;
		}

		$response_code         = wp_remote_retrieve_response_code( $response );
		$response_body         = wp_remote_retrieve_body( $response );
		$decoded_response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		$this->log_request_ends( $response_code, $response_body );

		if ( 201 !== $response_code ) {
			return new WP_Error( 'pagbank_charge_refund_failed', 'PagBank charge refund failed', $decoded_response_body );
		}

		return $decoded_response_body;
	}

	/**
	 * Get charge fees.
	 *
	 * @param float  $amount                         The charge value.
	 * @param int    $max_installments             The max installments.
	 * @param int    $max_installments_no_interest The max installments no interest.
	 * @param string $credit_card_bin              The credit card bin.
	 *
	 * @return array|WP_Error The fees data.
	 */
	public function charge_fees( float $amount, int $max_installments, int $max_installments_no_interest, string $credit_card_bin ) {
		$url = add_query_arg(
			array(
				'payment_methods'              => 'CREDIT_CARD',
				'value'                        => $amount,
				'max_installments'             => $max_installments,
				'max_installments_no_interest' => $max_installments_no_interest,
				'credit_card_bin'              => $credit_card_bin,
			),
			$this->get_api_url( 'charges/fees/calculate' )
		);

		$url_hash        = hash( 'sha256', $url );
		$cached_response = get_transient( 'pagbank_cached_request_' . $url_hash );

		if ( $cached_response ) {
			return json_decode( $cached_response, true );
		}

		$this->log_request_begin( $url, '' );

		$args = array(
			'method'  => 'GET',
			'headers' => array(
				'Authorization' => $this->connect->get_access_token(),
				'Content-Type'  => 'application/json',
			),
		);

		$response = $this->request(
			$url,
			$args
		);

		if ( is_wp_error( $response ) ) {
			$this->log_request_error( $response );

			return $response;
		}

		$response_code         = wp_remote_retrieve_response_code( $response );
		$response_body         = wp_remote_retrieve_body( $response );
		$decoded_response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		$this->log_request_ends( $response_code, $response_body );

		if ( 200 !== $response_code ) {
			return new WP_Error( 'pagbank_charge_calculate_fees_failed', 'PagBank calculate fees failed', $decoded_response_body );
		}

		set_transient( 'pagbank_cached_request_' . $url_hash, wp_json_encode( $decoded_response_body ), 15 * MINUTE_IN_SECONDS );

		return $decoded_response_body;
	}

	/**
	 * Get public key.
	 *
	 * @param string $access_token The access token.
	 *
	 * @return array|WP_Error The public key data.
	 */
	public function get_public_key( string $access_token = null ) {
		$url = $this->get_api_url( 'public-keys' );

		$body = $this->json_encode(
			array(
				'type' => 'card',
			)
		);

		$this->log_request_begin( $url, $body, 'pagbank_oauth' );

		$response = $this->request(
			$url,
			array(
				'method'  => 'POST',
				'headers' => array(
					'Authorization' => $access_token ?? $this->connect->get_access_token(),
					'Content-Type'  => 'application/json',
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_request_error( $response, 'pagbank_oauth' );

			return $response;
		}

		$response_code         = wp_remote_retrieve_response_code( $response );
		$response_body         = wp_remote_retrieve_body( $response );
		$decoded_response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		$this->log_request_ends( $response_code, $response_body, 'pagbank_oauth' );

		if ( 200 !== $response_code ) {
			return new WP_Error( 'pagbank_public_key_failed', 'PagBank get public key failed', $decoded_response_body );
		}

		return $decoded_response_body;
	}

	/**
	 * Log a message.
	 *
	 * @param string $message The message to be logged.
	 * @param string $log_id  The log ID.
	 */
	private function log( string $message, string $log_id = null ): void {
		$id = $log_id ?? $this->log_id;
		if ( $id ) {
			$this->logger->add( $id, $message );
		}
	}

	/**
	 * Log request begin.
	 *
	 * @param string $url  The request URL.
	 * @param string $body The request body.
	 * @param string $log_id  The log ID.
	 *
	 * @return void
	 */
	private function log_request_begin( string $url, string $body, string $log_id = null ): void {
		$this->log( 'REQUEST BEGINS', $log_id );
		$this->log( 'REQUEST URL: ' . $url, $log_id );
		$this->log( 'REQUEST BODY: ' . $body, $log_id );
	}

	/**
	 * Log request error.
	 *
	 * @param WP_Error $error The request error.
	 * @param string   $log_id  The log ID.
	 *
	 * @return void
	 */
	private function log_request_error( WP_Error $error, string $log_id = null ): void {
		$this->log( 'REQUEST ERROR: ' . $error->get_error_message(), $log_id );
		$this->log( "REQUEST ENDS\n", $log_id );
	}

	/**
	 * Log request ends.
	 *
	 * @param int    $response_code The response code.
	 * @param string $response_body The response body.
	 * @param string $log_id  The log ID.
	 *
	 * @return void
	 */
	private function log_request_ends( int $response_code, string $response_body, string $log_id = null ): void {
		$this->log( 'RESPONSE CODE: ' . $response_code, $log_id );
		$this->log( 'RESPONSE BODY: ' . $response_body, $log_id );
		$this->log( "REQUEST ENDS\n", $log_id );
	}

	/**
	 * Encode data.
	 *
	 * @param array $data The data to be encoded.
	 *
	 * @return string The encoded data.
	 */
	private function json_encode( $data ) {
		return wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Request API.
	 *
	 * @param string $url  The request URL.
	 * @param array  $args The request args.
	 */
	private function request( string $url, array $args = array() ) {
		$default_args = array(
			'timeout' => 15, // timeout in seconds.
		);
		$request_args = wp_parse_args( $args, $default_args );

		return wp_remote_request( $url, $request_args );
	}
}
