<?php
/**
 * Debit card payment gateway.
 *
 * @package PagBank_WooCommerce\Gateways
 */

namespace PagBank_WooCommerce\Gateways;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PagBank_WooCommerce\Presentation\Api;
use PagBank_WooCommerce\Presentation\Connect;
/**
 * Class DebitCardPaymentGateway.
 */
class DebitCardPaymentGateway extends CreditCardPaymentGateway {

	/**
	 * DebitCardPaymentGateway constructor.
	 */
	public function __construct() {
		$this->id                 = 'pagbank_debit_card';
		$this->icon               = plugins_url( 'dist/images/icons/card.png', PAGBANK_WOOCOMMERCE_FILE_PATH );
		$this->card_type          = 'DEBIT_CARD';
		$this->card_field_prefix  = 'pagbank_debit_card';
		$this->method_title       = __( 'PagBank Cartão de Débito', 'pagbank-for-woocommerce' );
		$this->method_description = __( 'Aceite pagamentos via cartão de débito através do PagBank. A autenticação 3DS é obrigatória.', 'pagbank-for-woocommerce' );
		$this->has_fields         = true;
		$this->supports           = array(
			'products',
			'refunds',
		);

		$this->init_form_fields();
		$this->init_settings();

		$this->title        = $this->get_option( 'title' );
		$this->environment  = $this->get_option( 'environment' );
		$this->logs_enabled = 'yes' === $this->get_option( 'logs_enabled' );
		$this->connect      = new Connect( $this->environment );
		$this->api          = new Api( $this->environment, $this->logs_enabled ? $this->id : null );

		$this->installments_enabled = false;
		$this->threeds_enabled      = true;

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_' . $this->id . '_3ds_session', array( $this, 'get_3ds_session' ) );
		add_filter( 'woocommerce_get_customer_payment_tokens', array( $this, 'filter_customer_tokens' ), 10, 3 );

		$this->is_available_validation();
	}

	/**
	 * Initialize form fields.
	 */
	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'         => array(
				'title'   => __( 'Habilitar/Desabilitar', 'pagbank-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Habilitar cartão de débito', 'pagbank-for-woocommerce' ),
				'default' => 'no',
			),
			'environment'     => array(
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
			'pagbank_connect' => array(
				'title'       => __( 'Conta PagBank', 'pagbank-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Conecte a sua conta PagBank para aceitar pagamentos.', 'pagbank-for-woocommerce' ),
			),
			'title'           => array(
				'title'       => __( 'Título', 'pagbank-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Isso irá controlar o título que o cliente verá durante o checkout.', 'pagbank-for-woocommerce' ),
				'default'     => __( 'Cartão de débito', 'pagbank-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'     => array(
				'title'       => __( 'Descrição', 'pagbank-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Isso irá controlar a descrição que o cliente verá durante o checkout.', 'pagbank-for-woocommerce' ),
				'default'     => 'Preencha os dados do seu cartão de débito no formulário abaixo:',
				'desc_tip'    => true,
			),
			'logs_enabled'    => array(
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
	 * Checkout payment fields.
	 */
	public function payment_fields(): void {
		if ( ! is_checkout() ) {
			echo '<p>' . esc_html( __( 'Você só pode adicionar um cartão de débito durante o checkout.', 'pagbank-for-woocommerce' ) ) . '</p>';
			return;
		}

		$this->form();
	}
}
