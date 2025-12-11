/**
 * Gateway settings TypeScript types.
 *
 * @package PagBank_WooCommerce
 */

export type GatewayId =
	| "pagbank_credit_card"
	| "pagbank_debit_card"
	| "pagbank_pix"
	| "pagbank_boleto"
	| "pagbank_pay_with_pagbank";

export type Environment = "sandbox" | "production";

export type YesNo = "yes" | "no";

export interface BaseGatewaySettings {
	enabled: YesNo;
	environment: Environment;
	title: string;
	description: string;
	logs_enabled: YesNo;
}

export interface CreditCardSettings extends BaseGatewaySettings {
	installments_enabled: YesNo;
	maximum_installments: string;
	transfer_of_interest_enabled: YesNo;
	maximum_installments_interest_free: string;
	threeds_enabled: YesNo;
	threeds_allow_continue: YesNo;
	threeds_for_saved_cards: YesNo;
}

export interface DebitCardSettings extends BaseGatewaySettings {
	// Debit card uses base settings only
}

export interface PixSettings extends BaseGatewaySettings {
	expiration_minutes: string;
}

export interface BoletoSettings extends BaseGatewaySettings {
	expiration_days: string;
}

export interface PayWithPagBankSettings extends BaseGatewaySettings {
	// Pay with PagBank uses base settings only
}

export type GatewaySettings =
	| CreditCardSettings
	| DebitCardSettings
	| PixSettings
	| BoletoSettings
	| PayWithPagBankSettings;

export interface AccountInfo {
	email: string | null;
	name: string | null;
}

export interface ConnectStatus {
	connected: boolean;
	account_id: string | null;
	environment: Environment;
	isLoading: boolean;
	account: AccountInfo | null;
	missing_scopes: string[];
	authentication_error: boolean;
}

export interface ConnectApplication {
	id: string;
	title: string;
	app_name: string;
	access_token: string;
	environment: Environment;
}

export interface GatewayInfo {
	id: GatewayId;
	title: string;
	description: string;
	method_title: string;
	method_description: string;
	settings: GatewaySettings;
}

export interface PagBankSettingsLocalized {
	gatewayId: GatewayId;
	settings: GatewaySettings;
	restUrl: string;
	nonce: string;
	oauthNonce: string;
	ajaxUrl: string;
	isLocalhost: boolean;
	connectApplications: Record<string, ConnectApplication>;
}

declare global {
	interface Window {
		pagbankSettings: PagBankSettingsLocalized;
	}
}
