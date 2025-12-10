import { globSync } from "node:fs";
import { build } from "vite";
import {
	entries,
	getEntryBuildConfig,
	getScssBuildConfig,
	rootDir,
} from "./helpers/vite-config.ts";

async function watchAll() {
	console.log("Starting development mode with watch...\n");

	const watchers: ReturnType<typeof build>[] = [];

	// Watch each entry point
	for (const [name, entry] of Object.entries(entries)) {
		console.log(`Watching ${name}...`);
		watchers.push(build(getEntryBuildConfig(name, entry, { isDev: true })));
	}

	// Watch SCSS files
	const scssFiles = globSync("src/ui/styles/**/*.scss", { cwd: rootDir });

	for (const scssFile of scssFiles) {
		const relativePath = scssFile.replace("src/ui/styles/", "").replace(".scss", "");
		console.log(`Watching ${relativePath}.scss...`);
		watchers.push(build(getScssBuildConfig(scssFile, { isDev: true })));
	}

	console.log("\nWatching for changes... Press Ctrl+C to stop.\n");

	await Promise.all(watchers);
}

watchAll().catch((err) => {
	console.error(err);
	process.exit(1);
});
