import { rmSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { build } from "vite";

const __dirname = dirname(fileURLToPath(import.meta.url));

const externals: Record<string, string> = {
	// React dependencies
	react: "React",
	"react-dom": "ReactDOM",
	// WordPress dependencies
	"@wordpress/admin-ui": "wp.adminUi",
	"@wordpress/dataviews": "wp.dataviews",
	"@wordpress/dataviews/wp": "wp.dataviews.wp",
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

const entries: Record<string, string> = {
	"admin/admin-settings": "src/ui/entries/admin/admin-settings.ts",
	"public/order": "src/ui/entries/public/order.ts",
	"public/checkout-credit-card": "src/ui/entries/public/checkout-credit-card.ts",
	"public/checkout-boleto": "src/ui/entries/public/checkout-boleto.tsx",
	"public/checkout-pix": "src/ui/entries/public/checkout-pix.tsx",
};

const rootDir = resolve(__dirname, "..");

async function buildAll() {
	// Clean dist directory first
	rmSync(resolve(rootDir, "dist"), { recursive: true, force: true });

	// Build each entry point separately
	for (const [name, entry] of Object.entries(entries)) {
		console.log(`\nBuilding ${name}...`);

		await build({
			configFile: false,
			root: rootDir,
			resolve: {
				alias: {
					"@": resolve(rootDir, "src/ui"),
				},
			},
			define: {
				"process.env.NODE_ENV": `"${process.env.NODE_ENV ?? "production"}"`,
			},
			build: {
				emptyOutDir: false,
				lib: {
					entry: resolve(rootDir, entry),
					name: name.replace(/[/-]/g, "_"),
					formats: ["iife"],
					fileName: () => `${name}.js`,
				},
				rollupOptions: {
					external: Object.keys(externals),
					output: {
						globals: externals,
						dir: resolve(rootDir, "dist"),
					},
				},
			},
		});
	}

	console.log("\nAll builds completed!");
}

buildAll().catch((err) => {
	console.error(err);
	process.exit(1);
});
