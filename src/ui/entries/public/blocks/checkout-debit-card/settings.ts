/**
 * Settings for PagBank Debit Card Blocks.
 *
 * @package PagBank_WooCommerce
 */

import { getSetting } from "@woocommerce/settings";
import { __ } from "@wordpress/i18n";
import { type CardPaymentMethodSettings, defaultCardPaymentMethodSettings } from "../shared";

export const settings = getSetting<CardPaymentMethodSettings>("pagbank_debit_card_data", {
	...defaultCardPaymentMethodSettings,
	title: __("Debit card", "pagbank-for-woocommerce"),
});
