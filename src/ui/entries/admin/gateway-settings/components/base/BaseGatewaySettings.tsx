/**
 * Base gateway settings component with common fields.
 *
 * @package PagBank_WooCommerce
 */

import { __ } from "@wordpress/i18n";
import { useWatch } from "react-hook-form";
import { useFormContext } from "../../context";
import type { Environment } from "../../schemas/settings";
import { SettingsCard } from "../common";
import { PagBankConnect } from "../connect";
import { FormInput, FormSelect, FormTextarea, FormToggle } from "../form";

interface BaseGatewaySettingsProps {
	children?: React.ReactNode;
}

const ENVIRONMENT_OPTIONS = [
	{ label: __("Sandbox (Testes)", "pagbank-for-woocommerce"), value: "sandbox" },
	{ label: __("Produção", "pagbank-for-woocommerce"), value: "production" },
];

export const BaseGatewaySettings = ({ children }: BaseGatewaySettingsProps) => {
	const { form } = useFormContext();
	const environment = useWatch({ control: form.control, name: "environment" }) as Environment;

	return (
		<>
			<SettingsCard title={__("Configurações Gerais", "pagbank-for-woocommerce")}>
				<div className="pagbank-settings-field">
					<FormToggle
						name="enabled"
						label={__("Ativar método de pagamento", "pagbank-for-woocommerce")}
						helpChecked={__(
							"Este método de pagamento está ativo.",
							"pagbank-for-woocommerce",
						)}
						helpUnchecked={__(
							"Este método de pagamento está desativado.",
							"pagbank-for-woocommerce",
						)}
					/>
				</div>

				<div className="pagbank-settings-field">
					<FormSelect
						name="environment"
						label={__("Ambiente", "pagbank-for-woocommerce")}
						options={ENVIRONMENT_OPTIONS}
						help={__(
							"Selecione o ambiente para processar os pagamentos.",
							"pagbank-for-woocommerce",
						)}
						fullWidth={false}
					/>
				</div>

				<div className="pagbank-settings-field">
					<PagBankConnect environment={environment} />
				</div>
			</SettingsCard>

			<SettingsCard title={__("Configurações de Exibição", "pagbank-for-woocommerce")}>
				<div className="pagbank-settings-field">
					<FormInput
						name="title"
						label={__("Título", "pagbank-for-woocommerce")}
						help={__(
							"Título exibido ao cliente durante o checkout.",
							"pagbank-for-woocommerce",
						)}
					/>
				</div>

				<div className="pagbank-settings-field">
					<FormTextarea
						name="description"
						label={__("Descrição", "pagbank-for-woocommerce")}
						help={__(
							"Descrição exibida ao cliente durante o checkout.",
							"pagbank-for-woocommerce",
						)}
					/>
				</div>
			</SettingsCard>

			{children}

			<SettingsCard title={__("Configurações Avançadas", "pagbank-for-woocommerce")}>
				<div className="pagbank-settings-field">
					<FormToggle
						name="logs_enabled"
						label={__("Habilitar logs", "pagbank-for-woocommerce")}
						help={__(
							"Registra eventos do gateway de pagamento para debug.",
							"pagbank-for-woocommerce",
						)}
					/>
				</div>
			</SettingsCard>
		</>
	);
};
