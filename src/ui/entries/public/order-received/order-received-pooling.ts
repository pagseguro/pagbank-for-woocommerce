import apiFetch from "@wordpress/api-fetch";

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

// Order status polling for Pix and Pay with PagBank
interface OrderStatusResponse {
	order_id: number;
	status: string;
	payment_method: string;
	is_paid: boolean;
	pix_expiration_date?: string;
	qrcode_expiration_date?: string;
	is_expired?: boolean;
}

const orderStatusPoolingInit = (): void => {
	// Support both Pix and Pay with PagBank
	const orderStatusElement = document.querySelector(
		"[data-pagbank-pix-order-status], [data-pagbank-pay-with-pagbank-order-status]",
	) as HTMLElement;

	if (orderStatusElement) {
		const orderId = orderStatusElement.dataset.orderId;
		const orderKey = orderStatusElement.dataset.orderKey;
		const restUrl = orderStatusElement.dataset.restUrl;
		const isPaid = orderStatusElement.dataset.isPaid === "yes";

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

					const data = await apiFetch<OrderStatusResponse>({
						url: url.toString(),
						method: "GET",
						headers: {
							"X-WP-Nonce": pagbankOrderStatus.nonce,
						},
					});

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

					// Check if Pix or Pay with PagBank has expired
					if (
						(data.payment_method === "pagbank_pix" ||
							data.payment_method === "pagbank_pay_with_pagbank") &&
						data.is_expired
					) {
						// Stop polling - payment expired
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

orderStatusPoolingInit();
