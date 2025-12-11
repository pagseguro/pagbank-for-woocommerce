<?php
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}
?>
<h2><?php esc_html_e( 'Instruções de pagamento - Pagar com PagBank', 'pagbank-for-woocommerce' ); ?></h2>
<div class="pagbank-pay-with-pagbank"
	data-pagbank-pay-with-pagbank-order-status
	data-order-id="<?php echo esc_attr( $order_id ); ?>"
	data-order-key="<?php echo esc_attr( $order_key ); ?>"
	data-is-paid="<?php echo esc_attr( $is_paid ? 'yes' : 'no' ); ?>"
	data-rest-url="<?php echo esc_url( rest_url() ); ?>">
	<?php if ( $is_paid ) : ?>
		<h3><?php esc_html_e( 'Pagamento confirmado!', 'pagbank-for-woocommerce' ); ?></h3>
		<p><?php esc_html_e( 'O pagamento do seu pedido foi confirmado com sucesso.', 'pagbank-for-woocommerce' ); ?></p>
	<?php else : ?>
		<h3><?php esc_html_e( 'Escaneie o QR Code com o app PagBank', 'pagbank-for-woocommerce' ); ?></h3>
		<ol>
			<li><?php esc_html_e( 'Abra o aplicativo PagBank e selecione a opção "Pix/QR Code"', 'pagbank-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Selecione "Pagar com QR Code" e escaneie o código abaixo', 'pagbank-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Escolha se deseja pagar com saldo, crédito à vista ou parcelado', 'pagbank-for-woocommerce' ); ?></li>
		</ol>
		<img src="<?php echo esc_attr( $qr_code_image ); ?>" alt="QR Code PagBank" />
		<hr />
		<h3><?php esc_html_e( 'Ou copie o código do QR Code', 'pagbank-for-woocommerce' ); ?></h3>
		<p><?php esc_html_e( 'Copie o código abaixo e cole no aplicativo PagBank:', 'pagbank-for-woocommerce' ); ?></p>
		<div class="pagbank-copy-and-paste">
			<input type="text" readonly value="<?php echo esc_attr( $qr_code_text ); ?>" data-select-on-click />
			<button type="button" class="button" data-copy-clipboard="<?php echo esc_attr( $qr_code_text ); ?>"><?php esc_html_e( 'Copiar', 'pagbank-for-woocommerce' ); ?></button>
		</div>
		<?php if ( $expiration_date ) : ?>
		<hr />
		<p class="pagbank-expiration">
			<?php
			printf(
				/* translators: %s: expiration date */
				esc_html__( 'Válido até: %s', 'pagbank-for-woocommerce' ),
				esc_html( wp_date( 'd/m/Y H:i', strtotime( $expiration_date ) ) )
			);
			?>
		</p>
		<?php endif; ?>
		<hr />
		<h3><?php esc_html_e( 'Quando o pagamento for concluído', 'pagbank-for-woocommerce' ); ?></h3>
		<p><?php esc_html_e( 'Quando finalizar a transação, você pode retornar à tela inicial.', 'pagbank-for-woocommerce' ); ?></p>
	<?php endif; ?>
</div>
