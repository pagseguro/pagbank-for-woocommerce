/**
 * Pix payment instructions component.
 *
 * @package PagBank_WooCommerce
 */

import { __ } from "@wordpress/i18n";
import { TEXT_DOMAIN } from "@/constants";
import { useOrderStatus } from "../hooks/useOrderStatus";
import type { PixInstructionsProps } from "../types";
import { CopyButton, PaidConfirmation, QRCodeDisplay } from "./shared";

export const PixInstructions = ({
	orderId,
	orderKey,
	isPaid: initialIsPaid,
	restUrl,
	pixQrCode,
	pixText,
	pixExpirationDate,
}: PixInstructionsProps): JSX.Element => {
	const { isPaid } = useOrderStatus({
		orderId,
		orderKey,
		restUrl,
		initialIsPaid,
		expirationDate: pixExpirationDate,
	});

	if (isPaid) {
		return <PaidConfirmation />;
	}

	return (
		<div className="pagbank-pix">
			<h2>{__("Instruções de pagamento do Pix", TEXT_DOMAIN)}</h2>

			<h3>{__("Opção 1: Escaneie o QR code do Pix", TEXT_DOMAIN)}</h3>
			<ol>
				<li>
					{__(
						'Abra o aplicativo do seu banco e selecione a opção "Pagar com Pix"',
						TEXT_DOMAIN,
					)}
				</li>
				<li>{__("Escaneie o QR code abaixo e confirme o pagamento", TEXT_DOMAIN)}</li>
			</ol>
			<QRCodeDisplay src={pixQrCode} alt="QR Code Pix" />

			<hr />

			<h3>{__("Opção 2: Use o código do Pix", TEXT_DOMAIN)}</h3>
			<p>{__("Copie o código abaixo. Em seguida, você precisará:", TEXT_DOMAIN)}</p>
			<ol>
				<li>
					{__(
						'Abrir o aplicativo ou site do seu banco e selecionar a opção "Pagar com Pix"',
						TEXT_DOMAIN,
					)}
				</li>
				<li>{__("Colar o código e concluir o pagamento", TEXT_DOMAIN)}</li>
			</ol>
			<div className="pix-copy-and-paste">
				<CopyButton value={pixText} />
			</div>

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
