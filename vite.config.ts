import { defineConfig } from "vite";
import { resolve } from "path";
import autoZip from "./plugins/auto-zip";

export default defineConfig({
	plugins: [autoZip()],
	build: {
		lib: {
			entry: {
				"admin/admin-settings": resolve(
					__dirname,
					"src/ui/entries/admin/admin-settings.ts"
				),
				"public/order": resolve(__dirname, "src/ui/entries/public/order.ts"),
				"public/checkout-credit-card": resolve(
					__dirname,
					"src/ui/entries/public/checkout-credit-card.ts"
				),
			},
			name: "[name]",
			formats: ["es"],
		},
		rollupOptions: {
			output: {
				chunkFileNames: "ui/shared/[name]-[hash].js",
			},
		},
	},
	resolve: {
		alias: {
			"@": resolve(__dirname, "src/ui"),
		},
	},
	define: {
		"process.env.NODE_ENV": `"${process.env.NODE_ENV ?? "development"}"`,
	},
});
