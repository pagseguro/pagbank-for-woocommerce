/**
 * TanStack Query hook for gateway settings.
 *
 * @package PagBank_WooCommerce
 */

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import apiFetch from "@wordpress/api-fetch";
import type {
	GatewayId,
	GatewaySettings,
	WCGatewayResponse,
	WCGatewaySetting,
} from "../schemas/settings";
import { getSubmissionSchemaByGatewayId } from "../schemas/settings";

// Field types that should not be saved via REST API (custom/display-only fields)
const EXCLUDED_FIELD_TYPES = ["pagbank_connect"];

// Field names that should not be saved via REST API
const EXCLUDED_FIELD_NAMES = ["pagbank_connect"];

interface GatewayData {
	settings: GatewaySettings;
	methodDescription: string;
	methodTitle: string;
	icon: string;
	fieldTypes: Record<string, string>;
	fieldDefaults: Record<string, string>;
}

interface SaveSettingsParams {
	gatewayId: GatewayId;
	settings: GatewaySettings;
	fieldTypes: Record<string, string>;
	fieldDefaults: Record<string, string>;
}

// Query key factory
export const gatewaySettingsKeys = {
	all: ["gateway-settings"] as const,
	detail: (gatewayId: GatewayId) => [...gatewaySettingsKeys.all, gatewayId] as const,
};

// Fetch gateway settings
const fetchGatewaySettings = async (gatewayId: GatewayId): Promise<GatewayData> => {
	const response = await apiFetch<WCGatewayResponse>({
		path: `/wc/v3/payment_gateways/${gatewayId}`,
	});

	const settings: Record<string, string> = {};
	const fieldTypes: Record<string, string> = {};
	const fieldDefaults: Record<string, string> = {};

	for (const [key, field] of Object.entries(response.settings) as [string, WCGatewaySetting][]) {
		settings[key] = field.value;
		fieldTypes[key] = field.type;
		fieldDefaults[key] = field.default;
	}

	// WooCommerce stores some fields at the gateway level, not in settings
	// We need to include them in our settings object
	if (!settings.title && response.title) {
		settings.title = response.title;
	}
	if (!settings.description && response.description) {
		settings.description = response.description;
	}
	// enabled comes as boolean from API, convert to yes/no
	if (!settings.enabled) {
		settings.enabled = response.enabled ? "yes" : "no";
	}

	return {
		settings: settings as unknown as GatewaySettings,
		methodDescription: response.method_description,
		methodTitle: response.method_title,
		icon: response.icon ?? "",
		fieldTypes,
		fieldDefaults,
	};
};

// Fields that WooCommerce expects at root level, not in settings
const ROOT_LEVEL_FIELDS = ["title", "description", "enabled"];

// Save gateway settings
const saveGatewaySettings = async ({
	gatewayId,
	settings,
	fieldTypes,
	fieldDefaults,
}: SaveSettingsParams): Promise<WCGatewayResponse> => {
	// Normalize settings through submission schema (resets hidden fields to defaults)
	const submissionSchema = getSubmissionSchemaByGatewayId(gatewayId);
	const normalizedSettings = submissionSchema.parse(settings);

	// Filter out fields that should not be saved via REST API
	const filteredSettings: Record<string, string> = {};
	const rootLevelData: Record<string, string | boolean> = {};

	for (const [key, value] of Object.entries(normalizedSettings) as [string, string][]) {
		// Skip excluded field names
		if (EXCLUDED_FIELD_NAMES.includes(key)) {
			continue;
		}
		// Skip excluded field types
		const fieldType = fieldTypes[key];
		if (fieldType && EXCLUDED_FIELD_TYPES.includes(fieldType)) {
			continue;
		}

		// Handle root level fields separately
		if (ROOT_LEVEL_FIELDS.includes(key)) {
			if (key === "enabled") {
				rootLevelData[key] = value === "yes";
			} else {
				rootLevelData[key] = value;
			}
			continue;
		}

		// Use default value if field is empty (required for select fields)
		const fieldDefault = fieldDefaults[key];
		filteredSettings[key] =
			value === "" && fieldDefault != null && fieldDefault !== "" ? fieldDefault : value;
	}

	return apiFetch<WCGatewayResponse>({
		path: `/wc/v3/payment_gateways/${gatewayId}`,
		method: "PUT",
		data: {
			...rootLevelData,
			settings: filteredSettings,
		},
	});
};

// Hook for fetching gateway settings
export const useGatewaySettingsQuery = (gatewayId: GatewayId) => {
	return useQuery({
		queryKey: gatewaySettingsKeys.detail(gatewayId),
		queryFn: () => fetchGatewaySettings(gatewayId),
		staleTime: 30 * 1000, // Consider data fresh for 30 seconds
	});
};

// Hook for saving gateway settings
export const useGatewaySettingsMutation = (gatewayId: GatewayId) => {
	const queryClient = useQueryClient();

	return useMutation({
		mutationFn: saveGatewaySettings,
		onSuccess: (_, variables) => {
			// Update the cache with the new settings
			queryClient.setQueryData(
				gatewaySettingsKeys.detail(gatewayId),
				(old: GatewayData | undefined) => {
					if (!old) return old;
					return {
						...old,
						settings: variables.settings,
					};
				},
			);
		},
	});
};
