/**
 * Main content component for new credit card payments.
 *
 * @package PagBank_WooCommerce
 */

import type {
	BillingDataProps,
	EmitResponseProps,
	EventRegistrationProps,
} from "@woocommerce/types";
import { decodeEntities } from "@wordpress/html-entities";
import cardValidator from "card-validator";
import parsePhoneNumber from "libphonenumber-js/mobile";
import { useEffect, useRef, useState } from "react";
import {
	CardFormFields,
	convertTwoDigitsYearToFourDigits,
	getCardBin,
	type ThreeDSAuthenticateParams,
	use3DS,
} from "../../shared";
import { useInstallments } from "../hooks/useInstallments";
import { settings } from "../settings";
import { InstallmentsSelect } from "./InstallmentsSelect";

interface ContentProps {
	eventRegistration: EventRegistrationProps;
	emitResponse: EmitResponseProps;
	billing: BillingDataProps;
	shouldSavePayment: boolean;
}

export const Content = ({
	eventRegistration,
	emitResponse,
	billing,
	shouldSavePayment,
}: ContentProps): JSX.Element => {
	// Form state
	const [holder, setHolder] = useState("");
	const [number, setNumber] = useState("");
	const [expiry, setExpiry] = useState("");
	const [cvc, setCvc] = useState("");

	const cardBin = getCardBin(number);

	const { installments, setInstallments, installmentPlans, isLoading } = useInstallments({
		cartTotalInCents: billing.cartTotal.value,
		cardBin,
	});

	const {
		authenticate,
		isAuthenticating,
		isEnabled: is3DSEnabled,
	} = use3DS({
		settings,
		cardType: "CREDIT_CARD",
	});

	// Refs for form data to avoid re-registering callback
	const formDataRef = useRef({
		holder,
		number,
		expiry,
		cvc,
		installments,
		shouldSavePayment,
	});

	// Ref for billing data
	const billingRef = useRef(billing);

	useEffect(() => {
		formDataRef.current = {
			holder,
			number,
			expiry,
			cvc,
			installments,
			shouldSavePayment,
		};
	}, [holder, number, expiry, cvc, installments, shouldSavePayment]);

	useEffect(() => {
		billingRef.current = billing;
	}, [billing]);

	// Payment setup handler - register only once
	// biome-ignore lint/correctness/useExhaustiveDependencies: eventRegistration and emitResponse are stable WooCommerce Blocks references.
	useEffect(() => {
		const unsubscribe = eventRegistration.onPaymentSetup(async () => {
			const { holder, number, expiry, cvc, installments, shouldSavePayment } =
				formDataRef.current;
			const currentBilling = billingRef.current;

			const cardBin = getCardBin(number);

			try {
				// Validate cardholder name
				const cardHolderValidation = cardValidator.cardholderName(holder);
				if (!cardHolderValidation.isValid) {
					return {
						type: emitResponse.responseTypes.ERROR,
						message: settings.messages.invalid_holder_name,
						messageContext: emitResponse.noticeContexts.PAYMENTS,
					};
				}

				// Validate card number
				const cardNumber = number.replace(/\s/g, "");
				const cardNumberValidation = cardValidator.number(cardNumber);

				if (cardNumberValidation.card == null || !cardNumberValidation.isValid) {
					return {
						type: emitResponse.responseTypes.ERROR,
						message: settings.messages.invalid_card_number,
						messageContext: emitResponse.noticeContexts.PAYMENTS,
					};
				}

				// Validate expiry date
				const cardExpirationDateValidation = cardValidator.expirationDate(expiry);
				if (!cardExpirationDateValidation.isValid) {
					return {
						type: emitResponse.responseTypes.ERROR,
						message: settings.messages.invalid_card_expiry_date,
						messageContext: emitResponse.noticeContexts.PAYMENTS,
					};
				}

				// Validate CVC (3-4 digits depending on card type - Amex uses 4)
				const cvcMaxLength = cardNumberValidation.card?.code?.size || 3;
				const cardCvcValidation = cardValidator.cvv(cvc, cvcMaxLength);
				if (!cardCvcValidation.isValid) {
					return {
						type: emitResponse.responseTypes.ERROR,
						message: settings.messages.invalid_security_code,
						messageContext: emitResponse.noticeContexts.PAYMENTS,
					};
				}

				// Check public key
				if (!settings.card_public_key) {
					return {
						type: emitResponse.responseTypes.ERROR,
						message: settings.messages.invalid_public_key,
						messageContext: emitResponse.noticeContexts.PAYMENTS,
					};
				}

				const expMonth = cardExpirationDateValidation.month as string;
				const expYear = convertTwoDigitsYearToFourDigits(
					cardExpirationDateValidation.year as string,
				);

				// Encrypt card using PagBank SDK
				const encryptedCard = PagSeguro.encryptCard({
					publicKey: settings.card_public_key,
					holder: holder.trim(),
					number: cardNumber,
					expMonth,
					expYear,
					securityCode: cvc.trim(),
				});

				// Handle encryption errors
				if (encryptedCard.hasErrors) {
					const errorMessages: Record<PagSeguroEncryptCardErrorCode, string> = {
						INVALID_NUMBER: settings.messages.invalid_card_number,
						INVALID_SECURITY_CODE: settings.messages.invalid_security_code,
						INVALID_EXPIRATION_MONTH: settings.messages.invalid_card_expiry_date,
						INVALID_EXPIRATION_YEAR: settings.messages.invalid_card_expiry_date,
						INVALID_PUBLIC_KEY: settings.messages.invalid_public_key,
						INVALID_HOLDER: settings.messages.invalid_holder_name,
					};

					const firstError = encryptedCard.errors[0];
					const errorMessage =
						errorMessages[firstError.code] || settings.messages.invalid_card_number;

					return {
						type: emitResponse.responseTypes.ERROR,
						message: errorMessage,
						messageContext: emitResponse.noticeContexts.PAYMENTS,
					};
				}

				// 3DS Authentication if enabled
				let threedsId: string | undefined;

				if (is3DSEnabled) {
					const cellphone = parsePhoneNumber(
						currentBilling.billingData["pagbank/cellphone"],
						"BR",
					);

					if (!cellphone) {
						return {
							type: emitResponse.responseTypes.ERROR,
							message: settings.messages.invalid_cellphone,
							messageContext: emitResponse.noticeContexts.PAYMENTS,
						};
					}

					if (cellphone.getType() !== "MOBILE") {
						return {
							type: emitResponse.responseTypes.ERROR,
							message: settings.messages.invalid_cellphone,
							messageContext: emitResponse.noticeContexts.PAYMENTS,
						};
					}

					const threeDSParams: ThreeDSAuthenticateParams = {
						customer: {
							name: holder.trim(),
							email: currentBilling.billingData.email,
							phones: [
								{
									country: cellphone.countryCallingCode,
									area: cellphone.nationalNumber
										.replace(/\D/g, "")
										.substring(0, 2),
									number: cellphone.nationalNumber
										.replace(/\D/g, "")
										.substring(2),
									type: "MOBILE",
								},
							],
						},
						card: {
							number: cardNumber,
							expMonth,
							expYear,
							holder: {
								name: holder.trim(),
							},
						},
						amount: {
							value: currentBilling.cartTotal.value,
							currency: currentBilling.currency.code,
						},
						installments: Number.parseInt(installments, 10),
						billingAddress: {
							street: currentBilling.billingData.address_1,
							number: currentBilling.billingData["pagbank/address-number"],
							regionCode: currentBilling.billingData.state,
							country: "BRA",
							city: currentBilling.billingData.city,
							postalCode: currentBilling.billingData.postcode.replace(/\D/g, ""),
						},
					};

					const result = await authenticate(threeDSParams);

					if (result.status === "CHANGE_PAYMENT_METHOD") {
						return {
							type: emitResponse.responseTypes.ERROR,
							message: settings.messages.threeds_change_payment_method,
							messageContext: emitResponse.noticeContexts.PAYMENTS,
						};
					}

					if (result.status === "AUTH_FLOW_COMPLETED" && result.id) {
						threedsId = result.id;
					}

					// AUTH_NOT_SUPPORTED: Block the transaction - 3DS authentication is required
					if (result.status === "AUTH_NOT_SUPPORTED") {
						return {
							type: emitResponse.responseTypes.ERROR,
							message: settings.messages.threeds_not_supported,
							messageContext: emitResponse.noticeContexts.PAYMENTS,
						};
					}
				}

				// Return success with payment data
				return {
					type: emitResponse.responseTypes.SUCCESS,
					meta: {
						paymentMethodData: {
							"pagbank_credit_card-encrypted-card": encryptedCard.encryptedCard,
							"pagbank_credit_card-card-holder": holder.trim(),
							"pagbank_credit_card-card-bin": cardBin,
							"pagbank_credit_card-installments": installments,
							...(threedsId && {
								"pagbank_credit_card-threeds-id": threedsId,
							}),
							...(shouldSavePayment && {
								"wc-pagbank_credit_card-payment-token": "new",
								"wc-pagbank_credit_card-new-payment-method": "true",
							}),
						},
					},
				};
			} catch (error) {
				return {
					type: emitResponse.responseTypes.ERROR,
					message:
						error instanceof Error
							? error.message
							: settings.messages.invalid_card_number,
					messageContext: emitResponse.noticeContexts.PAYMENTS,
				};
			}
		});

		return unsubscribe;
	}, [authenticate, is3DSEnabled]);

	return (
		<div className="pagbank-credit-card-form">
			{settings.description && (
				<p className="pagbank-credit-card-description">
					{decodeEntities(settings.description)}
				</p>
			)}

			<div className="pagbank-credit-card-fields">
				<CardFormFields
					holder={holder}
					onHolderChange={setHolder}
					number={number}
					onNumberChange={setNumber}
					expiry={expiry}
					onExpiryChange={setExpiry}
					cvc={cvc}
					onCvcChange={setCvc}
				/>

				{settings.installments_enabled && !settings.cart_has_subscription && (
					<InstallmentsSelect
						id="pagbank-installments"
						value={installments}
						onChange={setInstallments}
						plans={installmentPlans}
						isLoading={isLoading || isAuthenticating}
						disabled={settings.transfer_of_interest_enabled && cardBin.length < 6}
					/>
				)}
			</div>
		</div>
	);
};
