/**
 * Type definitions for PagBank Credit Card Blocks.
 *
 * @package PagBank_WooCommerce
 */

export interface InstallmentPlan {
	installments: number;
	installment_value: number;
	interest_free: boolean;
	title: string;
	amount?: number;
}

export interface PaymentMethodSettings {
	title: string;
	description: string;
	supports: string[];
	card_public_key: string | null;
	installments_enabled: boolean;
	maximum_installments: number;
	transfer_of_interest_enabled: boolean;
	maximum_installments_interest_free: number;
	installments_plan: InstallmentPlan[];
	api_installments_url: string;
	nonce: string;
	// 3DS settings
	threeds_enabled: boolean;
	threeds_allow_continue: boolean;
	threeds_for_saved_cards: boolean;
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
		installments_error: string;
		// 3DS messages
		threeds_session_error: string;
		threeds_auth_error: string;
		threeds_change_payment_method: string;
		invalid_cellphone: string;
		threeds_not_supported: string;
	};
}
