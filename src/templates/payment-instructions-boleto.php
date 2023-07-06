<div class="pagbank-boleto">
	<h3><?php _e('Opção 1: faça download do boleto', 'pagbank-for-woocommerce') ;?></h3>
	<ol>
		<li><?php _e('Abra o aplicativo do seu banco e selecione a opção "Pagar boleto"', 'pagbank-for-woocommerce'); ?></li>
		<li><?php _e('Escaneie o código de barras', 'pagbank-for-woocommerce') ;?></li>
	</ol>
	<div class="center">
		<a class="button" target="_blank" href="<?php echo esc_url_raw($boleto_link_pdf); ?>"><?php _e('Download boleto', 'pagbank-for-woocommerce'); ?></a>
	</div>
	<hr />
	<h3><?php _e('Opção 2: copie o código de barras', 'pagbank-for-woocommerce'); ?></h3>
	<ol>
		<li><?php _e('Abra o aplicativo do seu banco e selecione a opção "Pagar boleto"', 'pagbank-for-woocommerce'); ?></li>
		<li><?php _e('Cole o código de barras abaixo', 'pagbank-for-woocommerce'); ?></li>
	</ol>
	<div class="boleto-barcode">
		<input type="text" readonly value="<?php echo esc_attr( $boleto_barcode ); ?>" data-select-on-click />
		<button type="button" class="button" data-copy-clipboard="<?php echo esc_attr( $boleto_barcode ); ?>"><?php _e('Copiar', 'pagbank-for-woocommerce'); ?></button>
	</div>
	<hr />
	<h3><?php _e('Quando o pagamento for concluído', 'pagbank-for-woocommerce'); ?></h3>
	<p><?php _e('Quando finalizar a transação, você pode retornar à tela inicial.', 'pagbank-for-woocommerce'); ?></p>
</div>
