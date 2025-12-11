/**
 * Connect modal component for OAuth flow.
 *
 * @package PagBank_WooCommerce
 */

import { Button, Modal, Spinner } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { TEXT_DOMAIN } from "@/constants";
import type { Environment } from "../../types/settings";
import { PlanSelector } from "./PlanSelector";

interface ConnectModalProps {
	isOpen: boolean;
	onClose: () => void;
	environment: Environment;
	onConnect: (applicationId: string) => void;
	isLoading: boolean;
}

// TODO: Get this application id from the localized settings.
const SANDBOX_APPLICATION_ID = "fa1553af-5f0c-4ff2-92c3-a0dd8984b6a1";

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
			? __("Conectar ao PagBank (Sandbox)", TEXT_DOMAIN)
			: __("Conectar ao PagBank", TEXT_DOMAIN);

	return (
		<Modal title={title} onRequestClose={onClose} className="pagbank-connect-modal">
			{environment === "sandbox" ? (
				<div className="pagbank-connect-modal__sandbox">
					<p>
						{__(
							"Você está em modo de testes, portanto nenhuma taxa será aplicada. Clique no botão abaixo para continuar.",
							TEXT_DOMAIN,
						)}
					</p>
					<Button
						variant="primary"
						onClick={() => onConnect(SANDBOX_APPLICATION_ID)}
						disabled={isLoading}
						isBusy={isLoading}
					>
						{isLoading ? (
							<>
								<Spinner />
								{__("Carregando...", TEXT_DOMAIN)}
							</>
						) : (
							__("Continuar", TEXT_DOMAIN)
						)}
					</Button>
				</div>
			) : (
				<PlanSelector onSelectPlan={onConnect} isLoading={isLoading} />
			)}
		</Modal>
	);
};
