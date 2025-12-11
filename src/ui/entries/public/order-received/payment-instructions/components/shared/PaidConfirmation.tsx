/**
 * Payment confirmed component.
 *
 * @package PagBank_WooCommerce
 */

import { __ } from "@wordpress/i18n";
import { TEXT_DOMAIN } from "@/constants";

export const PaidConfirmation = (): JSX.Element => {
	return (
		<div className="pagbank-paid-confirmation">
			<h3>{__("Pagamento confirmado!", TEXT_DOMAIN)}</h3>
			<p>{__("O pagamento do seu pedido foi confirmado com sucesso.", TEXT_DOMAIN)}</p>
		</div>
	);
};
