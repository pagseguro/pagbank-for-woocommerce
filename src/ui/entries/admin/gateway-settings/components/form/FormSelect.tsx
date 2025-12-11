/**
 * Form select component integrated with React Hook Form.
 *
 * @package PagBank_WooCommerce
 */

import { SelectControl } from "@wordpress/components";
import { Controller } from "react-hook-form";
import { useFormContext } from "../../context";
import type { GatewaySettings } from "../../schemas/settings";

interface SelectOption {
	label: string;
	value: string;
}

interface FormSelectProps {
	name: keyof GatewaySettings;
	label: string;
	options: SelectOption[];
	help?: string;
	fullWidth?: boolean;
}

export const FormSelect = ({ name, label, options, help, fullWidth = true }: FormSelectProps) => {
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
					<SelectControl
						__nextHasNoMarginBottom
						label={label}
						value={(field.value as string) ?? ""}
						options={options}
						onChange={field.onChange}
						help={fieldState.error?.message ?? help}
						disabled={isSaving}
					/>
				</div>
			)}
		/>
	);
};
