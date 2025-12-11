/**
 * Form toggle component integrated with React Hook Form.
 *
 * @package PagBank_WooCommerce
 */

import { ToggleControl } from "@wordpress/components";
import { Controller } from "react-hook-form";
import { useFormContext } from "../../context";
import type { GatewaySettings } from "../../schemas/settings";

interface FormToggleProps {
	name: keyof GatewaySettings;
	label: string;
	help?: string;
	helpChecked?: string;
	helpUnchecked?: string;
}

export const FormToggle = ({ name, label, help, helpChecked, helpUnchecked }: FormToggleProps) => {
	const { form, isSaving } = useFormContext();

	return (
		<Controller
			name={name}
			control={form.control}
			render={({ field }) => {
				const isChecked = field.value === "yes";
				const helpText = help ?? (isChecked ? helpChecked : helpUnchecked);

				return (
					<ToggleControl
						__nextHasNoMarginBottom
						label={label}
						help={helpText}
						checked={isChecked}
						onChange={(checked) => field.onChange(checked ? "yes" : "no")}
						disabled={isSaving}
					/>
				);
			}}
		/>
	);
};
