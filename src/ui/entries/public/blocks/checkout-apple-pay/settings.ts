/**
 * Apple Pay settings from PHP.
 *
 * @package PagBank_WooCommerce
 */

import { getSetting } from "@woocommerce/settings";

export interface ApplePayMessages {
	apple_pay_not_available: string;
	payment_error: string;
	token_error: string;
}

export interface ApplePaySettings {
	title: string;
	description: string;
	icon: string;
	supports: string[];
	environment: "sandbox" | "production";
	gateway_merchant_id: string | null;
	merchant_name: string;
	messages: ApplePayMessages;
}

const defaultSettings: ApplePaySettings = {
	title: "Apple Pay",
	description: "",
	icon: "",
	supports: [],
	environment: "sandbox",
	gateway_merchant_id: null,
	merchant_name: "",
	messages: {
		apple_pay_not_available: "O Apple Pay não está disponível neste dispositivo ou navegador.",
		payment_error: "Houve um erro ao processar o pagamento. Tente novamente.",
		token_error: "Não foi possível obter o token de pagamento do Apple Pay.",
	},
};

export const settings = getSetting<ApplePaySettings>("pagbank_apple_pay_data", defaultSettings);
