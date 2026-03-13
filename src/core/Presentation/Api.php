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
use Wilkques\PKCE\Generator;
use WP_Error;

/**
 * Class Api.
 */
class Api {

	/**
	 * Required OAuth scopes for full functionality.
	 *
	 * @var array
	 */
	public const REQUIRED_SCOPES = array(
		'accounts.read',
		'payments.read',
		'payments.create',
		'payments.refund',
		'payments.split.read',
		'checkout.create',
		'checkout.view',
		'checkout.update',
	);

	/**
	 * The Connect instance.
	 */
	private Connect $connect;

	/**
	 * The log ID.
	 */
	private ?string $log_id;

	/**
	 * The logger instance.
	 */
	private \WC_Logger_Interface $logger;

	/**
	 * Whether to use the sandbox environment.
	 */
	private bool $is_sandbox;

	/**
	 * Api constructor.
	 *
	 * @param string      $environment The environment to use.
	 * @param string|null $log_id The log ID.
	 */
	public function __construct( string $environment, ?string $log_id = null ) {
		$this->connect    = new Connect( $environment );
		$this->is_sandbox = $environment === 'sandbox';
		$this->log_id     = $log_id;
		$this->logger     = wc_get_logger();
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
	private function get_environment(): string {
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
						'scope=' . implode( '+', self::REQUIRED_SCOPES ),
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

		$this->log(
			'OAuth URL generated',
			'pagbank_oauth',
			array(
				'url'           => $url,
				'code_verifier' => $code_challenge['code_verifier'],
			)
		);

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

		$data = array(
			'grant_type'    => 'authorization_code',
			'code'          => $oauth_code,
			'redirect_uri'  => $callback_url,
			'code_verifier' => $code_verifier,
		);
		$body = $this->json_encode( $data );

		$headers = array(
			'Authorization' => 'Pub ' . $applications[ $application_id ]['access_token'],
			'Content-Type'  => 'application/json',
		);

		if ( true === Helpers::get_constant_value( 'PAGBANK_LOG_OAUTH_REQUEST' ) ) {
			$this->log_api_request( 'POST', $url, $data, $headers, 'pagbank_oauth' );
		}

		$response = $this->request(
			$url,
			array(
				'method'  => 'POST',
				'headers' => $headers,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( true === Helpers::get_constant_value( 'PAGBANK_LOG_OAUTH_REQUEST' ) ) {
				$this->log_api_request_error( $response, 'pagbank_oauth' );
			}

			return $response;
		}

		$response_code         = wp_remote_retrieve_response_code( $response );
		$response_body         = wp_remote_retrieve_body( $response );
		$decoded_response_body = json_decode( $response_body, true );

		if ( true === Helpers::get_constant_value( 'PAGBANK_LOG_OAUTH_REQUEST' ) ) {
			$this->log_api_response( $response_code, $response_body, 'pagbank_oauth' );
		}

		if ( ! $this->is_success_response_code( $response_code ) ) {
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

		$data = array(
			'grant_type'    => 'refresh_token',
			'refresh_token' => $refresh_token,
		);
		$body = $this->json_encode( $data );

		$headers = array(
			'Authorization' => 'Pub ' . $applications[ $application_id ]['access_token'],
			'Content-Type'  => 'application/json',
		);

		$this->log_api_request( 'POST', $url, $data, $headers, 'pagbank_oauth' );

		$response = $this->request(
			$url,
			array(
				'method'  => 'POST',
				'headers' => $headers,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_api_request_error( $response, 'pagbank_oauth' );

			return $response;
		}

		$response_code         = wp_remote_retrieve_response_code( $response );
		$response_body         = wp_remote_retrieve_body( $response );
		$decoded_response_body = json_decode( $response_body, true );

		$this->log_api_response( $response_code, $response_body, 'pagbank_oauth' );

		if ( ! $this->is_success_response_code( $response_code ) ) {
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
	public function generate_code_challenge(): array {
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
	public function create_order( array $data ) {
		$url = $this->get_api_url( 'orders' );

		$body = $this->json_encode( $data );

		$headers = array(
			'Authorization' => $this->connect->get_access_token(),
			'Content-Type'  => 'application/json',
		);

		$this->log_api_request( 'POST', $url, $data, $headers );

		$response = $this->request(
			$url,
			array(
				'method'  => 'POST',
				'headers' => $headers,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_api_request_error( $response );

			return $response;
		}

		$response_code         = wp_remote_retrieve_response_code( $response );
		$response_body         = wp_remote_retrieve_body( $response );
		$decoded_response_body = json_decode( $response_body, true );

		$this->log_api_response( $response_code, $response_body );

		if ( 201 !== $response_code ) {
			return new WP_Error( 'pagbank_order_creation_failed', 'PagBank order creation failed', $decoded_response_body );
		}

		return $decoded_response_body;
	}

	/**
	 * Create checkout.
	 *
	 * @param array $data The checkout data.
	 *
	 * @return array|WP_Error The checkout data.
	 */
	public function create_checkout( array $data ) {
		$url = $this->get_api_url( 'checkouts' );

		$body = $this->json_encode( $data );

		$headers = array(
			'Authorization' => 'Bearer ' . $this->connect->get_access_token(),
			'Content-Type'  => 'application/json',
		);

		$this->log_api_request( 'POST', $url, $data, $headers );

		$response = $this->request(
			$url,
			array(
				'method'  => 'POST',
				'headers' => $headers,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_api_request_error( $response );

			return $response;
		}

		$response_code         = wp_remote_retrieve_response_code( $response );
		$response_body         = wp_remote_retrieve_body( $response );
		$decoded_response_body = json_decode( $response_body, true );

		$this->log_api_response( $response_code, $response_body );

		if ( 201 !== $response_code ) {
			return new WP_Error( 'pagbank_checkout_creation_failed', 'PagBank checkout creation failed', $decoded_response_body );
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

		$data = array(
			'amount' => array(
				'value' => Helpers::format_money_cents( $amount ),
			),
		);
		$body = $this->json_encode( $data );

		$headers = array(
			'Authorization' => $this->connect->get_access_token(),
			'Content-Type'  => 'application/json',
		);

		$this->log_api_request( 'POST', $url, $data, $headers );

		$response = $this->request(
			$url,
			array(
				'method'  => 'POST',
				'headers' => $headers,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_api_request_error( $response );

			return $response;
		}

		$response_code         = wp_remote_retrieve_response_code( $response );
		$response_body         = wp_remote_retrieve_body( $response );
		$decoded_response_body = json_decode( $response_body, true );

		$this->log_api_response( $response_code, $response_body );

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

		$headers = array(
			'Authorization' => $this->connect->get_access_token(),
			'Content-Type'  => 'application/json',
		);

		$this->log_api_request( 'GET', $url, null, $headers );

		$args = array(
			'method'  => 'GET',
			'headers' => $headers,
		);

		$response = $this->request(
			$url,
			$args
		);

		if ( is_wp_error( $response ) ) {
			$this->log_api_request_error( $response );

			return $response;
		}

		$response_code         = wp_remote_retrieve_response_code( $response );
		$response_body         = wp_remote_retrieve_body( $response );
		$decoded_response_body = json_decode( $response_body, true );

		$this->log_api_response( $response_code, $response_body );

		if ( 200 !== $response_code ) {
			return new WP_Error( 'pagbank_charge_calculate_fees_failed', 'PagBank calculate fees failed', $decoded_response_body );
		}

		set_transient( 'pagbank_cached_request_' . $url_hash, wp_json_encode( $decoded_response_body ), 15 * MINUTE_IN_SECONDS );

		return $decoded_response_body;
	}

	/**
	 * Get the 3DS SDK API URL.
	 *
	 * @param string $path The path to append to the API URL.
	 *
	 * @return string The API URL.
	 */
	public function get_3ds_api_url( string $path = '' ): string {
		return "https://{$this->get_environment()}sdk.pagseguro.com/$path";
	}

	/**
	 * Create 3DS authentication session.
	 *
	 * The session is used for authentication operations with PagBank's internal 3DS SDK.
	 * The session is valid for 30 minutes after creation.
	 *
	 * @return array|WP_Error The session data containing 'session' and 'expires_at'.
	 */
	public function create_3ds_session() {
		$url = $this->get_3ds_api_url( 'checkout-sdk/sessions' );

		$headers = array(
			'Authorization' => $this->connect->get_access_token(),
			'Content-Type'  => 'application/json',
		);

		$this->log_api_request( 'POST', $url, null, $headers );

		$response = $this->request(
			$url,
			array(
				'method'  => 'POST',
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_api_request_error( $response );

			return $response;
		}

		$response_code         = wp_remote_retrieve_response_code( $response );
		$response_body         = wp_remote_retrieve_body( $response );
		$decoded_response_body = json_decode( $response_body, true );

		$this->log_api_response( $response_code, $response_body );

		if ( 201 !== $response_code ) {
			return new WP_Error( 'pagbank_3ds_session_failed', 'PagBank 3DS session creation failed', $decoded_response_body );
		}

		return $decoded_response_body;
	}

	/**
	 * Get account data using a provided access token.
	 *
	 * This method is used during OAuth callback when the token is not yet saved.
	 *
	 * @param string $account_id   The account ID.
	 * @param string $access_token The access token.
	 *
	 * @return array|WP_Error The account data.
	 */
	public function get_account_with_token( string $account_id, string $access_token ) {
		$url = $this->get_api_url( 'accounts/' . $account_id );

		$headers = array(
			'Authorization' => $access_token,
			'Content-Type'  => 'application/json',
		);

		$this->log_api_request( 'GET', $url, null, $headers, 'pagbank_oauth' );

		$response = $this->request(
			$url,
			array(
				'method'  => 'GET',
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_api_request_error( $response, 'pagbank_oauth' );

			return $response;
		}

		$response_code         = wp_remote_retrieve_response_code( $response );
		$response_body         = wp_remote_retrieve_body( $response );
		$decoded_response_body = json_decode( $response_body, true );

		$this->log_api_response( $response_code, $response_body, 'pagbank_oauth' );

		if ( 200 !== $response_code ) {
			return new WP_Error(
				'pagbank_get_account_failed',
				'PagBank get account failed',
				array(
					'http_code' => $response_code,
					'response'  => $decoded_response_body,
				)
			);
		}

		return $decoded_response_body;
	}

	/**
	 * Get public key.
	 *
	 * @param string|null $access_token The access token.
	 *
	 * @return array|WP_Error The public key data.
	 */
	public function get_public_key( ?string $access_token = null ) {
		$url = $this->get_api_url( 'public-keys' );

		$data = array(
			'type' => 'card',
		);
		$body = $this->json_encode( $data );

		$headers = array(
			'Authorization' => $access_token ?? $this->connect->get_access_token(),
			'Content-Type'  => 'application/json',
		);

		$this->log_api_request( 'POST', $url, $data, $headers, 'pagbank_oauth' );

		$response = $this->request(
			$url,
			array(
				'method'  => 'POST',
				'headers' => $headers,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_api_request_error( $response, 'pagbank_oauth' );

			return $response;
		}

		$response_code         = wp_remote_retrieve_response_code( $response );
		$response_body         = wp_remote_retrieve_body( $response );
		$decoded_response_body = json_decode( $response_body, true );

		$this->log_api_response( $response_code, $response_body, 'pagbank_oauth' );

		if ( 200 !== $response_code ) {
			return new WP_Error( 'pagbank_public_key_failed', 'PagBank get public key failed', $decoded_response_body );
		}

		return $decoded_response_body;
	}

	/**
	 * Log a message.
	 *
	 * @param string      $message The message to be logged.
	 * @param string|null $log_id  The log ID.
	 * @param array       $context Additional context data.
	 * @param string      $level   Log level (debug, info, notice, warning, error, critical, alert, emergency).
	 */
	private function log( string $message, ?string $log_id = null, array $context = array(), string $level = 'debug' ): void {
		$id = $log_id ?? $this->log_id;
		if ( $id ) {
			$log_context = array_merge( array( 'source' => $id ), $context );
			$this->logger->log( $level, $message, $log_context );
		}
	}

	/**
	 * Log request begin.
	 *
	 * @param string            $method  The request method.
	 * @param string            $url     The request URL.
	 * @param string|array|null $body    The request body.
	 * @param array             $headers The request headers.
	 * @param string|null       $log_id  The log ID.
	 */
	private function log_api_request( string $method, string $url, $body = null, array $headers = array(), ?string $log_id = null ): void {
		$context = array(
			'method' => $method,
			'url'    => $url,
		);

		if ( null !== $body ) {
			$context['body'] = $body;
			// Even with the `format_log_entry` filter, the UI breaks the `reference_id` escaped JSON, so we need to remove it from the context.
			unset( $context['body']['reference_id'] );
			unset( $context['body']['charges'][0]['reference_id'] );
		}

		if ( ! empty( $headers ) ) {
			$safe_log = false !== Helpers::get_constant_value( 'PAGBANK_SAFE_REQUEST_LOG' );

			if ( $safe_log && isset( $headers['Authorization'] ) ) {
				$headers['Authorization'] = preg_replace(
					'/^(Bearer|Pub)\s+.+$/i',
					'$1 *****',
					$headers['Authorization']
				);
			}

			$context['headers'] = $headers;
		}

		$this->log(
			'API Request (' . $method . ' ' . $url . ')',
			$log_id,
			$context
		);
	}

	/**
	 * Log request error.
	 *
	 * @param WP_Error    $error  The request error.
	 * @param string|null $log_id The log ID.
	 */
	private function log_api_request_error( WP_Error $error, ?string $log_id = null ): void {
		$this->log(
			'API Request Error (' . $error->get_error_code() . ')',
			$log_id,
			array(
				'error_code'    => $error->get_error_code(),
				'error_message' => $error->get_error_message(),
				'error_data'    => $error->get_error_data(),
			),
			'error'
		);
	}

	/**
	 * Log request ends.
	 *
	 * @param int         $response_code The response code.
	 * @param string      $response_body The response body.
	 * @param string|null $log_id        The log ID.
	 */
	private function log_api_response( int $response_code, string $response_body, ?string $log_id = null ): void {
		$is_success   = $response_code >= 200 && $response_code < 300;
		$level        = $is_success ? 'debug' : 'error';
		$decoded_body = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$decoded_body = $response_body;
		}

		// Even with the `format_log_entry` filter, the UI breaks the `reference_id` escaped JSON, so we need to remove it from the context.
		if ( is_array( $decoded_body ) ) {
			unset( $decoded_body['reference_id'] );
			unset( $decoded_body['charges'][0]['reference_id'] );
		}

		$this->log(
			'API Response (' . $response_code . ')',
			$log_id,
			array(
				'response_code' => $response_code,
				'response_body' => null !== $decoded_body && json_last_error() === JSON_ERROR_NONE ? $decoded_body : $response_body,
			),
			$level
		);
	}

	/**
	 * Encode data.
	 *
	 * @param array $data The data to be encoded.
	 *
	 * @return string The encoded data.
	 */
	private function json_encode( array $data ): string {
		return wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Request API.
	 *
	 * @param string $url  The request URL.
	 * @param array  $args The request args.
	 *
	 * @return array|WP_Error
	 */
	private function request( string $url, array $args = array() ) {
		$default_args = array(
			'timeout' => 15, // timeout in seconds.
		);
		$request_args = wp_parse_args( $args, $default_args );

		return wp_remote_request( $url, $request_args );
	}

	/**
	 * Check if a response code is success.
	 *
	 * @param int $response_code The response code.
	 * @return bool if it's a success response code.
	 */
	private function is_success_response_code( int $response_code ): bool {
		return 201 === $response_code || 200 === $response_code;
	}
}
