/**
 * Main gateway settings application component.
 *
 * @package PagBank_WooCommerce
 */

import { zodResolver } from "@hookform/resolvers/zod";
import { Button, Notice, Spinner } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useEffect, useMemo, useRef, useState } from "react";
import { useForm } from "react-hook-form";
import { TEXT_DOMAIN } from "@/constants";
import { ApplePaySettingsForm } from "./components/apple-pay";
import { BoletoSettingsForm } from "./components/boleto";
import { CreditCardSettingsForm } from "./components/credit-card";
import { DebitCardSettingsForm } from "./components/debit-card";
import { GooglePaySettingsForm } from "./components/google-pay";
import { PayWithPagBankSettingsForm } from "./components/pay-with-pagbank";
import { PixSettingsForm } from "./components/pix";
import { FormProvider } from "./context";
import { useGatewaySettingsMutation, useGatewaySettingsQuery } from "./hooks/useGatewaySettings";
import { useWooCommerceFormDisable } from "./hooks/useWooCommerceFormDisable";
import type { GatewayId, GatewaySettings } from "./schemas/settings";
import { getSchemaByGatewayId } from "./schemas/settings";

interface GatewaySettingsAppProps {
	gatewayId: GatewayId;
}

const GATEWAY_TITLES: Record<GatewayId, string> = {
	pagbank_credit_card: __("Cartão de Crédito", TEXT_DOMAIN),
	pagbank_debit_card: __("Cartão de Débito", TEXT_DOMAIN),
	pagbank_pix: __("Pix", TEXT_DOMAIN),
	pagbank_boleto: __("Boleto", TEXT_DOMAIN),
	pagbank_pay_with_pagbank: __("Pague com PagBank", TEXT_DOMAIN),
	pagbank_google_pay: __("Google Pay", TEXT_DOMAIN),
	pagbank_apple_pay: __("Apple Pay", TEXT_DOMAIN),
};

const GATEWAY_ICONS: Record<GatewayId, string> = {
	pagbank_credit_card: "card.png",
	pagbank_debit_card: "card.png",
	pagbank_pix: "pix.png",
	pagbank_boleto: "boleto.png",
	pagbank_pay_with_pagbank: "pagbank.png",
	pagbank_google_pay: "google-pay.png",
	pagbank_apple_pay: "apple-pay.png",
};

const ALL_GATEWAYS: GatewayId[] = [
	"pagbank_credit_card",
	"pagbank_debit_card",
	"pagbank_pix",
	"pagbank_boleto",
	"pagbank_pay_with_pagbank",
	"pagbank_google_pay",
	"pagbank_apple_pay",
];

