/**
 * Settings for PagBank Credit Card Blocks.
 *
 * @package PagBank_WooCommerce
 */

import { getSetting } from "@woocommerce/settings";
import { __ } from "@wordpress/i18n";
import { type CardPaymentMethodSettings, defaultCardPaymentMethodSettings } from "../shared";

export const settings = getSetting<CardPaymentMethodSettings>("pagbank_credit_card_data", {
	...defaultCardPaymentMethodSettings,
	title: __("Credit card", "pagbank-for-woocommerce"),
});
