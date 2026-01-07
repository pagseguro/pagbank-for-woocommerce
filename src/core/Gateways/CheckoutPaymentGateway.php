<?php
/**
 * Checkout PagBank payment gateway.
 *
 * This gateway redirects the user to PagBank's checkout page.
 *
 * @package PagBank_WooCommerce\Gateways
 */

namespace PagBank_WooCommerce\Gateways;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
use PagBank_WooCommerce\Gateways\Traits\ReactSettingsTrait;
use PagBank_WooCommerce\Presentation\Api;
use PagBank_WooCommerce\Presentation\ApiHelpers;
use PagBank_WooCommerce\Presentation\Connect;
use PagBank_WooCommerce\Presentation\PaymentGatewaysFields;
use WC_Order;
use WC_Payment_Gateway;
use WP_Error;

/**
 * Class CheckoutPaymentGateway.
 */
class CheckoutPaymentGateway extends WC_Payment_Gateway {

	use ReactSettingsTrait;

	/**
	 * Api instance.
	 *
	 * @var Api
	 */
	private $api;

	/**
	 * Connect instance.
	 *
	 * @var Connect
	 */
	public $connect;

	/**
	 * Environment.
	 *
	 * @var string
	 */
	public $environment;

	/**
	 * Logs enabled.
	 *
	 * @var string yes|no.
	 */
	private $logs_enabled;

	/**
	 * Expiration time in minutes.
	 *
	 * @var int
	 */
	private $expiration_minutes;

	/**
	 * CheckoutPaymentGateway constructor.
	 */
	public function __construct() {
		$this->id                 = 'pagbank_checkout';
		$this->icon               = plugins_url( 'dist/images/icons/pagbank.png', PAGBANK_WOOCOMMERCE_FILE_PATH );
		$this->method_title       = __( 'Checkout PagBank', 'pagbank-for-woocommerce' );
		$this->method_description = __( 'Redirecione o cliente para a página de checkout do PagBank para concluir o pagamento. Aceita cartão de crédito, Pix, boleto e saldo PagBank.', 'pagbank-for-woocommerce' );
		$this->description        = $this->get_option( 'description' );
		$this->has_fields         = ! empty( $this->description );
		$this->supports           = array(
			'products',
			'refunds',
		);

		$this->init_form_fields();
		$this->init_settings();

		$this->title              = $this->get_option( 'title' );
		$this->environment        = $this->get_option( 'environment' );
		$this->logs_enabled       = $this->get_option( 'logs_enabled' );
		$this->expiration_minutes = (int) $this->get_option( 'expiration_minutes', 120 );
		$this->connect            = new Connect( $this->environment );
		$this->api                = new Api( $this->environment, $this->logs_enabled === 'yes' ? $this->id : null );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		$this->is_available_validation();
	}

