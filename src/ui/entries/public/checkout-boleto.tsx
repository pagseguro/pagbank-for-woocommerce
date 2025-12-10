/**
 * PagBank Boleto - WooCommerce Checkout Blocks Integration.
 *
 * @package PagBank_WooCommerce
 */

import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { getSetting } from "@woocommerce/settings";
import { decodeEntities } from "@wordpress/html-entities";

interface PaymentMethodSettings {
	title: string;
	description: string;
	supports: string[];
}

const settings = getSetting<PaymentMethodSettings>("pagbank_boleto_data", {
	title: "Boleto",
	description: "O boleto será gerado assim que você finalizar o pedido.",
	supports: [],
});

const Label = (): JSX.Element => {
	return <span>{decodeEntities(settings.title)}</span>;
};

const Content = (): JSX.Element => {
	return (
		<div className="pagbank-boleto-description">
			{decodeEntities(settings.description || "")}
		</div>
	);
};

registerPaymentMethod({
	name: "pagbank_boleto",
	label: <Label />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => true,
	ariaLabel: decodeEntities(settings.title),
	supports: {
		features: settings.supports,
	},
});
