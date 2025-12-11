/**
 * Installments section component for credit card settings.
 *
 * @package PagBank_WooCommerce
 */

import { __ } from "@wordpress/i18n";
import { useMemo } from "react";
import { useWatch } from "react-hook-form";
import { TEXT_DOMAIN } from "@/constants";
import { useFormContext } from "../../context";
import type { GatewaySettings, YesNo } from "../../schemas/settings";
import { AnimatedField, SettingsCard } from "../common";
import { FormSelect, FormToggle } from "../form";

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
];

export const InstallmentsSection = () => {
	const { form } = useFormContext();

	const installmentsEnabled = useWatch({
		control: form.control,
		name: "installments_enabled" as keyof GatewaySettings,
	}) as YesNo;

	const transferOfInterestEnabled = useWatch({
		control: form.control,
		name: "transfer_of_interest_enabled" as keyof GatewaySettings,
	}) as YesNo;

	const maximumInstallments = useWatch({
		control: form.control,
		name: "maximum_installments" as keyof GatewaySettings,
	}) as string;

	const isInstallmentsEnabled = installmentsEnabled === "yes";
	const isTransferOfInterestEnabled = transferOfInterestEnabled === "yes";

	// Filter options for interest-free installments (can't be more than max installments)
	const interestFreeOptions = useMemo(() => {
		const maxValue = Number.parseInt(maximumInstallments, 10) || 12;
		return INSTALLMENT_OPTIONS.filter((opt) => Number.parseInt(opt.value, 10) <= maxValue);
	}, [maximumInstallments]);

	return (
		<SettingsCard title={__("Parcelamento", TEXT_DOMAIN)}>
			<div className="pagbank-settings-field">
				<FormToggle
					name={"installments_enabled" as keyof GatewaySettings}
					label={__("Habilitar parcelamento", TEXT_DOMAIN)}
					help={__("Permite que os clientes paguem em parcelas.", TEXT_DOMAIN)}
				/>
			</div>

			<AnimatedField visible={isInstallmentsEnabled}>
				<div className="pagbank-settings-field">
					<FormSelect
						name={"maximum_installments" as keyof GatewaySettings}
						label={__("Máximo de parcelas", TEXT_DOMAIN)}
						options={INSTALLMENT_OPTIONS}
						help={__("Número máximo de parcelas permitidas.", TEXT_DOMAIN)}
					/>
				</div>

				<div className="pagbank-settings-field">
					<FormToggle
						name={"transfer_of_interest_enabled" as keyof GatewaySettings}
						label={__("Repasse de juros ao comprador", TEXT_DOMAIN)}
						help={__(
							"Quando ativado, os juros do parcelamento serão repassados ao comprador.",
							TEXT_DOMAIN,
						)}
					/>
				</div>

				<AnimatedField visible={isTransferOfInterestEnabled}>
					<div className="pagbank-settings-field">
						<FormSelect
							name={"maximum_installments_interest_free" as keyof GatewaySettings}
							label={__("Máximo de parcelas sem juros", TEXT_DOMAIN)}
							options={interestFreeOptions}
							help={__(
								"Número máximo de parcelas sem juros. Parcelas acima deste valor terão juros.",
								TEXT_DOMAIN,
							)}
						/>
					</div>
				</AnimatedField>
			</AnimatedField>
		</SettingsCard>
	);
};
