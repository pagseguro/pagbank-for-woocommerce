/**
 * Input masks for the PagBank additional checkout fields in the Blocks checkout.
 *
 * Mirrors the classic checkout masks (CPF, CNPJ and cellphone). The Blocks
 * inputs are React-controlled, so the value is written through the native
 * HTMLInputElement value setter and an `input` event is dispatched — this is
 * the standard way to update a controlled input from outside React (setting
 * `input.value` directly would desync the React state).
 *
 * Fields are matched by their wrapper class
 * (`wc-block-components-address-form__pagbank-*`) via a delegated listener,
 * so the masks survive React re-renders.
 */

import {
	caretPositionForCount,
	countSignificantChars,
	formatCellphone,
	formatCnpj,
	formatCpf,
	maskDocumentForDisplay,
} from "@/shared/masks";

interface BlocksCheckoutFieldsConfig {
	alphanumeric_cnpj: boolean;
}

type StoreAddress = Record<string, string | undefined>;

interface WcCartStoreSelect {
	getCustomerData?: () => {
		billingAddress?: StoreAddress;
		shippingAddress?: StoreAddress;
	};
}

interface WpData {
	select?: (store: string) => WcCartStoreSelect | undefined;
	subscribe?: (callback: () => void) => void;
}

declare global {
	interface Window {
		PagBankBlocksCheckoutFieldsConfig?: BlocksCheckoutFieldsConfig;
		wp?: {
			data?: WpData;
		};
	}
}

// The fallback mirrors the PHP defaults (the alphanumeric CNPJ feature flag
// defaults to enabled), in case the inline config script is stripped by an
// optimizer plugin.
const config: BlocksCheckoutFieldsConfig = window.PagBankBlocksCheckoutFieldsConfig ?? {
	alphanumeric_cnpj: true,
};

const nativeValueSetter = Object.getOwnPropertyDescriptor(
	window.HTMLInputElement.prototype,
	"value",
)?.set;

/**
 * Write a value into a React-controlled input and notify React.
 */
const setReactInputValue = (input: HTMLInputElement, value: string): void => {
	if (!nativeValueSetter) {
		return;
	}

	nativeValueSetter.call(input, value);
	input.dispatchEvent(new Event("input", { bubbles: true }));
};

const applyMask = (
	input: HTMLInputElement,
	format: (value: string) => string,
	isAlphanumeric: boolean,
): void => {
	const caret = input.selectionStart ?? input.value.length;
	const significantBefore = countSignificantChars(input.value, caret, isAlphanumeric);
	const formatted = format(input.value);

	// Also breaks the loop for the synthetic event dispatched below: by then
	// the value is already formatted, so this returns before dispatching again.
	if (formatted === input.value) {
		return;
	}

	setReactInputValue(input, formatted);

	const newCaret = caretPositionForCount(formatted, significantBefore, isAlphanumeric);
	input.setSelectionRange(newCaret, newCaret);
};

document.addEventListener("input", (event) => {
	const target = event.target;

	if (!(target instanceof HTMLInputElement)) {
		return;
	}

	if (target.closest(".wc-block-components-address-form__pagbank-cpf")) {
		applyMask(target, formatCpf, false);
		return;
	}

	if (target.closest(".wc-block-components-address-form__pagbank-cnpj")) {
		applyMask(
			target,
			(value) => formatCnpj(value, config.alphanumeric_cnpj),
			config.alphanumeric_cnpj,
		);
		return;
	}

	if (target.closest(".wc-block-components-address-form__pagbank-cellphone")) {
		applyMask(target, formatCellphone, false);
	}
});

// =============================================================================
// Address card enhancement
// =============================================================================
//
// The collapsed address card renders the address through a formatter with a
// hardcoded placeholder list, so the additional fields (number, neighborhood,
// CPF/CNPJ) never appear on it and WooCommerce offers no extension point.
// As a workaround the card DOM is post-processed with the values read from
// the cart data store: the number/neighborhood/cellphone are woven into the
// summary text and an obfuscated document line (with the Razão Social for
// legal persons) is appended. React re-renders discard the changes, so a
// MutationObserver (plus a data-store subscription)
// re-applies them; every write is equality-guarded to avoid observer loops.
// If WooCommerce changes the card markup this degrades gracefully back to
// the default summary.

const EXTRA_LINE_CLASS = "pagbank-address-card-extra";

const getCustomerData = (): {
	billingAddress?: StoreAddress;
	shippingAddress?: StoreAddress;
} => window.wp?.data?.select?.("wc/store/cart")?.getCustomerData?.() ?? {};

const cardTarget = (card: HTMLElement): "billing" | "shipping" => {
	const controls = card
		.querySelector(".wc-block-components-address-card__edit")
		?.getAttribute("aria-controls");

	if ("shipping" === controls || "billing" === controls) {
		return controls;
	}

	return card.closest("#shipping-fields, .wc-block-checkout__shipping-fields")
		? "shipping"
		: "billing";
};

