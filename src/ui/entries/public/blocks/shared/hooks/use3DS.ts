/**
 * Shared hook for managing 3DS authentication.
 *
 * @package PagBank_WooCommerce
 */

import apiFetch from "@wordpress/api-fetch";
import { useCallback, useRef, useState } from "react";
import type { CardPaymentMethodSettings, CardType } from "../types";

export type ThreeDSStatus =
	| "AUTH_FLOW_COMPLETED"
	| "AUTH_NOT_SUPPORTED"
	| "CHANGE_PAYMENT_METHOD"
	| "REQUIRE_CHALLENGE"
	| "REQUIRE_INITIALIZATION";

export interface ThreeDSResult {
	status: ThreeDSStatus;
	id?: string;
}

interface ThreeDSCustomerPhone {
	country: string;
	area: string;
	number: string;
	type: "MOBILE" | "HOME" | "BUSINESS";
}

interface ThreeDSAddress {
	street: string;
	number: string;
	complement?: string;
	regionCode: string;
	country: string;
	city: string;
	postalCode: string;
}

export interface ThreeDSAuthenticateParams {
	customer: {
		name: string;
		email: string;
		phones?: ThreeDSCustomerPhone[];
	};
	card: {
		number: string;
		expMonth: string;
		expYear: string;
		holder: {
			name: string;
		};
	};
	amount: {
		value: number;
		currency: string;
	};
	installments: number;
	billingAddress: ThreeDSAddress;
	shippingAddress?: ThreeDSAddress;
}

interface Use3DSOptions {
	settings: CardPaymentMethodSettings;
	cardType: CardType;
}

interface Use3DSReturn {
	authenticate: (params: ThreeDSAuthenticateParams) => Promise<ThreeDSResult>;
	isAuthenticating: boolean;
	error: string | null;
	isEnabled: boolean;
}

interface SessionData {
	session: string;
	expires_at: number;
	env: PagSeguroEnvironment;
}

export const use3DS = ({ settings, cardType }: Use3DSOptions): Use3DSReturn => {
	const [isAuthenticating, setIsAuthenticating] = useState(false);
	const [error, setError] = useState<string | null>(null);
	const sessionDataRef = useRef<SessionData | null>(null);

	const fetchSession = useCallback(async (): Promise<SessionData | null> => {
		try {
			const params = new URLSearchParams({
				nonce: settings.threeds_nonce,
			});

			const data = await apiFetch<{ success: boolean; data: SessionData }>({
				url: `${settings.api_3ds_session_url}?${params}`,
			});

			if (data.success && data.data) {
				sessionDataRef.current = data.data;
				return data.data;
			}

			setError(settings.messages.threeds_session_error);
			return null;
		} catch {
			setError(settings.messages.threeds_session_error);
			return null;
		}
	}, [
		settings.api_3ds_session_url,
		settings.threeds_nonce,
		settings.messages.threeds_session_error,
	]);

	const authenticate = useCallback(
		async (params: ThreeDSAuthenticateParams): Promise<ThreeDSResult> => {
			setIsAuthenticating(true);
			setError(null);

			try {
				// Fetch a new session for each authentication
				const sessionData = await fetchSession();

				if (!sessionData) {
					return { status: "CHANGE_PAYMENT_METHOD" };
				}

				// Setup PagSeguro SDK with session
				PagSeguro.setUp({
					session: sessionData.session,
					env: sessionData.env,
				});

				// Build the authentication request
				const request: PagSeguro3DSAuthenticationRequest = {
					data: {
						customer: {
							name: params.customer.name,
							email: params.customer.email,
							phones: params.customer.phones || [],
						},
						paymentMethod: {
							type: cardType,
							installments: cardType === "DEBIT_CARD" ? 1 : params.installments,
							card: {
								number: params.card.number,
								expMonth: params.card.expMonth,
								expYear: params.card.expYear,
								holder: {
									name: params.card.holder.name,
								},
							},
						},
						amount: {
							value: params.amount.value,
							currency: params.amount.currency,
						},
						billingAddress: params.billingAddress,
						shippingAddress: params.shippingAddress,
						dataOnly: false, // Allow challenge
					},
					beforeChallenge: (challenge) => {
						// The SDK handles the challenge iframe automatically
						challenge.open();
					},
				};

				const result = await PagSeguro.authenticate3DS(request);

				return {
					status: result.status,
					id: result.id,
				};
			} catch (err) {
				// Handle PagSeguro errors
				if (err instanceof PagSeguro.PagSeguroError) {
					console.error("3DS Authentication error:", err.detail);
					setError(err.detail?.message || settings.messages.threeds_auth_error);
				} else {
					console.error("3DS Authentication error:", err);
					setError(settings.messages.threeds_auth_error);
				}

				return { status: "CHANGE_PAYMENT_METHOD" };
			} finally {
				setIsAuthenticating(false);
			}
		},
		[fetchSession, cardType, settings.messages.threeds_auth_error],
	);

	return {
		authenticate,
		isAuthenticating,
		error,
		isEnabled: settings.threeds_enabled,
	};
};
