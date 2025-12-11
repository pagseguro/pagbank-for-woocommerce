/**
 * Settings card container component.
 *
 * @package PagBank_WooCommerce
 */

import { Card, CardBody, CardHeader } from "@wordpress/components";
import { decodeEntities } from "@wordpress/html-entities";

interface SettingsCardProps {
	title: string;
	children: React.ReactNode;
	className?: string;
}

export const SettingsCard = ({ title, children, className = "" }: SettingsCardProps) => {
	return (
		<Card className={`pagbank-settings-card ${decodeEntities(className)}`.trim()}>
			<CardHeader>
				<h2 className="pagbank-settings-card__title">{decodeEntities(title)}</h2>
			</CardHeader>
			<CardBody>{children}</CardBody>
		</Card>
	);
};
