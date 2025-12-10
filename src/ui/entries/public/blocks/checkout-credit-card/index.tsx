/**
 * PagBank Credit Card - WooCommerce Checkout Blocks Integration.
 *
 * @package PagBank_WooCommerce
 */

import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { decodeEntities } from "@wordpress/html-entities";

import { Content } from "./components/Content";
import { SavedTokenContent } from "./components/SavedTokenContent";
import { settings } from "./settings";

const Label = (): JSX.Element => {
	return <span>{decodeEntities(settings.title)}</span>;
};

registerPaymentMethod({
	name: "pagbank_credit_card",
	label: <Label />,
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