export const GatewaySettingsApp = ({ gatewayId }: GatewaySettingsAppProps) => {
	const noticeRef = useRef<HTMLDivElement>(null);
	const [isInitialized, setIsInitialized] = useState(false);

	// TanStack Query hooks
	const { data, isLoading, error: fetchError } = useGatewaySettingsQuery(gatewayId);
	const mutation = useGatewaySettingsMutation(gatewayId);

	// Get the appropriate schema for validation
	const schema = getSchemaByGatewayId(gatewayId);

	// React Hook Form
	const form = useForm<GatewaySettings>({
		resolver: zodResolver(schema),
		defaultValues: data?.settings,
	});

	const { handleSubmit, reset, watch } = form;

	// Watch all form values to compute dirty state manually
	const watchedValues = watch();

	// Store the "clean" values (last saved or initial load)
	const [cleanValues, setCleanValues] = useState<GatewaySettings | null>(null);

	// Compute isDirty by comparing watched values with clean values
	const isDirty = useMemo(() => {
		if (!cleanValues) return false;
		// Get all unique keys from both objects
		const allKeys = new Set([...Object.keys(cleanValues), ...Object.keys(watchedValues)]);
		// Compare values for all keys
		for (const key of allKeys) {
			const cleanValue = cleanValues[key as keyof GatewaySettings] ?? "";
			const watchedValue = watchedValues[key as keyof GatewaySettings] ?? "";
			if (cleanValue !== watchedValue) {
				return true;
			}
		}
		return false;
	}, [watchedValues, cleanValues]);

	// Reset form only on initial data load
	useEffect(() => {
		if (data?.settings && !isInitialized) {
			reset(data.settings);
			setCleanValues(data.settings);
			setIsInitialized(true);
		}
	}, [data?.settings, reset, isInitialized]);

	// Disable WooCommerce's native form change detection
	useWooCommerceFormDisable(isDirty);

	// Scroll to error notice
	useEffect(() => {
		const error = fetchError || mutation.error;
		if (error && noticeRef.current) {
			noticeRef.current.scrollIntoView({ behavior: "smooth", block: "center" });
		}
	}, [fetchError, mutation.error]);

	const pluginUrl = window.pagbankSettings?.pluginUrl ?? "";
	const settingsUrl = window.pagbankSettings?.settingsUrl ?? "";
	const iconUrl = `${pluginUrl}/dist/images/icons/${GATEWAY_ICONS[gatewayId]}`;
	const logoUrl = `${pluginUrl}/dist/images/logos/logo-pagbank.png`;

	const onSubmit = handleSubmit(
		async (formData) => {
			if (!data) return;

			await mutation.mutateAsync({
				gatewayId,
				settings: formData,
				fieldTypes: data.fieldTypes,
				fieldDefaults: data.fieldDefaults,
			});

			// Update clean values to match saved data (resets isDirty)
			setCleanValues(formData);
		},
		(errors) => {
			// Log validation errors for debugging
			console.error("Form validation errors:", errors);
			console.error("Form values:", form.getValues());
		},
	);

	const handleReset = () => {
		if (cleanValues) {
			// Reset form to clean values
			reset(cleanValues);
		}
	};

	if (isLoading) {
		return (
			<div className="pagbank-gateway-settings pagbank-gateway-settings--loading">
				<Spinner />
				<span>{__("Carregando configurações...", TEXT_DOMAIN)}</span>
			</div>
		);
	}

	const error = fetchError || mutation.error;
	const errorMessage = error instanceof Error ? error.message : null;

	if (errorMessage && !data) {
		return (
			<div className="pagbank-gateway-settings pagbank-gateway-settings--error">
				<Notice status="error" isDismissible={false}>
					{errorMessage}
				</Notice>
			</div>
		);
	}

	if (!data) {
		return (
			<div className="pagbank-gateway-settings pagbank-gateway-settings--error">
				<Notice status="error" isDismissible={false}>
					{__("Não foi possível carregar as configurações.", TEXT_DOMAIN)}
				</Notice>
			</div>
		);
	}

	const renderGatewaySettings = () => {
		switch (gatewayId) {
			case "pagbank_credit_card":
				return <CreditCardSettingsForm />;
			case "pagbank_debit_card":
				return <DebitCardSettingsForm />;
			case "pagbank_pix":
				return <PixSettingsForm />;
			case "pagbank_boleto":
				return <BoletoSettingsForm />;
			case "pagbank_pay_with_pagbank":
				return <PayWithPagBankSettingsForm />;
			case "pagbank_google_pay":
				return <GooglePaySettingsForm />;
			case "pagbank_apple_pay":
				return <ApplePaySettingsForm />;
			default:
				return (
					<Notice status="error" isDismissible={false}>
						{__("Gateway não suportado.", TEXT_DOMAIN)}
					</Notice>
				);
		}
	};

	return (
		<FormProvider value={{ form, isSaving: mutation.isPending }}>
			<form className="pagbank-gateway-settings" onSubmit={onSubmit}>
				<div className="pagbank-gateway-settings__header">
					<div className="pagbank-gateway-settings__title-row">
						<img
							src={iconUrl}
							alt=""
							className="pagbank-gateway-settings__icon"
							width={32}
							height={32}
						/>
						<h1 className="pagbank-gateway-settings__title">
							{GATEWAY_TITLES[gatewayId]}
						</h1>
						<img
							src={logoUrl}
							alt="PagBank"
							className="pagbank-gateway-settings__logo"
							height={24}
						/>
					</div>
					{data.methodDescription && (
						<p className="pagbank-gateway-settings__subtitle">
							{data.methodDescription}
						</p>
					)}
					<nav className="pagbank-gateway-settings__nav">
						{ALL_GATEWAYS.map((id) => {
							const isCurrentGateway = id === gatewayId;
							if (isCurrentGateway) {
								return (
									<span
										key={id}
										className="pagbank-gateway-settings__nav-link pagbank-gateway-settings__nav-link--current"
									>
										{GATEWAY_TITLES[id]}
									</span>
								);
							}
							return (
								<a
									key={id}
									href={`${settingsUrl}${id}`}
									className="pagbank-gateway-settings__nav-link"
								>
									{GATEWAY_TITLES[id]}
								</a>
							);
						})}
					</nav>
				</div>

				{errorMessage && (
					<div ref={noticeRef}>
						<Notice
							status="error"
							isDismissible={false}
							className="pagbank-gateway-settings__notice"
						>
							{errorMessage}
						</Notice>
					</div>
				)}

				<div className="pagbank-gateway-settings__content">{renderGatewaySettings()}</div>

				<div className="pagbank-gateway-settings__actions">
					<Button
						type="submit"
						variant="primary"
						disabled={!isDirty || mutation.isPending}
						isBusy={mutation.isPending}
					>
						{mutation.isPending
							? __("Salvando...", TEXT_DOMAIN)
							: __("Salvar alterações", TEXT_DOMAIN)}
					</Button>

					{isDirty && (
						<Button
							variant="secondary"
							onClick={handleReset}
							disabled={mutation.isPending}
						>
							{__("Descartar alterações", TEXT_DOMAIN)}
						</Button>
					)}
				</div>
			</form>
		</FormProvider>
	);
};
