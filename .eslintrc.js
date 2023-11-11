module.exports = {
	env: {
		browser: true,
		es2021: true,
		node: true,
	},
	extends: [
        "@eliasjnior/eslint-config-settings/rules/common",
    ],
	parserOptions: {
		ecmaVersion: "latest",
		sourceType: "module",
		project: "./tsconfig.json",
	},
};
