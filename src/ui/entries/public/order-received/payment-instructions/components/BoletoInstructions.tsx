/**
 * Boleto payment instructions component.
 *
 * @package PagBank_WooCommerce
 */

import { __ } from "@wordpress/i18n";
import { TEXT_DOMAIN } from "@/constants";
import type { BoletoInstructionsProps } from "../types";
import { CopyButton, PaidConfirmation } from "./shared";

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
			<h3>{__("Opção 1: faça download do boleto", TEXT_DOMAIN)}</h3>
			<ol>
				<li>
					{__(
						'Abra o aplicativo do seu banco e selecione a opção "Pagar boleto"',
						TEXT_DOMAIN,
					)}
				</li>
				<li>{__("Escaneie o código de barras", TEXT_DOMAIN)}</li>
			</ol>
			<div className="center">
				<a className="button" target="_blank" href={boletoLinkPdf} rel="noreferrer">
					{__("Download boleto", TEXT_DOMAIN)}
				</a>
			</div>

			<hr />

			<h3>{__("Opção 2: copie o código de barras", TEXT_DOMAIN)}</h3>
			<ol>
				<li>
					{__(
						'Abra o aplicativo do seu banco e selecione a opção "Pagar boleto"',
						TEXT_DOMAIN,
					)}
				</li>
				<li>{__("Cole o código de barras abaixo", TEXT_DOMAIN)}</li>
			</ol>
			<div className="boleto-barcode">
				<CopyButton value={boletoBarcode} />
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
