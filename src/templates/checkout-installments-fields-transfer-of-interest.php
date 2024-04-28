<?php
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
			data-amount="<?php echo esc_attr(WC()->cart->get_totals()['total']); ?>"
			data-nonce="<?php echo esc_attr(wp_create_nonce('pagbank_get_installments'));  ?>"
			data-url="<?php echo esc_attr($gateway->get_api_installments_url()); ?>"
			disabled="disabled"
		></select>
	</span>
</p>
