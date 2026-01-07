/**
 * Checkout PagBank gateway settings component.
 *
 * @package PagBank_WooCommerce
 */

import { __ } from "@wordpress/i18n";
import type { GatewaySettings } from "../../schemas/settings";
import { BaseGatewaySettings } from "../base";
import { SettingsCard } from "../common";
import { FormNumberStepper } from "../form";

export const CheckoutSettingsForm = () => {
	return (
		<BaseGatewaySettings>
			<SettingsCard title={__("Configurações do Checkout", "pagbank-for-woocommerce")}>
				<div className="pagbank-settings-field">
					<FormNumberStepper
						name={"expiration_minutes" as keyof GatewaySettings}
						label={__("Tempo de expiração", "pagbank-for-woocommerce")}
						help={__(
							"Tempo para o checkout expirar. Padrão: 120 minutos (2 horas). Máximo: 10080 minutos (7 dias).",
							"pagbank-for-woocommerce",
						)}
						min={1}
						max={10080}
						suffix={__("minutos", "pagbank-for-woocommerce")}
					/>
				</div>
			</SettingsCard>
		</BaseGatewaySettings>
	);
};
