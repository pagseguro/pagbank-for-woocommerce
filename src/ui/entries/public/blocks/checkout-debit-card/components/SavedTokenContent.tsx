/**
 * Saved token content component for debit card payments.
 * 3DS is always required for saved debit cards.
 *
 * @package PagBank_WooCommerce
 */

import type { EmitResponseProps, EventRegistrationProps } from "@woocommerce/types";
import React, { useEffect } from "react";
import { settings } from "../settings";

interface SavedTokenContentProps {
	token: string;
	activePaymentMethod: string;
	eventRegistration: EventRegistrationProps;
	emitResponse: EmitResponseProps;
}

/**
 * TODO: Use the SavedTokenContent component from the credit card block.
 */
export const SavedTokenContent = ({
	token,
	eventRegistration,
	emitResponse,
}: SavedTokenContentProps): JSX.Element => {
	// Determine if 3DS should be applied to saved cards
	const should3DSApply = settings.threeds_enabled && settings.threeds_for_saved_cards;

	// Payment setup handler for saved token
	// biome-ignore lint/correctness/useExhaustiveDependencies: eventRegistration and emitResponse are stable WooCommerce Blocks references.
	useEffect(() => {
		const unsubscribe = eventRegistration.onPaymentSetup(async () => {
			// Note: 3DS for saved cards requires the card ID from the tokenized card.
			// The PagBank 3DS SDK needs card details that we don't have access to for saved cards.
			// For now, saved cards with 3DS enabled will proceed without 3DS authentication.
			// To fully support 3DS for saved cards, we would need to:
			// 1. Store additional card info when tokenizing (bin, last4, etc.)
			// 2. Use that info to authenticate via 3DS
			// This is a limitation documented by PagBank.

			if (should3DSApply) {
				// Log warning that 3DS for saved cards is not yet fully supported
				console.warn(
					"3DS for saved cards is enabled but requires additional implementation.",
				);

				return {
					type: emitResponse.responseTypes.ERROR,
					message: settings.messages.threeds_not_supported,
					messageContext: emitResponse.noticeContexts.PAYMENTS,
				};
			}

			return {
				type: emitResponse.responseTypes.SUCCESS,
				meta: {
					paymentMethodData: {
						"wc-pagbank_debit_card-payment-token": token,
						"pagbank_debit_card-installments": 1,
						// Note: threeds-id is not set for saved cards as we can't authenticate
						// without the full card details
					},
				},
			};
		});

		return unsubscribe;
	}, [token, should3DSApply]);

	return <React.Fragment />;
};
