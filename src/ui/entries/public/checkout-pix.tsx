/**
 * PagBank Pix - WooCommerce Checkout Blocks Integration.
 *
 * @package PagBank_WooCommerce
 */
import * as React from "react";

import { decodeEntities } from "@wordpress/html-entities";

const settings: PaymentMethodSettings = wc.wcSettings.getSetting("pagbank_pix_data", {
	title: "Pix",
	description: "O código Pix será gerado assim que você finalizar o pedido.",
	supports: [],
});

const Label = (): JSX.Element => {
	return <span>{decodeEntities(settings.title)}</span>;
};

const Content = (): JSX.Element => {
	return (
		<div className="pagbank-pix-description">{decodeEntities(settings.description || "")}</div>
	);
};

const { registerPaymentMethod } = wc.wcBlocksRegistry;

registerPaymentMethod({
	name: "pagbank_pix",
	label: <Label />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => true,
	ariaLabel: decodeEntities(settings.title),
	supports: {
		features: settings.supports,
	},
});
