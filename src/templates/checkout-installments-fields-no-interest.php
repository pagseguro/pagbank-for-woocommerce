<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use function PagBank_WooCommerce\Presentation\format_money_cents;
use function PagBank_WooCommerce\Presentation\get_installments_plan_no_interest;

$cart_total = format_money_cents(WC()->cart->get_totals()['total']);
$installments_plan = get_installments_plan_no_interest($cart_total, $gateway->maximum_installments);
?>
<p
	class="form-row pagbank-credit-card-installments form-row-wide validate-required"
	id="<?php echo $gateway->id; ?>-installments_field"
	style="margin-top: 20px;"
>
	<label for="<?php echo $gateway->id; ?>-installments" class="">
		<?php esc_html_e('Parcelas', 'pagbank-for-woocommerce'); ?>&nbsp;<abbr class="required" title="required">*</abbr>
	</label>
	<span class="woocommerce-input-wrapper">
		<select
			name="<?php echo $gateway->id; ?>-installments"
			id="<?php echo $gateway->id; ?>-installments"
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
