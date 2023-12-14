import axios from "axios";
import cardValidator from "card-validator";
import first from "lodash/first";

// eslint-disable-next-line @typescript-eslint/no-explicit-any
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
	messages: {
		invalid_public_key: string;
		invalid_holder_name: string;
		invalid_card_number: string;
		invalid_card_expiry_date: string;
		invalid_security_code: string;
		invalid_encrypted_card: string;
		invalid_card_bin: string;
	};
	settings: {
		installments_enabled: boolean;
		maximum_installments: number;
		transfer_of_interest_enabled: boolean;
		maximum_installments_interest_free: number;
		card_public_key: string;
	};
};

const scrollToNotices = (): void => {
	let scrollElement = jQuery(
		".woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout",
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
			"</div>",
	); // eslint-disable-line max-len
	$checkoutForm.removeClass("processing").unblock();
	$checkoutForm.find(".input-text, select, input:checkbox").trigger("validate").trigger("blur");

	scrollToNotices();

	jQuery(document.body).trigger("checkout_error", [errorMessage]);
};

// eslint-disable-next-line no-undef
jQuery("form.checkout").on("checkout_place_order_pagbank_credit_card", () => {
	const selectedPaymentToken = document.querySelector(
		"[name=wc-pagbank_credit_card-payment-token]:checked",
	) as HTMLInputElement;

	if (selectedPaymentToken !== null && selectedPaymentToken.value !== "new") {
		return true;
	}

	try {
		const cardHolderInput = document.getElementById(
			"pagbank_credit_card-card-holder",
		) as HTMLInputElement | null;
		const cardNumberInput = document.getElementById(
			"pagbank_credit_card-card-number",
		) as HTMLInputElement | null;
		const cardExpiryInput = document.getElementById(
			"pagbank_credit_card-card-expiry",
		) as HTMLInputElement | null;
		const cardCvcInput = document.getElementById(
			"pagbank_credit_card-card-cvc",
		) as HTMLInputElement | null;

		if (
			cardHolderInput == null ||
			cardNumberInput == null ||
			cardExpiryInput == null ||
			cardCvcInput == null
		) {
			throw new Error(
				"Não foi possível encontrar os campos do cartão de crédito. Entre em contato com nosso suporte.",
			);
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
					PagBankCheckoutCreditCardVariables.messages.invalid_card_expiry_date,
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
						cardExpirationDateValidation.year as string,
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
			publicKey: PagBankCheckoutCreditCardVariables.settings.card_public_key,
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
			"pagbank_credit_card-encrypted-card",
		) as HTMLInputElement | null;

		const cardBinInput = document.getElementById(
			"pagbank_credit_card-card-bin",
		) as HTMLInputElement | null;

		if (encryptedCardInput == null) {
			throw new Error(PagBankCheckoutCreditCardVariables.messages.invalid_encrypted_card);
		} else if (cardBinInput === null) {
			throw new Error(PagBankCheckoutCreditCardVariables.messages.invalid_card_bin);
		}

		encryptedCardInput.value = encryptedCard.encryptedCard;
		cardBinInput.value = card.number.substring(0, 6);

		return true;
	} catch (error: unknown) {
		if (error instanceof Error) {
			submitCheckoutError(error.message);
		}

		return false;
	}
});

