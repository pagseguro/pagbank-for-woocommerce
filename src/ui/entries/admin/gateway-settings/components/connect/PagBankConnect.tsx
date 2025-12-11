/**
 * PagBank Connect component with OAuth flow.
 *
 * @package PagBank_WooCommerce
 */

import { Button, Notice, Spinner } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useCallback, useState } from "react";
import { TEXT_DOMAIN } from "@/constants";
import { useConnectStatus } from "../../hooks/useConnectStatus";
import type { Environment } from "../../types/settings";
import { ConnectModal } from "./ConnectModal";

interface PagBankConnectProps {
	environment: Environment;
}

export const PagBankConnect = ({ environment }: PagBankConnectProps) => {
	const {
		connected,
		account,
		account_id,
		isLoading,
		isRefreshing,
		refresh,
		error,
		missing_scopes,
		authentication_error,
	} = useConnectStatus(environment);
	const hasMissingScopes = missing_scopes.length > 0;
	const [isModalOpen, setIsModalOpen] = useState(false);
	const [isConnecting, setIsConnecting] = useState(false);
	const [connectError, setConnectError] = useState<string | null>(null);

	const isLocalhost = window.pagbankSettings?.isLocalhost ?? false;
	const oauthNonce = window.pagbankSettings?.oauthNonce ?? "";
	const ajaxUrl = window.pagbankSettings?.ajaxUrl ?? "/wp-admin/admin-ajax.php";

	const handleOpenModal = useCallback(() => {
		if (isLocalhost) {
			setConnectError(
				__(
					"A conexão com o PagBank não está disponível em localhost. Por favor, use um domínio válido.",
					TEXT_DOMAIN,
				),
			);
			return;
		}
		setConnectError(null);
		setIsModalOpen(true);
	}, [isLocalhost]);

	const handleCloseModal = useCallback(() => {
		setIsModalOpen(false);
	}, []);

	const handleConnect = useCallback(
		async (applicationId: string) => {
			setIsConnecting(true);
			setConnectError(null);

			try {
				const params = new URLSearchParams({
					action: "pagbank_woocommerce_oauth_url",
					nonce: oauthNonce,
					id: applicationId,
					environment,
				});

				const response = await fetch(`${ajaxUrl}?${params.toString()}`, {
					credentials: "same-origin",
				});

				if (!response.ok) {
					throw new Error(__("Erro ao obter URL de autorização", TEXT_DOMAIN));
				}

				const data = await response.json();

				if (!data.oauth_url) {
					throw new Error(__("URL de autorização inválida", TEXT_DOMAIN));
				}

				// Open OAuth popup
				const popup = window.open(
					data.oauth_url,
					"pagbank_oauth",
					"width=600,height=700,scrollbars=yes,resizable=yes",
				);

				if (!popup) {
					throw new Error(
						__(
							"Não foi possível abrir a janela de autorização. Verifique se o bloqueador de pop-ups está desativado.",
							TEXT_DOMAIN,
						),
					);
				}

				// Check for popup close
				const checkPopup = setInterval(() => {
					if (popup.closed) {
						clearInterval(checkPopup);
						setIsConnecting(false);
						setIsModalOpen(false);
						// Refresh status after popup closes
						refresh();
					}
				}, 500);
			} catch (err) {
				setConnectError(
					err instanceof Error ? err.message : __("Erro desconhecido", TEXT_DOMAIN),
				);
				setIsConnecting(false);
			}
		},
		[ajaxUrl, environment, oauthNonce, refresh],
	);

	const buttonText = connected
		? __("Conectar a outra conta do PagBank", TEXT_DOMAIN)
		: __("Conectar a uma conta do PagBank", TEXT_DOMAIN);

	return (
		<div className="pagbank-connect">
			{(error || connectError) && (
				<Notice status="error" isDismissible={false} className="pagbank-connect__error">
					{error || connectError}
				</Notice>
			)}

			{isLoading ? (
				<div className="pagbank-connect__loading">
					<Spinner />
					<span>{__("Verificando conexão...", TEXT_DOMAIN)}</span>
				</div>
			) : (
				<div className="pagbank-connect__content">
					{isRefreshing && (
						<div className="pagbank-connect__overlay">
							<Spinner />
						</div>
					)}

					{connected && authentication_error && (
						<Notice
							status="error"
							isDismissible={false}
							className="pagbank-connect__status"
						>
							{__(
								"Erro de autenticação. Reconecte sua conta do PagBank.",
								TEXT_DOMAIN,
							)}
						</Notice>
					)}

					{connected && !authentication_error && account && (
						<Notice
							status="success"
							isDismissible={false}
							className="pagbank-connect__status"
						>
							<div className="pagbank-connect__account-info">
								<span className="pagbank-connect__account-label">
									{__("Conectado à conta:", TEXT_DOMAIN)}
								</span>
								{account.name && (
									<span className="pagbank-connect__account-name">
										{account.name}
									</span>
								)}
								{account.email && (
									<span className="pagbank-connect__account-email">
										{account.email}
									</span>
								)}
							</div>
						</Notice>
					)}

					{connected && !authentication_error && !account && account_id && (
						<Notice
							status="success"
							isDismissible={false}
							className="pagbank-connect__status"
						>
							{__("Conectado à conta:", TEXT_DOMAIN)} <strong>{account_id}</strong>
						</Notice>
					)}

					{connected && hasMissingScopes && (
						<Notice
							status="warning"
							isDismissible={false}
							className="pagbank-connect__scope-warning"
						>
							{__(
								"Recomendamos reconectar ao PagBank para obter novas permissões e funcionalidades.",
								TEXT_DOMAIN,
							)}
						</Notice>
					)}

					<Button
						variant="primary"
						onClick={handleOpenModal}
						disabled={isConnecting || isRefreshing}
						className="pagbank-connect__button"
					>
						{buttonText}
					</Button>
				</div>
			)}

			<ConnectModal
				isOpen={isModalOpen}
				onClose={handleCloseModal}
				environment={environment}
				onConnect={handleConnect}
				isLoading={isConnecting}
			/>
		</div>
	);
};
