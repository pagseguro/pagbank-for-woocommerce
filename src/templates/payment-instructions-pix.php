<div class="pagbank-pix">
	<h3><?php _e('Opção 1: Escaneie o QR code do Pix', 'pagbank-for-woocommerce'); ?></h3>
	<ol>
		<li><?php _e('Abra o aplicativo do seu banco e selecione a opção "Pagar com Pix"', 'pagbank-for-woocommerce'); ?></li>
		<li><?php _e('Escaneie o QR code abaixo e confirme o pagamento', 'pagbank-for-woocommerce'); ?></li>
	</ol>
	<img src="<?php echo esc_attr( $pix_qr_code ); ?>" alt="QR Code Pix" />
	<hr />
	<h3><?php _e('Opção 2: Use o código do Pix', 'pagbank-for-woocommerce'); ?></h3>
	<p><?php _e('Copie o código abaixo. Em seguida, você precisará:', 'pagbank-for-woocommerce'); ?></p>
	<ol>
		<li><?php _e('Abrir o aplicativo ou site do seu banco e selecionar a opção "Pagar com Pix"', 'pagbank-for-woocommerce'); ?></li>
		<li><?php _e('Colar o código e concluir o pagamento', 'pagbank-for-woocommerce'); ?></li>
	</ol>
	<div class="pix-copy-and-paste">
		<input type="text" readonly value="<?php echo esc_attr( $pix_text ); ?>" data-select-on-click />
		<button type="button" class="button" data-copy-clipboard="<?php echo esc_attr( $pix_text ); ?>"><?php _e('Copiar', 'pagbank-for-woocommerce'); ?></button>
	</div>
	<hr />
	<h3><?php _e('Quando o pagamento for concluído', 'pagbank-for-woocommerce'); ?></h3>
	<p><?php _e('Quando finalizar a transação, você pode retornar à tela inicial.', 'pagbank-for-woocommerce'); ?></p>
</div>
