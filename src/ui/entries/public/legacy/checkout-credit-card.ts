import axios from "axios";
import cardValidator from "card-validator";
import escapeHtml from "escape-html";
import jQuery from "jquery";
import parsePhoneNumber from "libphonenumber-js/mobile";

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

interface GatewayConfig {
	gateway_id: string;
	card_field_prefix: string;
	card_type: "CREDIT_CARD" | "DEBIT_CARD";
	messages: {
		inputs_not_found: string;
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
}

declare global {
	interface Window {
		PagBankLegacyCheckoutGateways?: Record<string, GatewayConfig>;
	}
}

const sessionCache = new Map<string, ThreeDSSession>();

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

const fetch3DSSession = async (gateway: GatewayConfig): Promise<ThreeDSSession> => {
	const cached = sessionCache.get(gateway.gateway_id);
	if (cached && cached.expires_at > Date.now() / 1000) {
		return cached;
	}

	const { data } = await axios.get<{ success: boolean; data: ThreeDSSession }>(
		gateway.settings.api_3ds_session_url,
		{
			params: {
				nonce: gateway.settings.threeds_nonce,
			},
		},
	);

	if (!data.success) {
		throw new Error(gateway.messages.threeds_session_error);
	}

	sessionCache.set(gateway.gateway_id, data.data);
	return data.data;
};

interface ThreeDSBillingAddress {
	street: string;
	number: string;
	regionCode: string;
	country: string;
	city: string;
	postalCode: string;
}

interface ThreeDSCustomerPhone {
	country: string;
	area: string;
	number: string;
	type: "MOBILE" | "HOME" | "BUSINESS";
}

interface ThreeDSAuthParams {
	cardNumber: string;
	expMonth: string;
	expYear: string;
	cardHolder: string;
	amount: number;
	installments: number;
	customer: {
		name: string;
		email: string;
		phones: ThreeDSCustomerPhone[];
	};
	billingAddress: ThreeDSBillingAddress;
}

// Mirror of ApiHelpers::sanitize_pagbank_name (PHP). PagBank's API rejects names
// with special characters, so any name sent to it (order API, 3DS payload) must
// be stripped to letters/digits/whitespace first.
const sanitizePagBankName = (name: string): string =>
	name
		.replace(/[^\p{L}\p{N}\s]+/gu, " ")
		.replace(/\s+/g, " ")
		.trim();

const authenticate3DS = async (
	gateway: GatewayConfig,
	params: ThreeDSAuthParams,
): Promise<ThreeDSResult> => {
	const session = await fetch3DSSession(gateway);

	const env = gateway.settings.environment === "production" ? "PROD" : "SANDBOX";

	PagSeguro.setUp({
		session: session.session,
		env,
	});

	try {
		// PagSeguro.authenticate3DS returns a Promise that rejects with
		// PagSeguroError (e.g. on HTTP 400 from the authentications endpoint).
		// Awaiting it directly lets that rejection bubble to processEncryptedCard's
		// try/catch — the previous callback-only wrapper would hang forever in
		// those cases because onFailure/onError don't fire for SDK-level errors.
		// Mirror the server-side ApiHelpers::sanitize_pagbank_name so the 3DS
		// holder/customer names match what the order API will receive.
		const sanitizedCardHolder = sanitizePagBankName(params.cardHolder);

		const result = await PagSeguro.authenticate3DS({
			data: {
				customer: {
					name: params.customer.name || sanitizedCardHolder,
					email: params.customer.email,
					phones: params.customer.phones,
				},
				paymentMethod: {
					type: gateway.card_type,
					installments: gateway.card_type === "DEBIT_CARD" ? 1 : params.installments,
					card: {
						number: params.cardNumber,
						expMonth: params.expMonth,
						expYear: params.expYear,
						holder: {
							name: sanitizedCardHolder,
						},
					},
				},
				amount: {
					value: params.amount,
					currency: "BRL",
				},
				billingAddress: params.billingAddress,
				dataOnly: false,
			},
			beforeChallenge: (challengeInfo) => {
				challengeInfo.open();
			},
		});

		return {
			status: result.status as ThreeDSResult["status"],
			id: result.id,
		};
	} catch (err) {
		// Don't surface SDK-internal messages to users (e.g. "POST ... return 400").
		// Log for debugging and show a friendly message instead.
		if (err instanceof PagSeguro.PagSeguroError) {
			console.error("3DS authentication error:", err.detail);
		} else {
			console.error("3DS authentication error:", err);
		}
		throw new Error(gateway.messages.threeds_auth_error);
	}
};

interface ThreeDSSnapshot {
	amount_cents: number;
	customer: { name: string; email: string; phone: string };
	billingAddress: ThreeDSBillingAddress;
}

const readThreeDSSnapshot = (prefix: string): ThreeDSSnapshot | null => {
	const input = document.getElementById(`${prefix}-threeds-snapshot`) as HTMLInputElement | null;
	if (!input?.value) {
		return null;
	}
	try {
		return JSON.parse(input.value) as ThreeDSSnapshot;
	} catch {
		return null;
	}
};

const readFieldValue = (id: string): string => {
	const el = document.getElementById(id) as HTMLInputElement | HTMLSelectElement | null;
	return el?.value?.trim() ?? "";
};

const buildCustomerPhones = (raw: string, cellphoneErrorMsg: string): ThreeDSCustomerPhone[] => {
	// PagBank's 3DS endpoint requires at least one phone in customer.phones —
	// an empty array makes the authentication call fail. Treat "no phone" the
	// same as "invalid phone" so the user gets a clear error and fills it in.
	if (!raw) {
		throw new Error(cellphoneErrorMsg);
	}
	const parsed = parsePhoneNumber(raw, "BR");
	if (!parsed) {
		throw new Error(cellphoneErrorMsg);
	}
	if (parsed.getType() !== "MOBILE") {
		throw new Error(cellphoneErrorMsg);
	}
	const national = parsed.nationalNumber.replace(/\D/g, "");
	return [
		{
			country: parsed.countryCallingCode,
			area: national.substring(0, 2),
			number: national.substring(2),
			type: "MOBILE",
		},
	];
};

const collectThreeDSData = (
	gateway: GatewayConfig,
	cardHolder: string,
): {
	customer: ThreeDSAuthParams["customer"];
	billingAddress: ThreeDSBillingAddress;
	amount: number;
} => {
	const snapshot = readThreeDSSnapshot(gateway.card_field_prefix);

	const email = readFieldValue("billing_email") || snapshot?.customer.email || "";
	// Prefer the (non-standard) cellphone field added by Brazilian Market on
	// WooCommerce; fall back to the default phone and finally the snapshot.
	const rawPhone =
		readFieldValue("billing_cellphone") ||
		readFieldValue("billing_phone") ||
		snapshot?.customer.phone ||
		"";

	const phones = buildCustomerPhones(rawPhone, gateway.messages.invalid_cellphone);

	const billingAddress: ThreeDSBillingAddress = {
		street: readFieldValue("billing_address_1") || snapshot?.billingAddress.street || "",
		number: readFieldValue("billing_number") || snapshot?.billingAddress.number || "",
		regionCode: readFieldValue("billing_state") || snapshot?.billingAddress.regionCode || "",
		country: "BRA",
		city: readFieldValue("billing_city") || snapshot?.billingAddress.city || "",
		postalCode: (
			readFieldValue("billing_postcode") ||
			snapshot?.billingAddress.postalCode ||
			""
		).replace(/\D/g, ""),
	};

	const firstName = readFieldValue("billing_first_name");
	const lastName = readFieldValue("billing_last_name");
	const formName = `${firstName} ${lastName}`.trim();
	const customerName = sanitizePagBankName(formName || snapshot?.customer.name || cardHolder);

	return {
		customer: { name: customerName, email, phones },
		billingAddress,
		amount: snapshot?.amount_cents ?? 0,
	};
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

const processEncryptedCard = async (gateway: GatewayConfig): Promise<boolean> => {
	const prefix = gateway.card_field_prefix;

	const selectedPaymentToken = document.querySelector(
		`[name=wc-${gateway.gateway_id}-payment-token]:checked`,
	) as HTMLInputElement | null;

	if (selectedPaymentToken !== null && selectedPaymentToken.value !== "new") {
		return true;
	}

	try {
		const cardHolderInput = document.getElementById(
			`${prefix}-card-holder`,
		) as HTMLInputElement | null;
		const cardNumberInput = document.getElementById(
			`${prefix}-card-number`,
		) as HTMLInputElement | null;
		const cardExpiryInput = document.getElementById(
			`${prefix}-card-expiry`,
		) as HTMLInputElement | null;
		const cardCvcInput = document.getElementById(
			`${prefix}-card-cvc`,
		) as HTMLInputElement | null;

		if (
			cardHolderInput == null ||
			cardNumberInput == null ||
			cardExpiryInput == null ||
			cardCvcInput == null
		) {
			throw new Error(gateway.messages.inputs_not_found);
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
				throw new Error(gateway.messages.invalid_holder_name);
			}

			const cardNumberValidation = cardValidator.number(number);
			if (cardNumberValidation.card == null || !cardNumberValidation.isValid) {
				throw new Error(gateway.messages.invalid_card_number);
			}

			const cardExpirationDateValidation = cardValidator.expirationDate(expiryDate);
			if (!cardExpirationDateValidation.isValid) {
				throw new Error(gateway.messages.invalid_card_expiry_date);
			}

			const cardCvcValidation = cardValidator.cvv(cvc);
			if (!cardCvcValidation.isValid) {
				throw new Error(gateway.messages.invalid_security_code);
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
			publicKey: gateway.settings.card_public_key,
			holder: card.holder,
			number: card.number,
			expMonth: card.expirationDate.month,
			expYear: card.expirationDate.year,
			securityCode: card.cvc,
		});

		const messages: Record<PagSeguroEncryptCardErrorCode, string> = {
			INVALID_NUMBER: gateway.messages.invalid_card_number,
			INVALID_SECURITY_CODE: gateway.messages.invalid_security_code,
			INVALID_EXPIRATION_MONTH: gateway.messages.invalid_card_expiry_date,
			INVALID_EXPIRATION_YEAR: gateway.messages.invalid_card_expiry_date,
			INVALID_PUBLIC_KEY: gateway.messages.invalid_public_key,
			INVALID_HOLDER: gateway.messages.invalid_holder_name,
		};

		if (encryptedCard.hasErrors) {
			const errors = encryptedCard.errors.map((item) => messages[item.code]);

			throw new Error(errors[0]);
		}

		const encryptedCardInput = document.getElementById(
			`${prefix}-encrypted-card`,
		) as HTMLInputElement | null;

		const cardBinInput = document.getElementById(
			`${prefix}-card-bin`,
		) as HTMLInputElement | null;

		const threedsIdInput = document.getElementById(
			`${prefix}-threeds-id`,
		) as HTMLInputElement | null;

		if (!encryptedCardInput) {
			throw new Error(gateway.messages.invalid_encrypted_card);
		} else if (!cardBinInput) {
			throw new Error(gateway.messages.invalid_card_bin);
		} else if (!encryptedCard.encryptedCard) {
			throw new Error(gateway.messages.invalid_encrypted_card);
		}

		encryptedCardInput.value = encryptedCard.encryptedCard;
		cardBinInput.value = card.number.substring(0, 6);

		// 3DS Authentication
		if (gateway.settings.threeds_enabled) {
			const installmentsSelect = document.getElementById(
				`${prefix}-installments`,
			) as HTMLSelectElement | null;

			const installments = installmentsSelect
				? Number.parseInt(installmentsSelect.value, 10)
				: 1;

			// PagBank's 3DS SDK expects amount.value in cents, matching the order POST.
			// Prefer the selected option's data-amount-cents (set per-plan in
			// setInstallments for transfer-of-interest, so the WITH-interest total is
			// authenticated). Fall back to the select-level data-amount-cents (cart
			// total in cents) and finally to the PHP-rendered snapshot — the
			// snapshot is the only source for debit and for the order-pay page.
			const selectedOption = installmentsSelect?.options[installmentsSelect.selectedIndex];
			const optionAmountCents = selectedOption?.getAttribute("data-amount-cents");
			const selectAmountCents = installmentsSelect?.getAttribute("data-amount-cents");

			const threeDSContext = collectThreeDSData(gateway, card.holder);
			const amount = Number.parseInt(
				optionAmountCents ||
					selectAmountCents ||
					(threeDSContext.amount > 0 ? String(threeDSContext.amount) : "0"),
				10,
			);

			const result = await authenticate3DS(gateway, {
				cardNumber: card.number,
				expMonth: card.expirationDate.month,
				expYear: card.expirationDate.year,
				cardHolder: card.holder,
				amount,
				installments,
				customer: threeDSContext.customer,
				billingAddress: threeDSContext.billingAddress,
			});

			if (result.status === "CHANGE_PAYMENT_METHOD") {
				throw new Error(gateway.messages.threeds_change_payment_method);
			}

			// AUTH_NOT_SUPPORTED: Block the transaction - 3DS authentication is required
			if (result.status === "AUTH_NOT_SUPPORTED") {
				throw new Error(gateway.messages.threeds_not_supported);
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

const bootstrapCheckout = (gateway: GatewayConfig): void => {
	try {
		const shouldContinue =
			gateway.settings.installments_enabled && gateway.settings.transfer_of_interest_enabled;
		if (!shouldContinue) {
			return;
		}

		const prefix = gateway.card_field_prefix;
		const $container = jQuery("#order_review");

		const installmentsSelect = document.getElementById(
			`${prefix}-installments`,
		) as HTMLSelectElement | null;

		const cardNumberInput = document.getElementById(
			`${prefix}-card-number`,
		) as HTMLInputElement | null;

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
			`[name=wc-${gateway.gateway_id}-payment-token]`,
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
				const option = new Option(
					plan.title,
					plan.installments.toString(),
					plan.installments === 1,
				);
				// Backend returns plan.amount in cents (charge_fees response). Carry it
				// per-option so 3DS authentication uses the WITH-interest amount that
				// will actually be charged.
				option.setAttribute("data-amount-cents", plan.amount.toString());
				installmentsSelect.appendChild(option);
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

		const selectedPaymentToken = document.querySelector(
			`[name=wc-${gateway.gateway_id}-payment-token]:checked`,
		) as HTMLInputElement | null;

		handleChangePaymentToken(
			selectedPaymentToken === null ? "new" : selectedPaymentToken.value,
		);

		cardNumberInput.addEventListener("change", () => {
			handleChangePaymentToken("new");
		});
	} catch (error) {
		if (error instanceof Error) {
			submitCheckoutError(error.message);
		} else {
			submitCheckoutError("Unknown error");
		}
	}
};

const resetEncryptedCardFields = (gateway: GatewayConfig): void => {
	const prefix = gateway.card_field_prefix;
	const ids = [`${prefix}-encrypted-card`, `${prefix}-card-bin`, `${prefix}-threeds-id`];

	ids.forEach((id) => {
		const input = document.getElementById(id) as HTMLInputElement | null;
		if (input) {
			input.value = "";
		}
	});
};

const init = (): void => {
	const gateways = Object.values(window.PagBankLegacyCheckoutGateways ?? {});

	if (gateways.length === 0) {
		return;
	}

	const gatewayById = new Map(gateways.map((g) => [g.gateway_id, g]));

	// Single submit handler dispatches to the gateway matching the chosen
	// payment_method, so multiple PagBank card gateways can coexist without
	// stacking duplicate preventDefault() calls.
	wcForms.orderReview.on("submit", async (event: JQuery.SubmitEvent) => {
		event.preventDefault();

		const selectedMethod = jQuery(
			"input[name=payment_method]:checked",
			event.currentTarget,
		).val() as string | undefined;

		const selectedGateway = selectedMethod ? gatewayById.get(selectedMethod) : undefined;

		const shouldContinue = !selectedGateway || (await processEncryptedCard(selectedGateway));

		if (shouldContinue) {
			if (!selectedGateway) {
				clearCheckoutErrors();
			}

			(event.currentTarget as HTMLFormElement).submit();
		}
	});

	// Gateways whose encryption + 3DS finished and are cleared for WC's next
	// place_order trigger. WC's checkout.js checks the handler result sync
	// (`triggerHandler(...) !== false`), so we MUST return false synchronously
	// to block submission while async work is in flight — returning a Promise
	// is treated as truthy and WC submits with an empty threeds-id, failing
	// server-side validation.
	const readyForSubmit = new Set<string>();

	gateways.forEach((gateway) => {
		wcForms.checkout.on(`checkout_place_order_${gateway.gateway_id}`, () => {
			if (readyForSubmit.has(gateway.gateway_id)) {
				// Second pass triggered by us after async work completed.
				readyForSubmit.delete(gateway.gateway_id);
				return true;
			}

			// Show the same overlay WC uses, but DON'T add the `.processing`
			// class: WC's own submit() bails out early when `$form.is('.processing')`
			// is true, which would block the submission we re-trigger after 3DS.
			// `$form.block(...)` alone gives the same visual effect.
			wcForms.checkout.block({
				message: null,
				overlayCSS: {
					background: "#fff",
					opacity: 0.6,
				},
			});

			processEncryptedCard(gateway).then((success) => {
				if (!success) {
					wcForms.checkout.unblock();
					return;
				}
				readyForSubmit.add(gateway.gateway_id);
				wcForms.checkout.trigger("submit");
			});

			return false;
		});

		jQuery(document.body).on("updated_checkout", () => bootstrapCheckout(gateway));
		jQuery(document.body).on("checkout_error updated_checkout", () => {
			resetEncryptedCardFields(gateway);
			readyForSubmit.delete(gateway.gateway_id);
		});

		window.addEventListener("pageshow", (event) => {
			if (event.persisted) {
				resetEncryptedCardFields(gateway);
				readyForSubmit.delete(gateway.gateway_id);
			}
		});
	});

	jQuery(() => {
		const isOrderReview = jQuery(document.body).hasClass("woocommerce-order-pay");

		if (isOrderReview) {
			gateways.forEach((gateway) => {
				bootstrapCheckout(gateway);
			});
		}
	});
};

init();
