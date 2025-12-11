<?php
/**
 * Boleto payment instructions template.
 * Renders a container that React will hydrate with the payment instructions UI.
 *
 * @package PagBank_WooCommerce
 *
 * @var int    $order_id               Order ID.
 * @var string $order_key              Order key.
 * @var bool   $is_paid                Whether the order is paid.
 * @var string $boleto_expiration_date Boleto expiration date.
 * @var string $boleto_barcode         Boleto barcode.
 * @var string $boleto_link_pdf        Boleto PDF link.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div
	id="pagbank-boleto-instructions"
	data-order-id="<?php echo esc_attr( $order_id ); ?>"
	data-order-key="<?php echo esc_attr( $order_key ); ?>"
	data-is-paid="<?php echo esc_attr( $is_paid ? 'true' : 'false' ); ?>"
	data-rest-url="<?php echo esc_url( rest_url() ); ?>"
	data-boleto-barcode="<?php echo esc_attr( sanitize_text_field( $boleto_barcode ) ); ?>"
	data-boleto-link-pdf="<?php echo esc_url( sanitize_text_field( $boleto_link_pdf ) ); ?>"
	data-boleto-expiration-date="<?php echo esc_attr( sanitize_text_field( $boleto_expiration_date ) ); ?>"
></div>
