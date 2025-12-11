/**
 * Animated field wrapper for conditional visibility with smooth transitions.
 *
 * @package PagBank_WooCommerce
 */

import { decodeEntities } from "@wordpress/html-entities";
import { useEffect, useRef, useState } from "react";

interface AnimatedFieldProps {
	visible: boolean;
	children: React.ReactNode;
	className?: string;
}

export const AnimatedField = ({ visible, children, className = "" }: AnimatedFieldProps) => {
	const [shouldRender, setShouldRender] = useState(visible);
	const [animationState, setAnimationState] = useState<"entering" | "exiting" | "idle">("idle");
	const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

	useEffect(() => {
		if (timeoutRef.current) {
			clearTimeout(timeoutRef.current);
		}

		if (visible) {
			setShouldRender(true);
			// Small delay to ensure DOM is ready for animation
			requestAnimationFrame(() => {
				setAnimationState("entering");
			});
			timeoutRef.current = setTimeout(() => {
				setAnimationState("idle");
			}, 250);
		} else if (shouldRender) {
			setAnimationState("exiting");
			timeoutRef.current = setTimeout(() => {
				setShouldRender(false);
				setAnimationState("idle");
			}, 200);
		}

		return () => {
			if (timeoutRef.current) {
				clearTimeout(timeoutRef.current);
			}
		};
	}, [visible, shouldRender]);

	if (!shouldRender) {
		return null;
	}

	return (
		<div
			className={`pagbank-animated-field ${animationState} ${decodeEntities(className)}`.trim()}
		>
			{children}
		</div>
	);
};
