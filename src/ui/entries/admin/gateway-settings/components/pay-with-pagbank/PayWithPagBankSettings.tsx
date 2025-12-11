/**
 * Pay with PagBank gateway settings component.
 *
 * @package PagBank_WooCommerce
 */

import type { PayWithPagBankSettings as PayWithPagBankSettingsType } from "../../types/settings";
import { BaseGatewaySettings } from "../base";

interface PayWithPagBankSettingsProps {
	settings: PayWithPagBankSettingsType;
	onSettingChange: (
		key: keyof PayWithPagBankSettingsType,
		value: PayWithPagBankSettingsType[keyof PayWithPagBankSettingsType],
	) => void;
	disabled?: boolean;
}

export const PayWithPagBankSettings = ({
	settings,
	onSettingChange,
	disabled = false,
}: PayWithPagBankSettingsProps) => {
	return (
		<BaseGatewaySettings<PayWithPagBankSettingsType>
			settings={settings}
			onSettingChange={onSettingChange}
			disabled={disabled}
		/>
	);
};
