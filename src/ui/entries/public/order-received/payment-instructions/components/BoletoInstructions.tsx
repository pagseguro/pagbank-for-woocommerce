/**
 * Boleto payment instructions component.
 *
 * @package PagBank_WooCommerce
 */

import { __ } from "@wordpress/i18n";
import type { BoletoInstructionsProps } from "../types";
import { BarcodeDisplay, CopyButton, PaidConfirmation } from "./shared";

export const BoletoInstructions = ({
	isPaid,
	boletoBarcode,
	boletoLinkPdf,
}: BoletoInstructionsProps): JSX.Element => {
	if (isPaid) {
		return <PaidConfirmation />;
	}

	return (
		<div className="pagbank-boleto">
			<h3>{__("Opção 1: faça download do boleto", "pagbank-for-woocommerce")}</h3>
			<ol>
				<li>
					{__(
						'Abra o aplicativo do seu banco e selecione a opção "Pagar boleto"',
						"pagbank-for-woocommerce",
					)}
				</li>
				<li>{__("Escaneie o código de barras", "pagbank-for-woocommerce")}</li>
			</ol>
			<div className="center">
				<a className="button" target="_blank" href={boletoLinkPdf} rel="noreferrer">
					{__("Baixar boleto", "pagbank-for-woocommerce")}
				</a>
			</div>

			<hr />

			<h3>
				{__("Opção 2: escaneie ou copie o código de barras", "pagbank-for-woocommerce")}
			</h3>
			<ol>
				<li>
					{__(
						'Abra o aplicativo do seu banco e selecione a opção "Pagar boleto"',
						"pagbank-for-woocommerce",
					)}
				</li>
				<li>
					{__(
						"Escaneie o código de barras abaixo ou copie o código",
						"pagbank-for-woocommerce",
					)}
				</li>
			</ol>
			<BarcodeDisplay value={boletoBarcode} />
			<div className="boleto-barcode">
				<CopyButton value={boletoBarcode} />
			</div>

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
