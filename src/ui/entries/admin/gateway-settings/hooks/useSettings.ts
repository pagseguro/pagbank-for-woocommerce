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

interface WCGatewaySetting {
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

interface WCGatewayResponse {
	id: string;
	title: string;
	description: string;
	order: number;
	enabled: boolean;
	method_title: string;
	method_description: string;
	method_supports: string[];
	settings: Record<string, WCGatewaySetting>;
}

// Field types that should not be saved via REST API (custom/display-only fields)
const EXCLUDED_FIELD_TYPES = ["pagbank_connect"];

// Field names that should not be saved via REST API
const EXCLUDED_FIELD_NAMES = ["pagbank_connect"];

interface UseSettingsReturn {
	settings: GatewaySettings | null;
	methodDescription: string | null;
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
	const [methodDescription, setMethodDescription] = useState<string | null>(null);
	const [fieldTypes, setFieldTypes] = useState<Record<string, string>>({});
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

			const extractedSettings: Record<string, string> = {};
			const extractedFieldTypes: Record<string, string> = {};

			for (const [key, field] of Object.entries(response.settings)) {
				extractedSettings[key] = field.value;
				extractedFieldTypes[key] = field.type;
			}

			setSettings(extractedSettings as unknown as GatewaySettings);
			setOriginalSettings(extractedSettings as unknown as GatewaySettings);
			setMethodDescription(response.method_description);
			setFieldTypes(extractedFieldTypes);
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

	// Manage beforeunload warning for unsaved changes
	useEffect(() => {
		const handleBeforeUnload = (e: BeforeUnloadEvent) => {
			if (isDirty) {
				e.preventDefault();
				e.returnValue = "";
				return "";
			}
		};

		window.addEventListener("beforeunload", handleBeforeUnload);

		return () => {
			window.removeEventListener("beforeunload", handleBeforeUnload);
		};
	}, [isDirty]);

	// Disable WooCommerce's native form change detection on mount
	useEffect(() => {
		// WooCommerce uses jQuery to detect form changes via wc_admin_meta_boxes.changed
		// We need to disable this since we handle our own dirty state
		const disableWcChangeDetection = () => {
			// eslint-disable-next-line @typescript-eslint/no-explicit-any
			const wc = (window as any).woocommerce_admin;
			if (wc) {
				wc.unsaved_changes = false;
			}

			// Also try to unbind the beforeunload from jQuery
			// eslint-disable-next-line @typescript-eslint/no-explicit-any
			const $ = (window as any).jQuery;
			if ($) {
				$(window).off("beforeunload.woocommerce");
			}
		};

		disableWcChangeDetection();

		// Re-run periodically in case WooCommerce re-attaches
		const interval = setInterval(disableWcChangeDetection, 1000);

		return () => {
			clearInterval(interval);
		};
	}, []);

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
			// Filter out fields that should not be saved via REST API
			const filteredSettings: Record<string, string> = {};
			for (const [key, value] of Object.entries(settings)) {
				// Skip excluded field names
				if (EXCLUDED_FIELD_NAMES.includes(key)) {
					continue;
				}
				// Skip excluded field types
				const fieldType = fieldTypes[key];
				if (fieldType && EXCLUDED_FIELD_TYPES.includes(fieldType)) {
					continue;
				}
				filteredSettings[key] = value;
			}

			// WooCommerce REST API expects settings as simple key-value pairs
			await apiFetch<WCGatewayResponse>({
				path: `/wc/v3/payment_gateways/${gatewayId}`,
				method: "PUT",
				data: {
					settings: filteredSettings,
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
	}, [gatewayId, settings, fieldTypes]);

	const resetSettings = useCallback(() => {
		setSettings(originalSettings);
	}, [originalSettings]);

	return {
		settings,
		methodDescription,
		isLoading,
		isSaving,
		isDirty,
		error,
		updateSetting,
		saveSettings,
		resetSettings,
	};
};
