/**
 * Boleto gateway settings component.
 *
 * @package PagBank_WooCommerce
 */

import { __ } from "@wordpress/i18n";
import { TEXT_DOMAIN } from "@/constants";
import type { GatewaySettings } from "../../schemas/settings";
import { BaseGatewaySettings } from "../base";
import { SettingsCard } from "../common";
import { FormInput } from "../form";

export const BoletoSettingsForm = () => {
	return (
		<BaseGatewaySettings>
			<SettingsCard title={__("Configurações do Boleto", TEXT_DOMAIN)}>
				<div className="pagbank-settings-field">
					<FormInput
						name={"expiration_days" as keyof GatewaySettings}
						label={__("Dias para vencimento", TEXT_DOMAIN)}
						help={__(
							"Número de dias para o vencimento do boleto a partir da data de emissão.",
							TEXT_DOMAIN,
						)}
					/>
				</div>
			</SettingsCard>
		</BaseGatewaySettings>
	);
};
