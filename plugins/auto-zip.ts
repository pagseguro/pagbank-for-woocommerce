import Fs from "fs";
import JSZip from "jszip";
import Path from "path";
import { type PluginOption } from "vite";

import data from "../package.json";

const exportFileName = `${data.name}-${data.version}.zip`;

const filesToIgnore = [
	".claude",
	"logs",
	".idea",
	"wordpress.ini",
	".editorconfig",
	".vscode",
	"TODO.md",
	"commitlint.config.cjs",
	".git",
	"build",
	".husky",
	"node_modules",
	"plugins",
	"scripts",
	"wp",
	".eslintrc.cjs",
	".gitignore",
	".commitlint.config.js",
	"composer.json",
	"composer.lock",
	"docker-compose.arm64v8.yml",
	"docker-compose.yml",
	"package.json",
	"README.md",
	"tsconfig.eslint.json",
	"tsconfig.json",
	"tsconfig.node.json",
	"vite.config.ts",
	"pnpm-lock.yaml",
	"phpcs.xml.dist",
	"phpunit.xml",
	"tests",
	"README_DEV.md",
	"landing-page",
	"wordpress_org_assets",
];

const getEntries = (dir: string): string[] => {
	if (!Fs.existsSync(dir)) {
		throw new Error("Invalid directory");
	}

	let foundEntries: string[] = [];
	const entries = Fs.readdirSync(dir);

	entries.forEach((entry) => {
		const path = Path.resolve(dir, entry);
		const stat = Fs.lstatSync(path);

		if (stat.isDirectory()) {
			const directoryEntries = getEntries(path);
			foundEntries = [...foundEntries, ...directoryEntries];
		} else {
			foundEntries = [...foundEntries, path];
		}
	});

	return foundEntries;
};

const autoZip = (): PluginOption => {
	return {
		name: "auto-zip",
		apply: "build",
		closeBundle: async () => {
			await new Promise((resolve, reject) => {
				if (process.env.NODE_ENV === "production") {
					const zipper = new JSZip();

					const folderPath = Path.resolve(__dirname, "../");

					const allFiles = getEntries(folderPath);

					// Add a folder with the plugin name
					const folder = zipper.folder(data.name);

					if (folder == null) {
						reject(new Error("Could not create folder"));

						return;
					}

					// Add all files to the folder
					allFiles.forEach((file) => {
						const filePath = file.slice(folderPath.length + 1);

						// Check if should ignore the file
						const shouldIgnore = filesToIgnore.some((ignore) =>
							filePath.startsWith(ignore),
						);

						if (!shouldIgnore) {
							folder.file(filePath, Fs.readFileSync(file));
						}
					});

					// Create build directory
					if (!Fs.existsSync("build")) {
						Fs.mkdirSync("build");
					}

					const exportPath = `build/${exportFileName}`;

					// Generate the zip
					zipper
						.generateNodeStream({
							type: "nodebuffer",
							streamFiles: true,
						})
						.pipe(Fs.createWriteStream(exportPath))
						.on("finish", () => {
							console.log("Zip file created: ", exportPath);
							resolve("Zip file created");
						})
						.on("error", (err) => {
							reject(err);
						});
				} else {
					resolve("Will not create zip file in development");
				}
			});
		},
	};
};

export default autoZip;
