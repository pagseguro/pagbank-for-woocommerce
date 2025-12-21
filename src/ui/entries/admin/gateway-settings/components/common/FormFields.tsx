/**
 * Common form field components for gateway settings.
 *
 * @package PagBank_WooCommerce
 */

import { SelectControl, TextareaControl, TextControl, ToggleControl } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import type { Environment, YesNo } from "../../schemas/settings";

interface EnabledToggleProps {
	value: YesNo;
	onChange: (value: YesNo) => void;
	disabled?: boolean;
}

export const EnabledToggle = ({ value, onChange, disabled = false }: EnabledToggleProps) => {
	return (
		<ToggleControl
			__nextHasNoMarginBottom
			label={__("Ativar método de pagamento", "pagbank-for-woocommerce")}
			help={
				value === "yes"
					? __("Este método de pagamento está ativo.", "pagbank-for-woocommerce")
					: __("Este método de pagamento está desativado.", "pagbank-for-woocommerce")
			}
			checked={value === "yes"}
			onChange={(checked) => onChange(checked ? "yes" : "no")}
			disabled={disabled}
		/>
	);
};

interface EnvironmentSelectProps {
	value: Environment;
	onChange: (value: Environment) => void;
	disabled?: boolean;
}

export const EnvironmentSelect = ({
	value,
	onChange,
	disabled = false,
}: EnvironmentSelectProps) => {
	return (
		<SelectControl
			__nextHasNoMarginBottom
			label={__("Ambiente", "pagbank-for-woocommerce")}
			value={value}
			options={[
				{ label: __("Sandbox (Testes)", "pagbank-for-woocommerce"), value: "sandbox" },
				{ label: __("Produção", "pagbank-for-woocommerce"), value: "production" },
			]}
			onChange={(newValue) => onChange(newValue as Environment)}
			help={__(
				"Selecione o ambiente para processar os pagamentos.",
				"pagbank-for-woocommerce",
			)}
			disabled={disabled}
		/>
	);
};

interface TitleInputProps {
	value: string;
	onChange: (value: string) => void;
	disabled?: boolean;
}

export const TitleInput = ({ value, onChange, disabled = false }: TitleInputProps) => {
	return (
		<TextControl
			__nextHasNoMarginBottom
			label={__("Título", "pagbank-for-woocommerce")}
			value={value}
			onChange={onChange}
			help={__("Título exibido ao cliente durante o checkout.", "pagbank-for-woocommerce")}
			disabled={disabled}
		/>
	);
};

interface DescriptionTextareaProps {
	value: string;
	onChange: (value: string) => void;
	disabled?: boolean;
}

export const DescriptionTextarea = ({
	value,
	onChange,
	disabled = false,
}: DescriptionTextareaProps) => {
	return (
		<TextareaControl
			__nextHasNoMarginBottom
			label={__("Descrição", "pagbank-for-woocommerce")}
			value={value}
			onChange={onChange}
			help={__("Descrição exibida ao cliente durante o checkout.", "pagbank-for-woocommerce")}
			disabled={disabled}
		/>
	);
};

interface LogsToggleProps {
	value: YesNo;
	onChange: (value: YesNo) => void;
	disabled?: boolean;
}

export const LogsToggle = ({ value, onChange, disabled = false }: LogsToggleProps) => {
	return (
		<ToggleControl
			__nextHasNoMarginBottom
			label={__("Habilitar logs", "pagbank-for-woocommerce")}
			help={__(
				"Registra eventos do gateway de pagamento para debug.",
				"pagbank-for-woocommerce",
			)}
			checked={value === "yes"}
			onChange={(checked) => onChange(checked ? "yes" : "no")}
			disabled={disabled}
		/>
	);
};
