/**
 * WooCommerce Blocks type declarations.
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

interface Window {
	React: typeof import("react");
}
