/**
 * Apple Pay content component for checkout.
 *
 * @package PagBank_WooCommerce
 */

import type {
	BillingDataProps,
	EmitResponseProps,
	EventRegistrationProps,
} from "@woocommerce/types";
import { decodeEntities } from "@wordpress/html-entities";
import { useEffect, useRef, useState } from "react";
import { settings } from "../settings";

interface ContentProps {
	eventRegistration: EventRegistrationProps;
	emitResponse: EmitResponseProps;
	billing: BillingDataProps;
}

// Apple Pay types
interface ApplePayPaymentToken {
	paymentData: object;
	paymentMethod: {
		displayName: string;
		network: string;
		type: string;
	};
	transactionIdentifier: string;
}

interface ApplePayPaymentAuthorizedEvent {
	payment: {
		token: ApplePayPaymentToken;
	};
}

declare const ApplePaySession: {
	new (version: number, request: ApplePayJS.ApplePayPaymentRequest): ApplePayJS.ApplePaySession;
	STATUS_SUCCESS: number;
	STATUS_FAILURE: number;
	canMakePayments(): boolean;
	supportsVersion(version: number): boolean;
};

declare namespace ApplePayJS {
	interface ApplePayPaymentRequest {
		countryCode: string;
		currencyCode: string;
		supportedNetworks: string[];
		merchantCapabilities: string[];
		total: {
			label: string;
			amount: string;
		};
	}

	interface ApplePaySession {
		onvalidatemerchant: (event: { validationURL: string }) => void;
		onpaymentauthorized: (event: ApplePayPaymentAuthorizedEvent) => void;
		oncancel: () => void;
		begin(): void;
		completeMerchantValidation(merchantSession: object): void;
		completePayment(status: number): void;
		abort(): void;
	}
}

// Supported networks
const SUPPORTED_NETWORKS = ["visa", "masterCard", "elo"];

// Merchant capabilities
const MERCHANT_CAPABILITIES = ["supports3DS"];

export const Content = ({
	eventRegistration,
	emitResponse,
	billing,
}: ContentProps): JSX.Element => {
	const [isApplePayReady, setIsApplePayReady] = useState(false);
	const [isLoading, setIsLoading] = useState(true);
	const billingRef = useRef(billing);

	// Keep billing ref updated
	useEffect(() => {
		billingRef.current = billing;
	}, [billing]);

	// Initialize Apple Pay - check availability
	useEffect(() => {
		const checkApplePayAvailability = () => {
			if (typeof ApplePaySession === "undefined") {
				setIsLoading(false);
				return;
			}

			try {
				// Check if Apple Pay is available and supported
				if (ApplePaySession.canMakePayments() && ApplePaySession.supportsVersion(3)) {
					setIsApplePayReady(true);
				}
			} catch (error) {
				console.error("Apple Pay availability check error:", error);
			} finally {
				setIsLoading(false);
			}
		};

		checkApplePayAvailability();
	}, []);

	// Payment setup handler - triggers Apple Pay when user clicks "Place Order"
	// biome-ignore lint/correctness/useExhaustiveDependencies: eventRegistration and emitResponse are stable WooCommerce Blocks references.
	useEffect(() => {
		const unsubscribe = eventRegistration.onPaymentSetup(async () => {
			if (!isApplePayReady) {
				return {
					type: emitResponse.responseTypes.ERROR,
					message: settings.messages.apple_pay_not_available,
					messageContext: emitResponse.noticeContexts.PAYMENTS,
				};
			}

			const cartTotal = billingRef.current.cartTotal.value / 100; // Convert from cents

			return new Promise((resolve) => {
				try {
					const paymentRequest: ApplePayJS.ApplePayPaymentRequest = {
						countryCode: "BR",
						currencyCode: "BRL",
						supportedNetworks: SUPPORTED_NETWORKS,
						merchantCapabilities: MERCHANT_CAPABILITIES,
						total: {
							label: settings.merchant_name,
							amount: cartTotal.toFixed(2),
						},
					};

					const session = new ApplePaySession(3, paymentRequest);

					session.onvalidatemerchant = (event) => {
						// For PagBank integration, the merchant validation is handled by PagBank
						// We need to call the PagBank API to get the merchant session
						// For now, we'll use a simplified flow where validation is automatic
						console.log("Merchant validation URL:", event.validationURL);

						// In production, you would call your server to validate with Apple
						// For PagBank, the token is generated client-side and sent to PagBank
						// The merchant session validation might not be required depending on PagBank's integration

						// Mock merchant session for development
						// In production, this should come from your server
						try {
							session.completeMerchantValidation({});
						} catch (error) {
							console.error("Merchant validation error:", error);
							resolve({
								type: emitResponse.responseTypes.ERROR,
								message: settings.messages.payment_error,
								messageContext: emitResponse.noticeContexts.PAYMENTS,
							});
						}
					};

					session.onpaymentauthorized = (event) => {
						const token = JSON.stringify(event.payment.token);

						// Complete the payment with success
						session.completePayment(ApplePaySession.STATUS_SUCCESS);

						resolve({
							type: emitResponse.responseTypes.SUCCESS,
							meta: {
								paymentMethodData: {
									"pagbank_apple_pay-token": token,
								},
							},
						});
					};

					session.oncancel = () => {
						resolve({
							type: emitResponse.responseTypes.ERROR,
							message: "Pagamento cancelado pelo usuário.",
							messageContext: emitResponse.noticeContexts.PAYMENTS,
						});
					};

					// Start the Apple Pay session
					session.begin();
				} catch (error) {
					console.error("Apple Pay session error:", error);
					resolve({
						type: emitResponse.responseTypes.ERROR,
						message: settings.messages.payment_error,
						messageContext: emitResponse.noticeContexts.PAYMENTS,
					});
				}
			});
		});

		return unsubscribe;
	}, [isApplePayReady]);

	// Show loading state
	if (isLoading) {
		return (
			<div className="pagbank-apple-pay-loading">
				<span className="pagbank-apple-pay-loading-text">Carregando Apple Pay...</span>
			</div>
		);
	}

	// Show error if Apple Pay is not available
	if (!isApplePayReady) {
		return (
			<div className="pagbank-apple-pay-not-available">
				<p>{settings.messages.apple_pay_not_available}</p>
			</div>
		);
	}

	return (
		<div className="pagbank-apple-pay-content">
			{settings.description && (
				<p className="pagbank-apple-pay-description">
					{decodeEntities(settings.description)}
				</p>
			)}
			<p className="pagbank-apple-pay-instruction">
				Ao clicar em "Finalizar compra", você será redirecionado para autorizar o pagamento
				com o Apple Pay.
			</p>
		</div>
	);
};
