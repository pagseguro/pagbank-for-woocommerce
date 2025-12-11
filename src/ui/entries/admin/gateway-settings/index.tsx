/**
 * Gateway settings entry point.
 *
 * @package PagBank_WooCommerce
 */

import { createRoot } from "react-dom/client";
import { GatewaySettingsApp } from "./GatewaySettingsApp";
import { QueryProvider } from "./providers";
import type { GatewayId } from "./schemas/settings";

const init = () => {
	const container = document.getElementById("pagbank-gateway-settings-root");

	if (!container) {
		return;
	}

	const gatewayId = container.dataset.gatewayId as GatewayId | undefined;

	if (!gatewayId) {
		console.error("PagBank: Gateway ID not found in data attribute");
		return;
	}

	const root = createRoot(container);
	root.render(
		<QueryProvider>
			<GatewaySettingsApp gatewayId={gatewayId} />
		</QueryProvider>,
	);
};

if (document.readyState === "loading") {
	document.addEventListener("DOMContentLoaded", init);
} else {
	init();
}
