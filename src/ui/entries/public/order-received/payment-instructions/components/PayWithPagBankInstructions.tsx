/**
 * Pay with PagBank payment instructions component.
 *
 * @package PagBank_WooCommerce
 */

import { __, sprintf } from "@wordpress/i18n";
import { TEXT_DOMAIN } from "@/constants";
import { useOrderStatus } from "../hooks/useOrderStatus";
import type { PayWithPagBankInstructionsProps } from "../types";
import { CopyButton, PaidConfirmation, QRCodeDisplay } from "./shared";

const formatDate = (dateString: string): string => {
	const date = new Date(dateString);
	return date.toLocaleString("pt-BR", {
		day: "2-digit",
		month: "2-digit",
		year: "numeric",
		hour: "2-digit",
		minute: "2-digit",
	});
};

export const PayWithPagBankInstructions = ({
	orderId,
	orderKey,
	isPaid: initialIsPaid,
	restUrl,
	qrCodeImage,
	qrCodeText,
	expirationDate,
}: PayWithPagBankInstructionsProps): JSX.Element => {
	const { isPaid } = useOrderStatus({
		orderId,
		orderKey,
		restUrl,
		initialIsPaid,
		expirationDate,
	});

	if (isPaid) {
		return <PaidConfirmation />;
	}

	return (
		<div className="pagbank-pay-with-pagbank">
			<h2>{__("Instruções de pagamento - Pagar com PagBank", TEXT_DOMAIN)}</h2>

			<h3>{__("Escaneie o QR Code com o app PagBank", TEXT_DOMAIN)}</h3>
			<ol>
				<li>
					{__('Abra o aplicativo PagBank e selecione a opção "Pix/QR Code"', TEXT_DOMAIN)}
				</li>
				<li>
					{__('Selecione "Pagar com QR Code" e escaneie o código abaixo', TEXT_DOMAIN)}
				</li>
				<li>
					{__(
						"Escolha se deseja pagar com saldo, crédito à vista ou parcelado",
						TEXT_DOMAIN,
					)}
				</li>
			</ol>
			<QRCodeDisplay src={qrCodeImage} alt="QR Code PagBank" />

			<hr />

			<h3>{__("Ou copie o código do QR Code", TEXT_DOMAIN)}</h3>
			<p>{__("Copie o código abaixo e cole no aplicativo PagBank:", TEXT_DOMAIN)}</p>
			<div className="pagbank-copy-and-paste">
				<CopyButton value={qrCodeText} />
			</div>

			{expirationDate && (
				<>
					<hr />
					<p className="pagbank-expiration">
						{sprintf(
							/* translators: %s: expiration date */
							__("Válido até: %s", TEXT_DOMAIN),
							formatDate(expirationDate),
						)}
					</p>
				</>
			)}

			<hr />

			<h3>{__("Quando o pagamento for concluído", TEXT_DOMAIN)}</h3>
			<p>
				{__(
					"Quando finalizar a transação, você pode retornar à tela inicial.",
					TEXT_DOMAIN,
				)}
			</p>
		</div>
	);
};
