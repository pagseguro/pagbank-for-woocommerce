module.exports = {
  env: {
    browser: true,
    es2021: true,
    node: true,
  },
  extends: [
    "plugin:@typescript-eslint/recommended",
    "plugin:prettier/recommended",
    "plugin:import/recommended",
  ],
  parserOptions: {
    ecmaVersion: "latest",
    sourceType: "module",
    project: "./tsconfig.json",
  },
  plugins: [
    "@typescript-eslint/eslint-plugin",
    "unused-imports",
    "eslint-plugin-import-helpers",
    "no-relative-import-paths",
  ],
  rules: {
    "prettier/prettier": "error",
    "unused-imports/no-unused-imports": "error",
    "no-unused-vars": "off",
    "@typescript-eslint/no-unused-vars": "off",
    "unused-imports/no-unused-vars": [
      "error",
      {
        vars: "all",
        varsIgnorePattern: "^_",
        args: "after-used",
        argsIgnorePattern: "^_",
      },
    ],
    "import/no-unresolved": "error",
    "import/named": "off",
    "import/no-duplicates": "error",
    "newline-before-return": "error",
    "import-helpers/order-imports": [
      "error",
      {
        newlinesBetween: "always",
        groups: [
          ["/^react$/", "/^react-native$/", "/^react-dom$/"],
          ["/^next$/", "/^next//"],
          "module",
          ["/^@//"],
          ["parent", "sibling", "index"],
        ],
        alphabetize: {
          order: "asc",
          ignoreCase: true,
        },
      },
    ],
    "sort-imports": [
      "error",
      {
        ignoreDeclarationSort: true,
      },
    ],
    "no-relative-import-paths/no-relative-import-paths": [
      "warn",
      { allowSameFolder: true, prefix: "@", rootDir: "src/" },
    ],
  },
  settings: {
    "import/parsers": {
      "@typescript-eslint/parser": [".ts", ".tsx"],
    },
    "import/resolver": {
      typescript: true,
      node: true,
    },
  },
};
