/**
 * Payment confirmed component.
 *
 * @package PagBank_WooCommerce
 */

import { __ } from "@wordpress/i18n";

export const PaidConfirmation = (): JSX.Element => {
	return (
		<div className="pagbank-paid-confirmation">
			<h3>{__("Pagamento confirmado!", "pagbank-for-woocommerce")}</h3>
			<p>
				{__(
					"O pagamento do seu pedido foi confirmado com sucesso.",
					"pagbank-for-woocommerce",
				)}
			</p>
		</div>
	);
};
