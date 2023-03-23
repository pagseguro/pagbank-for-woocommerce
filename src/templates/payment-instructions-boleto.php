<div class="pagbank-boleto">
	<h3>Opção 1: faça download do boleto</h3>
	<ol>
		<li>Abra o aplicativo do seu banco e selecione a opção "Pagar boleto"</li>
		<li>Escaneie o código de barras</li>
	</ol>
	<div class="center">
		<a class="button" target="_blank" href="<?php echo esc_url_raw($boleto_link_pdf); ?>"><?php _e('Baixar boleto', 'pagbank-woocommerce'); ?></a>
	</div>
	<hr />
	<h3>Opção 2: copie o código de barras</h3>
	<ol>
		<li>Abra o aplicativo do seu banco e selecione a opção "Pagar boleto"</li>
		<li>Cole o código de barras abaixo</li>
	</ol>
	<div class="boleto-barcode">
		<input type="text" readonly value="<?php echo esc_attr( $boleto_barcode ); ?>" data-select-on-click />
		<button type="button" class="button" data-copy-clipboard="<?php echo esc_attr( $boleto_barcode ); ?>"><?php _e('Copy barcode', 'pagbank-woocommerce'); ?></button>
	</div>
	<hr />
	<h3>Quando o pagamento for concluído</h3>
	<p>Quando finalizar a transação, você pode retornar à tela inicial.</p>
</div>
