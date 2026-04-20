/**
 * ESLint Configuration (v9 - Flat Config Format)
 * Single config file for all JavaScript linting
 */

const js = require('@eslint/js');
const globals = require('globals');

module.exports = [
  {
    ignores: ['**/node_modules/', '**/dist/', '**/*.min.js', '**/.next/', '**/vendor/'],
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
      },
    },
    rules: {
      ...js.configs.recommended.rules,
      'no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
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
      ecmaVersion: 2020,
      sourceType: 'module',
      parserOptions: {
        ecmaFeatures: {
          jsx: true,
        },
      },
      globals: {
        ...globals.browser,
        ...globals.node,
      },
    },
    rules: {
      ...js.configs.recommended.rules,
      'no-unused-vars': 'off', // Handled by TypeScript
    },
  },
];
