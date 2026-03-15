module.exports = {
    root: true,
    env: {
        browser: true,
        node: true,
        es2022: true
    },
    parserOptions: {
        ecmaVersion: 2022,
        sourceType: 'module'
    },
    ignorePatterns: [
        'public_html/assets/js/dist/**',
        'public_html/assets/firebase/v2/dist/**'
    ],
    rules: {
        'no-unused-vars': ['warn', {
            argsIgnorePattern: '^_',
            varsIgnorePattern: '^_',
            caughtErrorsIgnorePattern: '^_'
        }]
    }
};
