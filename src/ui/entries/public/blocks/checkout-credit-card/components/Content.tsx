/**
 * Main content component for new credit card payments.
 *
 * @package PagBank_WooCommerce
 */

import { decodeEntities } from "@wordpress/html-entities";
import cardValidator from "card-validator";
import { useEffect, useRef, useState } from "react";

import { useInstallments } from "../hooks/useInstallments";
import { settings } from "../settings";
import type { Billing, EmitResponse, EventRegistration } from "../types";
import { convertTwoDigitsYearToFourDigits, getCardBin } from "../utils";
import { CardFormFields } from "./CardFormFields";
import { InstallmentsSelect } from "./InstallmentsSelect";

interface ContentProps {
	eventRegistration: EventRegistration;
	emitResponse: EmitResponse;
	billing: Billing;
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

	// Refs for form data to avoid re-registering callback
	const formDataRef = useRef({
		holder,
		number,
		expiry,
		cvc,
		installments,
		shouldSavePayment,
	});

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

	// Payment setup handler - register only once
	// biome-ignore lint/correctness/useExhaustiveDependencies: eventRegistration and emitResponse are stable WooCommerce Blocks references.
	useEffect(() => {
		const unsubscribe = eventRegistration.onPaymentSetup(() => {
			const { holder, number, expiry, cvc, installments, shouldSavePayment } =
				formDataRef.current;

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

				// Encrypt card using PagBank SDK
				const encryptedCard = PagSeguro.encryptCard({
					publicKey: settings.card_public_key,
					holder: holder.trim(),
					number: cardNumber,
					expMonth: cardExpirationDateValidation.month as string,
					expYear: convertTwoDigitsYearToFourDigits(
						cardExpirationDateValidation.year as string,
					),
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

				// Return success with payment data
				return {
					type: emitResponse.responseTypes.SUCCESS,
					meta: {
						paymentMethodData: {
							"pagbank_credit_card-encrypted-card": encryptedCard.encryptedCard,
							"pagbank_credit_card-card-holder": holder.trim(),
							"pagbank_credit_card-card-bin": cardBin,
							"pagbank_credit_card-installments": installments,
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
	}, []);

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

				{settings.installments_enabled && (
					<InstallmentsSelect
						id="pagbank-installments"
						value={installments}
						onChange={setInstallments}
						plans={installmentPlans}
						isLoading={isLoading}
						disabled={settings.transfer_of_interest_enabled && cardBin.length < 6}
					/>
				)}
			</div>

			{/*
				TODO: 3DS Authentication
				O fluxo 3DS será implementado aqui quando necessário.
				Documentação: https://dev.pagbank.uol.com.br/reference/3ds-autenticacao
			*/}
		</div>
	);
};
