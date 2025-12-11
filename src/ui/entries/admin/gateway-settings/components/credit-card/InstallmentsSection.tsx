/**
 * Installments section component for credit card settings.
 *
 * @package PagBank_WooCommerce
 */

import { SelectControl, ToggleControl } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { TEXT_DOMAIN } from "@/constants";
import type { CreditCardSettings } from "../../types/settings";
import { AnimatedField, SettingsCard } from "../common";

interface InstallmentsSectionProps {
	settings: Pick<
		CreditCardSettings,
		| "installments_enabled"
		| "maximum_installments"
		| "transfer_of_interest_enabled"
		| "maximum_installments_interest_free"
	>;
	onSettingChange: (
		key: keyof CreditCardSettings,
		value: CreditCardSettings[keyof CreditCardSettings],
	) => void;
	disabled?: boolean;
}

const INSTALLMENT_OPTIONS = [
	{ label: "1x", value: "1" },
	{ label: "2x", value: "2" },
	{ label: "3x", value: "3" },
	{ label: "4x", value: "4" },
	{ label: "5x", value: "5" },
	{ label: "6x", value: "6" },
	{ label: "7x", value: "7" },
	{ label: "8x", value: "8" },
	{ label: "9x", value: "9" },
	{ label: "10x", value: "10" },
	{ label: "11x", value: "11" },
	{ label: "12x", value: "12" },
	{ label: "18x", value: "18" },
];

export const InstallmentsSection = ({
	settings,
	onSettingChange,
	disabled = false,
}: InstallmentsSectionProps) => {
	const installmentsEnabled = settings.installments_enabled === "yes";
	const transferOfInterestEnabled = settings.transfer_of_interest_enabled === "yes";

	// Filter options for interest-free installments (can't be more than max installments)
	const maxInstallmentsValue = parseInt(settings.maximum_installments, 10) || 12;
	const interestFreeOptions = INSTALLMENT_OPTIONS.filter(
		(opt) => parseInt(opt.value, 10) <= maxInstallmentsValue,
	);

	return (
		<SettingsCard title={__("Parcelamento", TEXT_DOMAIN)}>
			<div className="pagbank-settings-field">
				<ToggleControl
					__nextHasNoMarginBottom
					label={__("Habilitar parcelamento", TEXT_DOMAIN)}
					help={__("Permite que os clientes paguem em parcelas.", TEXT_DOMAIN)}
					checked={installmentsEnabled}
					onChange={(checked) =>
						onSettingChange("installments_enabled", checked ? "yes" : "no")
					}
					disabled={disabled}
				/>
			</div>

			<AnimatedField visible={installmentsEnabled}>
				<div className="pagbank-settings-field">
					<SelectControl
						__nextHasNoMarginBottom
						label={__("Máximo de parcelas", TEXT_DOMAIN)}
						value={settings.maximum_installments}
						options={INSTALLMENT_OPTIONS}
						onChange={(value) => onSettingChange("maximum_installments", value)}
						help={__("Número máximo de parcelas permitidas.", TEXT_DOMAIN)}
						disabled={disabled}
					/>
				</div>

				<div className="pagbank-settings-field">
					<ToggleControl
						__nextHasNoMarginBottom
						label={__("Repasse de juros ao comprador", TEXT_DOMAIN)}
						help={__(
							"Quando ativado, os juros do parcelamento serão repassados ao comprador.",
							TEXT_DOMAIN,
						)}
						checked={transferOfInterestEnabled}
						onChange={(checked) =>
							onSettingChange("transfer_of_interest_enabled", checked ? "yes" : "no")
						}
						disabled={disabled}
					/>
				</div>

				<AnimatedField visible={transferOfInterestEnabled}>
					<div className="pagbank-settings-field">
						<SelectControl
							__nextHasNoMarginBottom
							label={__("Máximo de parcelas sem juros", TEXT_DOMAIN)}
							value={settings.maximum_installments_interest_free}
							options={interestFreeOptions}
							onChange={(value) =>
								onSettingChange("maximum_installments_interest_free", value)
							}
							help={__(
								"Número máximo de parcelas sem juros. Parcelas acima deste valor terão juros.",
								TEXT_DOMAIN,
							)}
							disabled={disabled}
						/>
					</div>
				</AnimatedField>
			</AnimatedField>
		</SettingsCard>
	);
};
