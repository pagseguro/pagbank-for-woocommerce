/**
 * Types for payment instructions components.
 *
 * @package PagBank_WooCommerce
 */

export interface BaseInstructionsProps {
	orderId: number;
	orderKey: string;
	isPaid: boolean;
	restUrl: string;
}

export interface BoletoInstructionsProps extends BaseInstructionsProps {
	boletoBarcode: string;
	boletoLinkPdf: string;
	boletoExpirationDate: string;
}

export interface PixInstructionsProps extends BaseInstructionsProps {
	pixQrCode: string;
	pixText: string;
	pixExpirationDate: string;
}

export interface PayWithPagBankInstructionsProps extends BaseInstructionsProps {
	qrCodeImage: string;
	qrCodeText: string;
	expirationDate: string;
	deeplinkUrl?: string;
}

export interface OrderStatusResponse {
	order_id: number;
	status: string;
	payment_method: string;
	is_paid: boolean;
	pix_expiration_date?: string;
	is_expired?: boolean;
	qrcode_expiration_date?: string;
}
