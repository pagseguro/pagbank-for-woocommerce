/**
 * Hook for fetching and saving gateway settings.
 *
 * @package PagBank_WooCommerce
 */

import apiFetch from "@wordpress/api-fetch";
import { __ } from "@wordpress/i18n";
import { useCallback, useEffect, useState } from "react";
import { TEXT_DOMAIN } from "@/constants";
import type { GatewayId, GatewaySettings } from "../types/settings";

interface WCGatewayResponse {
	id: string;
	title: string;
	description: string;
	order: number;
	enabled: boolean;
	method_title: string;
	method_description: string;
	method_supports: string[];
	settings: Record<
		string,
		{
			id: string;
			label: string;
			description: string;
			type: string;
			value: string;
			default: string;
			tip: string;
			placeholder: string;
			options?: Record<string, string>;
		}
	>;
}

interface UseSettingsReturn {
	settings: GatewaySettings | null;
	isLoading: boolean;
	isSaving: boolean;
	isDirty: boolean;
	error: string | null;
	updateSetting: (
		key: keyof GatewaySettings,
		value: GatewaySettings[keyof GatewaySettings],
	) => void;
	saveSettings: () => Promise<boolean>;
	resetSettings: () => void;
}

export const useSettings = (gatewayId: GatewayId): UseSettingsReturn => {
	const [settings, setSettings] = useState<GatewaySettings | null>(null);
	const [originalSettings, setOriginalSettings] = useState<GatewaySettings | null>(null);
	const [isLoading, setIsLoading] = useState(true);
	const [isSaving, setIsSaving] = useState(false);
	const [error, setError] = useState<string | null>(null);

	const isDirty =
		settings !== null && JSON.stringify(settings) !== JSON.stringify(originalSettings);

	const fetchSettings = useCallback(async () => {
		setIsLoading(true);
		setError(null);

		try {
			const response = await apiFetch<WCGatewayResponse>({
				path: `/wc/v3/payment_gateways/${gatewayId}`,
			});

			const extractedSettings = Object.entries(response.settings).reduce(
				(acc, [key, field]) => {
					acc[key] = field.value;
					return acc;
				},
				{} as Record<string, string>,
			) as unknown as GatewaySettings;

			setSettings(extractedSettings);
			setOriginalSettings(extractedSettings);
		} catch (err) {
			setError(
				err instanceof Error
					? err.message
					: __("Erro ao carregar configurações", TEXT_DOMAIN),
			);
		} finally {
			setIsLoading(false);
		}
	}, [gatewayId]);

	useEffect(() => {
		fetchSettings();
	}, [fetchSettings]);

	const updateSetting = useCallback(
		(key: keyof GatewaySettings, value: GatewaySettings[keyof GatewaySettings]) => {
			setSettings((prev) => {
				if (!prev) return prev;
				return { ...prev, [key]: value };
			});
		},
		[],
	);

	const saveSettings = useCallback(async (): Promise<boolean> => {
		if (!settings) return false;

		setIsSaving(true);
		setError(null);

		try {
			const settingsPayload = Object.entries(settings).reduce(
				(acc, [key, value]) => {
					acc[key] = { value };
					return acc;
				},
				{} as Record<string, { value: string }>,
			);

			await apiFetch<WCGatewayResponse>({
				path: `/wc/v3/payment_gateways/${gatewayId}`,
				method: "PUT",
				data: {
					settings: settingsPayload,
				},
			});

			setOriginalSettings(settings);
			return true;
		} catch (err) {
			setError(err instanceof Error ? err.message : "Erro ao salvar configurações");
			return false;
		} finally {
			setIsSaving(false);
		}
	}, [gatewayId, settings]);

	const resetSettings = useCallback(() => {
		setSettings(originalSettings);
	}, [originalSettings]);

	return {
		settings,
		isLoading,
		isSaving,
		isDirty,
		error,
		updateSetting,
		saveSettings,
		resetSettings,
	};
};
