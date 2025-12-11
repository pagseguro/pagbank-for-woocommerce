/**
 * Credit Card gateway settings component.
 *
 * @package PagBank_WooCommerce
 */

import { BaseGatewaySettings } from "../base";
import { InstallmentsSection } from "./InstallmentsSection";
import { ThreeDSSection } from "./ThreeDSSection";

export const CreditCardSettingsForm = () => {
	return (
		<BaseGatewaySettings>
			<InstallmentsSection />
			<ThreeDSSection />
		</BaseGatewaySettings>
	);
};
