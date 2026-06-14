/**
 * ESLint Configuration (v9 - Flat Config Format)
 * Single config file for all JavaScript linting
 */

const js = require('@eslint/js');
const globals = require('globals');
const typescriptParser = require('@typescript-eslint/parser');
const tsEslint = require('@typescript-eslint/eslint-plugin');

module.exports = [
  {
    ignores: [
      '**/node_modules/',
      '**/dist/',
      '**/*.min.js',
      '**/.next/',
      '**/vendor/',
      'build/Scripts/**',
      'build/esbuild.config.js',
      'build/esbuild-firebase.mjs',
      'public_html/assets/js/bootstrap-lite.js',
      'public_html/assets/firebase/v2/**',
    ],
  },
  {
    files: ['**/*.js', '**/*.mjs'],
    languageOptions: {
      ecmaVersion: 2020,
      sourceType: 'module',
      globals: {
        ...globals.browser,
        ...globals.node,
        firebase: 'readonly',
        moment: 'readonly',
        Apex: 'readonly',
        bootstrap: 'readonly',
        Chart: 'readonly',
        Swal: 'readonly',
        showToast: 'readonly',
        withAssetVersion: 'readonly',
        MessageHandlerConfig: 'readonly',
      },
    },
    rules: {
      ...js.configs.recommended.rules,
      'no-unused-vars': ['warn', { argsIgnorePattern: '^_', caughtErrors: 'none' }],
      'no-console': ['warn', { allow: ['warn', 'error', 'info'] }],
      'require-await': 'error',
      'no-constant-condition': ['error', { checkLoops: false }],
      'no-debugger': 'warn',
      'no-var': 'error',
      'prefer-const': ['error', { destructuring: 'all' }],
      'prefer-arrow-callback': 'warn',
      'prefer-template': 'warn',
      eqeqeq: ['error', 'always', { null: 'ignore' }],
      'no-implicit-coercion': 'warn',
      'no-multi-spaces': 'warn',
      'keyword-spacing': 'warn',
      'space-before-function-paren': ['warn', { anonymous: 'always', named: 'never' }],
      semi: ['error', 'always'],
      quotes: ['warn', 'single', { avoidEscape: true }],
      indent: ['warn', 2],
      'comma-dangle': ['warn', { arrays: 'always', objects: 'always', functions: 'never' }],
      'object-curly-spacing': ['warn', 'always'],
      'array-bracket-spacing': ['warn', 'never'],
      'space-in-parens': ['warn', 'never'],
      'key-spacing': ['warn', { beforeColon: false, afterColon: true }],
      'no-trailing-spaces': 'warn',
      'no-multiple-empty-lines': ['warn', { max: 2 }],
    },
  },
  {
    files: ['**/*.ts', '**/*.tsx'],
    languageOptions: {
      parser: typescriptParser,
      parserOptions: {
        ecmaVersion: 2020,
        sourceType: 'module',
        project: './tsconfig.json',
        tsconfigRootDir: __dirname,
        ecmaFeatures: {
          jsx: true,
        },
      },
      globals: {
        ...globals.browser,
        ...globals.node,
      },
    },
    plugins: {
      '@typescript-eslint': tsEslint,
    },
    rules: {
      ...js.configs.recommended.rules,
      ...tsEslint.configs.recommended.rules,
      'no-unused-vars': 'off', // Handled by TypeScript
    },
  },
];
