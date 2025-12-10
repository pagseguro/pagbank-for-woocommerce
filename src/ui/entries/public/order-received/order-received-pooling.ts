document.querySelectorAll("[data-copy-clipboard]").forEach((element) => {
	element.addEventListener("click", () => {
		const textToCopy = element.getAttribute("data-copy-clipboard");

		if (textToCopy !== null) {
			navigator.clipboard.writeText(textToCopy);
		}
	});
});

document.querySelectorAll("[data-select-on-click]").forEach((element) => {
	element.addEventListener("focus", (event) => {
		const target = event.target as HTMLInputElement;
		target.select();
	});
});

// Pix order status polling
interface OrderStatusResponse {
	order_id: number;
	status: string;
	payment_method: string;
	is_paid: boolean;
	pix_expiration_date?: string;
	is_expired?: boolean;
}

interface PagBankOrderStatusGlobal {
	nonce: string;
}

declare const pagbankOrderStatus: PagBankOrderStatusGlobal;

const pixOrderStatusPoolingInit = (): void => {
	const pixOrderStatusElement = document.querySelector(
		"[data-pagbank-pix-order-status]",
	) as HTMLElement;

	if (pixOrderStatusElement) {
		const orderId = pixOrderStatusElement.dataset.orderId;
		const orderKey = pixOrderStatusElement.dataset.orderKey;
		const restUrl = pixOrderStatusElement.dataset.restUrl;
		const isPaid = pixOrderStatusElement.dataset.isPaid === "yes";

		// Don't start polling if order is already paid
		if (isPaid) {
			return;
		}

		if (orderId && orderKey && restUrl && typeof pagbankOrderStatus !== "undefined") {
			let pollingInterval: number | null = null;
			let pollCount = 0;
			const maxPolls = 120; // 10 minutes (5 seconds * 120 = 600 seconds)

			const checkOrderStatus = async () => {
				try {
					const url = new URL(`pagbank/v1/order/${orderId}/status`, restUrl);
					url.searchParams.set("key", orderKey);

					const response = await fetch(url.toString(), {
						method: "GET",
						headers: {
							"Content-Type": "application/json",
							"X-WP-Nonce": pagbankOrderStatus.nonce,
						},
					});

					if (!response.ok) {
						console.error("Error checking order status:", response.statusText);

						return;
					}

					const data: OrderStatusResponse = await response.json();

					// Check if order is paid
					if (data.is_paid) {
						// Stop polling
						if (pollingInterval !== null) {
							clearInterval(pollingInterval);
							pollingInterval = null;
						}

						// Reload page to show updated status
						window.location.reload();

						return;
					}

					// Check if Pix has expired
					if (data.payment_method === "pagbank_pix" && data.is_expired) {
						// Stop polling - Pix expired
						if (pollingInterval !== null) {
							clearInterval(pollingInterval);
							pollingInterval = null;
						}

						return;
					}

					pollCount++;

					// Stop polling after max attempts
					if (pollCount >= maxPolls && pollingInterval !== null) {
						clearInterval(pollingInterval);
						pollingInterval = null;
					}
				} catch (error) {
					console.error("Error checking order status:", error);
				}
			};

			// Start polling every 5 seconds
			pollingInterval = window.setInterval(checkOrderStatus, 5000);

			// Also check immediately
			checkOrderStatus();

			// Clean up interval on page unload
			window.addEventListener("beforeunload", () => {
				if (pollingInterval !== null) {
					clearInterval(pollingInterval);
				}
			});
		}
	}
};

pixOrderStatusPoolingInit();
