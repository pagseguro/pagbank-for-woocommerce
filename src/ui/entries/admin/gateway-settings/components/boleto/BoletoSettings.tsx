/**
 * Boleto gateway settings component.
 *
 * @package PagBank_WooCommerce
 */

import { TextControl } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { TEXT_DOMAIN } from "@/constants";
import type { BoletoSettings as BoletoSettingsType } from "../../types/settings";
import { BaseGatewaySettings } from "../base";
import { SettingsCard } from "../common";

interface BoletoSettingsProps {
	settings: BoletoSettingsType;
	onSettingChange: (
		key: keyof BoletoSettingsType,
		value: BoletoSettingsType[keyof BoletoSettingsType],
	) => void;
	disabled?: boolean;
}

export const BoletoSettings = ({
	settings,
	onSettingChange,
	disabled = false,
}: BoletoSettingsProps) => {
	return (
		<BaseGatewaySettings<BoletoSettingsType>
			settings={settings}
			onSettingChange={onSettingChange}
			disabled={disabled}
		>
			<SettingsCard title={__("Configurações do Boleto", TEXT_DOMAIN)}>
				<div className="pagbank-settings-field">
					<TextControl
						__nextHasNoMarginBottom
						label={__("Dias para vencimento", TEXT_DOMAIN)}
						type="number"
						value={settings.expiration_days}
						onChange={(value) => onSettingChange("expiration_days", value)}
						help={__(
							"Número de dias para o vencimento do boleto a partir da data de emissão.",
							TEXT_DOMAIN,
						)}
						min={1}
						disabled={disabled}
					/>
				</div>
			</SettingsCard>
		</BaseGatewaySettings>
	);
};
