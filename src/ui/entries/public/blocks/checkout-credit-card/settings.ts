/**
 * Settings for PagBank Credit Card Blocks.
 *
 * @package PagBank_WooCommerce
 */

import { getSetting } from "@woocommerce/settings";
import { __ } from "@wordpress/i18n";
import { TEXT_DOMAIN } from "../../../../constants";
import type { PaymentMethodSettings } from "./types";

export const settings = getSetting<PaymentMethodSettings>("pagbank_credit_card_data", {
	title: __("Credit card", TEXT_DOMAIN),
	description: "",
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
	threeds_allow_continue: true,
	threeds_for_saved_cards: false,
	api_3ds_session_url: "",
	threeds_nonce: "",
	messages: {
		invalid_public_key: __("Invalid public key.", TEXT_DOMAIN),
		invalid_holder_name: __("Invalid cardholder name.", TEXT_DOMAIN),
		invalid_card_number: __("Invalid card number.", TEXT_DOMAIN),
		invalid_card_expiry_date: __("Invalid card expiry date.", TEXT_DOMAIN),
		invalid_security_code: __("Invalid security code.", TEXT_DOMAIN),
		invalid_encrypted_card: __("Encrypted credit card not found.", TEXT_DOMAIN),
		invalid_card_bin: __("Credit card BIN not found.", TEXT_DOMAIN),
		installments_error: __("Failed to load installments. Please try again.", TEXT_DOMAIN),
		// 3DS messages
		threeds_session_error: __("Failed to create 3DS session. Please try again.", TEXT_DOMAIN),
		threeds_auth_error: __(
			"3DS authentication failed. Please try again or use a different card.",
			TEXT_DOMAIN,
		),
		threeds_change_payment_method: __(
			"This card cannot be authenticated. Please use a different payment method.",
			TEXT_DOMAIN,
		),
		invalid_cellphone: __("The cellphone informed is not valid.", TEXT_DOMAIN),
		threeds_not_supported: __(
			"The credit card cannot be authenticated. Please use a different payment method.",
			TEXT_DOMAIN,
		),
	},
});
