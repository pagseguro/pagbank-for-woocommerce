import Fs from "node:fs";
import Path, { dirname } from "node:path";
import { fileURLToPath } from "node:url";
import JSZip from "jszip";

import data from "../package.json";

const __dirname = dirname(fileURLToPath(import.meta.url));

const exportFileName = `${data.name}-${data.version}.zip`;

/**
 * Files and directories to include in the bundle.
 * Use trailing slash for directories (e.g., "dist/").
 */
const filesToInclude = [
	"dist/",
	"languages/",
	"src/core/",
	"src/templates/",
	"vendor/",
	"pagbank-for-woocommerce.php",
	"readme.txt",
];

const getEntries = (dir: string): string[] => {
	if (!Fs.existsSync(dir)) {
		return [];
	}

	let foundEntries: string[] = [];
	const entries = Fs.readdirSync(dir);

	for (const entry of entries) {
		const path = Path.resolve(dir, entry);
		const stat = Fs.lstatSync(path);

		if (stat.isDirectory()) {
			const directoryEntries = getEntries(path);
			foundEntries = [...foundEntries, ...directoryEntries];
		} else {
			foundEntries = [...foundEntries, path];
		}
	}

	return foundEntries;
};

async function createZip() {
	const zipper = new JSZip();
	const rootDir = Path.resolve(__dirname, "..");
	const allFiles = getEntries(rootDir);

	// Add a folder with the plugin name
	const folder = zipper.folder(data.name);

	if (folder == null) {
		throw new Error("Could not create folder");
	}

	let fileCount = 0;

	// Add only files that match the include patterns
	for (const file of allFiles) {
		const filePath = file.slice(rootDir.length + 1);

		// Check if file should be included
		const shouldInclude = filesToInclude.some((include) => filePath.startsWith(include));

		if (shouldInclude) {
			folder.file(filePath, Fs.readFileSync(file));
			fileCount++;
		}
	}

	console.log(`Adding ${fileCount} files to zip...`);

	// Create build directory
	if (!Fs.existsSync("build")) {
		Fs.mkdirSync("build");
	}

	const exportPath = `build/${exportFileName}`;

	// Generate the zip
	await new Promise<void>((resolve, reject) => {
		zipper
			.generateNodeStream({
				type: "nodebuffer",
				streamFiles: true,
			})
			.pipe(Fs.createWriteStream(exportPath))
			.on("finish", () => {
				console.log(`Zip file created: ${exportPath}`);
				resolve();
			})
			.on("error", (err) => {
				reject(err);
			});
	});
}

createZip().catch((err) => {
	console.error("Failed to create zip:", err);
	process.exit(1);
});
