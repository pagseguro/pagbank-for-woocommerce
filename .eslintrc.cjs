module.exports = {
	env: {
		browser: true,
		es2021: true,
		node: true,
	},
	extends: ["standard-with-typescript", "prettier"],
	overrides: [],
	parserOptions: {
		ecmaVersion: "latest",
		sourceType: "module",
		project: "./tsconfig.json",
	},
	plugins: ["prettier"],
	rules: {
		"prettier/prettier": "error",
		"@typescript-eslint/no-floating-promises": "off",
		"@typescript-eslint/explicit-function-return-type": "warn",
		"@typescript-eslint/triple-slash-reference": "off",
	},
};
