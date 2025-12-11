/**
 * Form number stepper component with + and - buttons.
 *
 * @package PagBank_WooCommerce
 */

import { BaseControl } from "@wordpress/components";
import { Controller } from "react-hook-form";
import { useFormContext } from "../../context";
import type { GatewaySettings } from "../../schemas/settings";

// Inline SVG icons to avoid external dependency issues
const MinusIcon = () => (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		width="24"
		height="24"
		fill="currentColor"
		aria-hidden="true"
	>
		<path d="M5 11h14v2H5z" />
	</svg>
);

const PlusIcon = () => (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		width="24"
		height="24"
		fill="currentColor"
		aria-hidden="true"
	>
		<path d="M11 5v6H5v2h6v6h2v-6h6v-2h-6V5z" />
	</svg>
);

interface FormNumberStepperProps {
	name: keyof GatewaySettings;
	label: string;
	help?: string;
	min?: number;
	max?: number;
	step?: number;
	suffix?: string;
}

export const FormNumberStepper = ({
	name,
	label,
	help,
	min = 1,
	max = 999,
	step = 1,
	suffix,
}: FormNumberStepperProps) => {
	const { form, isSaving } = useFormContext();

	return (
		<Controller
			name={name}
			control={form.control}
			render={({ field, fieldState }) => {
				const currentValue = Number.parseInt(field.value as string, 10) || min;

				const handleDecrement = () => {
					const newValue = Math.max(min, currentValue - step);
					field.onChange(String(newValue));
				};

				const handleIncrement = () => {
					const newValue = Math.min(max, currentValue + step);
					field.onChange(String(newValue));
				};

				const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
					const value = e.target.value;
					// Allow empty input for typing
					if (value === "") {
						field.onChange("");
						return;
					}
					const numValue = Number.parseInt(value, 10);
					if (!Number.isNaN(numValue)) {
						const clampedValue = Math.min(max, Math.max(min, numValue));
						field.onChange(String(clampedValue));
					}
				};

				const handleBlur = () => {
					// Ensure valid value on blur
					const numValue = Number.parseInt(field.value as string, 10);
					if (Number.isNaN(numValue) || numValue < min) {
						field.onChange(String(min));
					} else if (numValue > max) {
						field.onChange(String(max));
					}
					field.onBlur();
				};

				return (
					<BaseControl
						__nextHasNoMarginBottom
						id={`${name}-stepper`}
						label={label}
						help={fieldState.error?.message ?? help}
					>
						<div className="pagbank-number-stepper-wrapper">
							<div className="pagbank-number-stepper">
								<button
									type="button"
									onClick={handleDecrement}
									disabled={isSaving || currentValue <= min}
									className="pagbank-number-stepper__button pagbank-number-stepper__button--minus"
									aria-label="Diminuir"
								>
									<MinusIcon />
								</button>
								<input
									type="text"
									inputMode="numeric"
									pattern="[0-9]*"
									value={(field.value as string) ?? ""}
									onChange={handleInputChange}
									onBlur={handleBlur}
									disabled={isSaving}
									className="pagbank-number-stepper__input"
								/>
								{suffix && (
									<span className="pagbank-number-stepper__suffix">{suffix}</span>
								)}
								<button
									type="button"
									onClick={handleIncrement}
									disabled={isSaving || currentValue >= max}
									className="pagbank-number-stepper__button pagbank-number-stepper__button--plus"
									aria-label="Aumentar"
								>
									<PlusIcon />
								</button>
							</div>
						</div>
					</BaseControl>
				);
			}}
		/>
	);
};
