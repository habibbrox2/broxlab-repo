/**
 * esbuild-css.mjs
 * ===================================================
 * CSS bundling and minification build script
 * 
 * Combines multiple CSS files into a single bundled file
 * for improved page load performance
 */

import * as esbuild from 'esbuild';
import { promises as fs } from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Configuration
const args = process.argv.slice(2);
const isWatch = args.includes('--watch');
const isMinify = !args.includes('--dev');

const OUTDIR = 'public_html/assets/css/dist';

async function ensureDir(dir) {
    try {
        await fs.mkdir(dir, { recursive: true });
    } catch (e) {
        if (e.code !== 'EEXIST') throw e;
    }
}

async function buildCSSBundle(name, cssFiles) {
    // Create entry file with @import statements
    const entryImports = cssFiles.map(f => {
        const relPath = path.relative(OUTDIR, f);
        return `@import "./${relPath.replace(/\\/g, '/')}";`;
    }).join('\n');

    const entryPath = `${OUTDIR}/${name}-entry.css`;
    await fs.writeFile(entryPath, entryImports);

    const cfg = {
        entryPoints: [entryPath],
        outdir: OUTDIR,
        bundle: true,
        minify: isMinify,
        sourcemap: isWatch ? 'inline' : false,
        target: ['es2020'],
        platform: 'browser',
        logLevel: 'info',
        loader: {
            '.css': 'css'
        },
        entryNames: name,
        mainFields: ['module', 'main'],
    };

    console.log(`Building ${name} CSS bundle (${isMinify ? 'minified' : 'dev'})...`);

    try {
        const result = await esbuild.build(cfg);
        if (result.errors && result.errors.length > 0) {
            console.error('Errors:', result.errors);
            process.exit(1);
        }

        const outFile = `${OUTDIR}/${name}.css`;
        try {
            const stats = await fs.stat(outFile);
            const sizeKB = (stats.size / 1024).toFixed(2);
            console.log(`✓ ${name} bundle: ${sizeKB} KB`);
        } catch (e) {
            console.log(`✓ ${name} bundle created`);
        }

        return true;
    } catch (err) {
        console.error(`Failed to build ${name}:`, err);
        return false;
    }
}

async function buildAll() {
    const publicFiles = [
        'public_html/assets/css/1-variables.css',
        'public_html/assets/css/2-base.css',
        'public_html/assets/css/3-navbar.css',
        'public_html/assets/css/4-components.css',
        'public_html/assets/css/5-feed.css',
        'public_html/assets/css/6-pages.css',
        'public_html/assets/css/7-dark-mode.css',
        'public_html/assets/css/8-responsive.css',
    ];

    const adminFiles = [
        'public_html/assets/css/1-variables.css',
        'public_html/assets/css/2-base.css',
        'public_html/assets/css/7-dark-mode.css',
        'public_html/assets/css/admin.css',
    ];

    await ensureDir(OUTDIR);

    await buildCSSBundle('public-bundle', publicFiles);
    await buildCSSBundle('admin-bundle', adminFiles);

    console.log('\n✅ CSS bundling complete!');
    console.log(`   Output directory: ${OUTDIR}`);
    console.log('   Files created: public-bundle.css, admin-bundle.css');
}

buildAll().catch(console.error);
