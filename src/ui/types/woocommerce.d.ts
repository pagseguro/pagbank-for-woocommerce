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
// @woocommerce/types
// ============================================================================

declare module "@woocommerce/types" {
	import type { ReactNode } from "react";

	export interface PaymentMethodIcon {
		id: string;
		src: string | null;
		alt: string;
	}

	export type PaymentMethodIcons = (PaymentMethodIcon | string)[];

	export interface SupportsConfiguration {
		showSavedCards?: boolean;
		showSaveOption?: boolean;
		features?: string[];
		savePaymentInfo?: boolean;
		style?: string[];
	}

	export interface CanMakePaymentArgument {
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

	export type CanMakePaymentReturnType =
		| boolean
		| Promise<boolean | { error: { message: string } }>;

	export type CanMakePaymentCallback = (
		cartData: CanMakePaymentArgument,
	) => CanMakePaymentReturnType;

	export interface PaymentMethodConfiguration {
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

	export interface ExpressPaymentMethodConfiguration {
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

	export type CanMakePaymentExtensionCallback = (cartData: CanMakePaymentArgument) => boolean;

	export type ActionCallbackType = (...args: unknown[]) => unknown;

	export interface EventRegistrationProps {
		onPaymentSetup: ReturnType<typeof emitterCallback>;
	}

	export interface EmitResponseProps {
		responseTypes: {
			SUCCESS: string;
			FAIL: string;
			ERROR: string;
		};
		noticeContexts: {
			CART: string;
			CHECKOUT: string;
			PAYMENTS: string;
			EXPRESS_PAYMENTS: string;
			CONTACT_INFORMATION: string;
			SHIPPING_ADDRESS: string;
			BILLING_ADDRESS: string;
			SHIPPING_METHODS: string;
			CHECKOUT_ACTIONS: string;
			ORDER_INFORMATION: string;
		};
	}

	export interface BillingDataProps {
		cartTotal: PreparedCartTotalItem;
		currency: Currency;
		customerId: number;
		billingData: {
			address_1: string;
			address_2: string;
			city: string;
			company: string;
			country: string;
			email: string;
			first_name: string;
			last_name: string;
			"pagbank/address-number": string;
			"pagbank/neighborhood": string;
			"pagbank/tax-id": string;
			"pagbank/cellphone": string;
			phone: string;
			postcode: string;
			state: string;
		};
	}

	export interface PreparedCartTotalItem {
		label: string;
		value: number;
	}

	export interface Currency {
		code: CurrencyCode;
		decimalSeparator: string;
		minorUnit: number;
		prefix: string;
		suffix: string;
		symbol: string;
		thousandSeparator: string;
	}

	export type CurrencyCode = string;

	export type PaymentMethodInterface = {
		activePaymentMethod: string;
		billing: BillingDataProps;
		checkoutStatus: CheckoutStatusProps;
		components: ComponentProps;
		emitResponse: EmitResponseProps;
		eventRegistration: EventRegistrationProps;
		onSubmit: () => void;
		paymentStatus: {
			isIdle: boolean;
			isStarted: boolean;
			isProcessing: boolean;
			hasError: boolean;
			isReady: boolean;
			isDoingExpressPayment: boolean;
		};
		setExpressPaymentError: (errorMessage?: string) => void;
		shouldSavePayment: boolean;
	};

	export interface CheckoutStatusProps {
		isCalculating: boolean;
		isComplete: boolean;
		isIdle: boolean;
		isProcessing: boolean;
	}

	export interface ComponentProps {
		LoadingMask: React.ComponentType<LoadingMaskProps>;
		PaymentMethodIcons: React.ComponentType<PaymentMethodIconsProps>;
		PaymentMethodLabel: React.ComponentType<PaymentMethodLabelProps>;
		ValidationInputError: React.ComponentType<ValidationInputErrorProps>;
	}

	export interface LoadingMaskProps {
		children?: React.ReactNode | React.ReactNode[];
		className?: string;
		screenReaderLabel?: string;
		showSpinner?: boolean;
		isLoading?: boolean;
	}

	export interface PaymentMethodIconsProps {
		icons: PaymentMethodIconsType;
		align?: "left" | "right" | "center";
		className?: string;
	}

	export interface PaymentMethodLabelProps {
		icon: "" | keyof NamedIcons | SVGElement;
		text: string;
	}

	export interface ValidationInputErrorProps {
		errorMessage?: string;
		propertyName?: string;
		elementId?: string;
	}
}

// ============================================================================
// @woocommerce/blocks-registry
// ============================================================================

declare module "@woocommerce/blocks-registry" {
	import type {
		PaymentMethodConfiguration,
		ExpressPaymentMethodConfiguration,
		CanMakePaymentExtensionCallback,
	} from "@woocommerce/types";

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
