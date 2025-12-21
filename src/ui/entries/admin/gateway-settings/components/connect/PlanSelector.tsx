/**
 * Plan selector component for production environment.
 *
 * @package PagBank_WooCommerce
 */

import { Button } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

interface Plan {
	id: string;
	title: string;
	features: string[];
}

interface PlanSelectorProps {
	onSelectPlan: (applicationId: string) => void;
	isLoading: boolean;
}

const PLANS: Plan[] = [
	{
		id: "31241905-5426-4f88-a140-4416a2cab404",
		title: __("Receba em 14 dias", "pagbank-for-woocommerce"),
		features: [
			__("Sem mensalidade e taxa de adesão", "pagbank-for-woocommerce"),
			__("4,39% + R$ 0,40 no crédito à vista e parcelado", "pagbank-for-woocommerce"),
			__("0,99% no PIX com recebimento em D0", "pagbank-for-woocommerce"),
			__("R$ 2,99 no boleto com recebimento em D2", "pagbank-for-woocommerce"),
			__("Antecipe o recebimento quando quiser por +2,99%", "pagbank-for-woocommerce"),
		],
	},
	{
		id: "c8672afd-abbb-4c47-a95d-7cf9cd4cee76",
		title: __("Receba em 30 dias", "pagbank-for-woocommerce"),
		features: [
			__("Sem mensalidade e taxa de adesão", "pagbank-for-woocommerce"),
			__("3,79% + R$ 0,40 no crédito à vista e parcelado", "pagbank-for-woocommerce"),
			__("0,99% no PIX com recebimento em D0", "pagbank-for-woocommerce"),
			__("R$ 2,99 no boleto com recebimento em D2", "pagbank-for-woocommerce"),
			__("Antecipe o recebimento quando quiser por +2,99%", "pagbank-for-woocommerce"),
		],
	},
];

const OWN_CONDITION_ID = "f2ad0df4-4e52-4cef-97b2-4fcf1405ab9a";

export const PlanSelector = ({ onSelectPlan, isLoading }: PlanSelectorProps) => {
	return (
		<div className="pagbank-plan-selector">
			<p className="pagbank-plan-selector__intro">
				{__(
					"Para conectar o método de pagamento, é necessário que você possua uma conta PagBank.",
					"pagbank-for-woocommerce",
				)}{" "}
				<a
					href="https://cadastro.pagseguro.uol.com.br/"
					target="_blank"
					rel="noopener noreferrer"
				>
					{__(
						"Caso ainda não tenha a conta, clique aqui para criar uma nova.",
						"pagbank-for-woocommerce",
					)}
				</a>
			</p>

			<p className="pagbank-plan-selector__choose">
				{__(
					"Escolha o plano de recebimento que mais combina com o seu negócio:",
					"pagbank-for-woocommerce",
				)}
			</p>

			<div className="pagbank-plan-selector__plans">
				{PLANS.map((plan) => (
					<div key={plan.id} className="pagbank-plan-selector__plan">
						<h4 className="pagbank-plan-selector__plan-title">{plan.title}</h4>
						<ul className="pagbank-plan-selector__plan-features">
							{plan.features.map((feature) => (
								<li key={feature}>{feature}</li>
							))}
						</ul>
						<Button
							variant="primary"
							onClick={() => onSelectPlan(plan.id)}
							disabled={isLoading}
							isBusy={isLoading}
						>
							{__("Escolher este", "pagbank-for-woocommerce")}
						</Button>
					</div>
				))}
			</div>

			<div className="pagbank-plan-selector__own-condition">
				<Button
					variant="secondary"
					onClick={() => onSelectPlan(OWN_CONDITION_ID)}
					disabled={isLoading}
				>
					{__(
						"Já negociei minha própria condição comercial com o PagBank",
						"pagbank-for-woocommerce",
					)}
				</Button>
			</div>
		</div>
	);
};
