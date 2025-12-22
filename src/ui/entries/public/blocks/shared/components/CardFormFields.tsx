/**
 * Shared card form fields component.
 *
 * @package PagBank_WooCommerce
 */

import { __ } from "@wordpress/i18n";
import { formatCardNumber, formatExpiry } from "../utils";

interface CardFormFieldsProps {
	holder: string;
	onHolderChange: (value: string) => void;
	number: string;
	onNumberChange: (value: string) => void;
	expiry: string;
	onExpiryChange: (value: string) => void;
	cvc: string;
	onCvcChange: (value: string) => void;
	idPrefix?: string;
}

export const CardFormFields = ({
	holder,
	onHolderChange,
	number,
	onNumberChange,
	expiry,
	onExpiryChange,
	cvc,
	onCvcChange,
	idPrefix = "pagbank-card",
}: CardFormFieldsProps): JSX.Element => {
	return (
		<>
			{/* Card Holder */}
			<div className="pagbank-field pagbank-field-holder">
				<label htmlFor={`${idPrefix}-holder`}>
					{__("Nome do titular", "pagbank-for-woocommerce")}
				</label>
				<input
					type="text"
					id={`${idPrefix}-holder`}
					value={holder}
					onChange={(e) => onHolderChange(e.target.value)}
					autoComplete="cc-name"
					aria-required="true"
					required
				/>
			</div>

			{/* Card Number */}
			<div className="pagbank-field pagbank-field-number">
				<label htmlFor={`${idPrefix}-number`}>
					{__("Número do cartão", "pagbank-for-woocommerce")}
				</label>
				<input
					type="text"
					id={`${idPrefix}-number`}
					value={number}
					onChange={(e) => onNumberChange(formatCardNumber(e.target.value))}
					placeholder="0000 0000 0000 0000"
					autoComplete="cc-number"
					inputMode="numeric"
					maxLength={19}
					aria-required="true"
					required
				/>
			</div>

			{/* Expiry and CVC */}
			<div className="pagbank-field-row">
				<div className="pagbank-field pagbank-field-expiry">
					<label htmlFor={`${idPrefix}-expiry`}>
						{__("Validade", "pagbank-for-woocommerce")}
					</label>
					<input
						type="text"
						id={`${idPrefix}-expiry`}
						value={expiry}
						onChange={(e) => onExpiryChange(formatExpiry(e.target.value, expiry))}
						placeholder="MM/AA"
						autoComplete="cc-exp"
						inputMode="numeric"
						maxLength={5}
						aria-required="true"
						required
					/>
				</div>

				<div className="pagbank-field pagbank-field-cvc">
					<label htmlFor={`${idPrefix}-cvc`}>
						{__("CVV", "pagbank-for-woocommerce")}
					</label>
					<input
						type="text"
						id={`${idPrefix}-cvc`}
						value={cvc}
						onChange={(e) =>
							onCvcChange(e.target.value.replace(/\D/g, "").substring(0, 4))
						}
						placeholder="000"
						autoComplete="cc-csc"
						inputMode="numeric"
						maxLength={4}
						aria-required="true"
						required
					/>
				</div>
			</div>
		</>
	);
};
