/**
 * Main gateway settings application component.
 *
 * @package PagBank_WooCommerce
 */

import { Button, Notice, Spinner } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useEffect, useRef } from "react";
import { TEXT_DOMAIN } from "@/constants";
import { BoletoSettings as BoletoSettingsComponent } from "./components/boleto";
import { CreditCardSettings as CreditCardSettingsComponent } from "./components/credit-card";
import { DebitCardSettings as DebitCardSettingsComponent } from "./components/debit-card";
import { PayWithPagBankSettings as PayWithPagBankSettingsComponent } from "./components/pay-with-pagbank";
import { PixSettings as PixSettingsComponent } from "./components/pix";
import { useSettings } from "./hooks/useSettings";
import type {
	BoletoSettings,
	CreditCardSettings as CreditCardSettingsType,
	DebitCardSettings,
	GatewayId,
	PayWithPagBankSettings,
	PixSettings,
} from "./types/settings";

interface GatewaySettingsAppProps {
	gatewayId: GatewayId;
}

const GATEWAY_TITLES: Record<GatewayId, string> = {
	pagbank_credit_card: __("Cartão de Crédito", TEXT_DOMAIN),
	pagbank_debit_card: __("Cartão de Débito", TEXT_DOMAIN),
	pagbank_pix: __("Pix", TEXT_DOMAIN),
	pagbank_boleto: __("Boleto", TEXT_DOMAIN),
	pagbank_pay_with_pagbank: __("Pague com PagBank", TEXT_DOMAIN),
};

const GATEWAY_ICONS: Record<GatewayId, string> = {
	pagbank_credit_card: "card.png",
	pagbank_debit_card: "card.png",
	pagbank_pix: "pix.png",
	pagbank_boleto: "boleto.png",
	pagbank_pay_with_pagbank: "pagbank.png",
};

const ALL_GATEWAYS: GatewayId[] = [
	"pagbank_credit_card",
	"pagbank_debit_card",
	"pagbank_pix",
	"pagbank_boleto",
	"pagbank_pay_with_pagbank",
];

export const GatewaySettingsApp = ({ gatewayId }: GatewaySettingsAppProps) => {
	const {
		settings,
		isLoading,
		isSaving,
		isDirty,
		error,
		updateSetting,
		saveSettings,
		resetSettings,
	} = useSettings(gatewayId);

	const noticeRef = useRef<HTMLDivElement>(null);

	const pluginUrl = window.pagbankSettings?.pluginUrl ?? "";
	const settingsUrl = window.pagbankSettings?.settingsUrl ?? "";
	const iconUrl = `${pluginUrl}/dist/images/icons/${GATEWAY_ICONS[gatewayId]}`;
	const logoUrl = `${pluginUrl}/dist/images/logos/logo-pagbank.png`;

	useEffect(() => {
		if (error && noticeRef.current) {
			noticeRef.current.scrollIntoView({ behavior: "smooth", block: "center" });
		}
	}, [error]);

	const handleSave = async () => {
		await saveSettings();
	};

	const handleSubmit = (e: React.FormEvent) => {
		e.preventDefault();
		if (isDirty && !isSaving) {
			handleSave();
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

	if (error && !settings) {
		return (
			<div className="pagbank-gateway-settings pagbank-gateway-settings--error">
				<Notice status="error" isDismissible={false}>
					{error}
				</Notice>
			</div>
		);
	}

	if (!settings) {
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
				return (
					<CreditCardSettingsComponent
						settings={settings as CreditCardSettingsType}
						onSettingChange={
							updateSetting as (
								key: keyof CreditCardSettingsType,
								value: CreditCardSettingsType[keyof CreditCardSettingsType],
							) => void
						}
						disabled={isSaving}
					/>
				);

			case "pagbank_debit_card":
				return (
					<DebitCardSettingsComponent
						settings={settings as DebitCardSettings}
						onSettingChange={
							updateSetting as (
								key: keyof DebitCardSettings,
								value: DebitCardSettings[keyof DebitCardSettings],
							) => void
						}
						disabled={isSaving}
					/>
				);

			case "pagbank_pix":
				return (
					<PixSettingsComponent
						settings={settings as PixSettings}
						onSettingChange={
							updateSetting as (
								key: keyof PixSettings,
								value: PixSettings[keyof PixSettings],
							) => void
						}
						disabled={isSaving}
					/>
				);

			case "pagbank_boleto":
				return (
					<BoletoSettingsComponent
						settings={settings as BoletoSettings}
						onSettingChange={
							updateSetting as (
								key: keyof BoletoSettings,
								value: BoletoSettings[keyof BoletoSettings],
							) => void
						}
						disabled={isSaving}
					/>
				);

			case "pagbank_pay_with_pagbank":
				return (
					<PayWithPagBankSettingsComponent
						settings={settings as PayWithPagBankSettings}
						onSettingChange={
							updateSetting as (
								key: keyof PayWithPagBankSettings,
								value: PayWithPagBankSettings[keyof PayWithPagBankSettings],
							) => void
						}
						disabled={isSaving}
					/>
				);

			default:
				return (
					<Notice status="error" isDismissible={false}>
						{__("Gateway não suportado.", TEXT_DOMAIN)}
					</Notice>
				);
		}
	};

	return (
		<form className="pagbank-gateway-settings" onSubmit={handleSubmit}>
			<div className="pagbank-gateway-settings__header">
				<div className="pagbank-gateway-settings__title-row">
					<img
						src={iconUrl}
						alt=""
						className="pagbank-gateway-settings__icon"
						width={32}
						height={32}
					/>
					<h1 className="pagbank-gateway-settings__title">{GATEWAY_TITLES[gatewayId]}</h1>
					<img
						src={logoUrl}
						alt="PagBank"
						className="pagbank-gateway-settings__logo"
						height={24}
					/>
				</div>
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

			{error && (
				<div ref={noticeRef}>
					<Notice
						status="error"
						isDismissible={false}
						className="pagbank-gateway-settings__notice"
					>
						{error}
					</Notice>
				</div>
			)}

			<div className="pagbank-gateway-settings__content">{renderGatewaySettings()}</div>

			<div className="pagbank-gateway-settings__actions">
				<Button
					type="submit"
					variant="primary"
					disabled={!isDirty || isSaving}
					isBusy={isSaving}
				>
					{isSaving
						? __("Salvando...", TEXT_DOMAIN)
						: __("Salvar alterações", TEXT_DOMAIN)}
				</Button>

				{isDirty && (
					<Button variant="secondary" onClick={resetSettings} disabled={isSaving}>
						{__("Descartar alterações", TEXT_DOMAIN)}
					</Button>
				)}
			</div>
		</form>
	);
};
