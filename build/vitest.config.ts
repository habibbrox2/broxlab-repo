/**
 * Vitest Configuration
 * Unit testing framework configuration
 */

import { defineConfig } from 'vitest/config';
import { fileURLToPath } from 'url';
import path from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

export default defineConfig({
    test: {
        environment: 'jsdom',
        globals: true,
        coverage: {
            provider: 'v8',
            reporter: ['text', 'json', 'html'],
            exclude: [
                'node_modules/',
                'build/',
                'public_html/assets/**/dist/',
            ],
        },
        include: ['**/__tests__/**/*.{test,spec}.{js,ts}'],
        exclude: ['node_modules', 'build', 'public_html/assets/**/dist'],
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './public_html/assets'),
            '@js': path.resolve(__dirname, './public_html/assets/js'),
            '@css': path.resolve(__dirname, './public_html/assets/css'),
        },
    },
});
