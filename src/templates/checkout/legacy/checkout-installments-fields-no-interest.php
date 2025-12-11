<?php
/**
 * Template for displaying credit card installments fields with no interest.
 *
 * @var bool $is_checkout Whether this is being rendered on the checkout page
 * @var WC_Order $order The order object
 * @var int $total The total amount of the order
 * @var PagBank_WooCommerce\Core\Gateways\CreditCardPaymentGateway $gateway The gateway object
 */

use PagBank_WooCommerce\Presentation\ApiHelpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$installments_plan = ApiHelpers::get_installments_plan_no_interest($total, $gateway->maximum_installments);
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
		>
			<?php foreach($installments_plan as $plan): ?>
				<option value="<?php echo esc_attr($plan['installments']); ?>">
					<?php echo esc_html($plan['title']); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</span>
</p>
