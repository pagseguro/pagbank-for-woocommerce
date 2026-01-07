/**
 * PagBank Connect component with OAuth flow.
 *
 * @package PagBank_WooCommerce
 */

import apiFetch from "@wordpress/api-fetch";
import { Button, Notice, Spinner } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useCallback, useState } from "react";
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
					"pagbank-for-woocommerce",
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

				const data = await apiFetch<{ oauth_url?: string }>({
					url: `${ajaxUrl}?${params.toString()}`,
					credentials: "same-origin",
				});

				if (!data.oauth_url) {
					throw new Error(__("URL de autorização inválida", "pagbank-for-woocommerce"));
				}

				console.info("OAuth URL", data.oauth_url);

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
							"pagbank-for-woocommerce",
						),
					);
				}

				let oauthError: string | null = null;

				// Listen for postMessage from popup.
				const messageHandler = (event: MessageEvent): void => {
					const data = event.data as {
						type?: string;
						success?: boolean;
						error?: string;
					};

					if (data?.type !== "pagbank_oauth_callback") {
						return;
					}

					if (!data.success && data.error) {
						oauthError = data.error;
					}
				};

				window.addEventListener("message", messageHandler);

				// Check for popup close.
				const checkPopup = setInterval(() => {
					if (popup.closed) {
						clearInterval(checkPopup);
						window.removeEventListener("message", messageHandler);

						if (oauthError) {
							setConnectError(oauthError);
						}

						setIsConnecting(false);
						setIsModalOpen(false);
						refresh();
					}
				}, 500);
			} catch (err) {
				setConnectError(
					err instanceof Error
						? err.message
						: __("Erro desconhecido", "pagbank-for-woocommerce"),
				);
				setIsConnecting(false);
			}
		},
		[ajaxUrl, environment, oauthNonce, refresh],
	);

	const errorMessage = error instanceof Error ? error.message : null;
	const displayError = errorMessage || connectError;

	const buttonText = connected
		? __("Conectar a outra conta do PagBank", "pagbank-for-woocommerce")
		: __("Conectar a uma conta do PagBank", "pagbank-for-woocommerce");

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
					<span>
						{__("Verificando a sua conta do PagBank...", "pagbank-for-woocommerce")}
					</span>
				</div>
			) : (
				<div className="pagbank-connect__content">
					{isRefreshing && (
						<div className="pagbank-connect__overlay">
							<Spinner />
						</div>
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
										{__("ID da conta:", "pagbank-for-woocommerce")}
									</span>
									<span className="pagbank-connect__account-details-value">
										{account_id}
									</span>
								</div>
								<div className="pagbank-connect__account-details">
									<span className="pagbank-connect__account-details-label">
										{__("Ambiente:", "pagbank-for-woocommerce")}
									</span>
									<span className="pagbank-connect__account-details-value">
										{environment === "sandbox" &&
											__("Sandbox (testes)", "pagbank-for-woocommerce")}
										{environment === "production" &&
											__("Produção", "pagbank-for-woocommerce")}
									</span>
								</div>
								{account?.name && (
									<div className="pagbank-connect__account-details">
										<span className="pagbank-connect__account-details-label">
											{__("Nome:", "pagbank-for-woocommerce")}
										</span>
										<span className="pagbank-connect__account-details-value">
											{account.name}
										</span>
									</div>
								)}
								{account?.email && (
									<div className="pagbank-connect__account-details">
										<span className="pagbank-connect__account-details-label">
											{__("Email:", "pagbank-for-woocommerce")}
										</span>
										<span className="pagbank-connect__account-details-value">
											{account.email}
										</span>
									</div>
								)}
								<div className="pagbank-connect__account-details">
									<span className="pagbank-connect__account-details-label">
										{__("Escopos:", "pagbank-for-woocommerce")}
									</span>
									<span className="pagbank-connect__account-details-value">
										{scopes.join(", ")}
									</span>
								</div>
							</div>
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
								"pagbank-for-woocommerce",
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
							title={__("Atualizar status", "pagbank-for-woocommerce")}
							aria-label={__("Atualizar status", "pagbank-for-woocommerce")}
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
