<?php
/**
 * Pix payment instructions template.
 * Renders a container that React will hydrate with the payment instructions UI.
 *
 * @package PagBank_WooCommerce
 *
 * @var int    $order_id            Order ID.
 * @var string $order_key           Order key.
 * @var bool   $is_paid             Whether the order is paid.
 * @var string $pix_expiration_date Pix expiration date.
 * @var string $pix_text            Pix copy and paste code.
 * @var string $pix_qr_code         Pix QR code image URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div
	id="pagbank-pix-instructions"
	data-order-id="<?php echo esc_attr( $order_id ); ?>"
	data-order-key="<?php echo esc_attr( $order_key ); ?>"
	data-is-paid="<?php echo esc_attr( $is_paid ? 'true' : 'false' ); ?>"
	data-rest-url="<?php echo esc_url( rest_url() ); ?>"
	data-pix-qr-code="<?php echo esc_attr( $pix_qr_code ); ?>"
	data-pix-text="<?php echo esc_attr( $pix_text ); ?>"
	data-pix-expiration-date="<?php echo esc_attr( sanitize_text_field( $pix_expiration_date ) ); ?>"
></div>
