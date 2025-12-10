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
	messages: {
		invalid_public_key: string;
		invalid_holder_name: string;
		invalid_card_number: string;
		invalid_card_expiry_date: string;
		invalid_security_code: string;
		invalid_encrypted_card: string;
		invalid_card_bin: string;
		installments_error: string;
	};
}

export interface EventRegistration {
	onPaymentSetup: (callback: () => unknown) => () => void;
}

export interface EmitResponse {
	responseTypes: {
		SUCCESS: string;
		FAIL: string;
		ERROR: string;
	};
	noticeContexts: {
		PAYMENTS: string;
	};
}

export interface Billing {
	cartTotal: {
		value: number;
	};
}
