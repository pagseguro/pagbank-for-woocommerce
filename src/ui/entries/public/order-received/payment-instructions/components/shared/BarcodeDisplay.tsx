/**
 * Barcode display component using JsBarcode.
 *
 * @package PagBank_WooCommerce
 */

import JsBarcode from "jsbarcode";
import { useEffect, useRef } from "react";

interface BarcodeDisplayProps {
	value: string;
}

/**
 * Convert Brazilian boleto "linha digitável" (47 digits) to barcode format (44 digits).
 *
 * The linha digitável has check digits that need to be removed and the order rearranged
 * to create the scannable barcode format as per Febraban standards.
 *
 * @see https://portal.febraban.org.br/pagina/3166/33/pt-br/layour-702
 */
const convertToBarcode = (linhaDigitavel: string): string | null => {
	// Remove any non-digit characters (spaces, dots, etc.)
	const digits = linhaDigitavel.replace(/\D/g, "");

	// Must be exactly 47 digits
	if (digits.length !== 47) {
		return null;
	}

	// Rearrange digits: remove check digits and reorder
	// Pattern: AAABC.CCCCX DDDDD.DDDDDY EEEEE.EEEEEZ K UUUUVVVVVVVVVV
	// Result:  AAABKUUUUVVVVVVVVVVCCCCCDDDDDDDDDDEEEEEEEEEE (44 digits)
	return digits.replace(/^(\d{4})(\d{5})\d{1}(\d{10})\d{1}(\d{10})\d{1}(\d{15})$/, "$1$5$2$3$4");
};

export const BarcodeDisplay = ({ value }: BarcodeDisplayProps): JSX.Element | null => {
	const svgRef = useRef<SVGSVGElement>(null);

	useEffect(() => {
		if (svgRef.current && value) {
			const barcode = convertToBarcode(value);

			if (!barcode) {
				console.error("Invalid boleto linha digitável format");
				return;
			}

			try {
				JsBarcode(svgRef.current, barcode, {
					format: "ITF",
					displayValue: false,
					height: 60,
					width: 1.5,
					margin: 10,
					background: "#ffffff",
					lineColor: "#000000",
				});
			} catch {
				console.error("Failed to generate barcode");
			}
		}
	}, [value]);

	return (
		<div className="pagbank-barcode-display">
			<svg ref={svgRef} />
		</div>
	);
};
