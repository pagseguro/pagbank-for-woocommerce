/**
 * Form input component integrated with React Hook Form.
 *
 * @package PagBank_WooCommerce
 */

import { TextControl } from "@wordpress/components";
import { Controller } from "react-hook-form";
import { useFormContext } from "../../context";
import type { GatewaySettings } from "../../schemas/settings";

interface FormInputProps {
	name: keyof GatewaySettings;
	label: string;
	help?: string;
	placeholder?: string;
	fullWidth?: boolean;
}

export const FormInput = ({ name, label, help, placeholder, fullWidth = true }: FormInputProps) => {
	const { form, isSaving } = useFormContext();

	const wrapperClassName = fullWidth
		? "pagbank-form-field"
		: "pagbank-form-field pagbank-form-field--auto-width";

	return (
		<Controller
			name={name}
			control={form.control}
			render={({ field, fieldState }) => (
				<div className={wrapperClassName}>
					<TextControl
						__nextHasNoMarginBottom
						label={label}
						value={(field.value as string) ?? ""}
						onChange={field.onChange}
						help={fieldState.error?.message ?? help}
						placeholder={placeholder}
						disabled={isSaving}
					/>
				</div>
			)}
		/>
	);
};
