/**
 * PagBank Pay with PagBank - WooCommerce Checkout Blocks Integration.
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

const settings = getSetting<PaymentMethodSettings>("pagbank_pay_with_pagbank_data", {
	title: "Pagar com PagBank",
	description: "Pague usando sua conta PagBank: saldo, crédito à vista ou parcelado.",
	icon: "",
	supports: [],
});

const Content = (): JSX.Element => {
	return (
		<div className="pagbank-pay-with-pagbank-description">
			{decodeEntities(settings.description || "")}
		</div>
	);
};

registerPaymentMethod({
	name: "pagbank_pay_with_pagbank",
	label: <Label title={settings.title} icon={settings.icon} />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => true,
	ariaLabel: decodeEntities(settings.title),
	supports: {
		features: settings.supports,
	},
});
