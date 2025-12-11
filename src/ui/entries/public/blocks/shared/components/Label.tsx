import { decodeEntities } from "@wordpress/html-entities";

type LabelProps = {
	title: string;
	icon: string;
};

export const Label = ({ title, icon }: LabelProps): JSX.Element => {
	return (
		<span className="pagbank-block-label">
			{icon && <img src={icon} alt={title} />}
			{decodeEntities(title)}
		</span>
	);
};
