/**
 * PagBank Boleto - WooCommerce Checkout Blocks Integration.
 *
 * @package PagBank_WooCommerce
 */

interface PaymentMethodSettings {
	title: string;
	description: string;
	supports: string[];
}

interface PaymentMethodRegistration {
	name: string;
	label: JSX.Element;
	content: JSX.Element;
	edit: JSX.Element;
	canMakePayment: () => boolean;
	ariaLabel: string;
	supports: {
		features: string[];
	};
}

interface WcBlocksRegistry {
	registerPaymentMethod: (options: PaymentMethodRegistration) => void;
}

interface WcSettings {
	getSetting: <T>(key: string, defaultValue?: T) => T;
}

interface WpHtmlEntities {
	decodeEntities: (text: string) => string;
}

declare const wc: {
	wcBlocksRegistry: WcBlocksRegistry;
	wcSettings: WcSettings;
};

declare const wp: {
	htmlEntities: WpHtmlEntities;
};

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
