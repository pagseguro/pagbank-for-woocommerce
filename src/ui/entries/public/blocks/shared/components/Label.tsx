import { decodeEntities } from "@wordpress/html-entities";

type LabelProps = {
	title: string;
	baseUrl: string;
	icon: "card" | "boleto" | "pix" | "pagbank" | "google-pay";
};

export const Label = ({ title, baseUrl, icon }: LabelProps): JSX.Element => {
	return (
		<span className="pagbank-block-label">
			{icon && <img src={`${baseUrl}/dist/images/icons/${icon}.png`} alt={title} />}
			{decodeEntities(title)}
		</span>
	);
};
