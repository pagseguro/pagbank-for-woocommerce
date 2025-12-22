/**
 * Pay with PagBank payment instructions component.
 *
 * @package PagBank_WooCommerce
 */

import { __, sprintf } from "@wordpress/i18n";
import { useIsMobile } from "../hooks/useIsMobile";
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
	deeplinkUrl,
}: PayWithPagBankInstructionsProps): JSX.Element => {
	const isMobile = useIsMobile();
	const { isPaid } = useOrderStatus({
		orderId,
		orderKey,
		restUrl,
		initialIsPaid,
		expirationDate,
	});

	// If there's a deeplink and user is not on mobile, show warning
	const isDesktopWithDeeplink = Boolean(deeplinkUrl) && !isMobile;

	if (isPaid) {
		return <PaidConfirmation />;
	}

	return (
		<div className="pagbank-pay-with-pagbank">
			<h2>{__("Instruções de pagamento - Pagar com PagBank", "pagbank-for-woocommerce")}</h2>

			{deeplinkUrl ? (
				<>
					{isDesktopWithDeeplink && (
						<div className="pagbank-alert pagbank-alert--warning">
							{__(
								"Este pedido foi iniciado em um dispositivo móvel. Para concluir o pagamento, abra esta página no seu celular.",
								"pagbank-for-woocommerce",
							)}
						</div>
					)}

					<h3>{__("Abra o app PagBank", "pagbank-for-woocommerce")}</h3>
					<p>
						{__(
							"Clique no botão abaixo para abrir o aplicativo PagBank e completar o pagamento:",
							"pagbank-for-woocommerce",
						)}
					</p>
					{isDesktopWithDeeplink ? (
						<span className="pagbank-open-app-button pagbank-open-app-button--disabled">
							{__("Abrir app PagBank", "pagbank-for-woocommerce")}
						</span>
					) : (
						<a href={deeplinkUrl} className="pagbank-open-app-button">
							{__("Abrir app PagBank", "pagbank-for-woocommerce")}
						</a>
					)}
				</>
			) : (
				<>
					<h3>{__("Escaneie o QR Code com o app PagBank", "pagbank-for-woocommerce")}</h3>
					<ol>
						<li>
							{__(
								'Abra o aplicativo PagBank e selecione a opção "Pix/QR Code"',
								"pagbank-for-woocommerce",
							)}
						</li>
						<li>
							{__(
								'Selecione "Pagar com QR Code" e escaneie o código abaixo',
								"pagbank-for-woocommerce",
							)}
						</li>
						<li>
							{__(
								"Escolha se deseja pagar com saldo, crédito à vista ou parcelado",
								"pagbank-for-woocommerce",
							)}
						</li>
					</ol>
					<QRCodeDisplay src={qrCodeImage} alt="QR Code PagBank" />

					<hr />

					<h3>{__("Ou copie o código do QR Code", "pagbank-for-woocommerce")}</h3>
					<p>
						{__(
							"Copie o código abaixo e cole no aplicativo PagBank:",
							"pagbank-for-woocommerce",
						)}
					</p>
					<div className="pagbank-copy-and-paste">
						<CopyButton value={qrCodeText} />
					</div>
				</>
			)}

			{expirationDate && (
				<>
					<hr />
					<p className="pagbank-expiration">
						{sprintf(
							/* translators: %s: expiration date */
							__("Válido até: %s", "pagbank-for-woocommerce"),
							formatDate(expirationDate),
						)}
					</p>
				</>
			)}

			<hr />

			<h3>{__("Quando o pagamento for concluído", "pagbank-for-woocommerce")}</h3>
			<p>
				{__(
					"Quando finalizar a transação, você pode retornar à tela inicial.",
					"pagbank-for-woocommerce",
				)}
			</p>
		</div>
	);
};
