/**
 * Zod schemas for gateway settings validation.
 *
 * @package PagBank_WooCommerce
 */

import { z } from "zod";

// Base types
export const yesNoSchema = z.enum(["yes", "no"]).catch("no");
export const environmentSchema = z.enum(["sandbox", "production"]).catch("sandbox");
export const gatewayIdSchema = z.enum([
	"pagbank_credit_card",
	"pagbank_debit_card",
	"pagbank_pix",
	"pagbank_boleto",
	"pagbank_pay_with_pagbank",
	"pagbank_google_pay",
	"pagbank_apple_pay",
	"pagbank_checkout",
]);

// Installment options (1-12 or 1-18 with feature flag)
const installmentValues = ["1", "2", "3", "4", "5", "6", "7", "8", "9", "10", "11", "12"] as const;
export const installmentSchema = z.enum(installmentValues);

// String schema that handles undefined/null by converting to empty string
const optionalString = z.string().catch("");

// Base gateway settings schema - passthrough allows extra fields from API
export const baseGatewaySettingsSchema = z
	.object({
		enabled: yesNoSchema,
		environment: environmentSchema,
		title: z.string().min(1, "Título é obrigatório"),
		description: optionalString,
		logs_enabled: yesNoSchema,
	})
	.passthrough();

// Credit Card settings schema
export const creditCardSettingsSchema = z
	.object({
		enabled: yesNoSchema,
		environment: environmentSchema,
		title: z.string().min(1, "Título é obrigatório"),
		description: optionalString,
		logs_enabled: yesNoSchema,
		installments_enabled: yesNoSchema,
		maximum_installments: z.string().catch("12"),
		transfer_of_interest_enabled: yesNoSchema,
		maximum_installments_interest_free: z.string().catch("0"),
		threeds_enabled: yesNoSchema,
	})
	.passthrough();

// Credit Card submission schema — normalizes hidden fields before API call
export const creditCardSubmissionSchema = creditCardSettingsSchema.transform((data) => {
	if (data.installments_enabled === "no") {
		return {
			...data,
			maximum_installments: "12",
			transfer_of_interest_enabled: "no",
			maximum_installments_interest_free: "0",
		};
	}
	if (data.transfer_of_interest_enabled === "no") {
		return {
			...data,
			maximum_installments_interest_free: "0",
		};
	}
	return data;
});

// Debit Card settings schema (same as base)
export const debitCardSettingsSchema = baseGatewaySettingsSchema;

// Pix settings schema
export const pixSettingsSchema = z
	.object({
		enabled: yesNoSchema,
		environment: environmentSchema,
		title: z.string().min(1, "Título é obrigatório"),
		description: optionalString,
		logs_enabled: yesNoSchema,
		expiration_minutes: z.string().catch("30"),
	})
	.passthrough();

// Boleto settings schema
export const boletoSettingsSchema = z
	.object({
		enabled: yesNoSchema,
		environment: environmentSchema,
		title: z.string().min(1, "Título é obrigatório"),
		description: optionalString,
		logs_enabled: yesNoSchema,
		expiration_days: z.string().catch("3"),
	})
	.passthrough();

// Pay with PagBank settings schema (same as base)
export const payWithPagBankSettingsSchema = baseGatewaySettingsSchema;

// Google Pay settings schema (same as base)
export const googlePaySettingsSchema = baseGatewaySettingsSchema;

// Apple Pay settings schema (same as base)
export const applePaySettingsSchema = baseGatewaySettingsSchema;

// Checkout PagBank settings schema
export const checkoutSettingsSchema = z
	.object({
		enabled: yesNoSchema,
		environment: environmentSchema,
		title: z.string().min(1, "Título é obrigatório"),
		description: optionalString,
		logs_enabled: yesNoSchema,
		expiration_minutes: z.string().catch("120"),
	})
	.passthrough();

