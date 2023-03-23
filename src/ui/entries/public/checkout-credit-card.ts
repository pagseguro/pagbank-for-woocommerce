import cardValidator from "card-validator";
import first from "lodash/first";

declare const jQuery: any;

type PagBankCardEncryptedErrors =
	| "INVALID_NUMBER"
	| "INVALID_SECURITY_CODE"
	| "INVALID_EXPIRATION_MONTH"
	| "INVALID_EXPIRATION_YEAR"
	| "INVALID_PUBLIC_KEY"
	| "INVALID_HOLDER";

interface PagBankCard {
	publicKey: string;
	holder: string;
	number: string;
	expMonth: string;
	expYear: string;
	securityCode: string;
}

interface PagBankCardEncrypted {
	hasErrors: boolean;
	errors: Array<{ code: PagBankCardEncryptedErrors }>;
	encryptedCard: string;
}

declare const PagSeguro: {
	encryptCard: (card: PagBankCard) => PagBankCardEncrypted;
};

declare const PagBankCheckoutCreditCardVariables: {
	publicKey: string;
	messages: {
		invalid_public_key: string;
		invalid_holder_name: string;
		invalid_card_number: string;
		invalid_card_expiry_date: string;
		invalid_security_code: string;
		invalid_encrypted_card: string;
	};
};

const scrollToNotices = (): void => {
	let scrollElement = jQuery(
		".woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout"
	);

	const hasScrollElements = scrollElement.length > 0;

	if (!hasScrollElements) {
		scrollElement = jQuery("form.checkout");
	}

	jQuery.scroll_to_notices(scrollElement);
};

const submitCheckoutError = (errorMessage: string): void => {
	jQuery(".woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message").remove();

	const $checkoutForm = jQuery("form.checkout");

	$checkoutForm.prepend(
		'<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' +
			'<ul class="woocommerce-error" role="alert">' +
			"<li>" +
			errorMessage +
			"</li>" +
			"</ul>" +
			"</div>"
	); // eslint-disable-line max-len
	$checkoutForm.removeClass("processing").unblock();
	$checkoutForm.find(".input-text, select, input:checkbox").trigger("validate").trigger("blur");

	scrollToNotices();

	jQuery(document.body).trigger("checkout_error", [errorMessage]);
};

// eslint-disable-next-line no-undef
jQuery("form.checkout").on("checkout_place_order_pagbank_credit_card", () => {
	try {
		const cardHolderInput = document.getElementById(
			"pagbank_credit_card-card-holder"
		) as HTMLInputElement | null;
		const cardNumberInput = document.getElementById(
			"pagbank_credit_card-card-number"
		) as HTMLInputElement | null;
		const cardExpiryInput = document.getElementById(
			"pagbank_credit_card-card-expiry"
		) as HTMLInputElement | null;
		const cardCvcInput = document.getElementById(
			"pagbank_credit_card-card-cvc"
		) as HTMLInputElement | null;

		if (
			cardHolderInput == null ||
			cardNumberInput == null ||
			cardExpiryInput == null ||
			cardCvcInput == null
		) {
			throw new Error("Inputs not found");
		}

		const convertTwoDigitsYearToFourDigits = (year: string): string => {
			if (year.length === 2) {
				return `20${year}`;
			}

			return year;
		};

		const validateCard = ({
			holder,
			number,
			expiryDate,
			cvc,
		}: {
			holder: string;
			number: string;
			expiryDate: string;
			cvc: string;
		}): {
			holder: string;
			number: string;
			expirationDate: {
				month: string;
				year: string;
			};
			cvc: string;
		} => {
			const cardHolderValidation = cardValidator.cardholderName(holder);
			if (!cardHolderValidation.isValid) {
				throw new Error(PagBankCheckoutCreditCardVariables.messages.invalid_holder_name);
			}

			const cardNumberValidation = cardValidator.number(number);
			if (cardNumberValidation.card == null || !cardNumberValidation.isValid) {
				throw new Error(PagBankCheckoutCreditCardVariables.messages.invalid_card_number);
			}

			const cardExpirationDateValidation = cardValidator.expirationDate(expiryDate);
			if (!cardExpirationDateValidation.isValid) {
				throw new Error(
					PagBankCheckoutCreditCardVariables.messages.invalid_card_expiry_date
				);
			}

			const cardCvcValidation = cardValidator.cvv(cvc);
			if (!cardCvcValidation.isValid) {
				throw new Error(PagBankCheckoutCreditCardVariables.messages.invalid_security_code);
			}

			return {
				holder: holder.trim(),
				number: number.replace(/\s/g, ""),
				expirationDate: {
					month: cardExpirationDateValidation.month as string,
					year: convertTwoDigitsYearToFourDigits(
						cardExpirationDateValidation.year as string
					),
				},
				cvc: cvc.trim(),
			};
		};

		const card = validateCard({
			holder: cardHolderInput.value,
			number: cardNumberInput.value,
			expiryDate: cardExpiryInput.value,
			cvc: cardCvcInput.value,
		});

		const encryptedCard = PagSeguro.encryptCard({
			publicKey: PagBankCheckoutCreditCardVariables.publicKey,
			holder: card.holder,
			number: card.number,
			expMonth: card.expirationDate.month,
			expYear: card.expirationDate.year,
			securityCode: card.cvc,
		});

		const messages: Record<PagBankCardEncryptedErrors, string> = {
			INVALID_NUMBER: PagBankCheckoutCreditCardVariables.messages.invalid_card_number,
			INVALID_SECURITY_CODE:
				PagBankCheckoutCreditCardVariables.messages.invalid_security_code,
			INVALID_EXPIRATION_MONTH:
				PagBankCheckoutCreditCardVariables.messages.invalid_card_expiry_date,
			INVALID_EXPIRATION_YEAR:
				PagBankCheckoutCreditCardVariables.messages.invalid_card_expiry_date,
			INVALID_PUBLIC_KEY: PagBankCheckoutCreditCardVariables.messages.invalid_public_key,
			INVALID_HOLDER: PagBankCheckoutCreditCardVariables.messages.invalid_holder_name,
		};

		if (encryptedCard.hasErrors) {
			const errors = encryptedCard.errors.map((item) => messages[item.code]);

			throw new Error(first(errors));
		}

		const encryptedCardInput = document.getElementById(
			"pagbank_credit_card-encrypted-card"
		) as HTMLInputElement | null;

		if (encryptedCardInput == null) {
			throw new Error(PagBankCheckoutCreditCardVariables.messages.invalid_encrypted_card);
		}

		encryptedCardInput.value = encryptedCard.encryptedCard;

		return true;
	} catch (error: any) {
		submitCheckoutError(error.message);

		return false;
	}
});