	/**
	 * Initialize form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'            => array(
				'title'   => __( 'Habilitar/Desabilitar', 'pagbank-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Habilitar Checkout PagBank', 'pagbank-for-woocommerce' ),
				'default' => 'no',
			),
			'environment'        => array(
				'title'       => __( 'Ambiente', 'pagbank-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Isso irá definir o ambiente de testes ou produção.', 'pagbank-for-woocommerce' ),
				'default'     => 'sandbox',
				'options'     => array(
					'sandbox'    => __( 'Ambiente de testes', 'pagbank-for-woocommerce' ),
					'production' => __( 'Produção', 'pagbank-for-woocommerce' ),
				),
				'desc_tip'    => true,
			),
			'pagbank_connect'    => array(
				'title'       => __( 'Conta PagBank', 'pagbank-for-woocommerce' ),
				'type'        => 'pagbank_connect',
				'description' => __( 'Conecte a sua conta PagBank para aceitar pagamentos.', 'pagbank-for-woocommerce' ),
			),
			'title'              => array(
				'title'       => __( 'Título', 'pagbank-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Isso irá controlar o título que o cliente verá durante o checkout.', 'pagbank-for-woocommerce' ),
				'default'     => __( 'Checkout PagBank', 'pagbank-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'        => array(
				'title'       => __( 'Descrição', 'pagbank-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Isso irá controlar a descrição que o cliente verá durante o checkout.', 'pagbank-for-woocommerce' ),
				'default'     => __( 'Você será redirecionado para o PagBank para finalizar o pagamento.', 'pagbank-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'expiration_minutes' => array(
				'title'             => __( 'Tempo de expiração (minutos)', 'pagbank-for-woocommerce' ),
				'type'              => 'number',
				'description'       => __( 'Tempo em minutos para o checkout expirar. Padrão: 120 (2 horas).', 'pagbank-for-woocommerce' ),
				'default'           => 120,
				'desc_tip'          => true,
				'custom_attributes' => array(
					'min'  => 1,
					'max'  => 10080, // 7 days.
					'step' => 1,
				),
			),
			'logs_enabled'       => array(
				'title'       => __( 'Logs para depuração', 'pagbank-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Ativar logs', 'pagbank-for-woocommerce' ),
				'description' => __( 'Isso irá ativar os logs para depuração para auxiliar em caso de suporte.', 'pagbank-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Get payment method title.
	 *
	 * @return string The title.
	 */
	public function get_title() {
		if ( is_admin() ) {
			$screen = get_current_screen();

			if ( $screen && $screen->id === 'woocommerce_page_wc-orders' ) {
				return $this->method_title;
			}
		}

		return apply_filters( 'woocommerce_gateway_title', $this->title, $this->id );
	}

