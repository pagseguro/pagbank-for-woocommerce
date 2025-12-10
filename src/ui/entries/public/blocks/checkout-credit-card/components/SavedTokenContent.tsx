/**
 * Saved token content component for credit card payments.
 *
 * @package PagBank_WooCommerce
 */

import type {
	BillingDataProps,
	EmitResponseProps,
	EventRegistrationProps,
} from "@woocommerce/types";
import { useEffect, useRef } from "react";
import { useInstallments } from "../hooks/useInstallments";
import { settings } from "../settings";
import { InstallmentsSelect } from "./InstallmentsSelect";

interface SavedTokenContentProps {
	token: string;
	activePaymentMethod: string;
	eventRegistration: EventRegistrationProps;
	emitResponse: EmitResponseProps;
	billing: BillingDataProps;
}

export const SavedTokenContent = ({
	token,
	eventRegistration,
	emitResponse,
	billing,
}: SavedTokenContentProps): JSX.Element => {
	const { installments, setInstallments, installmentPlans, isLoading } = useInstallments({
		cartTotalInCents: billing.cartTotal.value,
		paymentToken: token,
	});

	// Ref for installments to avoid re-registering callback
	const installmentsRef = useRef(installments);

	useEffect(() => {
		installmentsRef.current = installments;
	}, [installments]);

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
						"wc-pagbank_credit_card-payment-token": token,
						"pagbank_credit_card-installments": installmentsRef.current,
						// Note: threeds-id is not set for saved cards as we can't authenticate
						// without the full card details
					},
				},
			};
		});

		return unsubscribe;
	}, [token, should3DSApply]);

	return (
		<div className="pagbank-credit-card-saved-token">
			{settings.installments_enabled && (
				<InstallmentsSelect
					id={`pagbank-installments-${token}`}
					value={installments}
					onChange={setInstallments}
					plans={installmentPlans}
					isLoading={isLoading}
				/>
			)}
		</div>
	);
};
