/**
 * Installments section component for credit card settings.
 *
 * @package PagBank_WooCommerce
 */

import { __ } from "@wordpress/i18n";
import { useEffect, useMemo, useRef } from "react";
import { useWatch } from "react-hook-form";
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

const INTEREST_FREE_OPTIONS = [
	{ label: "1x", value: "0" },
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

	const maximumInstallmentsInterestFree = useWatch({
		control: form.control,
		name: "maximum_installments_interest_free" as keyof GatewaySettings,
	}) as string;

	const isInstallmentsEnabled = installmentsEnabled === "yes";
	const canTransferInterest = maximumInstallments !== "1";
	const isTransferOfInterestEnabled = transferOfInterestEnabled === "yes" && canTransferInterest;

	// Track if this is the initial render to avoid adjusting values on load
	const isInitialRender = useRef(true);

	// Disable transfer of interest when max installments is 1
	useEffect(() => {
		if (isInitialRender.current) {
			isInitialRender.current = false;
			return;
		}

		if (!canTransferInterest && transferOfInterestEnabled === "yes") {
			form.setValue("transfer_of_interest_enabled" as keyof GatewaySettings, "no");
			form.setValue("maximum_installments_interest_free" as keyof GatewaySettings, "0");
		}

		const maxInstallments = Number.parseInt(maximumInstallments, 10) || 12;
		const currentInterestFree = Number.parseInt(maximumInstallmentsInterestFree, 10) || 0;

		// Only adjust if current interest-free value exceeds the new max
		if (currentInterestFree > maxInstallments) {
			form.setValue(
				"maximum_installments_interest_free" as keyof GatewaySettings,
				String(maxInstallments),
			);
		}
	}, [
		maximumInstallments,
		maximumInstallmentsInterestFree,
		form,
		canTransferInterest,
		transferOfInterestEnabled,
	]);

	// Filter options for interest-free installments (can't be more than max installments)
	const interestFreeOptions = useMemo(() => {
		const maxValue = Number.parseInt(maximumInstallments, 10) || 12;
		return INTEREST_FREE_OPTIONS.filter((opt) => {
			const val = Number.parseInt(opt.value, 10);
			return val === 0 || val <= maxValue;
		});
	}, [maximumInstallments]);

	return (
		<SettingsCard title={__("Parcelamento", "pagbank-for-woocommerce")}>
			<div className="pagbank-settings-field">
				<FormToggle
					name={"installments_enabled" as keyof GatewaySettings}
					label={__("Habilitar parcelamento", "pagbank-for-woocommerce")}
					help={__(
						"Permite que os clientes paguem em parcelas.",
						"pagbank-for-woocommerce",
					)}
				/>
			</div>

			<AnimatedField visible={isInstallmentsEnabled}>
				<div className="pagbank-settings-field">
					<FormSelect
						name={"maximum_installments" as keyof GatewaySettings}
						label={__("Máximo de parcelas", "pagbank-for-woocommerce")}
						options={INSTALLMENT_OPTIONS}
						help={__(
							"Número máximo de parcelas permitidas.",
							"pagbank-for-woocommerce",
						)}
						fullWidth={false}
					/>
				</div>

				<AnimatedField visible={canTransferInterest}>
					<div className="pagbank-settings-field">
						<FormToggle
							name={"transfer_of_interest_enabled" as keyof GatewaySettings}
							label={__("Repasse de juros ao comprador", "pagbank-for-woocommerce")}
							help={__(
								"Quando ativado, os juros do parcelamento serão repassados ao comprador.",
								"pagbank-for-woocommerce",
							)}
						/>
					</div>
				</AnimatedField>

				<AnimatedField visible={isTransferOfInterestEnabled}>
					<div className="pagbank-settings-field">
						<FormSelect
							name={"maximum_installments_interest_free" as keyof GatewaySettings}
							label={__("Máximo de parcelas sem juros", "pagbank-for-woocommerce")}
							options={interestFreeOptions}
							help={__(
								"Número máximo de parcelas sem juros. Parcelas acima deste valor terão juros.",
								"pagbank-for-woocommerce",
							)}
							fullWidth={false}
						/>
					</div>
				</AnimatedField>
			</AnimatedField>
		</SettingsCard>
	);
};
