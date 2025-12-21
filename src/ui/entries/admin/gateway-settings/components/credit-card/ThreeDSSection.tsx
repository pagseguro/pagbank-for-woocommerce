/**
 * 3DS section component for credit card settings.
 *
 * @package PagBank_WooCommerce
 */

import { __ } from "@wordpress/i18n";
import type { GatewaySettings } from "../../schemas/settings";
import { SettingsCard } from "../common";
import { FormToggle } from "../form";

export const ThreeDSSection = () => {
	return (
		<SettingsCard title={__("Autenticação 3DS", "pagbank-for-woocommerce")}>
			<div className="pagbank-settings-field">
				<FormToggle
					name={"threeds_enabled" as keyof GatewaySettings}
					label={__("Habilitar autenticação 3DS", "pagbank-for-woocommerce")}
					help={__(
						"3DS (3D Secure) adiciona uma camada extra de segurança nas transações com cartão de crédito.",
						"pagbank-for-woocommerce",
					)}
				/>
			</div>
		</SettingsCard>
	);
};
