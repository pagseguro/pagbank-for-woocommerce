/**
 * QR Code display component.
 *
 * @package PagBank_WooCommerce
 */

interface QRCodeDisplayProps {
	src: string;
	alt: string;
}

export const QRCodeDisplay = ({ src, alt }: QRCodeDisplayProps): JSX.Element => {
	return (
		<div className="pagbank-qrcode">
			<img src={src} alt={alt} />
		</div>
	);
};
