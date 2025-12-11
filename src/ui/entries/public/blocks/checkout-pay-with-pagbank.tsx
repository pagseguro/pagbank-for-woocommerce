/**
 * PagBank Pay with PagBank - WooCommerce Checkout Blocks Integration.
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

const settings = getSetting<PaymentMethodSettings>("pagbank_pay_with_pagbank_data", {
	title: "Pagar com PagBank",
	description: "Pague usando sua conta PagBank: saldo, crédito à vista ou parcelado.",
	supports: [],
});

const Label = (): JSX.Element => {
	return <span>{decodeEntities(settings.title)}</span>;
};

const Content = (): JSX.Element => {
	return (
		<div className="pagbank-pay-with-pagbank-description">
			{decodeEntities(settings.description || "")}
		</div>
	);
};

registerPaymentMethod({
	name: "pagbank_pay_with_pagbank",
	label: <Label />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => true,
	ariaLabel: decodeEntities(settings.title),
	supports: {
		features: settings.supports,
	},
});
