/**
 * Type definitions for PagBank Debit Card Blocks.
 *
 * @package PagBank_WooCommerce
 */

export interface PaymentMethodSettings {
	title: string;
	description: string;
	supports: string[];
	card_public_key: string | null;
	// Installments disabled for debit cards (always 1x)
	installments_enabled: false;
	maximum_installments: 1;
	// 3DS is always enabled and mandatory for debit cards
	threeds_enabled: true;
	threeds_allow_continue: false;
	threeds_for_saved_cards: true;
	api_3ds_session_url: string;
	threeds_nonce: string;
	messages: {
		invalid_public_key: string;
		invalid_holder_name: string;
		invalid_card_number: string;
		invalid_card_expiry_date: string;
		invalid_security_code: string;
		invalid_encrypted_card: string;
		invalid_card_bin: string;
		// 3DS messages
		threeds_session_error: string;
		threeds_auth_error: string;
		threeds_change_payment_method: string;
		invalid_cellphone: string;
		threeds_not_supported: string;
	};
}
