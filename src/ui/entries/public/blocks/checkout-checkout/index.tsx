/**
 * PagBank Checkout - WooCommerce Checkout Blocks Integration.
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
	icon: string;
	supports: string[];
}

const settings = getSetting<PaymentMethodSettings>("pagbank_checkout_data", {
	title: "Checkout PagBank",
	description: "Você será redirecionado para o PagBank para finalizar o pagamento.",
	icon: "",
	supports: [],
});

const Content = (): JSX.Element => {
	return (
		<div className="pagbank-checkout-description">
			{decodeEntities(settings.description || "")}
		</div>
	);
};

registerPaymentMethod({
	name: "pagbank_checkout",
	label: <Label title={settings.title} icon={settings.icon} />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => true,
	ariaLabel: decodeEntities(settings.title),
	supports: {
		features: settings.supports,
	},
});
