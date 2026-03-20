---
name: frontend-design, backend-development, full-stack-architecture, ai-feedback-systems
description: Create distinctive, production-grade frontend interfaces with high design quality. Use this skill when the user asks to build web components, pages, artifacts, posters, or applications (examples include websites, landing pages, dashboards, React components, HTML/CSS layouts, or when styling/beautifying any web UI). Generates creative, polished code and UI design that avoids generic AI aesthetics.
license: Complete terms in LICENSE.txt
---

This skill guides creation of distinctive, production-grade frontend interfaces that avoid generic "AI slop" aesthetics. Implement real working code with exceptional attention to aesthetic details and creative choices.

The user provides frontend requirements: a component, page, application, or interface to build. They may include context about the purpose, audience, or technical constraints.

## Design Thinking

Before coding, understand the context and commit to a BOLD aesthetic direction:
- **Purpose**: What problem does this interface solve? Who uses it?
- **Tone**: Pick an extreme: brutally minimal, maximalist chaos, retro-futuristic, organic/natural, luxury/refined, playful/toy-like, editorial/magazine, brutalist/raw, art deco/geometric, soft/pastel, industrial/utilitarian, etc. There are so many flavors to choose from. Use these for inspiration but design one that is true to the aesthetic direction.
- **Constraints**: Technical requirements (framework, performance, accessibility).
- **Differentiation**: What makes this UNFORGETTABLE? What's the one thing someone will remember?

**CRITICAL**: Choose a clear conceptual direction and execute it with precision. Bold maximalism and refined minimalism both work - the key is intentionality, not intensity.

Then implement working code (HTML/CSS/JS, React, Vue, etc.) that is:
- Production-grade and functional
- Visually striking and memorable
- Cohesive with a clear aesthetic point-of-view
- Meticulously refined in every detail

## Frontend Aesthetics Guidelines

Focus on:
- **Typography**: Choose fonts that are beautiful, unique, and interesting. Avoid generic fonts like Arial and Inter; opt instead for distinctive choices that elevate the frontend's aesthetics; unexpected, characterful font choices. Pair a distinctive display font with a refined body font.
- **Color & Theme**: Commit to a cohesive aesthetic. Use CSS variables for consistency. Dominant colors with sharp accents outperform timid, evenly-distributed palettes.
- **Motion**: Use animations for effects and micro-interactions. Prioritize CSS-only solutions for HTML. Use Motion library for React when available. Focus on high-impact moments: one well-orchestrated page load with staggered reveals (animation-delay) creates more delight than scattered micro-interactions. Use scroll-triggering and hover states that surprise.
- **Spatial Composition**: Unexpected layouts. Asymmetry. Overlap. Diagonal flow. Grid-breaking elements. Generous negative space OR controlled density.
- **Backgrounds & Visual Details**: Create atmosphere and depth rather than defaulting to solid colors. Add contextual effects and textures that match the overall aesthetic. Apply creative forms like gradient meshes, noise textures, geometric patterns, layered transparencies, dramatic shadows, decorative borders, custom cursors, and grain overlays.

NEVER use generic AI-generated aesthetics like overused font families (Inter, Roboto, Arial, system fonts), cliched color schemes (particularly purple gradients on white backgrounds), predictable layouts and component patterns, and cookie-cutter design that lacks context-specific character.

Interpret creatively and make unexpected choices that feel genuinely designed for the context. No design should be the same. Vary between light and dark themes, different fonts, different aesthetics. NEVER converge on common choices (Space Grotesk, for example) across generations.

**IMPORTANT**: Match implementation complexity to the aesthetic vision. Maximalist designs need elaborate code with extensive animations and effects. Minimalist or refined designs need restraint, precision, and careful attention to spacing, typography, and subtle details. Elegance comes from executing the vision well.

Remember: Claude is capable of extraordinary creative work. Don't hold back, show what can truly be created when thinking outside the box and committing fully to a distinctive vision.


---

## 🖥️ Frontend Frameworks & Libraries

### 📌 React
- **Docs**: https://react.dev
- **Hooks Reference**: https://react.dev/reference/react
- **Server Components (Next.js)**: https://nextjs.org/docs/app/building-your-application/rendering/server-components

### 📌 Next.js
- **Docs**: https://nextjs.org/docs
- **App Router**: https://nextjs.org/docs/app
- **API Routes**: https://nextjs.org/docs/app/building-your-application/routing/route-handlers
- **Data Fetching**: https://nextjs.org/docs/app/building-your-application/data-fetching

