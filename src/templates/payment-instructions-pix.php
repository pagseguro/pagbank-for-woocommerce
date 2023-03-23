<div class="pagbank-pix">
	<h3>Opção 1: Escaneie o QR code do Pix</h3>
	<ol>
		<li>Abra o aplicativo do seu banco e selecione a opção "Pagar com Pix"</li>
		<li>Escaneie o QR code abaixo e confirme o pagamento</li>
	</ol>
	<img src="<?php echo esc_attr( $pix_qr_code ); ?>" alt="QR Code Pix" />
	<hr />
	<h3>Opção 2: Use o código do Pix</h3>
	<p>Copie o código abaixo. Em seguida, você precisará:</p>
	<ol>
		<li>Abrir o aplicativo ou site do seu banco e selecionar a opção "Pagar com Pix"</li>
		<li>Colar o código e concluir o pagamento</li>
	</ol>
	<div class="pix-copy-and-paste">
		<input type="text" readonly value="<?php echo esc_attr( $pix_text ); ?>" data-select-on-click />
		<button type="button" class="button" data-copy-clipboard="<?php echo esc_attr( $pix_text ); ?>"><?php _e('Copy code', 'pagbank-woocommerce'); ?></button>
	</div>
	<hr />
	<h3>Quando o pagamento for concluído</h3>
	<p>Quando finalizar a transação, você pode retornar à tela inicial.</p>
</div>
