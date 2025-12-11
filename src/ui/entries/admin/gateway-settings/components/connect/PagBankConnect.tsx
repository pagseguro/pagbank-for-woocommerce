/**
 * PagBank Connect component with OAuth flow.
 *
 * @package PagBank_WooCommerce
 */

import { Button, Notice, Spinner } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useCallback, useState } from "react";
import { TEXT_DOMAIN } from "@/constants";
import { useConnectStatusQuery } from "../../hooks/useConnectStatusQuery";
import type { Environment } from "../../schemas/settings";
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
		scopes,
		missing_scopes,
		authentication_error,
		authorization_error,
	} = useConnectStatusQuery(environment);

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

				// Open OAuth popup centered on screen
				const popupWidth = 600;
				const popupHeight = 700;
				const left = window.screenX + (window.outerWidth - popupWidth) / 2;
				const top = window.screenY + (window.outerHeight - popupHeight) / 2;

				const popup = window.open(
					data.oauth_url,
					"pagbank_oauth",
					`width=${popupWidth},height=${popupHeight},left=${left},top=${top},scrollbars=yes,resizable=yes`,
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

	const errorMessage = error instanceof Error ? error.message : null;
	const displayError = errorMessage || connectError;

	const buttonText = connected
		? __("Conectar a outra conta do PagBank", TEXT_DOMAIN)
		: __("Conectar a uma conta do PagBank", TEXT_DOMAIN);

	return (
		<div className="pagbank-connect">
			{displayError && (
				<Notice status="error" isDismissible={false} className="pagbank-connect__error">
					{displayError}
				</Notice>
			)}

			{isLoading ? (
				<div className="pagbank-connect__loading">
					<Spinner />
					<span>{__("Verificando a sua conta do PagBank...", TEXT_DOMAIN)}</span>
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
							{__("Sessão expirada. Reconecte sua conta do PagBank.", TEXT_DOMAIN)}
						</Notice>
					)}

					{connected && !authentication_error && authorization_error && (
						<Notice
							status="warning"
							isDismissible={false}
							className="pagbank-connect__status"
						>
							{__(
								"Conectado, mas com permissões insuficientes. Reconecte para obter todas as permissões.",
								TEXT_DOMAIN,
							)}
						</Notice>
					)}

					{connected && (account || account_id) && (
						<Notice
							status="info"
							isDismissible={false}
							className="pagbank-connect__status"
						>
							<div className="pagbank-connect__account-info">
								<div className="pagbank-connect__account-details">
									<span className="pagbank-connect__account-details-label">
										{__("ID da conta:", TEXT_DOMAIN)}
									</span>
									<span className="pagbank-connect__account-details-value">
										{account_id}
									</span>
								</div>
								<div className="pagbank-connect__account-details">
									<span className="pagbank-connect__account-details-label">
										{__("Ambiente:", TEXT_DOMAIN)}
									</span>
									<span className="pagbank-connect__account-details-value">
										{environment === "sandbox" &&
											__("Sandbox (testes)", TEXT_DOMAIN)}
										{environment === "production" &&
											__("Produção", TEXT_DOMAIN)}
									</span>
								</div>
								<div className="pagbank-connect__account-details">
									<span className="pagbank-connect__account-details-label">
										{__("Nome:", TEXT_DOMAIN)}
									</span>
									<span className="pagbank-connect__account-details-value">
										{account?.name || __("**********", TEXT_DOMAIN)}
									</span>
								</div>
								<div className="pagbank-connect__account-details">
									<span className="pagbank-connect__account-details-label">
										{__("Email:", TEXT_DOMAIN)}
									</span>
									<span className="pagbank-connect__account-details-value">
										{account?.email || __("**********", TEXT_DOMAIN)}
									</span>
								</div>
								<div className="pagbank-connect__account-details">
									<span className="pagbank-connect__account-details-label">
										{__("Escopos:", TEXT_DOMAIN)}
									</span>
									<span className="pagbank-connect__account-details-value">
										{scopes.join(", ")}
									</span>
								</div>
							</div>
						</Notice>
					)}

					{connected &&
						!authentication_error &&
						!authorization_error &&
						!account &&
						account_id && (
							<Notice
								status="success"
								isDismissible={false}
								className="pagbank-connect__status"
							>
								{__("Conectado à conta:", TEXT_DOMAIN)}{" "}
								<strong>{account_id}</strong>
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

					<div className="pagbank-connect__actions">
						<Button
							variant="primary"
							onClick={handleOpenModal}
							disabled={isConnecting || isRefreshing}
							className="pagbank-connect__button"
						>
							{buttonText}
						</Button>
						<button
							type="button"
							onClick={refresh}
							disabled={isRefreshing}
							className="pagbank-connect__refresh-button"
							title={__("Atualizar status", TEXT_DOMAIN)}
							aria-label={__("Atualizar status", TEXT_DOMAIN)}
						>
							<svg
								xmlns="http://www.w3.org/2000/svg"
								viewBox="0 0 24 24"
								width="16"
								height="16"
								fill="currentColor"
								aria-hidden="true"
							>
								<path d="M17.65 6.35A7.958 7.958 0 0 0 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0 1 12 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z" />
							</svg>
						</button>
					</div>
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
