/**
 * Connect modal component for OAuth flow.
 *
 * @package PagBank_WooCommerce
 */

import { Button, Modal, Spinner } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import type { Environment } from "../../schemas/settings";
import { PlanSelector } from "./PlanSelector";

interface ConnectModalProps {
	isOpen: boolean;
	onClose: () => void;
	environment: Environment;
	onConnect: (applicationId: string) => void;
	isLoading: boolean;
}

export const ConnectModal = ({
	isOpen,
	onClose,
	environment,
	onConnect,
	isLoading,
}: ConnectModalProps) => {
	if (!isOpen) {
		return null;
	}

	const title =
		environment === "sandbox"
			? __("Conectar ao PagBank (Sandbox)", "pagbank-for-woocommerce")
			: __("Conectar ao PagBank", "pagbank-for-woocommerce");

	return (
		<Modal title={title} onRequestClose={onClose} className="pagbank-connect-modal">
			{environment === "sandbox" ? (
				<div className="pagbank-connect-modal__sandbox">
					<p>
						{__(
							"Você está em modo de testes, portanto nenhuma taxa será aplicada. Clique no botão abaixo para continuar.",
							"pagbank-for-woocommerce",
						)}
					</p>
					<Button
						variant="primary"
						onClick={() =>
							onConnect(window.pagbankSettings?.defaultSandboxApplicationId ?? "")
						}
						disabled={isLoading}
						isBusy={isLoading}
					>
						{isLoading ? (
							<>
								<Spinner />
								{__("Carregando...", "pagbank-for-woocommerce")}
							</>
						) : (
							__("Continuar", "pagbank-for-woocommerce")
						)}
					</Button>
				</div>
			) : (
				<PlanSelector onSelectPlan={onConnect} isLoading={isLoading} />
			)}
		</Modal>
	);
};
