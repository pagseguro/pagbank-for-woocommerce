import { globSync, rmSync } from "node:fs";
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
	"public/order-received/order-received-pooling":
		"src/ui/entries/public/order-received/order-received-pooling.ts",
	"public/legacy/checkout-credit-card": "src/ui/entries/public/legacy/checkout-credit-card.ts",
	"public/blocks/checkout-boleto": "src/ui/entries/public/blocks/checkout-boleto.tsx",
	"public/blocks/checkout-pix": "src/ui/entries/public/blocks/checkout-pix.tsx",
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

	// Build SCSS files
	console.log("\nBuilding SCSS styles...");
	const scssFiles = globSync("src/ui/styles/**/*.scss", { cwd: rootDir });

	for (const scssFile of scssFiles) {
		const relativePath = scssFile.replace("src/ui/styles/", "").replace(".scss", "");
		console.log(`  Compiling ${relativePath}...`);

		await build({
			configFile: false,
			root: rootDir,
			build: {
				emptyOutDir: false,
				cssCodeSplit: false,
				rollupOptions: {
					input: resolve(rootDir, scssFile),
					output: {
						dir: resolve(rootDir, "dist/styles"),
						assetFileNames: `${relativePath}.css`,
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
