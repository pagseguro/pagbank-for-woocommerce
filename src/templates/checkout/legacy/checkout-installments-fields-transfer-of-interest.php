<?php
/**
 * Template for displaying credit card installments fields with no interest.
 *
 * @var bool $is_checkout Whether this is being rendered on the checkout page
 * @var WC_Order $order The order object
 * @var int $total The total amount of the order
 * @var PagBank_WooCommerce\Core\Gateways\CreditCardPaymentGateway $gateway The gateway object
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p
	class="form-row pagbank-credit-card-installments form-row-wide validate-required"
	id="<?php echo esc_attr($gateway->id); ?>-installments_field"
	style="margin-top: 20px;"
>
	<label for="<?php echo esc_attr($gateway->id); ?>-installments" class="">
		<?php esc_html_e('Parcelas', 'pagbank-for-woocommerce'); ?>&nbsp;<abbr class="required" title="required">*</abbr>
	</label>
	<span class="woocommerce-input-wrapper">
		<select
			name="<?php echo esc_attr($gateway->id); ?>-installments"
			id="<?php echo esc_attr($gateway->id); ?>-installments"
			class="select"
			data-amount="<?php echo esc_attr($total / 100); ?>"
			data-nonce="<?php echo esc_attr(wp_create_nonce('pagbank_get_installments'));  ?>"
			data-url="<?php echo esc_attr($gateway->get_api_installments_url()); ?>"
			disabled="disabled"
		></select>
	</span>
</p>
