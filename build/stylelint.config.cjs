module.exports = {
    extends: [
        'stylelint-config-standard',
        'stylelint-config-recommended'
    ],
    rules: {
        // Allow CSS custom properties (variables)
        'property-no-unknown': [
            true,
            {
                ignoreProperties: [
                    // CSS custom properties
                    /^--/
                ]
            }
        ],
        // Allow vendor prefixes for better browser support
        'property-no-unknown': [
            true,
            {
                ignoreProperties: [
                    // Vendor prefixes
                    /^-webkit-/,
                    /^-moz-/,
                    /^-ms-/,
                    /^-o-/
                ]
            }
        ],
        // Allow at-rules for modern CSS
        'at-rule-no-unknown': [
            true,
            {
                ignoreAtRules: [
                    'tailwind',
                    'apply',
                    'variants',
                    'responsive',
                    'screen',
                    'layer'
                ]
            }
        ],
        // Allow unknown functions for CSS functions
        'function-no-unknown': [
            true,
            {
                ignoreFunctions: [
                    'theme',
                    'screen'
                ]
            }
        ],
        // Relax some rules for modern CSS
        'selector-class-pattern': null,
        'selector-id-pattern': null,
        'no-descending-specificity': null,
        'declaration-block-no-duplicate-properties': true,
        'declaration-block-no-shorthand-property-overrides': true,
        // Allow empty lines in blocks
        'rule-empty-line-before': null,
        // Allow duplicate selectors for CSS modules/overrides
        'no-duplicate-selectors': null,
        // Allow modern color functions
        'color-function-notation': null,
        'alpha-value-notation': null,
        'color-function-alias-notation': null,
        // Allow long hex colors for consistency
        'color-hex-length': null,
        // Allow system font names as-is
        'value-keyword-case': null,
        // Allow empty lines before comments
        'comment-empty-line-before': null,
        // Allow longhand properties for clarity
        'declaration-block-no-redundant-longhand-properties': null,
        // Allow deprecated properties for compatibility
        'declaration-property-value-keyword-no-deprecated': null,
        'property-no-deprecated': null,
        // Allow keyframe naming conventions
        'keyframes-name-pattern': null,
        // Allow media feature notations
        'media-feature-range-notation': null,
        'media-feature-name-value-no-unknown': null
    }
};