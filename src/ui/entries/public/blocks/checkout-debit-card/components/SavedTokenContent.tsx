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
	// Payment setup handler for saved token
	// biome-ignore lint/correctness/useExhaustiveDependencies: eventRegistration and emitResponse are stable WooCommerce Blocks references.
	useEffect(() => {
		const unsubscribe = eventRegistration.onPaymentSetup(async () => {
			// 3DS is always required for saved debit cards
			// However, we cannot authenticate saved cards without full card details.
			// This is a limitation of PagBank's 3DS SDK.
			// For saved debit cards, we return an error since 3DS is mandatory.
			return {
				type: emitResponse.responseTypes.ERROR,
				message: settings.messages.threeds_not_supported,
				messageContext: emitResponse.noticeContexts.PAYMENTS,
			};
		});

		return unsubscribe;
	}, [token]);

	return <React.Fragment />;
};
