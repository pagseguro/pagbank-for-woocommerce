/**
 * Base gateway settings component with common fields.
 *
 * @package PagBank_WooCommerce
 */

import { __ } from "@wordpress/i18n";
import { useWatch } from "react-hook-form";
import { TEXT_DOMAIN } from "@/constants";
import { useFormContext } from "../../context";
import type { Environment } from "../../schemas/settings";
import { SettingsCard } from "../common";
import { PagBankConnect } from "../connect";
import { FormInput, FormSelect, FormTextarea, FormToggle } from "../form";

interface BaseGatewaySettingsProps {
	children?: React.ReactNode;
}

const ENVIRONMENT_OPTIONS = [
	{ label: __("Sandbox (Testes)", TEXT_DOMAIN), value: "sandbox" },
	{ label: __("Produção", TEXT_DOMAIN), value: "production" },
];

export const BaseGatewaySettings = ({ children }: BaseGatewaySettingsProps) => {
	const { form } = useFormContext();
	const environment = useWatch({ control: form.control, name: "environment" }) as Environment;

	return (
		<>
			<SettingsCard title={__("Configurações Gerais", TEXT_DOMAIN)}>
				<div className="pagbank-settings-field">
					<FormToggle
						name="enabled"
						label={__("Ativar método de pagamento", TEXT_DOMAIN)}
						helpChecked={__("Este método de pagamento está ativo.", TEXT_DOMAIN)}
						helpUnchecked={__("Este método de pagamento está desativado.", TEXT_DOMAIN)}
					/>
				</div>

				<div className="pagbank-settings-field">
					<FormSelect
						name="environment"
						label={__("Ambiente", TEXT_DOMAIN)}
						options={ENVIRONMENT_OPTIONS}
						help={__("Selecione o ambiente para processar os pagamentos.", TEXT_DOMAIN)}
						fullWidth={false}
					/>
				</div>

				<div className="pagbank-settings-field">
					<PagBankConnect environment={environment} />
				</div>
			</SettingsCard>

			<SettingsCard title={__("Configurações de Exibição", TEXT_DOMAIN)}>
				<div className="pagbank-settings-field">
					<FormInput
						name="title"
						label={__("Título", TEXT_DOMAIN)}
						help={__("Título exibido ao cliente durante o checkout.", TEXT_DOMAIN)}
					/>
				</div>

				<div className="pagbank-settings-field">
					<FormTextarea
						name="description"
						label={__("Descrição", TEXT_DOMAIN)}
						help={__("Descrição exibida ao cliente durante o checkout.", TEXT_DOMAIN)}
					/>
				</div>
			</SettingsCard>

			{children}

			<SettingsCard title={__("Configurações Avançadas", TEXT_DOMAIN)}>
				<div className="pagbank-settings-field">
					<FormToggle
						name="logs_enabled"
						label={__("Habilitar logs", TEXT_DOMAIN)}
						help={__(
							"Registra eventos do gateway de pagamento para debug.",
							TEXT_DOMAIN,
						)}
					/>
				</div>
			</SettingsCard>
		</>
	);
};
