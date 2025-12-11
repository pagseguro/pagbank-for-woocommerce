/**
 * Form context for gateway settings.
 *
 * @package PagBank_WooCommerce
 */

import { createContext, useContext } from "react";
import type { UseFormReturn } from "react-hook-form";
import type { GatewaySettings } from "../schemas/settings";

interface FormContextValue {
	form: UseFormReturn<GatewaySettings>;
	isSaving: boolean;
}

const FormContext = createContext<FormContextValue | null>(null);

export const FormProvider = FormContext.Provider;

export const useFormContext = () => {
	const context = useContext(FormContext);
	if (!context) {
		throw new Error("useFormContext must be used within a FormProvider");
	}
	return context;
};
