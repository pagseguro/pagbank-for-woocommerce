/**
 * PagBank Credit Card - WooCommerce Checkout Blocks Integration.
 *
 * @package PagBank_WooCommerce
 */

import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { decodeEntities } from "@wordpress/html-entities";
import { Label } from "../shared";
import { Content } from "./components/Content";
import { SavedTokenContent } from "./components/SavedTokenContent";
import { settings } from "./settings";

registerPaymentMethod({
	name: "pagbank_credit_card",
	label: <Label title={settings.title} icon={settings.icon} />,
	// @ts-expect-error: WooCommerce Blocks injects props at runtime.
	content: <Content />,
	// @ts-expect-error: WooCommerce Blocks injects props at runtime.
	edit: <Content />,
	canMakePayment: () => true,
	ariaLabel: decodeEntities(settings.title),
	supports: {
		features: settings.supports,
		showSavedCards: true,
		showSaveOption: true,
	},
	// @ts-expect-error: WooCommerce Blocks injects props at runtime.
	savedTokenComponent: <SavedTokenContent />,
});
