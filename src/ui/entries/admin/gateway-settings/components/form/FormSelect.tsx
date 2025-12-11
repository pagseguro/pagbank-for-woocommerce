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
}

export const FormSelect = ({ name, label, options, help }: FormSelectProps) => {
	const { form, isSaving } = useFormContext();

	return (
		<Controller
			name={name}
			control={form.control}
			render={({ field, fieldState }) => (
				<SelectControl
					__nextHasNoMarginBottom
					label={label}
					value={(field.value as string) ?? ""}
					options={options}
					onChange={field.onChange}
					help={fieldState.error?.message ?? help}
					disabled={isSaving}
				/>
			)}
		/>
	);
};