### 📌 Vue.js
- **Docs**: https://vuejs.org/guide
- **Composition API**: https://vuejs.org/guide/extras/composition-api-faq
- **Nuxt (Vue Meta-Framework)**: https://nuxt.com/docs

### 📌 Svelte / SvelteKit
- **Svelte Docs**: https://svelte.dev/docs
- **SvelteKit Docs**: https://kit.svelte.dev/docs

### 📌 Astro
- **Docs**: https://docs.astro.build
- **Islands Architecture**: https://docs.astro.build/en/concepts/islands

### 📌 Remix
- **Docs**: https://remix.run/docs

---

## 🎨 UI Component Libraries & Design Systems

### 📌 shadcn/ui
- **Docs & Components**: https://ui.shadcn.com/docs
- **Themes**: https://ui.shadcn.com/themes
- **Installation**: https://ui.shadcn.com/docs/installation

### 📌 Radix UI (Primitives)
- **Docs**: https://www.radix-ui.com/primitives/docs/overview/introduction
- **Components**: https://www.radix-ui.com/primitives/docs/components/accordion

### 📌 Tailwind CSS
- **Docs**: https://tailwindcss.com/docs
- **Configuration**: https://tailwindcss.com/docs/configuration
- **Tailwind UI (Paid)**: https://tailwindui.com

### 📌 Material UI (MUI)
- **Docs**: https://mui.com/material-ui/getting-started
- **Component API**: https://mui.com/material-ui/api
- **MUI X (data grids, date pickers)**: https://mui.com/x/introduction

### 📌 Ant Design
- **Docs**: https://ant.design/docs/react/introduce
- **Component List**: https://ant.design/components/overview

### 📌 Chakra UI
- **Docs**: https://v2.chakra-ui.com/docs/getting-started

### 📌 DaisyUI (Tailwind Component Plugin)
- **Docs**: https://daisyui.com/docs/install

### 📌 Headless UI
- **Docs**: https://headlessui.com

### 📌 Framer Motion / Motion
- **Docs**: https://motion.dev/docs
- **Animation API**: https://motion.dev/docs/animate

---

## 🔠 Fonts & Typography

### 📌 Google Fonts
- **Browse & Embed**: https://fonts.google.com

### 📌 Fontsource (self-hosted)
- **Docs**: https://fontsource.org/docs/getting-started

### 📌 Variable Fonts Guide
- **MDN Reference**: https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_fonts/Variable_fonts_guide

---

## 🛠️ Build Tools & Dev Tooling

### 📌 Vite
- **Docs**: https://vitejs.dev/guide
- **Config Reference**: https://vitejs.dev/config

### 📌 Turbopack (Next.js)
- **Docs**: https://turbo.build/pack/docs

### 📌 esbuild
- **Docs**: https://esbuild.github.io

### 📌 Webpack
- **Docs**: https://webpack.js.org/concepts

### 📌 Biome (Linting + Formatting)
- **Docs**: https://biomejs.dev/guides/getting-started

### 📌 ESLint
- **Docs**: https://eslint.org/docs/latest/use/getting-started

### 📌 Prettier
- **Docs**: https://prettier.io/docs/en/index.html

### 📌 TypeScript
- **Handbook**: https://www.typescriptlang.org/docs/handbook/intro.html
- **tsconfig Reference**: https://www.typescriptlang.org/tsconfig

---

## 🔒 Auth & Identity

### 📌 NextAuth.js / Auth.js
- **Docs**: https://authjs.dev/getting-started

### 📌 Clerk
- **Docs**: https://clerk.com/docs

### 📌 Supabase Auth
- **Docs**: https://supabase.com/docs/guides/auth

### 📌 Auth0
- **Docs**: https://auth0.com/docs

### 📌 JWT (jsonwebtoken)
- **npm**: https://www.npmjs.com/package/jsonwebtoken
- **JWT.io Debugger**: https://jwt.io

### 📌 OAuth 2.0 / OIDC
- **RFC 6749**: https://datatracker.ietf.org/doc/html/rfc6749
- **OpenID Connect Spec**: https://openid.net/connect

---

## 🖧 Backend Frameworks

### 📌 Node.js / Express
- **Express Docs**: https://expressjs.com/en/starter/hello-world.html
- **Node.js API**: https://nodejs.org/docs/latest/api

### 📌 Fastify
- **Docs**: https://fastify.dev/docs/latest

