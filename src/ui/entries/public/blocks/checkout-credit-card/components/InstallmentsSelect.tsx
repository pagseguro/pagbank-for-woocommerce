/**
 * Installments select component.
 *
 * @package PagBank_WooCommerce
 */

import { __ } from "@wordpress/i18n";
import type { InstallmentPlan } from "../../shared";

interface InstallmentsSelectProps {
	id: string;
	value: string;
	onChange: (value: string) => void;
	plans: InstallmentPlan[];
	isLoading: boolean;
	disabled?: boolean;
}

export const InstallmentsSelect = ({
	id,
	value,
	onChange,
	plans,
	isLoading,
	disabled = false,
}: InstallmentsSelectProps): JSX.Element => {
	return (
		<div className="pagbank-field pagbank-field-installments">
			<label htmlFor={id}>{__("Parcelas", "pagbank-for-woocommerce")}</label>
			<select
				id={id}
				value={value}
				onChange={(e) => onChange(e.target.value)}
				disabled={isLoading || disabled}
			>
				{isLoading ? (
					<option value="1">{__("Carregando...", "pagbank-for-woocommerce")}</option>
				) : plans.length > 0 ? (
					plans.map((plan) => (
						<option key={plan.installments} value={plan.installments}>
							{plan.title}
						</option>
					))
				) : (
					<option value="1">1x</option>
				)}
			</select>
		</div>
	);
};