// Union type for all gateway settings
export const gatewaySettingsSchema = z.union([
	creditCardSettingsSchema,
	debitCardSettingsSchema,
	pixSettingsSchema,
	boletoSettingsSchema,
	payWithPagBankSettingsSchema,
	googlePaySettingsSchema,
	applePaySettingsSchema,
	checkoutSettingsSchema,
]);

// Type exports inferred from schemas
export type YesNo = z.infer<typeof yesNoSchema>;
export type Environment = z.infer<typeof environmentSchema>;
export type GatewayId = z.infer<typeof gatewayIdSchema>;
export type BaseGatewaySettings = z.infer<typeof baseGatewaySettingsSchema>;
export type CreditCardSettings = z.infer<typeof creditCardSettingsSchema>;
export type DebitCardSettings = z.infer<typeof debitCardSettingsSchema>;
export type PixSettings = z.infer<typeof pixSettingsSchema>;
export type BoletoSettings = z.infer<typeof boletoSettingsSchema>;
export type PayWithPagBankSettings = z.infer<typeof payWithPagBankSettingsSchema>;
export type GooglePaySettings = z.infer<typeof googlePaySettingsSchema>;
export type ApplePaySettings = z.infer<typeof applePaySettingsSchema>;
export type CheckoutSettings = z.infer<typeof checkoutSettingsSchema>;
export type GatewaySettings = z.infer<typeof gatewaySettingsSchema>;

// Helper function to get schema by gateway ID (for form validation)
export const getSchemaByGatewayId = (gatewayId: GatewayId) => {
	switch (gatewayId) {
		case "pagbank_credit_card":
			return creditCardSettingsSchema;
		case "pagbank_debit_card":
			return debitCardSettingsSchema;
		case "pagbank_pix":
			return pixSettingsSchema;
		case "pagbank_boleto":
			return boletoSettingsSchema;
		case "pagbank_pay_with_pagbank":
			return payWithPagBankSettingsSchema;
		case "pagbank_google_pay":
			return googlePaySettingsSchema;
		case "pagbank_apple_pay":
			return applePaySettingsSchema;
		case "pagbank_checkout":
			return checkoutSettingsSchema;
	}
};

// Helper function to get submission schema by gateway ID (normalizes hidden fields)
export const getSubmissionSchemaByGatewayId = (
	gatewayId: GatewayId,
): z.ZodType<GatewaySettings> => {
	switch (gatewayId) {
		case "pagbank_credit_card":
			return creditCardSubmissionSchema as unknown as z.ZodType<GatewaySettings>;
		default:
			return getSchemaByGatewayId(gatewayId) as z.ZodType<GatewaySettings>;
	}
};

// WooCommerce API response types
export const wcGatewaySettingSchema = z.object({
	id: z.string(),
	label: z.string(),
	description: z.string(),
	type: z.string(),
	value: z.string(),
	default: z.string(),
	tip: z.string(),
	placeholder: z.string(),
	options: z.record(z.string(), z.string()).optional(),
});

export const wcGatewayResponseSchema = z.object({
	id: z.string(),
	title: z.string(),
	description: z.string(),
	order: z.number(),
	enabled: z.boolean(),
	method_title: z.string(),
	method_description: z.string(),
	method_supports: z.array(z.string()),
	settings: z.record(z.string(), wcGatewaySettingSchema),
	icon: z.string().optional(),
});

export type WCGatewaySetting = z.infer<typeof wcGatewaySettingSchema>;
export type WCGatewayResponse = z.infer<typeof wcGatewayResponseSchema>;

// Account info schema
export const accountInfoSchema = z.object({
	email: z.string().nullable(),
	name: z.string().nullable(),
});

// Connect status schema
export const connectStatusSchema = z.object({
	connected: z.boolean(),
	account_id: z.string().nullable(),
	environment: environmentSchema,
	account: accountInfoSchema.nullable(),
	scopes: z.array(z.string()),
	missing_scopes: z.array(z.string()),
});

export type AccountInfo = z.infer<typeof accountInfoSchema>;
export type ConnectStatus = z.infer<typeof connectStatusSchema>;
