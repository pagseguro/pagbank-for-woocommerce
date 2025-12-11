/**
 * Hook for fetching and managing PagBank connection status.
 *
 * @package PagBank_WooCommerce
 */

import apiFetch from "@wordpress/api-fetch";
import { __ } from "@wordpress/i18n";
import { useCallback, useEffect, useState } from "react";
import { TEXT_DOMAIN } from "@/constants";
import type { AccountInfo, ConnectStatus, Environment } from "../types/settings";

interface UseConnectStatusReturn extends ConnectStatus {
	refresh: () => Promise<void>;
	error: string | null;
}

export const useConnectStatus = (environment: Environment): UseConnectStatusReturn => {
	const [status, setStatus] = useState<ConnectStatus>({
		connected: false,
		account_id: null,
		environment,
		isLoading: true,
		isRefreshing: false,
		account: null,
		missing_scopes: [],
		authentication_error: false,
	});
	const [error, setError] = useState<string | null>(null);
	const [hasLoadedOnce, setHasLoadedOnce] = useState(false);

	const refresh = useCallback(async () => {
		// Only show full loading state on initial load, otherwise show refresh indicator
		if (!hasLoadedOnce) {
			setStatus((prev) => ({ ...prev, isLoading: true }));
		} else {
			setStatus((prev) => ({ ...prev, isRefreshing: true }));
		}
		setError(null);

		try {
			const response = await apiFetch<{
				connected: boolean;
				account_id: string | null;
				environment: Environment;
				account: AccountInfo | null;
				missing_scopes: string[];
				authentication_error: boolean;
			}>({
				path: `pagbank/v1/connect-status?environment=${environment}`,
			});

			setStatus({
				connected: response.connected,
				account_id: response.account_id,
				environment: response.environment,
				isLoading: false,
				isRefreshing: false,
				account: response.account,
				missing_scopes: response.missing_scopes,
				authentication_error: response.authentication_error,
			});
			setHasLoadedOnce(true);
		} catch (err) {
			setError(
				err instanceof Error ? err.message : __("Erro ao verificar conexão", TEXT_DOMAIN),
			);
			setStatus((prev) => ({ ...prev, isLoading: false, isRefreshing: false }));
			setHasLoadedOnce(true);
		}
	}, [environment, hasLoadedOnce]);

	useEffect(() => {
		refresh();

		const handleFocus = () => {
			refresh();
		};

		const handleVisibilityChange = () => {
			if (document.visibilityState === "visible") {
				refresh();
			}
		};

		window.addEventListener("focus", handleFocus);
		document.addEventListener("visibilitychange", handleVisibilityChange);

		return () => {
			window.removeEventListener("focus", handleFocus);
			document.removeEventListener("visibilitychange", handleVisibilityChange);
		};
	}, [refresh]);

	return { ...status, refresh, error };
};
