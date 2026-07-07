/**
 * Input mask helpers for the Brazilian checkout fields (CPF, CNPJ, cellphone).
 *
 * Shared between the classic checkout entry (plain inputs) and the Blocks
 * checkout entry (React-controlled inputs). The CNPJ helpers accept letters
 * in the first 12 positions when the alphanumeric CNPJ format is enabled
 * (new format starting July 2026); the two check digits are always numeric.
 */

const CPF_LENGTH = 11;
const CNPJ_LENGTH = 14;

export const sanitizeCpf = (value: string): string =>
	value.replace(/[^0-9]/g, "").slice(0, CPF_LENGTH);

/**
 * Strip the CNPJ input to its significant characters.
 */
export const sanitizeCnpj = (value: string, alphanumeric: boolean): string => {
	const allowed = alphanumeric ? /[^0-9A-Za-z]/g : /[^0-9]/g;

	let sanitized = value.replace(allowed, "").toUpperCase().slice(0, CNPJ_LENGTH);

	if (sanitized.length > 12) {
		// The last two positions (check digits) are always numeric.
		sanitized = sanitized.slice(0, 12) + sanitized.slice(12).replace(/[^0-9]/g, "");
	}

	return sanitized;
};

export const formatCpf = (value: string): string => {
	const digits = sanitizeCpf(value);
	const parts = [digits.slice(0, 3), digits.slice(3, 6), digits.slice(6, 9), digits.slice(9, 11)];

	let formatted = parts[0];
	if (parts[1]) formatted += `.${parts[1]}`;
	if (parts[2]) formatted += `.${parts[2]}`;
	if (parts[3]) formatted += `-${parts[3]}`;

	return formatted;
};

export const formatCnpj = (value: string, alphanumeric: boolean): string => {
	const chars = sanitizeCnpj(value, alphanumeric);
	const parts = [
		chars.slice(0, 2),
		chars.slice(2, 5),
		chars.slice(5, 8),
		chars.slice(8, 12),
		chars.slice(12, 14),
	];

	let formatted = parts[0];
	if (parts[1]) formatted += `.${parts[1]}`;
	if (parts[2]) formatted += `.${parts[2]}`;
	if (parts[3]) formatted += `/${parts[3]}`;
	if (parts[4]) formatted += `-${parts[4]}`;

	return formatted;
};

export const sanitizePhone = (value: string): string => value.replace(/[^0-9]/g, "").slice(0, 11);

export const formatCellphone = (value: string): string => {
	const digits = sanitizePhone(value);

	if (digits.length === 0) {
		return "";
	}

	if (digits.length <= 2) {
		return `(${digits}`;
	}

	// Landline-length numbers use (00) 0000-0000; mobile uses (00) 00000-0000.
	const splitAt = digits.length <= 10 ? 6 : 7;
	const prefix = digits.slice(2, splitAt);
	const suffix = digits.slice(splitAt);

	let formatted = `(${digits.slice(0, 2)}) ${prefix}`;
	if (suffix) formatted += `-${suffix}`;

	return formatted;
};

const significantPattern = (alphanumeric: boolean): RegExp =>
	alphanumeric ? /[0-9A-Za-z]/ : /[0-9]/;

/**
 * Obfuscate a formatted document (CPF/CNPJ) for display, keeping only the
 * first and last `visible` significant characters (mask punctuation is
 * preserved): "062.556.385-96" -> "062.***.**5-96".
 */
export const maskDocumentForDisplay = (formatted: string, visible = 3): string => {
	const significant = /[0-9A-Za-z]/;
	const total = formatted.split("").filter((char) => significant.test(char)).length;

	let seen = 0;

	return formatted
		.split("")
		.map((char) => {
			if (!significant.test(char)) {
				return char;
			}

			seen++;

			return seen <= visible || seen > total - visible ? char : "*";
		})
		.join("");
};

/**
 * Count the significant (non-mask) characters before the caret so the caret
 * can be restored to the equivalent position after reformatting.
 */
export const countSignificantChars = (
	value: string,
	caret: number,
	alphanumeric: boolean,
): number => {
	const significant = significantPattern(alphanumeric);

	let count = 0;

	for (let i = 0; i < caret && i < value.length; i++) {
		if (significant.test(value[i])) {
			count++;
		}
	}

	return count;
};

export const caretPositionForCount = (
	formatted: string,
	count: number,
	alphanumeric: boolean,
): number => {
	if (count === 0) {
		return 0;
	}

	const significant = significantPattern(alphanumeric);

	let seen = 0;

	for (let i = 0; i < formatted.length; i++) {
		if (significant.test(formatted[i])) {
			seen++;

			if (seen === count) {
				return i + 1;
			}
		}
	}

	return formatted.length;
};
