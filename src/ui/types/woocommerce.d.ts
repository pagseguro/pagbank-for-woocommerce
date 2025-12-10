/**
 * WooCommerce external module declarations.
 *
 * These module declarations allow importing from @woocommerce/* packages
 * for type checking and autocompletion, while at runtime the code uses
 * global variables (wc.wcBlocksRegistry, wc.wcSettings, etc).
 *
 * @package PagBank_WooCommerce
 */

// ============================================================================
// @woocommerce/blocks-registry
// ============================================================================

declare module "@woocommerce/blocks-registry" {
	import type { ReactNode } from "react";

	interface PaymentMethodIcon {
		id: string;
		src: string | null;
		alt: string;
	}

	type PaymentMethodIcons = (PaymentMethodIcon | string)[];

	interface SupportsConfiguration {
		showSavedCards?: boolean;
		showSaveOption?: boolean;
		features?: string[];
		savePaymentInfo?: boolean;
		style?: string[];
	}

	interface CanMakePaymentArgument {
		cart: unknown;
		cartTotals: unknown;
		cartNeedsShipping: boolean;
		billingData: unknown;
		shippingAddress: unknown;
		billingAddress: unknown;
		selectedShippingMethods: Record<string, unknown>;
		paymentRequirements: string[];
		paymentMethods: string[];
	}

	type CanMakePaymentReturnType = boolean | Promise<boolean | { error: { message: string } }>;

	type CanMakePaymentCallback = (cartData: CanMakePaymentArgument) => CanMakePaymentReturnType;

	interface PaymentMethodConfiguration {
		name: string;
		content: ReactNode;
		edit: ReactNode;
		canMakePayment: CanMakePaymentCallback;
		paymentMethodId?: string;
		supports: SupportsConfiguration;
		icons?: null | PaymentMethodIcons;
		label: ReactNode;
		ariaLabel: string;
		placeOrderButtonLabel?: string;
		savedTokenComponent?: ReactNode | null;
	}

	interface ExpressPaymentMethodConfiguration {
		name: string;
		title?: string;
		description?: string;
		gatewayId?: string;
		content: ReactNode;
		edit: ReactNode;
		canMakePayment: CanMakePaymentCallback;
		paymentMethodId?: string;
		supports: SupportsConfiguration;
		savedTokenComponent?: ReactNode | null;
	}

	type CanMakePaymentExtensionCallback = (cartData: CanMakePaymentArgument) => boolean;

	export function registerPaymentMethod(options: PaymentMethodConfiguration): void;
	export function registerExpressPaymentMethod(options: ExpressPaymentMethodConfiguration): void;
	export function registerPaymentMethodExtensionCallbacks(
		namespace: string,
		callbacks: Record<string, CanMakePaymentExtensionCallback>,
	): void;
	export function __experimentalDeRegisterPaymentMethod(paymentMethodName: string): void;
	export function __experimentalDeRegisterExpressPaymentMethod(paymentMethodName: string): void;
	export function getPaymentMethods(): Record<string, unknown>;
	export function getExpressPaymentMethods(): Record<string, unknown>;
}

// ============================================================================
// @woocommerce/settings
// ============================================================================

declare module "@woocommerce/settings" {
	export function getSetting<T>(key: string, defaultValue?: T): T;
	export function setSetting(key: string, value: unknown): void;
	export function getAdminLink(path: string): string;
}

// ============================================================================
// @woocommerce/price-format
// ============================================================================

declare module "@woocommerce/price-format" {
	export function formatPrice(price: number | string): string;
}
