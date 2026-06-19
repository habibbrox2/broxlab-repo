/**
 * ESLint Flat Config (v10) — converted from .eslintrc.cjs + build/eslint.config.cjs
 *
 * Flat config removes support for .eslintrc.* files and .eslintignore.
 * All configuration lives in this single file.
 */
import js from '@eslint/js';
import globals from 'globals';
import stylistic from '@stylistic/eslint-plugin';

/** @type {import('eslint').Linter.Config[]} */
export default [
  // ── Ignored paths ──────────────────────────────────────────────────────────
  {
    ignores: [
      '**/node_modules/',
      '**/dist/',
      '**/*.min.js',
      'public_html/assets/js/dist/',
      'public_html/assets/js/admin-bulk-article-writer.js',
      'public_html/assets/js/bangla-converter.js',
      'public_html/assets/firebase/v2/**',
      'public_html/assets/js/test-template.mjs',
      'build/Scripts/**',
      'build/esbuild.config.js',
      'build/esbuild-firebase.mjs',
    ],
  },

  // ── JavaScript / MJS files ─────────────────────────────────────────────────
  {
    files: ['public_html/assets/js/**/*.js', 'public_html/assets/js/**/*.mjs'],
    plugins: {
      '@stylistic': stylistic,
    },
    languageOptions: {
      ecmaVersion: 2021,
      sourceType: 'module',
      globals: {
        ...globals.browser,
        ...globals.node,
        // Project-specific globals (non-standard browser globals)
        Swal: 'readonly',
        Chart: 'readonly',
        broxI18n: 'readonly',
        broxUI: 'readonly',
        withAssetVersion: 'readonly',
        showToast: 'readonly',
        MessageHandlerConfig: 'readonly',
        Apex: 'readonly',
        delegateClick: 'readonly',
        delegateChange: 'readonly',
        delegateKeydown: 'readonly',
        delegateAll: 'readonly',
        delegateEvent: 'readonly',
      },
    },
    rules: {
      // ESLint recommended core rules
      ...js.configs.recommended.rules,

      // From original .eslintrc.cjs
      'no-console': 'off',
      'no-unused-vars': ['warn', {
        argsIgnorePattern: '^(_.*|e|err|error|params)$',
        varsIgnorePattern: '^(_.*|e|err|error|params)$',
        caughtErrorsIgnorePattern: '^(_.*|e|err|error|params)$',
      }],

      // From build/eslint.config.cjs (flat config attempt)
      'require-await': 'error',
      'no-debugger': 'warn',
      'no-var': 'error',
      'prefer-const': ['error', { destructuring: 'all' }],
      'prefer-arrow-callback': 'warn',
      'prefer-template': 'warn',
      eqeqeq: ['error', 'always', { null: 'ignore' }],
      'no-implicit-coercion': 'warn',
      '@stylistic/no-multi-spaces': 'warn',
      '@stylistic/keyword-spacing': 'warn',
      '@stylistic/space-before-function-paren': ['warn', { anonymous: 'always', named: 'never' }],
      '@stylistic/semi': ['error', 'always'],
      '@stylistic/quotes': ['warn', 'single', { avoidEscape: true }],
      '@stylistic/indent': ['warn', 2],
      '@stylistic/comma-dangle': ['warn', { arrays: 'always', objects: 'always', functions: 'never' }],
      '@stylistic/object-curly-spacing': ['warn', 'always'],
      '@stylistic/array-bracket-spacing': ['warn', 'never'],
      '@stylistic/space-in-parens': ['warn', 'never'],
      '@stylistic/key-spacing': ['warn', { beforeColon: false, afterColon: true }],
      '@stylistic/no-trailing-spaces': 'warn',
      '@stylistic/no-multiple-empty-lines': ['warn', { max: 2 }],
      'no-constant-condition': ['error', { checkLoops: false }],
    },
  },
];