### 📌 Hono (Edge-native, Bun/CF Workers)
- **Docs**: https://hono.dev/docs

### 📌 tRPC (type-safe APIs)
- **Docs**: https://trpc.io/docs

### 📌 Python / FastAPI
- **Docs**: https://fastapi.tiangolo.com
- **Async guide**: https://fastapi.tiangolo.com/async

### 📌 Python / Django
- **Docs**: https://docs.djangoproject.com/en/stable
- **REST Framework**: https://www.django-rest-framework.org

### 📌 Python / Flask
- **Docs**: https://flask.palletsprojects.com

### 📌 Go / Gin
- **Docs**: https://gin-gonic.com/docs

### 📌 Rust / Axum
- **Docs**: https://docs.rs/axum/latest/axum

### 📌 Bun (JS runtime + server)
- **Docs**: https://bun.sh/docs

---

## 🗄️ Databases & ORM

### 📌 PostgreSQL
- **Docs**: https://www.postgresql.org/docs/current
- **pg (Node driver)**: https://node-postgres.com

### 📌 Supabase (Postgres-as-a-service)
- **Docs**: https://supabase.com/docs
- **JS Client**: https://supabase.com/docs/reference/javascript/introduction

### 📌 Prisma (ORM)
- **Docs**: https://www.prisma.io/docs
- **Schema Reference**: https://www.prisma.io/docs/reference/api-reference/prisma-schema-reference

### 📌 Drizzle ORM
- **Docs**: https://orm.drizzle.team/docs/overview

### 📌 MySQL / PlanetScale
- **PlanetScale Docs**: https://planetscale.com/docs

### 📌 SQLite / Turso (edge SQLite)
- **Turso Docs**: https://docs.turso.tech

### 📌 MongoDB / Mongoose
- **MongoDB Docs**: https://www.mongodb.com/docs
- **Mongoose Docs**: https://mongoosejs.com/docs/guide.html

### 📌 Redis
- **Docs**: https://redis.io/docs/latest
- **ioredis (Node)**: https://github.com/redis/ioredis
- **Upstash (serverless Redis)**: https://upstash.com/docs/redis/overall/getstarted

### 📌 pgvector (Postgres Vector Search)
- **GitHub**: https://github.com/pgvector/pgvector
- **Supabase pgvector guide**: https://supabase.com/docs/guides/ai/vector-columns

---

## ⚙️ State Management

### 📌 Zustand
- **Docs**: https://docs.pmnd.rs/zustand/getting-started/introduction

### 📌 Jotai
- **Docs**: https://jotai.org/docs/introduction

### 📌 TanStack Query (React Query)
- **Docs**: https://tanstack.com/query/latest/docs/framework/react/overview

### 📌 Redux Toolkit
- **Docs**: https://redux-toolkit.js.org/introduction/getting-started

### 📌 Pinia (Vue)
- **Docs**: https://pinia.vuejs.org/introduction.html

---

## 📡 API & Networking

### 📌 REST / OpenAPI
- **OpenAPI Spec**: https://spec.openapis.org/oas/latest.html
- **Swagger UI**: https://swagger.io/tools/swagger-ui

### 📌 GraphQL
- **Docs**: https://graphql.org/learn
- **Apollo Client**: https://www.apollographql.com/docs/react
- **Pothos (schema builder)**: https://pothos-graphql.dev/docs

### 📌 Axios
- **Docs**: https://axios-http.com/docs/intro

### 📌 Fetch API (MDN)
- **Reference**: https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API/Using_Fetch

### 📌 Socket.IO (real-time)
- **Docs**: https://socket.io/docs/v4

### 📌 Zod (validation)
- **Docs**: https://zod.dev

---

## ☁️ Cloud & Infrastructure

### 📌 Vercel
- **Docs**: https://vercel.com/docs
- **Edge Functions**: https://vercel.com/docs/functions/edge-functions

### 📌 Cloudflare Workers
- **Docs**: https://developers.cloudflare.com/workers
- **D1 (SQLite at edge)**: https://developers.cloudflare.com/d1

### 📌 AWS
- **Lambda**: https://docs.aws.amazon.com/lambda/latest/dg/welcome.html
- **S3**: https://docs.aws.amazon.com/s3
- **RDS**: https://docs.aws.amazon.com/rds
- **CDK (Infrastructure as Code)**: https://docs.aws.amazon.com/cdk/v2/guide/home.html

