/**
 * Hook for polling order payment status.
 *
 * @package PagBank_WooCommerce
 */

import apiFetch from "@wordpress/api-fetch";
import { useCallback, useEffect, useRef, useState } from "react";
import type { OrderStatusResponse } from "../types";

interface UseOrderStatusOptions {
	orderId: number;
	orderKey: string;
	restUrl: string;
	initialIsPaid: boolean;
	expirationDate?: string;
}

interface UseOrderStatusReturn {
	isPaid: boolean;
	isExpired: boolean;
	isPolling: boolean;
}

const POLLING_INTERVAL = 5000; // 5 seconds
const MAX_POLLING_DURATION = 600000; // 10 minutes

export const useOrderStatus = (options: UseOrderStatusOptions): UseOrderStatusReturn => {
	const [isPaid, setIsPaid] = useState(options.initialIsPaid);
	const [isExpired, setIsExpired] = useState(false);
	const [isPolling, setIsPolling] = useState(!options.initialIsPaid);
	const pollCountRef = useRef(0);
	const startTimeRef = useRef(Date.now());

	const checkStatus = useCallback(async (): Promise<boolean> => {
		try {
			const url = `${options.restUrl}pagbank/v1/order/${options.orderId}/status?key=${options.orderKey}`;
			const data = await apiFetch<OrderStatusResponse>({
				url,
				headers: {
					"X-WP-Nonce": pagbankOrderStatus.nonce,
				},
			});

			if (data.is_paid) {
				setIsPaid(true);
				setIsPolling(false);
				// Reload page to show updated content
				window.location.reload();
				return true;
			}

			if (data.is_expired) {
				setIsExpired(true);
				setIsPolling(false);
				return true;
			}

			return false;
		} catch {
			// Continue polling on error
			return false;
		}
	}, [options.orderId, options.orderKey, options.restUrl]);

	useEffect(() => {
		// Don't poll if already paid
		if (isPaid) {
			setIsPolling(false);
			return;
		}

		// Check if already expired based on expiration date
		if (options.expirationDate) {
			const expirationTime = new Date(options.expirationDate).getTime();
			if (Date.now() > expirationTime) {
				setIsExpired(true);
				setIsPolling(false);
				return;
			}
		}

		const interval = setInterval(async () => {
			pollCountRef.current += 1;

			// Check if max duration exceeded
			if (Date.now() - startTimeRef.current > MAX_POLLING_DURATION) {
				setIsPolling(false);
				clearInterval(interval);
				return;
			}

			// Check if expired based on expiration date
			if (options.expirationDate) {
				const expirationTime = new Date(options.expirationDate).getTime();
				if (Date.now() > expirationTime) {
					setIsExpired(true);
					setIsPolling(false);
					clearInterval(interval);
					return;
				}
			}

			const shouldStop = await checkStatus();
			if (shouldStop) {
				clearInterval(interval);
			}
		}, POLLING_INTERVAL);

		return () => {
			clearInterval(interval);
		};
	}, [isPaid, options.expirationDate, checkStatus]);

	return { isPaid, isExpired, isPolling };
};
