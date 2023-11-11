import AutoNumeric from "autonumeric";
import axios from "axios";

// eslint-disable-next-line @typescript-eslint/naming-convention
declare const woocommerce_admin: {
	ajax_url: string;
};

/**
 * Add toggle functionality to checkbox.
 */
document.querySelectorAll("[data-toggle]").forEach((toggleInput) => {
	toggleInput.addEventListener("change", (event) => {
		const eventTarget = event.target as HTMLInputElement;
		const toggleTargetValue = eventTarget.getAttribute("data-toggle");
		const isChecked = eventTarget.checked;

		const hasToggleTargetValue = toggleTargetValue !== null;

		if (!hasToggleTargetValue) {
			throw new Error("data-toggle attribute is required");
		}

		const toggleTargetElements = document.querySelectorAll(toggleTargetValue);

		const hasToggleTargetElements = toggleTargetElements.length > 0;

		if (!hasToggleTargetElements) {
			throw new Error("data-toggle elements not found");
		}

		toggleTargetElements.forEach((toggleTargetElement) => {
			if (isChecked) {
				toggleTargetElement.removeAttribute("disabled");
			} else {
				toggleTargetElement.setAttribute("disabled", "disabled");

				if (
					toggleTargetElement instanceof HTMLInputElement &&
					toggleTargetElement.type === "checkbox"
				) {
					toggleTargetElement.checked = false;
					toggleTargetElement.dispatchEvent(new Event("change"));
				}
			}
		});
	});

	toggleInput.dispatchEvent(new Event("change"));
});

/**
 * Format currency input.
 */
document.querySelectorAll("[data-format-currency]").forEach((currencyInput) => {
	// eslint-disable-next-line no-new
	new AutoNumeric(currencyInput as HTMLElement, null, {
		currencySymbol: currencyInput.getAttribute("data-currency-symbol") ?? "R$ ",
		decimalCharacter: currencyInput.getAttribute("data-decimal-character") ?? ",",
		digitGroupSeparator: currencyInput.getAttribute("data-digit-group-separator") ?? ".",
		unformatOnSubmit: true,
	});
});

/**
 * Add PagBank Connect handler.
 */
document
	.querySelectorAll("[data-pagbank-connect-environment-select]")
	.forEach((pagBankConnectButton) => {
		const environmentModalId = pagBankConnectButton.getAttribute(
			"data-pagbank-connect-modal-environment-id",
		);

		if (environmentModalId === null) {
			throw new Error(
				"Missing data-pagbank-connect-modal-production-id or data-pagbank-connect-modal-sandbox-id attribute",
			);
		}

		const environmentSelectData = pagBankConnectButton.getAttribute(
			"data-pagbank-connect-environment-select",
		);

		if (environmentSelectData === null) {
			throw new Error("Missing data-pagbank-connect-environment-select attribute");
		}

		const environmentSelect = document.getElementById(
			environmentSelectData,
		) as HTMLSelectElement | null;

		if (environmentSelect === null) {
			throw new Error("data-pagbank-connect-environment-select element not found");
		}

		const getEnvironmentModalId = (): string => {
			const environment = environmentSelect.value;

			return environmentModalId.replace("{{environment}}", environment);
		};

		const openModal = (): void => {
			const id = getEnvironmentModalId();
			const modal = document.getElementById(id);

			if (modal === null) {
				throw new Error(`Modal with id ${id} not found`);
			}

			modal.classList.remove("hidden");

			modal.querySelectorAll("[data-modal-close-button]").forEach((closeButton) => {
				closeButton.addEventListener("click", () => {
					modal.classList.add("hidden");
				});
			});
		};

		const closeModal = (): void => {
			const id = getEnvironmentModalId();
			const modal = document.getElementById(id);

			if (modal === null) {
				throw new Error(`Modal with id ${id} not found`);
			}

			modal.classList.add("hidden");
		};

		pagBankConnectButton.addEventListener("click", () => {
			openModal();
		});

		const setButtonAsLoading = (): void => {
			pagBankConnectButton.classList.remove("button-primary");
			pagBankConnectButton.setAttribute("disabled", "disabled");
			pagBankConnectButton.textContent = pagBankConnectButton.getAttribute(
				"data-pagbank-loading-text",
			);
		};

		const setButtonAsDisconnected = (): void => {
			pagBankConnectButton.removeAttribute("disabled");
			pagBankConnectButton.classList.add("button-primary");
			pagBankConnectButton.textContent = pagBankConnectButton.getAttribute(
				"data-pagbank-not-connected-text",
			);
		};

		const setButtonAsConnected = (): void => {
			pagBankConnectButton.removeAttribute("disabled");
			pagBankConnectButton.classList.remove("button-primary");
			pagBankConnectButton.textContent = pagBankConnectButton.getAttribute(
				"data-pagbank-connected-text",
			);
		};

		window.addEventListener("update_pagbank_connect_oauth_status", () => {
			(async () => {
				closeModal();
				setButtonAsLoading();

				const { data } = await axios.get<{
					oauth_status: "connected" | "not_connected";
				}>(woocommerce_admin.ajax_url, {
					params: {
						action: "pagbank_woocommerce_oauth_status",
						environment: environmentSelect.value,
						nonce: pagBankConnectButton.getAttribute("data-pagbank-connect-nonce"),
					},
				});

				if (data.oauth_status === "connected") {
					setButtonAsConnected();
				} else {
					setButtonAsDisconnected();
				}
			})();
		});

		environmentSelect.addEventListener("change", () => {
			window.dispatchEvent(new Event("update_pagbank_connect_oauth_status"));
		});

		window.dispatchEvent(new Event("update_pagbank_connect_oauth_status"));
	});

document.querySelectorAll("[data-connect-application-id]").forEach((connectButton) => {
	const applicationId = connectButton.getAttribute("data-connect-application-id");
	const environment = connectButton.getAttribute("data-connect-application-environment");
	const nonce = connectButton.getAttribute("data-connect-nonce");

	if (applicationId == null) {
		throw new Error("Missing data-connect-application-id attribute");
	} else if (environment === null) {
		throw new Error("Missing data-connect-application-environment attribute");
	} else if (nonce === null) {
		throw new Error("Missing data-connect-nonce attribute");
	}

	connectButton.addEventListener("click", (event) => {
		event.preventDefault();
		const target = event.target as HTMLButtonElement;

		(async () => {
			try {
				target.classList.add("disabled");
				target.setAttribute("disabled", "disabled");

				const { data } = await axios.get(woocommerce_admin.ajax_url, {
					params: {
						action: "pagbank_woocommerce_oauth_url",
						id: applicationId,
						nonce,
						environment,
					},
				});

				const oauthWindow = window.open(data.oauth_url);

				if (oauthWindow != null) {
					const timer = setInterval(() => {
						if (oauthWindow.closed) {
							clearInterval(timer);
							window.dispatchEvent(new Event("update_pagbank_connect_oauth_status"));
						}
					}, 500);
				} else {
					alert(
						"Parece que seu navegador bloqueou a janela de autenticação. Por favor, desbloqueie e tente novamente.",
					);
				}
			} catch (error) {
				alert("Houve um erro na conexão. Por favor, tente novamente.");
			}

			target.classList.remove("disabled");
			target.removeAttribute("disabled");
		})();
	});
});
