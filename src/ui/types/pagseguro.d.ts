/**
 * PagSeguro SDK type definitions.
 *
 * @see https://assets.pagseguro.com.br/checkout-sdk-js/rc/dist/browser/pagseguro.js
 * @package PagBank_WooCommerce
 */

// ============================================================================
// Error Types
// ============================================================================

type PagSeguroEncryptCardErrorCode =
	| "INVALID_NUMBER"
	| "INVALID_SECURITY_CODE"
	| "INVALID_EXPIRATION_MONTH"
	| "INVALID_EXPIRATION_YEAR"
	| "INVALID_PUBLIC_KEY"
	| "INVALID_HOLDER";

interface PagSeguroEncryptCardError {
	code: PagSeguroEncryptCardErrorCode;
	message: string;
}

interface PagSeguroErrorDetail {
	httpStatus: number | string;
	traceId: string | null;
	message: string;
	errorMessages?: Array<{
		code: string;
		description: string;
		parameterName: string;
	}>;
}

// ============================================================================
// encryptCard Types
// ============================================================================

interface PagSeguroEncryptCardRequest {
	publicKey: string;
	holder: string;
	number: string;
	securityCode: string;
	expMonth: string;
	expYear: string;
}

interface PagSeguroEncryptCardResponse {
	hasErrors: boolean;
	errors: PagSeguroEncryptCardError[];
	encryptedCard: string | null;
}

// ============================================================================
// setUp Types
// ============================================================================

type PagSeguroEnvironment = "PROD" | "SANDBOX" | "QA" | "SANDBOX-QA" | "LOCAL";

interface PagSeguroSetUpParameters {
	env?: PagSeguroEnvironment;
	session?: string;
}

// ============================================================================
// 3DS Authentication Types
// ============================================================================

interface PagSeguroDeviceInformation {
	httpBrowserColorDepth: number | null;
	httpBrowserJavaEnabled: boolean | null;
	httpBrowserJavaScriptEnabled: boolean;
	httpBrowserLanguage: string;
	httpBrowserScreenHeight: number | null;
	httpBrowserScreenWidth: number | null;
	httpBrowserTimeDifference: number;
	httpDeviceChannel: string;
	userAgentBrowserValue: string;
}

interface PagSeguroPaymentMethodCard {
	number?: string;
	expMonth?: string;
	expYear?: string;
	holder?: {
		name: string;
	};
	encrypted?: string;
}

type PagSeguroPaymentMethodType = "CREDIT_CARD" | "DEBIT_CARD";

interface PagSeguro3DSAuthenticationRequest {
	data: {
		paymentMethod?: {
			type?: PagSeguroPaymentMethodType;
			installments?: number;
			card?: PagSeguroPaymentMethodCard;
		};
		customer?: {
			name: string;
			email: string;
			phones?: Array<{
				country: string;
				area: string;
				number: string;
				type: "MOBILE" | "HOME" | "BUSINESS";
			}>;
		};
		amount?: {
			value: number;
			currency: string;
		};
		billingAddress?: {
			street: string;
			number: string;
			complement?: string;
			regionCode: string;
			country: string;
			city: string;
			postalCode: string;
		};
		dataOnly?: boolean;
		deviceInformation?: PagSeguroDeviceInformation | null;
		[key: string]: unknown;
	};
	beforeChallenge?: (challenge: { open: () => void; brand: string; issuer: string }) => void;
	onSuccess?: (result: PagSeguro3DSAuthenticationResponse) => void;
	onFailure?: (error: Error) => void;
	onError?: (error: Error) => void;
}

interface PagSeguro3DSInitialization {
	bin: string;
	jwt: string;
}

interface PagSeguro3DSChallenge {
	acsUrl: string;
	payload: string;
	transactionId: string;
	brand: string;
	issuer: string;
}

type PagSeguro3DSStatus =
	| "REQUIRE_INITIALIZATION"
	| "REQUIRE_CHALLENGE"
	| "AUTH_FLOW_COMPLETED"
	| "AUTH_NOT_SUPPORTED"
	| "CHANGE_PAYMENT_METHOD";

interface PagSeguro3DSAuthenticationResponse {
	id: string;
	status: PagSeguro3DSStatus;
	initialization?: PagSeguro3DSInitialization;
	challenge?: PagSeguro3DSChallenge;
	authenticationStatus?: string;
	[key: string]: unknown;
}

// ============================================================================
// Device Fingerprint Types
// ============================================================================

interface PagSeguroDeviceFingerprintOptions {
	timeout?: number;
}

interface PagSeguroDeviceFingerprintResponse {
	device_id: string;
}

// ============================================================================
// Main PagSeguro Object
// ============================================================================

declare const PagSeguro: {
	/**
	 * Encrypts credit card data using PagBank's public key.
	 */
	encryptCard: (card: PagSeguroEncryptCardRequest) => PagSeguroEncryptCardResponse;

	/**
	 * Sets up the PagSeguro SDK with environment and session configuration.
	 */
	setUp: (parameters: PagSeguroSetUpParameters) => void;

	/**
	 * Authenticates a payment using 3DS (3D Secure).
	 */
	authenticate3DS: (
		request: PagSeguro3DSAuthenticationRequest,
	) => Promise<PagSeguro3DSAuthenticationResponse>;

	/**
	 * Generates a device fingerprint for fraud prevention.
	 */
	generateDeviceFingerprint: (
		options?: PagSeguroDeviceFingerprintOptions,
	) => Promise<PagSeguroDeviceFingerprintResponse>;

	/**
	 * Environment constants.
	 */
	env: {
		PROD: "PROD";
		SANDBOX: "SANDBOX";
	};

	/**
	 * Custom error class for PagSeguro errors.
	 */
	PagSeguroError: new (
		message: string,
		detail: PagSeguroErrorDetail,
	) => Error & {
		detail: PagSeguroErrorDetail;
	};
};
