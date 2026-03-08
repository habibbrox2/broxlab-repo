
# Firebase v2 Source Of Truth

This folder is the **single source of truth** for Firebase v2 modular scripts.

## Source roots

- Firebase source: `public/assets/firebase/v2`
- App source: `public/assets/js`

## Build Output (Runtime)

- Firebase runtime (bundled): `public/assets/firebase/v2/dist/*`
- App runtime (dist mirror): `public/assets/js/dist/*`

Notes:
- `dist` files are generated artifacts. Source/dist coexistence is expected during development — the duplicate-asset check ignores `dist/` to avoid false positives.
- Firebase build uses bundling + splitting (chunks). App build preserves per-file output (no full-graph bundling) to maintain classic script compatibility.

## Build Commands

- Clean old build output: `npm run clean:build`
- Firebase dev watch: `npm run dev:firebase:v2`
- Firebase production build: `npm run build:firebase:v2`
- App dist build: `npm run build:app:dist`
- App dev watch (dist): `npm run dev:app:dist`
- Asset checks: `npm run check:assets`

## Canonical Local Flow

1. `npm run clean:build`
2. `npm run build:firebase:v2`
3. `npm run build:app:dist`
4. `npm run check:assets`

Developer guidance:
- Edit source files under `public/assets/firebase/v2` and `public/assets/js` only.
- App source imports referencing other app files must use relative imports (e.g. `import './shared/util.js'`).
- Firebase runtime imports remain absolute and served from `/assets/firebase/v2/dist/...`.
- This project intentionally hard-switches runtime app script URLs to `/assets/js/dist/` — there is no fallback to legacy `/assets/js/*.js` paths.
