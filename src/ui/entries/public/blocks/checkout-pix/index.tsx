/**
 * PagBank Pix - WooCommerce Checkout Blocks Integration.
 *
 * @package PagBank_WooCommerce
 */

import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { getSetting } from "@woocommerce/settings";
import { decodeEntities } from "@wordpress/html-entities";
import { Label } from "../shared";

interface PaymentMethodSettings {
	title: string;
	description: string;
	supports: string[];
}

const settings = getSetting<PaymentMethodSettings>("pagbank_pix_data", {
	title: "Pix",
	description: "O código Pix será gerado assim que você finalizar o pedido.",
	supports: [],
});

const Content = (): JSX.Element => {
	return (
		<div className="pagbank-pix-description">{decodeEntities(settings.description || "")}</div>
	);
};

registerPaymentMethod({
	name: "pagbank_pix",
	label: <Label title={settings.title} baseUrl={pagbank_pix_data.plugin_url} icon="pix" />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => true,
	ariaLabel: decodeEntities(settings.title),
	supports: {
		features: settings.supports,
	},
});
