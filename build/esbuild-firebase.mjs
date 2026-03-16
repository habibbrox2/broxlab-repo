import * as esbuild from 'esbuild';
import { fileURLToPath } from 'url';
import { dirname } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));

const firebaseFiles = [
  'firebase-config.js',
  'analytics.js',
  'auth.js',
  'auth-ui-handler.js',
  'account-conflict-handler.js',
  'debug.js',
  'init.js',
  'messaging.js',
  'notification-system.js',
  'offline-handler.js',
  'permission-request.js',
  'scheduled-notifications.js'
].map(f => `${__dirname}/../public_html/assets/firebase/v2/${f}`);

const args = process.argv.slice(2);
const isWatch = args.includes('--watch');
const hasSourceMap = args.includes('--sourcemap=external');
const hasMinify = args.includes('--minify');
const isClassic = args.includes('--classic');

const options = {
  entryPoints: firebaseFiles,
  outdir: `${__dirname}/../public_html/assets/firebase/v2/dist`,
  bundle: true,
  format: isClassic ? 'iife' : 'esm',
  splitting: isClassic ? false : true,
  chunkNames: 'chunks/[name]-[hash]',
  target: 'es2020',
  platform: 'browser',
  minify: hasMinify,
  sourcemap: hasSourceMap ? 'external' : isWatch ? true : false,
  globalName: isClassic ? 'BroxFirebase' : undefined
};

if (isWatch) {
  esbuild.context(options).then(ctx => ctx.watch()).catch(() => process.exit(1));
} else {
  if (isClassic) {
    // For classic build, produce a single bundle from init.js to firebase.classic.js
    const classicOpts = {
      entryPoints: [`${__dirname}/../public_html/assets/firebase/v2/init.js`],
      outfile: `${__dirname}/../public_html/assets/firebase/v2/dist/firebase.classic.js`,
      bundle: true,
      format: 'iife',
      globalName: 'BroxFirebase',
      target: 'es2020',
      platform: 'browser',
      minify: hasMinify,
      sourcemap: hasSourceMap ? 'external' : false
    };
    esbuild.build(classicOpts).catch(() => process.exit(1));
  } else {
    esbuild.build(options).catch(() => process.exit(1));
  }
}
