import { resolve } from "path";
import { defineConfig } from "vite";
import createExternal from "vite-plugin-external";

import autoZip from "./plugins/auto-zip";

export default defineConfig({
	plugins: [
		autoZip(),
		createExternal({
			externals: {
				"@woocommerce/blocks-registry": "wc.wcBlocksRegistry",
				"@woocommerce/settings": "wc.wcSettings",
				"@wordpress/html-entities": "wp.htmlEntities",
				"@wordpress/element": "wp.element",
				react: "React",
				"react-dom": "ReactDOM",
			},
		}),
	],
	build: {
		lib: {
			entry: {
				"admin/admin-settings": resolve(
					__dirname,
					"src/ui/entries/admin/admin-settings.ts",
				),
				"public/order": resolve(__dirname, "src/ui/entries/public/order.ts"),
				"public/checkout-credit-card": resolve(
					__dirname,
					"src/ui/entries/public/checkout-credit-card.ts",
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
