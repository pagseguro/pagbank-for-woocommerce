/**
 * Copy to clipboard button component.
 *
 * @package PagBank_WooCommerce
 */

import { __ } from "@wordpress/i18n";
import { type MouseEvent, useCallback, useState } from "react";

interface CopyButtonProps {
	value: string;
	label?: string;
}

export const CopyButton = ({ value, label }: CopyButtonProps): JSX.Element => {
	const [copied, setCopied] = useState(false);

	const handleCopy = useCallback(async () => {
		try {
			await navigator.clipboard.writeText(value);
			setCopied(true);
			setTimeout(() => setCopied(false), 2000);
		} catch {
			// Fallback for older browsers
			const textArea = document.createElement("textarea");
			textArea.value = value;
			document.body.appendChild(textArea);
			textArea.select();
			document.execCommand("copy");
			document.body.removeChild(textArea);
			setCopied(true);
			setTimeout(() => setCopied(false), 2000);
		}
	}, [value]);

	const handleSelectAll = useCallback((e: MouseEvent<HTMLInputElement>) => {
		(e.target as HTMLInputElement).select();
	}, []);

	return (
		<div className="pagbank-copy-field">
			<input type="text" readOnly value={value} onClick={handleSelectAll} />
			<button type="button" className="button" onClick={handleCopy}>
				{copied
					? __("Copiado!", "pagbank-for-woocommerce")
					: label || __("Copiar", "pagbank-for-woocommerce")}
			</button>
		</div>
	);
};
