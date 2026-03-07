import * as esbuild from 'esbuild';
import { fileURLToPath } from 'url';
import { dirname } from 'path';
import { copyFileSync, mkdirSync } from 'fs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const assistantCssSource = `${__dirname}/../public_html/assets/ai-assistant/styles/assistant-ui.css`;
const assistantCssDest = `${__dirname}/../public_html/assets/ai-assistant/dist/assistant-ui.css`;

const copyAssistantCssPlugin = {
    name: 'copy-assistant-css',
    setup(build) {
        build.onEnd(() => {
            mkdirSync(`${__dirname}/../public_html/assets/ai-assistant/dist`, { recursive: true });
            copyFileSync(assistantCssSource, assistantCssDest);
        });
    }
};

const entryPoints = [
    'bootstrap/admin-assistant.js',
    'bootstrap/public-assistant.js'
].map(f => `${__dirname}/../public_html/assets/ai-assistant/${f}`);

const args = process.argv.slice(2);
const isWatch = args.includes('--watch');
const hasSourceMap = args.includes('--sourcemap=external');
const hasMinify = args.includes('--minify');

const options = {
    entryPoints,
    outdir: `${__dirname}/../public_html/assets/ai-assistant/dist`,
    bundle: true,
    format: 'esm',
    splitting: true,
    chunkNames: 'chunks/[name]-[hash]',
    target: 'es2020',
    platform: 'browser',
    plugins: [copyAssistantCssPlugin],
    minify: hasMinify,
    sourcemap: hasSourceMap ? 'external' : isWatch ? true : false
};

if (isWatch) {
    const ctx = await esbuild.context(options);
    await ctx.watch();
    console.log('[assistants] watching for changes...');
} else {
    await esbuild.build(options).catch(() => process.exit(1));
    console.log('[assistants] build complete');
}
