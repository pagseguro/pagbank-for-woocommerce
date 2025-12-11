/**
 * Google Pay API type declarations.
 *
 * @see https://developers.google.com/pay/api/web/reference/request-objects
 */

declare namespace google.payments.api {
	type Environment = "TEST" | "PRODUCTION";

	type CardNetwork =
		| "AMEX"
		| "DISCOVER"
		| "INTERAC"
		| "JCB"
		| "MASTERCARD"
		| "VISA"
		| "ELO"
		| "ELO_DEBIT";

	type CardAuthMethod = "PAN_ONLY" | "CRYPTOGRAM_3DS";

	type TotalPriceStatus = "NOT_CURRENTLY_KNOWN" | "ESTIMATED" | "FINAL";

	type ButtonColor = "default" | "black" | "white";
	type ButtonType =
		| "book"
		| "buy"
		| "checkout"
		| "donate"
		| "order"
		| "pay"
		| "plain"
		| "subscribe";
	type ButtonSizeMode = "static" | "fill";

	interface CardParameters {
		allowedAuthMethods: CardAuthMethod[];
		allowedCardNetworks: CardNetwork[];
		billingAddressRequired?: boolean;
		billingAddressParameters?: BillingAddressParameters;
	}

	interface BillingAddressParameters {
		format?: "MIN" | "FULL";
		phoneNumberRequired?: boolean;
	}

	interface TokenizationSpecification {
		type: "PAYMENT_GATEWAY" | "DIRECT";
		parameters: {
			gateway?: string;
			gatewayMerchantId?: string;
			protocolVersion?: string;
			publicKey?: string;
		};
	}

	interface PaymentMethodSpecification {
		type: "CARD";
		parameters: CardParameters;
		tokenizationSpecification: TokenizationSpecification;
	}

	interface TransactionInfo {
		totalPriceStatus: TotalPriceStatus;
		totalPrice: string;
		currencyCode: string;
		countryCode?: string;
	}

	interface MerchantInfo {
		merchantId?: string;
		merchantName?: string;
	}

	interface IsReadyToPayRequest {
		apiVersion: number;
		apiVersionMinor: number;
		allowedPaymentMethods: PaymentMethodSpecification[];
		existingPaymentMethodRequired?: boolean;
	}

	interface IsReadyToPayResponse {
		result: boolean;
		paymentMethodPresent?: boolean;
	}

	interface PaymentDataRequest {
		apiVersion: number;
		apiVersionMinor: number;
		allowedPaymentMethods: PaymentMethodSpecification[];
		transactionInfo: TransactionInfo;
		merchantInfo?: MerchantInfo;
		emailRequired?: boolean;
		shippingAddressRequired?: boolean;
		shippingOptionRequired?: boolean;
	}

	interface PaymentMethodData {
		type: string;
		description: string;
		info: {
			cardNetwork: string;
			cardDetails: string;
			billingAddress?: {
				name?: string;
				postalCode?: string;
				countryCode?: string;
				phoneNumber?: string;
				address1?: string;
				address2?: string;
				address3?: string;
				locality?: string;
				administrativeArea?: string;
				sortingCode?: string;
			};
		};
		tokenizationData: {
			type: string;
			token: string;
		};
	}

	interface PaymentData {
		apiVersion: number;
		apiVersionMinor: number;
		paymentMethodData: PaymentMethodData;
		email?: string;
		shippingAddress?: object;
	}

	interface ButtonOptions {
		onClick: () => void;
		allowedPaymentMethods: PaymentMethodSpecification[];
		buttonColor?: ButtonColor;
		buttonType?: ButtonType;
		buttonSizeMode?: ButtonSizeMode;
		buttonLocale?: string;
	}

	class PaymentsClient {
		constructor(config: { environment: Environment; merchantInfo?: MerchantInfo });

		isReadyToPay(request: IsReadyToPayRequest): Promise<IsReadyToPayResponse>;
		loadPaymentData(request: PaymentDataRequest): Promise<PaymentData>;
		createButton(options: ButtonOptions): HTMLElement;
		prefetchPaymentData(request: PaymentDataRequest): void;
	}
}

declare const google: {
	payments: {
		api: {
			PaymentsClient: typeof google.payments.api.PaymentsClient;
		};
	};
};
