/**
 * CPF/CNPJ input masks for the PagBank fields on the admin order edit screen.
 *
 * Blocks orders render inputs with ids like `_wc_billing/pagbank/cpf`;
 * classic-checkout orders use the interop ids (`_billing_cpf`,
 * `_shipping_cnpj`, ...). Fields are matched by id via a delegated listener
 * (the billing/shipping panels are re-rendered when the address edit form is
 * toggled). The interop ids are only masked when the document group is ours —
 * when the Brazilian Market plugin provides it, its own masks apply.
 *
 * The cellphone field is intentionally not masked: it stores the
 * international format ("+55 27 98169-1098"), which the local checkout mask
 * would mangle.
 */

import {
	caretPositionForCount,
	countSignificantChars,
	formatCnpj,
	formatCpf,
} from "@/shared/masks";

interface AdminOrderFieldsConfig {
	alphanumeric_cnpj: boolean;
	interop_document_masks: boolean;
}

declare global {
	interface Window {
		PagBankAdminOrderFieldsConfig?: AdminOrderFieldsConfig;
	}
}

// The fallback mirrors the PHP defaults (the alphanumeric CNPJ feature flag
// defaults to enabled), in case the inline config script is stripped by an
// optimizer plugin.
const config: AdminOrderFieldsConfig = window.PagBankAdminOrderFieldsConfig ?? {
	alphanumeric_cnpj: true,
	interop_document_masks: true,
};

const matchesDocumentField = (id: string, suffix: "cpf" | "cnpj"): boolean => {
	if (id.endsWith(`pagbank/${suffix}`)) {
		return true;
	}

	return (
		config.interop_document_masks &&
		(id === `_billing_${suffix}` || id === `_shipping_${suffix}`)
	);
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

document.addEventListener("input", (event) => {
	const target = event.target;

	if (!(target instanceof HTMLInputElement)) {
		return;
	}

	if (matchesDocumentField(target.id, "cpf")) {
		applyMask(target, formatCpf, false);
		return;
	}

	if (matchesDocumentField(target.id, "cnpj")) {
		applyMask(
			target,
			(value) => formatCnpj(value, config.alphanumeric_cnpj),
			config.alphanumeric_cnpj,
		);
	}
});
