/**
 * Hook to disable WooCommerce's native form change detection.
 *
 * @package PagBank_WooCommerce
 */

import { useEffect, useRef } from "react";

/**
 * Disables WooCommerce's native form change detection and handles
 * the beforeunload event based on the provided isDirty state.
 */
export const useWooCommerceFormDisable = (isDirty: boolean) => {
	// Use ref to always have current isDirty value in the event handler
	const isDirtyRef = useRef(isDirty);
	isDirtyRef.current = isDirty;

	// Manage beforeunload warning for unsaved changes
	useEffect(() => {
		const handleBeforeUnload = (e: BeforeUnloadEvent) => {
			if (isDirtyRef.current) {
				e.preventDefault();
				e.returnValue = "";
				return "";
			}
		};

		window.addEventListener("beforeunload", handleBeforeUnload);

		return () => {
			window.removeEventListener("beforeunload", handleBeforeUnload);
		};
	}, []); // Empty deps - handler uses ref for current value

	// Disable WooCommerce's native form change detection
	useEffect(() => {
		const disableWcChangeDetection = () => {
			// biome-ignore lint/suspicious/noExplicitAny: WooCommerce global not typed
			const wc = (window as any).woocommerce_admin;
			if (wc) {
				wc.unsaved_changes = false;
			}

			// biome-ignore lint/suspicious/noExplicitAny: jQuery global not typed
			const $ = (window as any).jQuery;
			if ($) {
				// Remove all WooCommerce/WordPress beforeunload handlers
				$(window).off("beforeunload.woocommerce");
				$(window).off("beforeunload.edit-post");
				$(window).off("beforeunload.wp-editor");
			}
		};

		disableWcChangeDetection();

		// Re-run periodically in case WooCommerce re-attaches
		const interval = setInterval(disableWcChangeDetection, 1000);

		return () => {
			clearInterval(interval);
		};
	}, []);

	// When form becomes clean, aggressively remove any beforeunload handlers
	useEffect(() => {
		if (!isDirty) {
			// biome-ignore lint/suspicious/noExplicitAny: jQuery global not typed
			const $ = (window as any).jQuery;
			if ($) {
				$(window).off("beforeunload");
			}

			// Also set returnValue to empty on the window to clear any stuck state
			window.onbeforeunload = null;
		}
	}, [isDirty]);
};
