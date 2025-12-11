/**
 * Form textarea component integrated with React Hook Form.
 *
 * @package PagBank_WooCommerce
 */

import { TextareaControl } from "@wordpress/components";
import { Controller } from "react-hook-form";
import { useFormContext } from "../../context";
import type { GatewaySettings } from "../../schemas/settings";

interface FormTextareaProps {
	name: keyof GatewaySettings;
	label: string;
	help?: string;
	placeholder?: string;
	rows?: number;
}

export const FormTextarea = ({ name, label, help, placeholder, rows }: FormTextareaProps) => {
	const { form, isSaving } = useFormContext();

	return (
		<Controller
			name={name}
			control={form.control}
			render={({ field, fieldState }) => (
				<TextareaControl
					__nextHasNoMarginBottom
					label={label}
					value={(field.value as string) ?? ""}
					onChange={field.onChange}
					help={fieldState.error?.message ?? help}
					placeholder={placeholder}
					rows={rows}
					disabled={isSaving}
				/>
			)}
		/>
	);
};
