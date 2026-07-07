/**
 * Person type toggle and input masks for the native classic-checkout fields.
 *
 * - The document fields (person type, CPF, CNPJ, Razão Social) are only
 *   visible when the billing country is Brazil, mirroring the Blocks
 *   checkout behavior.
 * - The `billing_persontype` selector toggles between the CPF field (Pessoa
 *   física) and the CNPJ + Razão Social fields (Pessoa jurídica).
 * - CPF, CNPJ and cellphone inputs are masked as the customer types, and
 *   prefilled values are formatted on load.
 *
 * Uses delegated listeners so the behavior survives the classic checkout DOM
 * churn (`updated_checkout` fragment replacements).
 */

import jQuery from "jquery";

import {
	caretPositionForCount,
	countSignificantChars,
	formatCellphone,
	formatCnpj,
	formatCpf,
} from "@/shared/masks";

interface CheckoutFieldsConfig {
	alphanumeric_cnpj: boolean;
	document: boolean;
	cellphone: boolean;
}

declare global {
	interface Window {
		PagBankCheckoutFieldsConfig?: CheckoutFieldsConfig;
	}
}

// The fallback mirrors the PHP defaults (the alphanumeric CNPJ feature flag
// defaults to enabled), in case the inline config script is stripped by an
// optimizer plugin.
const config: CheckoutFieldsConfig = window.PagBankCheckoutFieldsConfig ?? {
	alphanumeric_cnpj: true,
	document: true,
	cellphone: true,
};

const applyMask = (
	input: HTMLInputElement,
	format: (value: string) => string,
	isAlphanumeric: boolean,
): void => {
	const caret = input.selectionStart ?? input.value.length;
	const significantBefore = countSignificantChars(input.value, caret, isAlphanumeric);
	const formatted = format(input.value);

	if (formatted === input.value) {
		return;
	}

	input.value = formatted;

	const newCaret = caretPositionForCount(formatted, significantBefore, isAlphanumeric);
	input.setSelectionRange(newCaret, newCaret);
};

/**
 * Show or hide a checkout field wrapper and toggle its required marker.
 */
const toggleFieldVisibility = (wrapperId: string, visible: boolean, required: boolean): void => {
	const wrapper = document.getElementById(wrapperId);

	if (!wrapper) {
		return;
	}

	wrapper.style.display = visible ? "" : "none";

	const label = wrapper.querySelector("label");

	if (!label) {
		return;
	}

	// Modern WooCommerce renders the marker as span.required; older versions
	// and some themes use abbr.required. Optional fields get span.optional.
	const marker = label.querySelector("abbr.required, span.required, span.optional");
	const isRequiredMarker = marker?.classList.contains("required") ?? false;

	if (required && visible) {
		if (!isRequiredMarker) {
			marker?.remove();
			label.insertAdjacentHTML(
				"beforeend",
				' <span class="required" aria-hidden="true">*</span>',
			);
		}
	} else if (isRequiredMarker) {
		marker?.remove();
	}
};

/**
 * Check if the selected billing country is Brazil.
 *
 * When the store sells to a single country, WooCommerce renders
 * `#billing_country` as a hidden input instead of a select. When the field
 * is absent entirely, assume Brazil (fail open — server-side validation only
 * applies to Brazil anyway).
 */
const billingCountryIsBrazil = (): boolean => {
	const country = document.getElementById("billing_country") as
		| HTMLSelectElement
		| HTMLInputElement
		| null;

	return !country || country.value === "BR";
};

/**
 * Apply the document fields visibility.
 *
 * The whole group (person type, CPF, CNPJ, Razão Social) only appears when
 * the billing country is Brazil — mirroring the Blocks checkout, where the
 * `pagbank/*` document fields are hidden for other countries. For Brazil,
 * Pessoa física shows CPF; Pessoa jurídica shows CNPJ and Razão Social.
 */
const applyPersonTypeVisibility = (): void => {
	const select = document.getElementById("billing_persontype") as HTMLSelectElement | null;

	if (!select) {
		return;
	}

	if (!billingCountryIsBrazil()) {
		toggleFieldVisibility("billing_persontype_field", false, false);
		toggleFieldVisibility("billing_cpf_field", false, false);
		toggleFieldVisibility("billing_cnpj_field", false, false);
		toggleFieldVisibility("billing_company_field", false, false);
		return;
	}

	const isLegalPerson = select.value === "2";

	toggleFieldVisibility("billing_persontype_field", true, true);
	toggleFieldVisibility("billing_cpf_field", !isLegalPerson, true);
	toggleFieldVisibility("billing_cnpj_field", isLegalPerson, true);
	toggleFieldVisibility("billing_company_field", isLegalPerson, true);
};

/**
 * Format prefilled values (e.g. from customer meta) on load.
 */
const formatPrefilledValues = (): void => {
	if (config.document) {
		const cpf = document.getElementById("billing_cpf") as HTMLInputElement | null;
		if (cpf?.value) {
			cpf.value = formatCpf(cpf.value);
		}

		const cnpj = document.getElementById("billing_cnpj") as HTMLInputElement | null;
		if (cnpj?.value) {
			cnpj.value = formatCnpj(cnpj.value, config.alphanumeric_cnpj);
		}
	}

	if (config.cellphone) {
		const cellphone = document.getElementById("billing_cellphone") as HTMLInputElement | null;
		if (cellphone?.value) {
			cellphone.value = formatCellphone(cellphone.value);
		}
	}
};

const init = (): void => {
	if (config.document) {
		applyPersonTypeVisibility();
	}

	formatPrefilledValues();
};

document.addEventListener("input", (event) => {
	const target = event.target;

	if (!(target instanceof HTMLInputElement)) {
		return;
	}

	if (config.document && target.id === "billing_cpf") {
		applyMask(target, formatCpf, false);
		return;
	}

	if (config.document && target.id === "billing_cnpj") {
		applyMask(
			target,
			(value) => formatCnpj(value, config.alphanumeric_cnpj),
			config.alphanumeric_cnpj,
		);
		return;
	}

	if (config.cellphone && target.id === "billing_cellphone") {
		applyMask(target, formatCellphone, false);
	}
});

document.addEventListener("change", (event) => {
	const target = event.target;

	if (!config.document) {
		return;
	}

	const isWatchedField =
		(target instanceof HTMLSelectElement || target instanceof HTMLInputElement) &&
		(target.id === "billing_persontype" || target.id === "billing_country");

	if (isWatchedField) {
		applyPersonTypeVisibility();
	}
});

if (document.readyState === "loading") {
	document.addEventListener("DOMContentLoaded", init);
} else {
	init();
}

// Re-apply after WooCommerce re-renders checkout fragments or address fields.
jQuery(document.body).on("updated_checkout country_to_state_changed", () => {
	if (config.document) {
		applyPersonTypeVisibility();
	}
});
