/**
 * Base gateway settings component with common fields.
 *
 * @package PagBank_WooCommerce
 */

import { __ } from "@wordpress/i18n";
import { TEXT_DOMAIN } from "@/constants";
import type { BaseGatewaySettings as BaseSettings, Environment, YesNo } from "../../types/settings";
import {
	DescriptionTextarea,
	EnabledToggle,
	EnvironmentSelect,
	LogsToggle,
	SettingsCard,
	TitleInput,
} from "../common";
import { PagBankConnect } from "../connect";

interface BaseGatewaySettingsProps<T extends BaseSettings> {
	settings: T;
	onSettingChange: (key: keyof T, value: T[keyof T]) => void;
	disabled?: boolean;
	children?: React.ReactNode;
}

export const BaseGatewaySettings = <T extends BaseSettings>({
	settings,
	onSettingChange,
	disabled = false,
	children,
}: BaseGatewaySettingsProps<T>) => {
	return (
		<>
			<SettingsCard title={__("Configurações Gerais", TEXT_DOMAIN)}>
				<div className="pagbank-settings-field">
					<EnabledToggle
						value={settings.enabled}
						onChange={(value: YesNo) =>
							onSettingChange("enabled" as keyof T, value as T[keyof T])
						}
						disabled={disabled}
					/>
				</div>

				<div className="pagbank-settings-field">
					<EnvironmentSelect
						value={settings.environment}
						onChange={(value: Environment) =>
							onSettingChange("environment" as keyof T, value as T[keyof T])
						}
						disabled={disabled}
					/>
				</div>

				<div className="pagbank-settings-field">
					<PagBankConnect environment={settings.environment} />
				</div>
			</SettingsCard>

			<SettingsCard title={__("Configurações de Exibição", TEXT_DOMAIN)}>
				<div className="pagbank-settings-field">
					<TitleInput
						value={settings.title}
						onChange={(value) =>
							onSettingChange("title" as keyof T, value as T[keyof T])
						}
						disabled={disabled}
					/>
				</div>

				<div className="pagbank-settings-field">
					<DescriptionTextarea
						value={settings.description}
						onChange={(value) =>
							onSettingChange("description" as keyof T, value as T[keyof T])
						}
						disabled={disabled}
					/>
				</div>
			</SettingsCard>

			{children}

			<SettingsCard title={__("Configurações Avançadas", TEXT_DOMAIN)}>
				<div className="pagbank-settings-field">
					<LogsToggle
						value={settings.logs_enabled}
						onChange={(value: YesNo) =>
							onSettingChange("logs_enabled" as keyof T, value as T[keyof T])
						}
						disabled={disabled}
					/>
				</div>
			</SettingsCard>
		</>
	);
};
