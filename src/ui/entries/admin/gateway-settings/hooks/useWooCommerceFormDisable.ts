/**
 * Hook to disable WooCommerce's native form change detection.
 *
 * @package PagBank_WooCommerce
 */

import { useEffect } from "react";

/**
 * Disables WooCommerce's native form change detection and handles
 * the beforeunload event based on the provided isDirty state.
 */
export const useWooCommerceFormDisable = (isDirty: boolean) => {
	// Manage beforeunload warning for unsaved changes
	useEffect(() => {
		const handleBeforeUnload = (e: BeforeUnloadEvent) => {
			if (isDirty) {
				e.preventDefault();
				e.returnValue = "";
				return "";
			}
		};

		window.addEventListener("beforeunload", handleBeforeUnload);

		return () => {
			window.removeEventListener("beforeunload", handleBeforeUnload);
		};
	}, [isDirty]);

	// Disable WooCommerce's native form change detection on mount
	useEffect(() => {
		// WooCommerce uses jQuery to detect form changes via wc_admin_meta_boxes.changed
		// We need to disable this since we handle our own dirty state
		const disableWcChangeDetection = () => {
			// biome-ignore lint/suspicious/noExplicitAny: WooCommerce global not typed
			const wc = (window as any).woocommerce_admin;
			if (wc) {
				wc.unsaved_changes = false;
			}

			// Also try to unbind the beforeunload from jQuery
			// biome-ignore lint/suspicious/noExplicitAny: jQuery global not typed
			const $ = (window as any).jQuery;
			if ($) {
				$(window).off("beforeunload.woocommerce");
			}
		};

		disableWcChangeDetection();

		// Re-run periodically in case WooCommerce re-attaches
		const interval = setInterval(disableWcChangeDetection, 1000);

		return () => {
			clearInterval(interval);
		};
	}, []);
};
