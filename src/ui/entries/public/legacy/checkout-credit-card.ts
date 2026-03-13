import axios from "axios";
import cardValidator from "card-validator";
import escapeHtml from "escape-html";
import jQuery from "jquery";

type CardBin = {
	type: "card_bin";
	nonce: string;
	amount: string;
	cardBin: string;
};

type PaymentToken = {
	type: "payment_token";
	nonce: string;
	amount: string;
	paymentToken: string;
};

type GetInstallmentsDto = CardBin | PaymentToken;

interface ThreeDSSession {
	session: string;
	expires_at: number;
}

interface ThreeDSResult {
	status: "AUTH_FLOW_COMPLETED" | "AUTH_NOT_SUPPORTED" | "CHANGE_PAYMENT_METHOD";
	id?: string;
}

let cachedSession: ThreeDSSession | null = null;

declare const PagBankCheckoutCreditCardVariables: {
	messages: {
		invalid_public_key: string;
		invalid_holder_name: string;
		invalid_card_number: string;
		invalid_card_expiry_date: string;
		invalid_security_code: string;
		invalid_encrypted_card: string;
		invalid_card_bin: string;
		threeds_session_error: string;
		threeds_auth_error: string;
		threeds_change_payment_method: string;
		invalid_cellphone: string;
		threeds_not_supported: string;
	};
	settings: {
		installments_enabled: boolean;
		maximum_installments: number;
		transfer_of_interest_enabled: boolean;
		maximum_installments_interest_free: number;
		card_public_key: string;
		threeds_enabled: boolean;
		api_3ds_session_url: string;
		threeds_nonce: string;
		environment: "sandbox" | "production";
	};
};

const wcForms = {
	checkout: jQuery("form.checkout"),
	orderReview: jQuery("form#order_review"),
};

const scrollToNotices = (): void => {
	const isOrderReview = jQuery(document.body).hasClass("woocommerce-order-pay");
	const $form = isOrderReview ? wcForms.orderReview : wcForms.checkout;

	let scrollElement = jQuery(
		isOrderReview
			? ".woocommerce-notices-wrapper"
			: ".woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout",
	);

	const hasScrollElements = scrollElement.length > 0;

	if (!hasScrollElements) {
		scrollElement = $form;
	}

	if (scrollElement.length > 0) {
		jQuery("html, body").animate(
			{
				scrollTop: (scrollElement.offset()?.top ?? 0) - 100,
			},
			1000,
		);
	}
};

const clearCheckoutErrors = (): void => {
	const $container = jQuery(".woocommerce-notices-wrapper");

	$container.empty();
};

const fetch3DSSession = async (): Promise<ThreeDSSession> => {
	if (cachedSession && cachedSession.expires_at > Date.now() / 1000) {
		return cachedSession;
	}

	const { data } = await axios.get<{ success: boolean; data: ThreeDSSession }>(
		PagBankCheckoutCreditCardVariables.settings.api_3ds_session_url,
		{
			params: {
				nonce: PagBankCheckoutCreditCardVariables.settings.threeds_nonce,
			},
		},
	);

	if (!data.success) {
		throw new Error(PagBankCheckoutCreditCardVariables.messages.threeds_session_error);
	}

	cachedSession = data.data;
	return cachedSession;
};

const authenticate3DS = async (params: {
	cardNumber: string;
	expMonth: string;
	expYear: string;
	cardHolder: string;
	amount: number;
	installments: number;
}): Promise<ThreeDSResult> => {
	const session = await fetch3DSSession();

	const env =
		PagBankCheckoutCreditCardVariables.settings.environment === "production"
			? "PROD"
			: "SANDBOX";

	PagSeguro.setUp({
		session: session.session,
		env,
	});

	return new Promise((resolve, reject) => {
		PagSeguro.authenticate3DS({
			data: {
				customer: {
					name: params.cardHolder,
					email: "",
				},
				paymentMethod: {
					type: "CREDIT_CARD",
					installments: params.installments,
					card: {
						number: params.cardNumber,
						expMonth: params.expMonth,
						expYear: params.expYear,
						holder: {
							name: params.cardHolder,
						},
					},
				},
				amount: {
					value: params.amount,
					currency: "BRL",
				},
				billingAddress: {
					street: "",
					number: "",
					regionCode: "",
					country: "BRA",
					city: "",
					postalCode: "",
				},
				dataOnly: false,
			},
			beforeChallenge: (challengeInfo) => {
				challengeInfo.open();
			},
			onSuccess: (result: PagSeguro3DSAuthenticationResponse) => {
				resolve({
					status: result.status as ThreeDSResult["status"],
					id: result.id,
				});
			},
			onFailure: () => {
				reject(new Error(PagBankCheckoutCreditCardVariables.messages.threeds_auth_error));
			},
			onError: () => {
				reject(new Error(PagBankCheckoutCreditCardVariables.messages.threeds_auth_error));
			},
		});
	});
};

