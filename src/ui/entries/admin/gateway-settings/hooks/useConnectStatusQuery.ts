/**
 * TanStack Query hook for PagBank connection status.
 *
 * @package PagBank_WooCommerce
 */

import { useQuery, useQueryClient } from "@tanstack/react-query";
import apiFetch from "@wordpress/api-fetch";
import { useCallback, useEffect } from "react";
import type { ConnectStatus, Environment } from "../schemas/settings";

// Query key factory
export const connectStatusKeys = {
	all: ["connect-status"] as const,
	byEnvironment: (environment: Environment) => [...connectStatusKeys.all, environment] as const,
};

interface ConnectStatusResponse {
	connected: boolean;
	account_id: string | null;
	environment: Environment;
	account: { email: string | null; name: string | null } | null;
	scopes: string[];
	missing_scopes: string[];
}

// Fetch connect status
const fetchConnectStatus = async (environment: Environment): Promise<ConnectStatus> => {
	const response = await apiFetch<ConnectStatusResponse>({
		path: `pagbank/v1/connect-status?environment=${environment}`,
	});

	return response;
};

// Hook for fetching connect status with auto-refresh on focus
export const useConnectStatusQuery = (environment: Environment) => {
	const queryClient = useQueryClient();

	const query = useQuery({
		queryKey: connectStatusKeys.byEnvironment(environment),
		queryFn: () => fetchConnectStatus(environment),
		staleTime: 60 * 1000, // Consider data fresh for 60 seconds
		refetchOnWindowFocus: true,
	});

	// Manual refresh function
	const refresh = useCallback(() => {
		queryClient.invalidateQueries({
			queryKey: connectStatusKeys.byEnvironment(environment),
		});
	}, [queryClient, environment]);

	// Refresh on visibility change (when tab becomes visible)
	useEffect(() => {
		const handleVisibilityChange = () => {
			if (document.visibilityState === "visible") {
				refresh();
			}
		};

		document.addEventListener("visibilitychange", handleVisibilityChange);

		return () => {
			document.removeEventListener("visibilitychange", handleVisibilityChange);
		};
	}, [refresh]);

	return {
		...query,
		refresh,
		// Convenience properties
		connected: query.data?.connected ?? false,
		account_id: query.data?.account_id ?? null,
		account: query.data?.account ?? null,
		scopes: query.data?.scopes ?? [],
		missing_scopes: query.data?.missing_scopes ?? [],
		isRefreshing: query.isFetching && !query.isLoading,
	};
};
