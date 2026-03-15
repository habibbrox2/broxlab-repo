import * as esbuild from 'esbuild';
import { fileURLToPath } from 'url';
import { dirname } from 'path';
import { copyFileSync, mkdirSync, readFileSync, writeFileSync } from 'fs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const assistantCssSource = `${__dirname}/../public_html/assets/ai-assistant/styles/assistant-ui.css`;
const assistantCssDest = `${__dirname}/../public_html/assets/ai-assistant/dist/assistant-ui.css`;

function patchBuiltAssistantFile(filePath) {
    const source = readFileSync(filePath, 'utf8');
    const patched = source.replace(
        /initializeSocket\(\)\s*\{[\s\S]*?bindSocketEvents\(\);\s*\}/,
        `initializeSocket() {
    if (this.socket) {
      this.socket.disconnect();
    }
    this.socket = null;
  }`
    );

    if (patched !== source) {
        writeFileSync(filePath, patched, 'utf8');
    }
}

const copyAssistantCssPlugin = {
    name: 'copy-assistant-css',
    setup(build) {
        build.onEnd(() => {
            mkdirSync(`${__dirname}/../public_html/assets/ai-assistant/dist`, { recursive: true });
            copyFileSync(assistantCssSource, assistantCssDest);
        });
    }
};

const patchPuterFsSocketPlugin = {
    name: 'patch-puter-fs-socket',
    setup(build) {
        build.onLoad({ filter: /@heyputer[\\\/]puter\.js[\\\/]src[\\\/]modules[\\\/]FileSystem[\\\/]index\.js$/ }, async (args) => {
            const source = readFileSync(args.path, 'utf8');
            const patched = source.replace(
                /initializeSocket\s*\(\)\s*\{[\s\S]*?this\.bindSocketEvents\(\);\s*\}/,
                `initializeSocket () {\n        if ( this.socket ) {\n            this.socket.disconnect();\n        }\n        this.socket = null;\n    }`
            );

            return {
                contents: patched,
                loader: 'js'
            };
        });
    }
};

const builds = [
    {
        entry: `${__dirname}/../public_html/assets/ai-assistant/bootstrap/admin-assistant.js`,
        outfile: `${__dirname}/../public_html/assets/ai-assistant/dist/admin-assistant.js`
    },
    {
        entry: `${__dirname}/../public_html/assets/ai-assistant/bootstrap/public-assistant.js`,
        outfile: `${__dirname}/../public_html/assets/ai-assistant/dist/public-assistant.js`
    }
];

const args = process.argv.slice(2);
const isWatch = args.includes('--watch');
const hasSourceMap = args.includes('--sourcemap=external');
const hasMinify = args.includes('--minify');

const options = {
    bundle: true,
    format: 'esm',
    target: 'es2020',
    platform: 'browser',
    plugins: [patchPuterFsSocketPlugin, copyAssistantCssPlugin],
    minify: hasMinify,
    sourcemap: hasSourceMap ? 'external' : isWatch ? true : false
};

if (isWatch) {
    const contexts = await Promise.all(
        builds.map(({ entry, outfile }) => esbuild.context({
            ...options,
            entryPoints: [entry],
            outfile
        }))
    );
    await Promise.all(contexts.map((ctx) => ctx.watch()));
    console.log('[assistants] watching for changes...');
} else {
    await Promise.all(
        builds.map(({ entry, outfile }) => esbuild.build({
            ...options,
            entryPoints: [entry],
            outfile
        }))
    ).catch(() => process.exit(1));
    builds.forEach(({ outfile }) => patchBuiltAssistantFile(outfile));
    console.log('[assistants] build complete');
}