	/**
	 * Process order payment.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array
	 *
	 * @throws Exception When an error occurs.
	 */
	public function process_payment( $order_id ) {
		try {
			$order      = wc_get_order( $order_id );
			$return_url = $this->get_return_url( $order );

			$data     = ApiHelpers::get_checkout_api_data( $this, $order, $this->expiration_minutes, $return_url );
			$response = $this->api->create_checkout( $data );

			if ( is_wp_error( $response ) ) {
				wc_add_notice( __( 'Houve um erro ao processar o pagamento. Tente novamente.', 'pagbank-for-woocommerce' ), 'error' );

				return array(
					'result'  => 'failure',
					'message' => __( 'Houve um erro ao processar o pagamento. Tente novamente.', 'pagbank-for-woocommerce' ),
				);
			}

			// Save order meta data.
			$this->save_order_meta_data( $order, $response, $data );

			// Update status to pending payment.
			$order->update_status( 'pending', __( 'Aguardando pagamento via Checkout PagBank.', 'pagbank-for-woocommerce' ) );

			// Get checkout redirect URL.
			$checkout_url = $this->get_checkout_redirect_url( $response );

			if ( ! $checkout_url ) {
				wc_add_notice( __( 'Não foi possível obter a URL de checkout. Tente novamente.', 'pagbank-for-woocommerce' ), 'error' );

				return array(
					'result'  => 'failure',
					'message' => __( 'Não foi possível obter a URL de checkout. Tente novamente.', 'pagbank-for-woocommerce' ),
				);
			}

			// Reduce stock levels.
			wc_reduce_stock_levels( $order_id );

			// Remove cart.
			WC()->cart->empty_cart();

			return array(
				'result'   => 'success',
				'redirect' => $checkout_url,
			);
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );

			return array(
				'result'  => 'failure',
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Get checkout redirect URL from response.
	 *
	 * @param array $response API response.
	 *
	 * @return string|null
	 */
	private function get_checkout_redirect_url( array $response ): ?string {
		if ( empty( $response['links'] ) ) {
			return null;
		}

		foreach ( $response['links'] as $link ) {
			if ( $link['rel'] === 'PAY' ) {
				return $link['href'];
			}
		}

		return null;
	}

	/**
	 * Process a refund.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $amount   Refund amount.
	 * @param string $reason   Refund reason.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order                       = wc_get_order( $order_id );
		$should_process_order_refund = apply_filters( 'pagbank_should_process_order_refund', true, $order );

		if ( is_wp_error( $should_process_order_refund ) ) {
			return $should_process_order_refund;
		}

		if ( $should_process_order_refund === true ) {
			return ApiHelpers::process_order_refund( $this->api, $order, $amount, $reason );
		}

		return new WP_Error( 'error', __( 'Houve um erro desconhecido ao tentar realizar o reembolso.', 'pagbank-for-woocommerce' ) );
	}

	/**
	 * Save order meta data.
	 *
	 * @param WC_Order $order    Order object.
	 * @param array    $response Response data.
	 * @param array    $request  Request data.
	 *
	 * @return void
	 */
	private function save_order_meta_data( WC_Order $order, array $response, array $request ) {
		$order->update_meta_data( '_pagbank_checkout_id', $response['id'] );
		$order->update_meta_data( '_pagbank_password', $request['metadata']['password'] );
		$order->update_meta_data( '_pagbank_environment', $this->environment );

		if ( ! empty( $response['expiration_date'] ) ) {
			$order->update_meta_data( '_pagbank_checkout_expiration_date', $response['expiration_date'] );
		}

		$order->save_meta_data();
	}

	/**
	 * Check if gateway needs setup.
	 *
	 * @return bool
	 */
	public function needs_setup() {
		$is_connected = (bool) $this->connect->get_data();

		return ! $is_connected;
	}

	/**
	 * Check if gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available() {
		$is_available = ( 'yes' === $this->enabled );

		if ( ! $is_available ) {
			return false;
		}

		if ( WC()->cart && 0 < $this->get_order_total() && 0 < $this->max_amount && $this->max_amount < $this->get_order_total() ) {
			return false;
		}

		$is_connected          = (bool) $this->connect->get_data();
		$is_brazilian_currency = get_woocommerce_currency() === 'BRL';

		if ( ! $is_connected || ! $is_brazilian_currency ) {
			return false;
		}

		return true;
	}

	/**
	 * Add errors in case of some validation error that will appear during the checkout.
	 *
	 * @return void
	 */
	public function is_available_validation() {
		$is_enabled            = ( 'yes' === $this->enabled );
		$is_connected          = (bool) $this->connect->get_data();
		$is_brazilian_currency = get_woocommerce_currency() === 'BRL';

		$errors = array();

		if ( ! $is_enabled ) {
			$errors[] = __( '- O método de pagamento está desabilitado.', 'pagbank-for-woocommerce' );
		}

		if ( ! $is_connected ) {
			$errors[] = __( '- A sua conta PagBank não está conectada.', 'pagbank-for-woocommerce' );
		}

		if ( ! $is_brazilian_currency ) {
			$errors[] = __( '- A moeda da loja não é BRL.', 'pagbank-for-woocommerce' );
		}

		if ( $errors ) {
			array_unshift( $errors, __( 'Alguns errors podem estar impedindo o método de pagamento de ser exibido durante o checkout:', 'pagbank-for-woocommerce' ) );

			$this->add_error( implode( '<br />', $errors ) );
		}
	}

	/**
	 * Generate HTML settings HTML with errors.
	 *
	 * @param array $form_fields The form fields to display.
	 * @param bool  $echo_output Should echo or return.
	 *
	 * @return string If $echo = false, return the HTML content.
	 */
	public function generate_settings_html( $form_fields = array(), $echo_output = true ) {
		ob_start();
		$this->display_errors();
		$html = ob_get_clean();

		if ( $echo_output ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XSS ok.
			echo $html . parent::generate_settings_html( $form_fields, $echo_output );
		} else {
			return $html . parent::generate_settings_html( $form_fields, $echo_output );
		}
	}

	/**
	 * Generate HTML for PagBank Connect field.
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Field value.
	 *
	 * @return string HTML output.
	 */
	public function generate_pagbank_connect_html( $key, $value ) {
		$pagbank_gateways_fields = PaymentGatewaysFields::get_instance();

		return $pagbank_gateways_fields->generate_pagbank_connect_html( '', $key, $value, $this );
	}
}
