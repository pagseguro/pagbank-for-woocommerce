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
} from "@/shared/masks";

interface BlocksCheckoutFieldsConfig {
	alphanumeric_cnpj: boolean;
}

declare global {
	interface Window {
		PagBankBlocksCheckoutFieldsConfig?: BlocksCheckoutFieldsConfig;
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
