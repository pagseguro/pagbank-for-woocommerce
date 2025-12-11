/**
 * 3DS section component for credit card settings.
 *
 * @package PagBank_WooCommerce
 */

import { ToggleControl } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { TEXT_DOMAIN } from "@/constants";
import type { CreditCardSettings } from "../../types/settings";
import { AnimatedField, SettingsCard } from "../common";

interface ThreeDSSectionProps {
	settings: Pick<
		CreditCardSettings,
		"threeds_enabled" | "threeds_allow_continue" | "threeds_for_saved_cards"
	>;
	onSettingChange: (
		key: keyof CreditCardSettings,
		value: CreditCardSettings[keyof CreditCardSettings],
	) => void;
	disabled?: boolean;
}

export const ThreeDSSection = ({
	settings,
	onSettingChange,
	disabled = false,
}: ThreeDSSectionProps) => {
	const threedsEnabled = settings.threeds_enabled === "yes";

	return (
		<SettingsCard title={__("Autenticação 3DS", TEXT_DOMAIN)}>
			<div className="pagbank-settings-field">
				<ToggleControl
					__nextHasNoMarginBottom
					label={__("Habilitar autenticação 3DS", TEXT_DOMAIN)}
					help={__(
						"3DS (3D Secure) adiciona uma camada extra de segurança nas transações com cartão de crédito.",
						TEXT_DOMAIN,
					)}
					checked={threedsEnabled}
					onChange={(checked) =>
						onSettingChange("threeds_enabled", checked ? "yes" : "no")
					}
					disabled={disabled}
				/>
			</div>

			<AnimatedField visible={threedsEnabled}>
				<div className="pagbank-settings-field">
					<ToggleControl
						__nextHasNoMarginBottom
						label={__("Permitir continuar sem autenticação", TEXT_DOMAIN)}
						help={__(
							"Quando ativado, permite que a transação continue mesmo quando a autenticação 3DS não estiver disponível.",
							TEXT_DOMAIN,
						)}
						checked={settings.threeds_allow_continue === "yes"}
						onChange={(checked) =>
							onSettingChange("threeds_allow_continue", checked ? "yes" : "no")
						}
						disabled={disabled}
					/>
				</div>

				<div className="pagbank-settings-field">
					<ToggleControl
						__nextHasNoMarginBottom
						label={__("3DS para cartões salvos", TEXT_DOMAIN)}
						help={__(
							"Quando ativado, exige autenticação 3DS também para cartões salvos.",
							TEXT_DOMAIN,
						)}
						checked={settings.threeds_for_saved_cards === "yes"}
						onChange={(checked) =>
							onSettingChange("threeds_for_saved_cards", checked ? "yes" : "no")
						}
						disabled={disabled}
					/>
				</div>
			</AnimatedField>
		</SettingsCard>
	);
};
