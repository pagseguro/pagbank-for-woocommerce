/**
 * Payment instructions entry point.
 * Hydrates React components into PHP-rendered containers.
 *
 * @package PagBank_WooCommerce
 */

import { createRoot } from "react-dom/client";
import { BoletoInstructions } from "./components/BoletoInstructions";
import { PayWithPagBankInstructions } from "./components/PayWithPagBankInstructions";
import { PixInstructions } from "./components/PixInstructions";
import type {
	BoletoInstructionsProps,
	PayWithPagBankInstructionsProps,
	PixInstructionsProps,
} from "./types";

/**
 * Convert kebab-case data attributes to camelCase props.
 */
const kebabToCamel = (str: string): string => {
	return str.replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());
};

/**
 * Extract props from dataset, converting kebab-case to camelCase.
 */
const extractProps = <T,>(dataset: DOMStringMap): T => {
	const props: Record<string, unknown> = {};

	for (const [key, value] of Object.entries(dataset)) {
		const camelKey = kebabToCamel(key);

		// Convert specific types
		if (camelKey === "orderId") {
			props[camelKey] = Number.parseInt(value || "0", 10);
		} else if (camelKey === "isPaid") {
			props[camelKey] = value === "true";
		} else {
			props[camelKey] = value;
		}
	}

	return props as T;
};

/**
 * Initialize payment instructions components.
 */
const initPaymentInstructions = (): void => {
	// Boleto instructions
	const boletoContainer = document.getElementById("pagbank-boleto-instructions");
	if (boletoContainer) {
		const props = extractProps<BoletoInstructionsProps>(boletoContainer.dataset);
		createRoot(boletoContainer).render(<BoletoInstructions {...props} />);
	}

	// Pix instructions
	const pixContainer = document.getElementById("pagbank-pix-instructions");
	if (pixContainer) {
		const props = extractProps<PixInstructionsProps>(pixContainer.dataset);
		createRoot(pixContainer).render(<PixInstructions {...props} />);
	}

	// Pay with PagBank instructions
	const pagbankContainer = document.getElementById("pagbank-pay-with-pagbank-instructions");
	if (pagbankContainer) {
		const props = extractProps<PayWithPagBankInstructionsProps>(pagbankContainer.dataset);
		createRoot(pagbankContainer).render(<PayWithPagBankInstructions {...props} />);
	}
};

// Initialize when DOM is ready
if (document.readyState === "loading") {
	document.addEventListener("DOMContentLoaded", initPaymentInstructions);
} else {
	initPaymentInstructions();
}
