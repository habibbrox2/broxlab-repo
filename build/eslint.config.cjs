const globals = require('globals');

module.exports = [
    {
        ignores: [
            'public_html/assets/js/dist/**',
            'public_html/assets/firebase/v2/dist/**'
        ]
    },
    {
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: 'module',
            globals: {
                ...globals.browser,
                ...globals.node
            }
        },
        rules: {
            'no-unused-vars': ['warn', {
                argsIgnorePattern: '^_',
                varsIgnorePattern: '^_',
                caughtErrorsIgnorePattern: '^(?:_|e|err|error)$'
            }]
        }
    }
];
