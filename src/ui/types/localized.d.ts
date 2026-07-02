/**
 * pagbank_pay_with_pagbank_data is localized by WordPress and injected via wp_localize_script.
 * This declaration allows TypeScript code to safely access it.
 */
declare const pagbank_pay_with_pagbank_data: {
	plugin_url: string;
};

declare const pagbank_boleto_data: {
	plugin_url: string;
};

declare const pagbank_credit_card_data: {
	plugin_url: string;
};

declare const pagbank_debit_card_data: {
	plugin_url: string;
};

declare const pagbank_pix_data: {
	plugin_url: string;
};

declare const pagbank_google_pay_data: {
	plugin_url: string;
};

declare const pagbank_apple_pay_data: {
	plugin_url: string;
};

/**
 * pagbankOrderStatus is localized for order status polling.
 */
declare const pagbankOrderStatus: {
	nonce: string;
};

/**
 * pagbankSettings is localized for gateway settings page.
 */
interface PagBankSettingsData {
	gatewayId?: string;
	registeredGatewayIds?: string[];
	pluginUrl?: string;
	settingsUrl?: string;
	iconUrl?: string;
	logoUrl?: string;
	isLocalhost?: boolean;
	oauthNonce?: string;
	ajaxUrl?: string;
	connectApplications?: Array<{
		id: string;
		name: string;
		description: string;
		fee: string;
	}>;
	defaultSandboxApplicationId?: string;
}

interface Window {
	pagbankSettings?: PagBankSettingsData;
}