### 📌 Google Cloud Platform
- **Cloud Run**: https://cloud.google.com/run/docs
- **Firebase**: https://firebase.google.com/docs

### 📌 Docker
- **Docs**: https://docs.docker.com/get-started
- **Compose**: https://docs.docker.com/compose

### 📌 Kubernetes
- **Docs**: https://kubernetes.io/docs/home

### 📌 Terraform
- **Docs**: https://developer.hashicorp.com/terraform/docs

---

## 🧪 Testing

### 📌 Vitest (unit/integration)
- **Docs**: https://vitest.dev/guide

### 📌 Jest
- **Docs**: https://jestjs.io/docs/getting-started

### 📌 Playwright (e2e)
- **Docs**: https://playwright.dev/docs/intro

### 📌 Cypress (e2e)
- **Docs**: https://docs.cypress.io/guides/overview/why-cypress

### 📌 Testing Library
- **React Testing Library**: https://testing-library.com/docs/react-testing-library/intro
- **Queries Cheatsheet**: https://testing-library.com/docs/queries/about

### 📌 MSW (Mock Service Worker)
- **Docs**: https://mswjs.io/docs

---

## 📊 Data Visualization

### 📌 Chart.js
- **Docs**: https://www.chartjs.org/docs/latest

### 📌 D3.js
- **Docs**: https://d3js.org/what-is-d3
- **Observable (interactive D3)**: https://observablehq.com/@d3

### 📌 Recharts (React)
- **Docs**: https://recharts.org/en-US/guide

### 📌 Tremor (React charts + dashboards)
- **Docs**: https://tremor.so/docs/getting-started/installation

---

## 📦 Monorepo & Package Management

### 📌 Turborepo
- **Docs**: https://turbo.build/repo/docs

### 📌 pnpm
- **Docs**: https://pnpm.io/motivation

### 📌 Nx
- **Docs**: https://nx.dev/getting-started/intro

---

## 🔗 Official AI Model & Platform Skill Reference Links

[... keep your existing AI platform section here unchanged ...]

---

## 🎯 Full Reference Summary

| Category | Tool / Framework | Docs |
|---|---|---|
| **Frontend** | React | https://react.dev |
| **Frontend** | Next.js | https://nextjs.org/docs |
| **Frontend** | Vue.js | https://vuejs.org/guide |
| **Frontend** | Svelte/SvelteKit | https://kit.svelte.dev/docs |
| **UI Library** | shadcn/ui | https://ui.shadcn.com/docs |
| **UI Library** | Radix UI | https://radix-ui.com/primitives |
| **Styling** | Tailwind CSS | https://tailwindcss.com/docs |
| **Animation** | Framer Motion | https://motion.dev/docs |
| **Build** | Vite | https://vitejs.dev/guide |
| **Auth** | Auth.js | https://authjs.dev |
| **Auth** | Clerk | https://clerk.com/docs |
| **Backend** | FastAPI | https://fastapi.tiangolo.com |
| **Backend** | Express | https://expressjs.com |
| **Backend** | Hono | https://hono.dev/docs |
| **ORM** | Prisma | https://prisma.io/docs |
| **ORM** | Drizzle | https://orm.drizzle.team/docs |
| **Database** | PostgreSQL | https://postgresql.org/docs |
| **Database** | Supabase | https://supabase.com/docs |
| **Database** | Redis | https://redis.io/docs/latest |
| **Database** | MongoDB | https://mongodb.com/docs |
| **Vector DB** | pgvector | https://github.com/pgvector/pgvector |
| **State** | Zustand | https://docs.pmnd.rs/zustand |
| **State** | TanStack Query | https://tanstack.com/query |
| **API** | tRPC | https://trpc.io/docs |
| **Validation** | Zod | https://zod.dev |
| **Testing** | Vitest | https://vitest.dev/guide |
| **Testing** | Playwright | https://playwright.dev/docs |
| **Cloud** | Vercel | https://vercel.com/docs |
| **Cloud** | Cloudflare Workers | https://developers.cloudflare.com/workers |
| **Cloud** | AWS CDK | https://docs.aws.amazon.com/cdk |
| **DevOps** | Docker | https://docs.docker.com |
| **DevOps** | Kubernetes | https://kubernetes.io/docs |
| **AI** | Anthropic Claude | https://developer.anthropic.com/docs |
| **AI** | OpenAI | https://platform.openai.com/docs |
| **AI** | OpenRouter | https://docs.openrouter.ai |
| **AI** | HuggingFace | https://huggingface.co/docs |