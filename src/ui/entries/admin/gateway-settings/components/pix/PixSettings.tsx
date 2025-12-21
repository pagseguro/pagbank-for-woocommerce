/**
 * Pix gateway settings component.
 *
 * @package PagBank_WooCommerce
 */

import { __ } from "@wordpress/i18n";
import type { GatewaySettings } from "../../schemas/settings";
import { BaseGatewaySettings } from "../base";
import { SettingsCard } from "../common";
import { FormNumberStepper } from "../form";

export const PixSettingsForm = () => {
	return (
		<BaseGatewaySettings>
			<SettingsCard title={__("Configurações do Pix", "pagbank-for-woocommerce")}>
				<div className="pagbank-settings-field">
					<FormNumberStepper
						name={"expiration_minutes" as keyof GatewaySettings}
						label={__("Tempo de expiração", "pagbank-for-woocommerce")}
						help={__(
							"Tempo para o QR Code do Pix expirar. Mínimo: 1 minuto.",
							"pagbank-for-woocommerce",
						)}
						min={1}
						max={1440}
						suffix={__("minutos", "pagbank-for-woocommerce")}
					/>
				</div>
			</SettingsCard>
		</BaseGatewaySettings>
	);
};
