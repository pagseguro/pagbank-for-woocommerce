import { __ } from "@wordpress/i18n";
import type { CardPaymentMethodSettings } from "./types";

export const defaultCardPaymentMethodSettings: Omit<CardPaymentMethodSettings, "title"> = {
	description: "",
	icon: "",
	supports: [],
	card_public_key: null,
	installments_enabled: false,
	maximum_installments: 12,
	transfer_of_interest_enabled: false,
	maximum_installments_interest_free: 1,
	installments_plan: [],
	api_installments_url: "",
	nonce: "",
	// 3DS settings
	threeds_enabled: false,
	api_3ds_session_url: "",
	threeds_nonce: "",
	messages: {
		invalid_public_key: __("Invalid public key.", "pagbank-for-woocommerce"),
		invalid_holder_name: __("Invalid cardholder name.", "pagbank-for-woocommerce"),
		invalid_card_number: __("Invalid card number.", "pagbank-for-woocommerce"),
		invalid_card_expiry_date: __("Invalid card expiry date.", "pagbank-for-woocommerce"),
		invalid_security_code: __("Invalid security code.", "pagbank-for-woocommerce"),
		invalid_encrypted_card: __("Encrypted card not found.", "pagbank-for-woocommerce"),
		invalid_card_bin: __("Card BIN not found.", "pagbank-for-woocommerce"),
		installments_error: __(
			"Failed to load installments. Please try again.",
			"pagbank-for-woocommerce",
		),
		// 3DS messages
		threeds_session_error: __(
			"Failed to create 3DS session. Please try again.",
			"pagbank-for-woocommerce",
		),
		threeds_auth_error: __(
			"3DS authentication failed. Please try again or use a different card.",
			"pagbank-for-woocommerce",
		),
		threeds_change_payment_method: __(
			"This card cannot be authenticated. Please use a different payment method.",
			"pagbank-for-woocommerce",
		),
		invalid_cellphone: __("The cellphone informed is not valid.", "pagbank-for-woocommerce"),
		threeds_not_supported: __(
			"The card cannot be authenticated. Please use a different payment method.",
			"pagbank-for-woocommerce",
		),
	},
	// Subscription support
	cart_has_subscription: false,
};
