/**
 * Hook to detect mobile viewport.
 *
 * @package PagBank_WooCommerce
 */

import { useEffect, useState } from "react";

/**
 * Breakpoint for mobile detection.
 * Matches WordPress "small" breakpoint (600px).
 */
const MOBILE_BREAKPOINT = 600;

/**
 * Custom hook to detect if the current viewport is mobile.
 * Uses native matchMedia API for reliable detection on frontend pages.
 *
 * @returns Whether the current viewport is mobile (< 600px).
 */
export const useIsMobile = (): boolean => {
	const [isMobile, setIsMobile] = useState(() => {
		if (typeof window === "undefined") {
			return false;
		}

		return window.matchMedia(`(max-width: ${MOBILE_BREAKPOINT - 1}px)`).matches;
	});

	useEffect(() => {
		const mediaQuery = window.matchMedia(`(max-width: ${MOBILE_BREAKPOINT - 1}px)`);

		const handleChange = (event: MediaQueryListEvent): void => {
			setIsMobile(event.matches);
		};

		// Set initial value
		setIsMobile(mediaQuery.matches);

		// Listen for changes
		mediaQuery.addEventListener("change", handleChange);

		return () => {
			mediaQuery.removeEventListener("change", handleChange);
		};
	}, []);

	return isMobile;
};
