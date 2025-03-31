<?php
defined('ABSPATH') || exit;

$env = get_option('woocommerce_pagbank_pix_environment', 'sandbox');
$application_id = $env === 'production'
	? '31241905-5426-4f88-a140-4416a2cab404'
	: 'fa1553af-5f0c-4ff2-92c3-a0dd8984b6a1';

$nonce = wp_create_nonce('pagbank_woocommerce_oauth');
?>
<div class="notice notice-info">
	<h2>🔗 Conectar conta PagBank (botão alternativo fixo)</h2>
	<p>Se o botão oficial não aparecer, use esse:</p>
	<button
		type="button"
		class="button button-primary"
		style="margin-top: 10px;"
		data-connect-application-id="<?php echo esc_attr($application_id); ?>"
		data-connect-application-environment="<?php echo esc_attr($env); ?>"
		data-connect-nonce="<?php echo esc_attr($nonce); ?>"
	>
		Conectar com PagBank (<?php echo esc_html($env === 'production' ? 'Produção' : 'Sandbox'); ?>)
	</button>
</div>
