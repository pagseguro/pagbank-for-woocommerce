/**
 * Credit Card gateway settings component.
 *
 * @package PagBank_WooCommerce
 */

import type { CreditCardSettings as CreditCardSettingsType } from "../../types/settings";
import { BaseGatewaySettings } from "../base";
import { InstallmentsSection } from "./InstallmentsSection";
import { ThreeDSSection } from "./ThreeDSSection";

interface CreditCardSettingsProps {
	settings: CreditCardSettingsType;
	onSettingChange: (
		key: keyof CreditCardSettingsType,
		value: CreditCardSettingsType[keyof CreditCardSettingsType],
	) => void;
	disabled?: boolean;
}

export const CreditCardSettings = ({
	settings,
	onSettingChange,
	disabled = false,
}: CreditCardSettingsProps) => {
	return (
		<BaseGatewaySettings<CreditCardSettingsType>
			settings={settings}
			onSettingChange={onSettingChange}
			disabled={disabled}
		>
			<InstallmentsSection
				settings={settings}
				onSettingChange={onSettingChange}
				disabled={disabled}
			/>

			<ThreeDSSection
				settings={settings}
				onSettingChange={onSettingChange}
				disabled={disabled}
			/>
		</BaseGatewaySettings>
	);
};
