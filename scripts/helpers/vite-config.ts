import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import type { BuildOptions } from "vite";

const __dirname = dirname(fileURLToPath(import.meta.url));

export const rootDir = resolve(__dirname, "../..");

export const externals: Record<string, string> = {
	// React dependencies
	react: "React",
	"react-dom": "ReactDOM",
	// WordPress dependencies
	"@wordpress/admin-ui": "wp.adminUi",
	"@wordpress/dataviews": "wp.dataviews",
	"@wordpress/dataviews/wp": "wp.dataviews.wp",
	"@wordpress/element": "wp.element",
	"@wordpress/icons": "wp.icons",
	"@wordpress/interface": "wp.interface",
	"@wordpress/sync": "wp.sync",
	"@wordpress/undo-manager": "wp.undoManager",
	"@wordpress/upload-media": "wp.uploadMedia",
	"@wordpress/fields": "wp.fields",
	"@wordpress/views": "wp.views",
	"@wordpress/html-entities": "wp.htmlEntities",
	// WooCommerce dependencies
	"@woocommerce/blocks-checkout": "wc.blocksCheckout",
	"@woocommerce/blocks-checkout-events": "wc.blocksCheckoutEvents",
	"@woocommerce/blocks-components": "wc.blocksComponents",
	"@woocommerce/price-format": "wc.priceFormat",
	"@woocommerce/block-data": "wc.wcBlocksData",
	"@woocommerce/blocks-registry": "wc.wcBlocksRegistry",
	"@woocommerce/shared-context": "wc.wcBlocksSharedContext",
	"@woocommerce/shared-hocs": "wc.wcBlocksSharedHocs",
	"@woocommerce/settings": "wc.wcSettings",
	"@woocommerce/types": "wc.wcTypes",
};

export const entries: Record<string, string> = {
	"admin/admin-settings": "src/ui/entries/admin/admin-settings.ts",
	"public/order-received/order-received-pooling":
		"src/ui/entries/public/order-received/order-received-pooling.ts",
	"public/legacy/checkout-credit-card": "src/ui/entries/public/legacy/checkout-credit-card.ts",
	"public/blocks/checkout-boleto": "src/ui/entries/public/blocks/checkout-boleto.tsx",
	"public/blocks/checkout-pix": "src/ui/entries/public/blocks/checkout-pix.tsx",
	"public/blocks/checkout-credit-card":
		"src/ui/entries/public/blocks/checkout-credit-card/index.tsx",
	"public/blocks/checkout-pay-with-pagbank":
		"src/ui/entries/public/blocks/checkout-pay-with-pagbank.tsx",
};

interface BuildConfigOptions {
	isDev?: boolean;
}

export function getEntryBuildConfig(name: string, entry: string, options: BuildConfigOptions = {}) {
	const { isDev = false } = options;

	return {
		configFile: false as const,
		root: rootDir,
		resolve: {
			alias: {
				"@": resolve(rootDir, "src/ui"),
			},
		},
		define: {
			"process.env.NODE_ENV": isDev ? '"development"' : '"production"',
		},
		build: {
			emptyOutDir: false,
			sourcemap: isDev,
			minify: !isDev,
			watch: isDev ? {} : null,
			lib: {
				entry: resolve(rootDir, entry),
				name: name.replace(/[/-]/g, "_"),
				formats: ["iife"] as const,
				fileName: () => `${name}.js`,
			},
			rollupOptions: {
				external: Object.keys(externals),
				output: {
					globals: externals,
					dir: resolve(rootDir, "dist"),
				},
			},
		} satisfies BuildOptions,
	};
}

export function getScssBuildConfig(scssFile: string, options: BuildConfigOptions = {}) {
	const { isDev = false } = options;
	const relativePath = scssFile.replace("src/ui/styles/", "").replace(".scss", "");

	return {
		configFile: false as const,
		root: rootDir,
		build: {
			emptyOutDir: false,
			sourcemap: isDev,
			minify: !isDev,
			watch: isDev ? {} : null,
			cssCodeSplit: false,
			rollupOptions: {
				input: resolve(rootDir, scssFile),
				output: {
					dir: resolve(rootDir, "dist/styles"),
					assetFileNames: `${relativePath}.css`,
				},
			},
		} satisfies BuildOptions,
	};
}
