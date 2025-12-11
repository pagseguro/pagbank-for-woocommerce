/**
 * Google Pay settings from PHP.
 *
 * @package PagBank_WooCommerce
 */

import { getSetting } from "@woocommerce/settings";

export interface GooglePayMessages {
	google_pay_not_available: string;
	payment_error: string;
	token_error: string;
}

export interface GooglePaySettings {
	title: string;
	description: string;
	icon: string;
	supports: string[];
	environment: "TEST" | "PRODUCTION";
	gateway_merchant_id: string | null;
	merchant_name: string;
	messages: GooglePayMessages;
}

const defaultSettings: GooglePaySettings = {
	title: "Google Pay",
	description: "",
	icon: "",
	supports: [],
	environment: "TEST",
	gateway_merchant_id: null,
	merchant_name: "",
	messages: {
		google_pay_not_available:
			"O Google Pay não está disponível neste dispositivo ou navegador.",
		payment_error: "Houve um erro ao processar o pagamento. Tente novamente.",
		token_error: "Não foi possível obter o token de pagamento do Google Pay.",
	},
};

export const settings = getSetting<GooglePaySettings>("pagbank_google_pay_data", defaultSettings);
