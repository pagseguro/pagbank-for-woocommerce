/**
 * Settings for PagBank Debit Card Blocks.
 *
 * @package PagBank_WooCommerce
 */

import { getSetting } from "@woocommerce/settings";
import { __ } from "@wordpress/i18n";
import { TEXT_DOMAIN } from "../../../../constants";
import type { PaymentMethodSettings } from "./types";

export const settings = getSetting<PaymentMethodSettings>("pagbank_debit_card_data", {
	title: __("Debit card", TEXT_DOMAIN),
	description: "",
	supports: [],
	card_public_key: null,
	// Installments disabled for debit cards
	installments_enabled: false,
	maximum_installments: 1,
	// 3DS is always enabled and mandatory for debit cards
	threeds_enabled: true,
	threeds_allow_continue: false,
	threeds_for_saved_cards: true,
	api_3ds_session_url: "",
	threeds_nonce: "",
	messages: {
		invalid_public_key: __("Invalid public key.", TEXT_DOMAIN),
		invalid_holder_name: __("Invalid cardholder name.", TEXT_DOMAIN),
		invalid_card_number: __("Invalid card number.", TEXT_DOMAIN),
		invalid_card_expiry_date: __("Invalid card expiry date.", TEXT_DOMAIN),
		invalid_security_code: __("Invalid security code.", TEXT_DOMAIN),
		invalid_encrypted_card: __("Encrypted debit card not found.", TEXT_DOMAIN),
		invalid_card_bin: __("Debit card BIN not found.", TEXT_DOMAIN),
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
			"The card cannot be authenticated. Please use a different payment method.",
			TEXT_DOMAIN,
		),
	},
});
