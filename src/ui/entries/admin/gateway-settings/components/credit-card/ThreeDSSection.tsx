/**
 * 3DS section component for credit card settings.
 *
 * @package PagBank_WooCommerce
 */

import { __ } from "@wordpress/i18n";
import { useWatch } from "react-hook-form";
import { TEXT_DOMAIN } from "@/constants";
import { useFormContext } from "../../context";
import type { GatewaySettings, YesNo } from "../../schemas/settings";
import { AnimatedField, SettingsCard } from "../common";
import { FormToggle } from "../form";

export const ThreeDSSection = () => {
	const { form } = useFormContext();

	const threedsEnabled = useWatch({
		control: form.control,
		name: "threeds_enabled" as keyof GatewaySettings,
	}) as YesNo;

	const isThreedsEnabled = threedsEnabled === "yes";

	return (
		<SettingsCard title={__("Autenticação 3DS", TEXT_DOMAIN)}>
			<div className="pagbank-settings-field">
				<FormToggle
					name={"threeds_enabled" as keyof GatewaySettings}
					label={__("Habilitar autenticação 3DS", TEXT_DOMAIN)}
					help={__(
						"3DS (3D Secure) adiciona uma camada extra de segurança nas transações com cartão de crédito.",
						TEXT_DOMAIN,
					)}
				/>
			</div>

			<AnimatedField visible={isThreedsEnabled}>
				<div className="pagbank-settings-field">
					<FormToggle
						name={"threeds_for_saved_cards" as keyof GatewaySettings}
						label={__("3DS para cartões salvos", TEXT_DOMAIN)}
						help={__(
							"Quando ativado, exige autenticação 3DS também para cartões salvos.",
							TEXT_DOMAIN,
						)}
					/>
				</div>
			</AnimatedField>
		</SettingsCard>
	);
};
