const globals = require('globals');

module.exports = [
    {
        ignores: [
            'public_html/assets/js/dist/**',
            'public_html/assets/firebase/v2/dist/**'
        ]
    },
    {
        files: [
            'public_html/assets/js/**/*.js',
            'public_html/assets/firebase/v2/**/*.js',
            'public_html/assets/ai-assistant/**/*.js',
            'build/Scripts/**/*.mjs',
            'build/esbuild.config.js',
            'build/esbuild-firebase.mjs',
            'build/esbuild-assistants.mjs'
        ],
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
