/**
 * Credit card form fields component.
 *
 * @package PagBank_WooCommerce
 */

import { __ } from "@wordpress/i18n";

import { formatCardNumber, formatExpiry } from "../utils";

const TEXT_DOMAIN = "pagbank-for-woocommerce";

interface CardFormFieldsProps {
	holder: string;
	onHolderChange: (value: string) => void;
	number: string;
	onNumberChange: (value: string) => void;
	expiry: string;
	onExpiryChange: (value: string) => void;
	cvc: string;
	onCvcChange: (value: string) => void;
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
}: CardFormFieldsProps): JSX.Element => {
	return (
		<>
			{/* Card Holder */}
			<div className="pagbank-field pagbank-field-holder">
				<label htmlFor="pagbank-card-holder">
					{__("Cardholder name", TEXT_DOMAIN)}
				</label>
				<input
					type="text"
					id="pagbank-card-holder"
					value={holder}
					onChange={(e) => onHolderChange(e.target.value)}
					placeholder="John Doe"
					autoComplete="cc-name"
				/>
			</div>

			{/* Card Number */}
			<div className="pagbank-field pagbank-field-number">
				<label htmlFor="pagbank-card-number">
					{__("Card number", TEXT_DOMAIN)}
				</label>
				<input
					type="text"
					id="pagbank-card-number"
					value={number}
					onChange={(e) => onNumberChange(formatCardNumber(e.target.value))}
					placeholder="0000 0000 0000 0000"
					autoComplete="cc-number"
					inputMode="numeric"
					maxLength={19}
				/>
			</div>

			{/* Expiry and CVC */}
			<div className="pagbank-field-row">
				<div className="pagbank-field pagbank-field-expiry">
					<label htmlFor="pagbank-card-expiry">
						{__("Expiry date", TEXT_DOMAIN)}
					</label>
					<input
						type="text"
						id="pagbank-card-expiry"
						value={expiry}
						onChange={(e) => onExpiryChange(formatExpiry(e.target.value, expiry))}
						placeholder="MM/YY"
						autoComplete="cc-exp"
						inputMode="numeric"
						maxLength={5}
					/>
				</div>

				<div className="pagbank-field pagbank-field-cvc">
					<label htmlFor="pagbank-card-cvc">
						{__("CVV", TEXT_DOMAIN)}
					</label>
					<input
						type="text"
						id="pagbank-card-cvc"
						value={cvc}
						onChange={(e) =>
							onCvcChange(e.target.value.replace(/\D/g, "").substring(0, 4))
						}
						placeholder="000"
						autoComplete="cc-csc"
						inputMode="numeric"
						maxLength={4}
					/>
				</div>
			</div>
		</>
	);
};
