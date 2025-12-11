/**
 * Google Pay content component for checkout.
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

// Google Pay API types
interface GooglePayPaymentData {
	paymentMethodData: {
		tokenizationData: {
			token: string;
		};
	};
}

// Allowed card networks for PagBank
const ALLOWED_CARD_NETWORKS: google.payments.api.CardNetwork[] = ["VISA", "MASTERCARD", "ELO"];

// Allowed authentication methods
const ALLOWED_AUTH_METHODS: google.payments.api.CardAuthMethod[] = ["PAN_ONLY", "CRYPTOGRAM_3DS"];

// Card payment method configuration
const getCardPaymentMethod = (): google.payments.api.PaymentMethodSpecification => ({
	type: "CARD",
	parameters: {
		allowedAuthMethods: ALLOWED_AUTH_METHODS,
		allowedCardNetworks: ALLOWED_CARD_NETWORKS,
		billingAddressRequired: true,
		billingAddressParameters: {
			format: "FULL",
			phoneNumberRequired: true,
		},
	},
	tokenizationSpecification: {
		type: "PAYMENT_GATEWAY",
		parameters: {
			gateway: "pagbank",
			gatewayMerchantId: settings.gateway_merchant_id || "",
		},
	},
});

export const Content = ({
	eventRegistration,
	emitResponse,
	billing,
}: ContentProps): JSX.Element => {
	const [isGooglePayReady, setIsGooglePayReady] = useState(false);
	const [isLoading, setIsLoading] = useState(true);
	const paymentsClientRef = useRef<google.payments.api.PaymentsClient | null>(null);
	const billingRef = useRef(billing);

	// Keep billing ref updated
	useEffect(() => {
		billingRef.current = billing;
	}, [billing]);

	// Initialize Google Pay - only once
	useEffect(() => {
		const initializeGooglePay = async () => {
			if (typeof google === "undefined" || !google.payments?.api?.PaymentsClient) {
				setIsLoading(false);
				return;
			}

			try {
				const paymentsClient = new google.payments.api.PaymentsClient({
					environment: settings.environment,
				});
				paymentsClientRef.current = paymentsClient;

				// Check if Google Pay is available
				const isReadyToPayRequest: google.payments.api.IsReadyToPayRequest = {
					apiVersion: 2,
					apiVersionMinor: 0,
					allowedPaymentMethods: [getCardPaymentMethod()],
				};

				const response = await paymentsClient.isReadyToPay(isReadyToPayRequest);

				if (response.result) {
					setIsGooglePayReady(true);
				}
			} catch (error) {
				console.error("Google Pay initialization error:", error);
			} finally {
				setIsLoading(false);
			}
		};

		initializeGooglePay();
	}, []);

	// Payment setup handler - triggers Google Pay when user clicks "Place Order"
	// biome-ignore lint/correctness/useExhaustiveDependencies: eventRegistration and emitResponse are stable WooCommerce Blocks references.
	useEffect(() => {
		const unsubscribe = eventRegistration.onPaymentSetup(async () => {
			if (!paymentsClientRef.current) {
				return {
					type: emitResponse.responseTypes.ERROR,
					message: settings.messages.google_pay_not_available,
					messageContext: emitResponse.noticeContexts.PAYMENTS,
				};
			}

			const cartTotal = billingRef.current.cartTotal.value / 100; // Convert from cents

			const paymentDataRequest: google.payments.api.PaymentDataRequest = {
				apiVersion: 2,
				apiVersionMinor: 0,
				allowedPaymentMethods: [getCardPaymentMethod()],
				transactionInfo: {
					totalPriceStatus: "FINAL",
					totalPrice: cartTotal.toFixed(2),
					currencyCode: "BRL",
					countryCode: "BR",
				},
				merchantInfo: {
					merchantName: settings.merchant_name,
					merchantId: settings.gateway_merchant_id || undefined,
				},
			};

			try {
				const paymentData = (await paymentsClientRef.current.loadPaymentData(
					paymentDataRequest,
				)) as GooglePayPaymentData;
				const token = paymentData.paymentMethodData.tokenizationData.token;

				return {
					type: emitResponse.responseTypes.SUCCESS,
					meta: {
						paymentMethodData: {
							"pagbank_google_pay-token": token,
						},
					},
				};
			} catch (error) {
				// User cancelled or error occurred
				const errorWithCode = error as { statusCode?: string };
				if (errorWithCode.statusCode === "CANCELED") {
					return {
						type: emitResponse.responseTypes.ERROR,
						message: "Pagamento cancelado pelo usuário.",
						messageContext: emitResponse.noticeContexts.PAYMENTS,
					};
				}

				console.error("Google Pay payment error:", error);
				return {
					type: emitResponse.responseTypes.ERROR,
					message: settings.messages.payment_error,
					messageContext: emitResponse.noticeContexts.PAYMENTS,
				};
			}
		});

		return unsubscribe;
	}, []);

	// Show loading state
	if (isLoading) {
		return (
			<div className="pagbank-google-pay-loading">
				<span className="pagbank-google-pay-loading-text">Carregando Google Pay...</span>
			</div>
		);
	}

	// Show error if Google Pay is not available
	if (!isGooglePayReady) {
		return (
			<div className="pagbank-google-pay-not-available">
				<p>{settings.messages.google_pay_not_available}</p>
			</div>
		);
	}

	return (
		<div className="pagbank-google-pay-content">
			{settings.description && (
				<p className="pagbank-google-pay-description">
					{decodeEntities(settings.description)}
				</p>
			)}
			<p className="pagbank-google-pay-instruction">
				Ao clicar em "Finalizar compra", você será redirecionado para autorizar o pagamento
				com o Google Pay.
			</p>
		</div>
	);
};
