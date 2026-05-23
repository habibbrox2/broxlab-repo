module.exports = {
    root: true,
    env: {
        browser: true,
        node: true,
        es2021: true,
    },
    parser: '@typescript-eslint/parser',
    parserOptions: {
        ecmaVersion: 2020,
        sourceType: 'module',
    },
    plugins: ['@typescript-eslint'],
    extends: [
        'eslint:recommended',
        'plugin:@typescript-eslint/recommended'
    ],
    overrides: [
        {
            files: ['**/*.ts', '**/*.tsx'],
            parserOptions: {
                project: './build/tsconfig.json',
            },
        },
    ],
    globals: {
        bootstrap: 'readonly',
        Swal: 'readonly',
        Chart: 'readonly',
        broxI18n: 'readonly',
        withAssetVersion: 'readonly',
        showToast: 'readonly',
        MessageHandlerConfig: 'readonly',
        Apex: 'readonly',
    },
    rules: {
        'no-console': 'off',
        '@typescript-eslint/no-unused-vars': ['warn', { 'argsIgnorePattern': '^_' }],
    },
};