/**
 * Main gateway settings application component.
 *
 * @package PagBank_WooCommerce
 */

import { Button, Notice, Spinner } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
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
	pagbank_pay_with_pagbank: __("Pay with PagBank", TEXT_DOMAIN),
};

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

	const handleSave = async () => {
		await saveSettings();
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
		<div className="pagbank-gateway-settings">
			<div className="pagbank-gateway-settings__header">
				<h1 className="pagbank-gateway-settings__title">
					{__("PagBank", TEXT_DOMAIN)} - {GATEWAY_TITLES[gatewayId]}
				</h1>
			</div>

			{error && (
				<Notice
					status="error"
					isDismissible={false}
					className="pagbank-gateway-settings__notice"
				>
					{error}
				</Notice>
			)}

			<div className="pagbank-gateway-settings__content">{renderGatewaySettings()}</div>

			<div className="pagbank-gateway-settings__actions">
				<Button
					variant="primary"
					onClick={handleSave}
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
		</div>
	);
};
