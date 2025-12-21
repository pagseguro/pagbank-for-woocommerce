/**
 * Boleto gateway settings component.
 *
 * @package PagBank_WooCommerce
 */

import { __ } from "@wordpress/i18n";
import type { GatewaySettings } from "../../schemas/settings";
import { BaseGatewaySettings } from "../base";
import { SettingsCard } from "../common";
import { FormNumberStepper } from "../form";

export const BoletoSettingsForm = () => {
	return (
		<BaseGatewaySettings>
			<SettingsCard title={__("Configurações do Boleto", "pagbank-for-woocommerce")}>
				<div className="pagbank-settings-field">
					<FormNumberStepper
						name={"expiration_days" as keyof GatewaySettings}
						label={__("Dias para vencimento", "pagbank-for-woocommerce")}
						help={__(
							"Número de dias para o vencimento do boleto a partir da data de emissão.",
							"pagbank-for-woocommerce",
						)}
						min={1}
						max={180}
						suffix={__("dias", "pagbank-for-woocommerce")}
					/>
				</div>
			</SettingsCard>
		</BaseGatewaySettings>
	);
};
