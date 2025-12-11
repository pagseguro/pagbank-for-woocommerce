/**
 * Pix gateway settings component.
 *
 * @package PagBank_WooCommerce
 */

import { TextControl } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { TEXT_DOMAIN } from "@/constants";
import type { PixSettings as PixSettingsType } from "../../types/settings";
import { BaseGatewaySettings } from "../base";
import { SettingsCard } from "../common";

interface PixSettingsProps {
	settings: PixSettingsType;
	onSettingChange: (
		key: keyof PixSettingsType,
		value: PixSettingsType[keyof PixSettingsType],
	) => void;
	disabled?: boolean;
}

export const PixSettings = ({ settings, onSettingChange, disabled = false }: PixSettingsProps) => {
	return (
		<BaseGatewaySettings<PixSettingsType>
			settings={settings}
			onSettingChange={onSettingChange}
			disabled={disabled}
		>
			<SettingsCard title={__("Configurações do Pix", TEXT_DOMAIN)}>
				<div className="pagbank-settings-field">
					<TextControl
						__nextHasNoMarginBottom
						label={__("Tempo de expiração (minutos)", TEXT_DOMAIN)}
						type="number"
						value={settings.expiration_minutes}
						onChange={(value) => onSettingChange("expiration_minutes", value)}
						help={__(
							"Tempo em minutos para o QR Code do Pix expirar. Mínimo: 1 minuto.",
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
