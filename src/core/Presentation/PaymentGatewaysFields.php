<?php
/**
 * Add support for custom fields to WooCommerce Settings API:
 * - currency
 * - pagbank_connect
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

/**
 * Class PaymentGatewaysFields.
 */
class PaymentGatewaysFields {

	/**
	 * Instance.
	 *
	 * @var PaymentGatewaysFields
	 */
	private static $instance = null;

	/**
	 * Init.
	 */
	public function __construct() {
		add_filter( 'woocommerce_generate_currency_html', array( $this, 'generate_currency_html' ), 10, 4 );
		add_filter( 'woocommerce_generate_pagbank_connect_html', array( $this, 'generate_pagbank_connect_html' ), 10, 4 );
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
	 * Generate currency field HTML.
	 *
	 * @param string $field_html The markup of the field being generated (initiated as an empty string).
	 * @param string $key The key of the field.
	 * @param array  $data The attributes of the field as an associative array.
	 * @param object $wc_settings The current WC_Settings_API object.
	 *
	 * @return string
	 */
	public function generate_currency_html( $field_html, $key, $data, $wc_settings ) {
		if ( ! in_array( $wc_settings->id, PaymentGateways::$gateway_ids, true ) ) {
			return $field_html;
		}

		$data['type']              = 'text';
		$data['custom_attributes'] = wp_parse_args(
			array(
				'data-format-currency' => '',
			),
			$data['custom_attributes']
		);

		return $wc_settings->generate_text_html( $key, $data );
	}

	/**
	 * Generate PagBank field HTML.
	 *
	 * @param string $field_html The markup of the field being generated (initiated as an empty string).
	 * @param string $key The key of the field.
	 * @param array  $data The attributes of the field as an associative array.
	 * @param object $wc_settings The current WC_Settings_API object.
	 *
	 * @return string
	 */
	public function generate_pagbank_connect_html( $field_html, $key, $data, $wc_settings ) {
		if ( ! in_array( $wc_settings->id, PaymentGateways::$gateway_ids, true ) ) {
			return $field_html;
		}

		$field_key = $wc_settings->get_field_key( $key );
		$defaults  = array(
			'title'              => '',
			'disabled'           => false,
			'class'              => '',
			'css'                => '',
			'placeholder'        => '',
			'type'               => 'pagbank_connect',
			'desc_tip'           => false,
			'description'        => '',
			'environment_select' => 'environment',
			'custom_attributes'  => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		$applications = Connect::get_connect_applications();

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?>
				<?php
					// phpcs:ignore Standard.Category.SniffName.ErrorCode
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $wc_settings->get_tooltip_html( $data );
				?>
				</label>
			</th>
			<td class="forminp">
				<fieldset>
					<?php foreach ( array( 'production', 'sandbox' ) as $environment ) : ?>
						<?php
						$modal_applications = array_filter(
							$applications,
							function( $item ) use ( $environment ) {
								return $item['environment'] === $environment;
							}
						);
						?>
						<div id="<?php echo esc_attr( $field_key ); ?>-modal-<?php echo esc_attr( $environment ); ?>" class="pagbank-connect-modal hidden">
							<div class="pagbank-connect-modal-wrapper">
								<div class="pagbank-connect-modal-content">
									<button class="pagbank-connect-modal-close-button" type="button" data-modal-close-button>&#10005;</button>
									<h2>Selecione a taxa de juros</h2>
									<p>Para conectar o método de pagamento, é necessário que você possua uma conta PagBank. Caso ainda não tenha a conta, <a href="https://cadastro.pagseguro.uol.com.br/" target="_blank">clique aqui</a> para criar uma nova.</p>

									<div class="fees-table">
										<?php foreach ( $modal_applications as $application ) : ?>
											<a
												href="#"
												data-connect-application-id="<?php echo esc_attr( $application['id'] ); ?>"
												data-connect-application-environment="<?php echo esc_attr( $application['environment'] ); ?>"
												data-connect-nonce="<?php echo esc_attr( wp_create_nonce( 'pagbank_woocommerce_oauth' ) ); ?>"
											>
												<h3><?php echo esc_html( $application['title'] ); ?></h3>
												<ul>
													<?php if ( null !== $application['fee'] ) : ?>
														<li>Taxa de juros: <?php echo esc_html( $application['fee'] ); ?>%</li>
													<?php endif; ?>
												</ul>
											</a>
										<?php endforeach; ?>
									</div>
								</div>
							</div>
						</div>
					<?php endforeach; ?>

					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<button
						name="<?php echo esc_attr( $field_key ); ?>"
						class="button button-primary"
						type="button"
						data-pagbank-connect-nonce="<?php echo esc_attr( wp_create_nonce( 'pagbank_woocommerce_oauth' ) ); ?>"
						data-pagbank-connect-environment-select="<?php echo esc_attr( $wc_settings->get_field_key( $data['environment_select'] ) ); ?>"
						data-pagbank-connected-text="<?php esc_attr_e( 'Conectar a outra conta do PagBank', 'pagbank-woocommerce' ); ?>"
						data-pagbank-not-connected-text="<?php esc_attr_e( 'Conectar a uma conta do PagBank', 'pagbank-woocommerce' ); ?>"
						data-pagbank-connect-modal-environment-id="<?php echo esc_attr( $field_key ); ?>-modal-{{environment}}"
						data-pagbank-loading-text="<?php esc_attr_e( 'Carregando...', 'pagbank-woocommerce' ); ?>"
						disabled
					>
						<?php esc_html_e( 'Carregando...', 'pagbank-woocommerce' ); ?>
					</button>
					<?php
						// phpcs:ignore Standard.Category.SniffName.ErrorCode
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo $wc_settings->get_description_html( $data );
					?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

}