/**
 * Weave the address number (after the street), the neighborhood (before the
 * city) and the cellphone (at the end) into the card summary text.
 */
const enhanceSummaryText = (text: string, address: StoreAddress): string => {
	let result = text;

	const address1 = address.address_1 ?? "";
	const number = address["pagbank/address-number"] ?? "";

	if (address1 && number) {
		const withNumber = `${address1}, ${number}`;

		if (!result.includes(withNumber) && result.startsWith(address1)) {
			result = withNumber + result.slice(address1.length);
		}
	}

	const neighborhood = address["pagbank/neighborhood"] ?? "";
	const city = address.city ?? "";

	if (neighborhood && city && !result.includes(neighborhood)) {
		const cityIndex = result.lastIndexOf(`, ${city}`);

		if (cityIndex >= 0) {
			result = `${result.slice(0, cityIndex)}, ${neighborhood}${result.slice(cityIndex)}`;
		}
	}

	// The cellphone goes at the end of the summary, the same way WooCommerce
	// appends the core phone field.
	const cellphone = address["pagbank/cellphone"] ?? "";

	if (cellphone && !result.includes(cellphone)) {
		result = `${result}, ${cellphone}`;
	}

	return result;
};

/**
 * Build the extra billing card lines: the obfuscated CPF/CNPJ, with the
 * Razão Social alongside for legal persons.
 */
const buildExtraLines = (address: StoreAddress): string[] => {
	const persontype = address["pagbank/persontype"] ?? "";
	const cnpj = address["pagbank/cnpj"] ?? "";
	const cpf = address["pagbank/cpf"] ?? "";
	const isLegalPerson = "2" === persontype || ("" === persontype && "" !== cnpj);
	const lines: string[] = [];

	const documentValue = isLegalPerson ? cnpj : cpf;

	if (documentValue) {
		let documentLine = `${isLegalPerson ? "CNPJ" : "CPF"}: ${maskDocumentForDisplay(documentValue)}`;

		if (isLegalPerson) {
			// Razão Social lives in the pagbank/company field when the store
			// hides the core company field, otherwise in the core field itself.
			const company = address["pagbank/company"] || address.company || "";

			if (company) {
				documentLine += ` (${company})`;
			}
		}

		lines.push(documentLine);
	}

	return lines;
};

/**
 * Add (or update) the extra lines on the billing address card, one
 * address-section span per line (matching the card's own markup).
 */
const upsertExtraLines = (card: HTMLElement, address: StoreAddress): void => {
	const lines = buildExtraLines(address);
	const existing = Array.from(card.querySelectorAll<HTMLElement>(`.${EXTRA_LINE_CLASS}`));

	if (
		existing.length === lines.length &&
		existing.every((element, index) => element.textContent === lines[index])
	) {
		return;
	}

	for (const element of existing) {
		element.remove();
	}

	const addressElement = card.querySelector("address");

	if (!addressElement) {
		return;
	}

	for (const text of lines) {
		const line = document.createElement("span");
		line.className = `wc-block-components-address-card__address-section ${EXTRA_LINE_CLASS}`;
		line.textContent = text;
		addressElement.appendChild(line);
	}
};

const enhanceAddressCards = (): void => {
	const data = getCustomerData();

	document.querySelectorAll<HTMLElement>(".wc-block-components-address-card").forEach((card) => {
		const target = cardTarget(card);
		const address = "shipping" === target ? data.shippingAddress : data.billingAddress;

		if (!address) {
			return;
		}

		const summary = card.querySelector<HTMLElement>(
			".wc-block-components-address-card__address-section--secondary",
		);

		if (summary) {
			const enhanced = enhanceSummaryText(summary.textContent ?? "", address);

			if (enhanced !== summary.textContent) {
				summary.textContent = enhanced;
			}
		}

		if ("billing" === target) {
			upsertExtraLines(card, address);
		}
	});
};

let enhanceScheduled = false;

const scheduleEnhance = (): void => {
	if (enhanceScheduled) {
		return;
	}

	enhanceScheduled = true;

	window.requestAnimationFrame(() => {
		enhanceScheduled = false;
		enhanceAddressCards();
	});
};

const initAddressCardEnhancement = (): void => {
	if (!document.querySelector(".wp-block-woocommerce-checkout")) {
		return;
	}

	new MutationObserver(scheduleEnhance).observe(document.body, {
		childList: true,
		subtree: true,
		characterData: true,
	});

	window.wp?.data?.subscribe?.(scheduleEnhance);
	scheduleEnhance();
};

if ("loading" === document.readyState) {
	document.addEventListener("DOMContentLoaded", initAddressCardEnhancement);
} else {
	initAddressCardEnhancement();
}
