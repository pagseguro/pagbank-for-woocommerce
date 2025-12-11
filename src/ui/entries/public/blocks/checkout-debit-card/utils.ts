/**
 * Utility functions for PagBank Debit Card Blocks.
 * Reuses most utilities from credit card.
 *
 * @package PagBank_WooCommerce
 */

import cardValidator from "card-validator";

/**
 * Format card number with proper spacing based on card type.
 * Amex: 4-6-5 format, Others: 4-4-4-4 format.
 */
export const formatCardNumber = (value: string): string => {
	const digits = value.replace(/\D/g, "");
	const cardInfo = cardValidator.number(digits);
	const cardType = cardInfo.card?.type;

	// Amex uses 4-6-5 format (15 digits)
	if (cardType === "american-express") {
		const part1 = digits.substring(0, 4);
		const part2 = digits.substring(4, 10);
		const part3 = digits.substring(10, 15);
		return [part1, part2, part3].filter(Boolean).join(" ");
	}

	// Default: 4-4-4-4 format (16 digits)
	const groups = digits.match(/.{1,4}/g) || [];

	return groups.join(" ").substring(0, 19);
};

/**
 * Format expiry date as MM/YY.
 */
export const formatExpiry = (value: string, previousValue: string): string => {
	const digits = value.replace(/\D/g, "");

	// If user is deleting
	if (value.length < previousValue.length) {
		if (digits.length <= 2) {
			return digits;
		}
		return `${digits.substring(0, 2)}/${digits.substring(2, 4)}`;
	}

	// Auto-add slash after month
	if (digits.length >= 2) {
		return `${digits.substring(0, 2)}/${digits.substring(2, 4)}`;
	}

	return digits;
};

/**
 * Convert two digit year to four digits (e.g., "25" -> "2025").
 */
export const convertTwoDigitsYearToFourDigits = (year: string): string => {
	if (year.length === 2) {
		return `20${year}`;
	}

	return year;
};

/**
 * Extract card BIN (first 6 digits) from card number.
 */
export const getCardBin = (cardNumber: string): string => {
	return cardNumber.replace(/\s/g, "").substring(0, 6);
};
