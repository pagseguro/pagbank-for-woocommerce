/**
 * Common form field components for gateway settings.
 *
 * @package PagBank_WooCommerce
 */

import { SelectControl, TextareaControl, TextControl, ToggleControl } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { TEXT_DOMAIN } from "@/constants";
import type { Environment, YesNo } from "../../types/settings";

interface EnabledToggleProps {
	value: YesNo;
	onChange: (value: YesNo) => void;
	disabled?: boolean;
}

export const EnabledToggle = ({ value, onChange, disabled = false }: EnabledToggleProps) => {
	return (
		<ToggleControl
			__nextHasNoMarginBottom
			label={__("Ativar método de pagamento", TEXT_DOMAIN)}
			help={
				value === "yes"
					? __("Este método de pagamento está ativo.", TEXT_DOMAIN)
					: __("Este método de pagamento está desativado.", TEXT_DOMAIN)
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
			label={__("Ambiente", TEXT_DOMAIN)}
			value={value}
			options={[
				{ label: __("Sandbox (Testes)", TEXT_DOMAIN), value: "sandbox" },
				{ label: __("Produção", TEXT_DOMAIN), value: "production" },
			]}
			onChange={(newValue) => onChange(newValue as Environment)}
			help={__("Selecione o ambiente para processar os pagamentos.", TEXT_DOMAIN)}
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
			label={__("Título", TEXT_DOMAIN)}
			value={value}
			onChange={onChange}
			help={__("Título exibido ao cliente durante o checkout.", TEXT_DOMAIN)}
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
			label={__("Descrição", TEXT_DOMAIN)}
			value={value}
			onChange={onChange}
			help={__("Descrição exibida ao cliente durante o checkout.", TEXT_DOMAIN)}
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
			label={__("Habilitar logs", TEXT_DOMAIN)}
			help={__("Registra eventos do gateway de pagamento para debug.", TEXT_DOMAIN)}
			checked={value === "yes"}
			onChange={(checked) => onChange(checked ? "yes" : "no")}
			disabled={disabled}
		/>
	);
};
