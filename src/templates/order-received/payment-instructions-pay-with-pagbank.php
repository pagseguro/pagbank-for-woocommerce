<?php
/**
 * Pay with PagBank payment instructions template.
 * Renders a container that React will hydrate with the payment instructions UI.
 *
 * @package PagBank_WooCommerce
 *
 * @var int    $order_id        Order ID.
 * @var string $order_key       Order key.
 * @var bool   $is_paid         Whether the order is paid.
 * @var bool   $is_mobile       Whether the request is from mobile.
 * @var string $deeplink_url    Deeplink URL for mobile app.
 * @var string $qr_code_image   QR code image URL.
 * @var string $qr_code_text    QR code text for copy.
 * @var string $expiration_date QR code expiration date.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div
	id="pagbank-pay-with-pagbank-instructions"
	data-order-id="<?php echo esc_attr( $order_id ); ?>"
	data-order-key="<?php echo esc_attr( $order_key ); ?>"
	data-is-paid="<?php echo esc_attr( $is_paid ? 'true' : 'false' ); ?>"
	data-rest-url="<?php echo esc_url( rest_url() ); ?>"
	data-deeplink-url="<?php echo esc_url( $deeplink_url ); ?>"
	data-qr-code-image="<?php echo esc_attr( $qr_code_image ); ?>"
	data-qr-code-text="<?php echo esc_attr( $qr_code_text ); ?>"
	data-expiration-date="<?php echo esc_attr( sanitize_text_field( $expiration_date ) ); ?>"
></div>