jQuery(document.body).on("updated_checkout", () => {
	try {
		const shouldContinue =
			PagBankCheckoutCreditCardVariables.settings.installments_enabled &&
			PagBankCheckoutCreditCardVariables.settings.transfer_of_interest_enabled;
		if (!shouldContinue) {
			return;
		}

		const $container = jQuery("#order_review");

		const installmentsSelect = document.getElementById(
			"pagbank_credit_card-installments",
		) as HTMLSelectElement;

		const cardNumberInput = document.getElementById(
			"pagbank_credit_card-card-number",
		) as HTMLInputElement;

		if (installmentsSelect === null) {
			throw new Error("Installments select not found");
		}

		if (cardNumberInput === null) {
			throw new Error("Card number input not found");
		}

		const nonce = installmentsSelect.getAttribute("data-nonce");
		const amount = installmentsSelect.getAttribute("data-amount");
		const url = installmentsSelect.getAttribute("data-url");

		if (nonce === null || amount === null || url === null) {
			throw new Error("Invalid nonce, amount or url");
		}

		const paymentTokensInputs = document.querySelectorAll(
			"[name=wc-pagbank_credit_card-payment-token]",
		);

		const setContainerLoading = (state: boolean): void => {
			if (state) {
				$container.addClass("processing").block({
					message: null,
					overlayCSS: {
						background: "#fff",
						opacity: 0.6,
					},
				});
			} else {
				$container.removeClass("processing").unblock();
			}
		};

		const setInstallments = (
			plans: Array<{
				installments: number;
				installment_value: number;
				interest_free: number;
				title: string;
				amount: number;
			}>,
		): void => {
			installmentsSelect.innerHTML = "";

			plans.forEach((plan) => {
				installmentsSelect.appendChild(
					new Option(plan.title, plan.installments.toString(), plan.installments === 1),
				);
			});

			installmentsSelect.removeAttribute("disabled");
		};

		type GetInstallmentsDto =
			| {
					type: "card_bin";
					nonce: string;
					amount: string;
					cardBin: string;
			  }
			| {
					type: "payment_token";
					nonce: string;
					amount: string;
					paymentToken: string;
			  };

		const getInstallments = async (data: GetInstallmentsDto): Promise<void> => {
			setContainerLoading(true);

			try {
				const { data: result } = await axios.get<{
					success: boolean;
					data: Array<{
						installments: number;
						installment_value: number;
						interest_free: number;
						title: string;
						amount: number;
					}>;
				}>(url, {
					params: {
						nonce: data.nonce,
						amount: data.amount,
						card_bin: data.type === "card_bin" ? data.cardBin : undefined,
						payment_token:
							data.type === "payment_token" ? data.paymentToken : undefined,
					},
				});
				setInstallments(result.data);
			} catch (error) {
				const cardNumberInput = document.getElementById(
					"pagbank_credit_card-card-number",
				) as HTMLInputElement | null;

				if (cardNumberInput != null) {
					cardNumberInput.value = "";
				}

				submitCheckoutError("Não foi possível calcular as parcelas. Tente novamente.");
			} finally {
				setContainerLoading(false);
			}
		};

		const handleChangePaymentToken = async (paymentToken: string): Promise<void> => {
			installmentsSelect.innerHTML = "";
			installmentsSelect.setAttribute("disabled", "disabled");
			if (paymentToken === "new") {
				const cardBin = cardNumberInput.value.replace(/\s/g, "").substring(0, 6);
				if (cardBin !== null && cardBin.length === 6) {
					getInstallments({
						type: "card_bin",
						nonce,
						amount,
						cardBin,
					});
				}
			} else {
				getInstallments({
					type: "payment_token",
					nonce,
					amount,
					paymentToken,
				});
			}
		};

		paymentTokensInputs.forEach((paymentTokenInput) => {
			paymentTokenInput.addEventListener("change", (event) => {
				const target = event.target as HTMLInputElement;
				if (target.checked) {
					handleChangePaymentToken(target.value);
				}
			});
		});

		const init = (): void => {
			const selectedPaymentToken = document.querySelector(
				"[name=wc-pagbank_credit_card-payment-token]:checked",
			) as HTMLInputElement;

			handleChangePaymentToken(
				selectedPaymentToken === null ? "new" : selectedPaymentToken.value,
			);

			cardNumberInput.addEventListener("change", () => {
				handleChangePaymentToken("new");
			});
		};

		init();
	} catch (error) {
		if (error instanceof Error) {
			submitCheckoutError(error.message);
		} else {
			submitCheckoutError("Unknown error");
		}
	}
});
