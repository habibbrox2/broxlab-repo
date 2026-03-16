# Generated Assets & Build Outputs — BroxBhai

এই রিপোতে কিছু ফাইল **generated** (build output)। এগুলো সরাসরি এডিট না করে সোর্স ফাইল এডিট করে build রান করা উচিত।

## JavaScript
**Source (edit these):**
- `public_html/assets/js/**` (non-`dist/` files)
- `public_html/assets/firebase/v2/**` (non-`dist/` files)

**Generated (avoid editing):**
- `public_html/assets/js/dist/**`
- `public_html/assets/firebase/v2/dist/**`

Build commands:
- Dev watch: `npm run dev`
- Production: `npm run build`

## CSS
**Source (edit these):**
- `public_html/assets/css/*.css` (layered CSS files)
- `public_html/assets/css/tailwind-input.css`

**Generated (avoid editing):**
- `public_html/assets/css/dist/**`
- `public_html/assets/css/tailwind.css` (Tailwind output)

Build command:
- `npm run build:tailwind` (or `npm run build`)

## Rules of thumb
- If a file exists under `dist/`, look for its non-`dist` source first.
- When you change sources, keep dist outputs in sync (this repo tracks dist artifacts).