const submitCheckoutError = (errorMessage: string): void => {
	const isOrderReview = jQuery(document.body).hasClass("woocommerce-order-pay");

	if (isOrderReview) {
		const $container = jQuery(".woocommerce-notices-wrapper");
		const $form = jQuery("form#order_review");

		$container.empty();

		$container.append(
			'<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' +
				'<ul class="woocommerce-error" role="alert">' +
				"<li>" +
				escapeHtml(errorMessage) +
				"</li>" +
				"</ul>" +
				"</div>",
		);

		// Needs to trigger in the next tick, because the order review form will be submitted again.
		setTimeout(() => {
			$form.removeClass("processing").unblock();
		}, 500);

		$form.find(".input-text, select, input:checkbox").trigger("validate").trigger("blur");

		scrollToNotices();
	} else {
		jQuery(
			".woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message",
		).remove();

		const $checkoutForm = jQuery("form.checkout");

		$checkoutForm.prepend(
			'<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' +
				'<ul class="woocommerce-error" role="alert">' +
				"<li>" +
				escapeHtml(errorMessage) +
				"</li>" +
				"</ul>" +
				"</div>",
		);
		$checkoutForm.removeClass("processing").unblock();
		$checkoutForm
			.find(".input-text, select, input:checkbox")
			.trigger("validate")
			.trigger("blur");

		scrollToNotices();

		jQuery(document.body).trigger("checkout_error", [escapeHtml(errorMessage)]);
	}
};

const processEncryptedCard = async (): Promise<boolean> => {
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

		const messages: Record<PagSeguroEncryptCardErrorCode, string> = {
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

			throw new Error(errors[0]);
		}

		const encryptedCardInput = document.getElementById(
			"pagbank_credit_card-encrypted-card",
		) as HTMLInputElement | null;

		const cardBinInput = document.getElementById(
			"pagbank_credit_card-card-bin",
		) as HTMLInputElement | null;

		const threedsIdInput = document.getElementById(
			"pagbank_credit_card-threeds-id",
		) as HTMLInputElement | null;

		if (!encryptedCardInput) {
			throw new Error(PagBankCheckoutCreditCardVariables.messages.invalid_encrypted_card);
		} else if (!cardBinInput) {
			throw new Error(PagBankCheckoutCreditCardVariables.messages.invalid_card_bin);
		} else if (!encryptedCard.encryptedCard) {
			throw new Error(PagBankCheckoutCreditCardVariables.messages.invalid_encrypted_card);
		}

		encryptedCardInput.value = encryptedCard.encryptedCard;
		cardBinInput.value = card.number.substring(0, 6);

		// 3DS Authentication
		if (PagBankCheckoutCreditCardVariables.settings.threeds_enabled) {
			const installmentsSelect = document.getElementById(
				"pagbank_credit_card-installments",
			) as HTMLSelectElement | null;

			const installments = installmentsSelect
				? Number.parseInt(installmentsSelect.value, 10)
				: 1;
			const amount = installmentsSelect
				? Number.parseInt(installmentsSelect.getAttribute("data-amount") || "0", 10)
				: 0;

			const result = await authenticate3DS({
				cardNumber: card.number,
				expMonth: card.expirationDate.month,
				expYear: card.expirationDate.year,
				cardHolder: card.holder,
				amount,
				installments,
			});

			if (result.status === "CHANGE_PAYMENT_METHOD") {
				throw new Error(
					PagBankCheckoutCreditCardVariables.messages.threeds_change_payment_method,
				);
			}

			// AUTH_NOT_SUPPORTED: Block the transaction - 3DS authentication is required
			if (result.status === "AUTH_NOT_SUPPORTED") {
				throw new Error(PagBankCheckoutCreditCardVariables.messages.threeds_not_supported);
			}

			if (result.status === "AUTH_FLOW_COMPLETED" && result.id && threedsIdInput) {
				threedsIdInput.value = result.id;
			}
		}

		return true;
	} catch (error: unknown) {
		if (error instanceof Error) {
			submitCheckoutError(error.message);
		}

		return false;
	}
};

wcForms.orderReview.on("submit", async (event: JQuery.SubmitEvent) => {
	event.preventDefault();

	const isPagBankCreditCard = jQuery(
		"input#payment_method_pagbank_credit_card[name=payment_method]",
		event.currentTarget,
	).is(":checked");

	const shouldContinue = !isPagBankCreditCard || (await processEncryptedCard());

	if (shouldContinue) {
		if (!isPagBankCreditCard) {
			clearCheckoutErrors();
		}

		event.currentTarget.submit();
	}
});

wcForms.checkout.on("checkout_place_order_pagbank_credit_card", async () => {
	const success = await processEncryptedCard();

	if (success) {
		wcForms.checkout.trigger("submit");
	}
});

const bootstrapCheckout = () => {
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
				console.error(error);

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
};

jQuery(document.body).on("updated_checkout", bootstrapCheckout);

jQuery(() => {
	const isOrderReview = jQuery(document.body).hasClass("woocommerce-order-pay");

	if (isOrderReview) {
		bootstrapCheckout();
	}
});
