/**
 * Debit Card gateway settings component.
 *
 * @package PagBank_WooCommerce
 */

import type { DebitCardSettings as DebitCardSettingsType } from "../../types/settings";
import { BaseGatewaySettings } from "../base";

interface DebitCardSettingsProps {
	settings: DebitCardSettingsType;
	onSettingChange: (
		key: keyof DebitCardSettingsType,
		value: DebitCardSettingsType[keyof DebitCardSettingsType],
	) => void;
	disabled?: boolean;
}

export const DebitCardSettings = ({
	settings,
	onSettingChange,
	disabled = false,
}: DebitCardSettingsProps) => {
	return (
		<BaseGatewaySettings<DebitCardSettingsType>
			settings={settings}
			onSettingChange={onSettingChange}
			disabled={disabled}
		/>
	);
};
