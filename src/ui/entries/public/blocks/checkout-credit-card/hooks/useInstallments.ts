/**
 * Custom hook for managing installments state and fetching.
 *
 * @package PagBank_WooCommerce
 */

import apiFetch from "@wordpress/api-fetch";
import { useCallback, useEffect, useState } from "react";
import { calculateFixedInstallmentPlans, type InstallmentPlan } from "../../shared";
import { settings } from "../settings";

interface UseInstallmentsOptions {
	cartTotalInCents: number;
	cardBin?: string;
	paymentToken?: string;
}

interface UseInstallmentsReturn {
	installments: string;
	setInstallments: (value: string) => void;
	installmentPlans: InstallmentPlan[];
	isLoading: boolean;
}

export const useInstallments = ({
	cartTotalInCents,
	cardBin,
	paymentToken,
}: UseInstallmentsOptions): UseInstallmentsReturn => {
	const [installments, setInstallments] = useState("1");
	const [installmentPlans, setInstallmentPlans] = useState<InstallmentPlan[]>(
		settings.installments_plan,
	);
	const [isLoading, setIsLoading] = useState(false);

	// Fetch dynamic installments from API
	const fetchInstallments = useCallback(
		async (bin?: string, token?: string) => {
			if (!settings.transfer_of_interest_enabled) {
				return;
			}

			// Need either a complete BIN or a payment token
			if (!token && (!bin || bin.length < 6)) {
				return;
			}

			setIsLoading(true);

			try {
				const params = new URLSearchParams({
					nonce: settings.nonce,
					amount: String(cartTotalInCents / 100),
				});

				if (token) {
					params.set("payment_token", token);
				} else if (bin) {
					params.set("card_bin", bin);
				}

				const data = await apiFetch<{ success: boolean; data: InstallmentPlan[] }>({
					url: `${settings.api_installments_url}?${params}`,
				});

				if (data.success && data.data) {
					setInstallmentPlans(data.data);
				}
			} catch {
				console.error("Failed to fetch installments");
			} finally {
				setIsLoading(false);
			}
		},
		[cartTotalInCents],
	);

	// Fetch dynamic installments when BIN is complete or for saved card
	useEffect(() => {
		if (settings.transfer_of_interest_enabled) {
			if (paymentToken) {
				fetchInstallments(undefined, paymentToken);
			} else if (cardBin && cardBin.length === 6) {
				fetchInstallments(cardBin);
			}
		}
	}, [cardBin, paymentToken, fetchInstallments]);

	// Recalculate fixed installments when cart total changes
	useEffect(() => {
		if (settings.installments_enabled && !settings.transfer_of_interest_enabled) {
			const plans = calculateFixedInstallmentPlans(
				cartTotalInCents,
				settings.maximum_installments,
			);
			setInstallmentPlans(plans);
		}
	}, [cartTotalInCents]);

	return {
		installments,
		setInstallments,
		installmentPlans,
		isLoading,
	};
};
