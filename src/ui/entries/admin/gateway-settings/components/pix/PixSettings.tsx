/**
 * Pix gateway settings component.
 *
 * @package PagBank_WooCommerce
 */

import { __ } from "@wordpress/i18n";
import { TEXT_DOMAIN } from "@/constants";
import type { GatewaySettings } from "../../schemas/settings";
import { BaseGatewaySettings } from "../base";
import { SettingsCard } from "../common";
import { FormInput } from "../form";

export const PixSettingsForm = () => {
	return (
		<BaseGatewaySettings>
			<SettingsCard title={__("Configurações do Pix", TEXT_DOMAIN)}>
				<div className="pagbank-settings-field">
					<FormInput
						name={"expiration_minutes" as keyof GatewaySettings}
						label={__("Tempo de expiração (minutos)", TEXT_DOMAIN)}
						help={__(
							"Tempo em minutos para o QR Code do Pix expirar. Mínimo: 1 minuto.",
							TEXT_DOMAIN,
						)}
					/>
				</div>
			</SettingsCard>
		</BaseGatewaySettings>
	);
};
