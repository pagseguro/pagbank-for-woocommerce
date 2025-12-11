import { cpSync, globSync, rmSync } from "node:fs";
import { resolve } from "node:path";
import { build } from "vite";
import {
	entries,
	getEntryBuildConfig,
	getScssBuildConfig,
	rootDir,
} from "./helpers/vite-config.ts";

async function buildAll() {
	// Clean dist directory first
	rmSync(resolve(rootDir, "dist"), { recursive: true, force: true });

	// Build each entry point separately
	for (const [name, entry] of Object.entries(entries)) {
		console.log(`\nBuilding ${name}...`);
		await build(getEntryBuildConfig(name, entry));
	}

	// Build SCSS files
	console.log("\nBuilding SCSS styles...");
	const scssFiles = globSync("src/ui/styles/**/*.scss", { cwd: rootDir });

	for (const scssFile of scssFiles) {
		const relativePath = scssFile.replace("src/ui/styles/", "").replace(".scss", "");
		console.log(`  Compiling ${relativePath}...`);
		await build(getScssBuildConfig(scssFile));
	}

	// Copy images to dist
	console.log("\nCopying images...");
	cpSync(resolve(rootDir, "src/images"), resolve(rootDir, "dist/images"), { recursive: true });

	console.log("\nAll builds completed!");
}

buildAll().catch((err) => {
	console.error(err);
	process.exit(1);
});
