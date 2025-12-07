/**
 * PagBank Boleto - WooCommerce Checkout Blocks Integration.
 *
 * This file uses global React from WordPress, not ES module imports.
 * Wrapped in IIFE to avoid variable conflicts with other scripts.
 *
 * @package PagBank_WooCommerce
 */

(function () {
	const React = window.React;
	const { decodeEntities } = wp.htmlEntities;

	const settings: PaymentMethodSettings = wc.wcSettings.getSetting("pagbank_boleto_data", {
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

	const { registerPaymentMethod } = wc.wcBlocksRegistry;

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
})();

export {};
