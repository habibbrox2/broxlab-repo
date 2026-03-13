**Title: Full-Repository Audit + AI/Scraper Architecture Blueprint**

**Summary**
- Produce the requested 10‑phase analysis and architecture upgrade plan, grounded in current codebase structure.
- Deliver a production‑grade design for scraping + knowledge ingestion + RAG integration, tailored to shared hosting and low scale.
- Return the exact final report format you specified.

**Key Changes (Analysis & Design Work)**
- **Phase 1 — Project Discovery**
  - Map backend layers (custom PHP router/controllers/models/helpers), frontend (Twig + compiled JS/CSS), APIs, AI integrations, scraper modules, deployment scripts, DB schema (`db.sql`).
- **Phase 2 — Self‑Improving System**
  - Propose automated checks: static analysis, linting, security audit, duplication detection, perf profiling.
  - Define triggers, cadence, and reporting outputs.
- **Phase 3 — Scraping System Design**
  - Scheduler + worker model using cron and PHP CLI, low-scale queue, retry policy, UA rotation, throttling.
  - Optional headless path via external service if needed (due to shared hosting constraints).
- **Phase 4 — Content Processing Pipeline**
  - Raw HTML store → extraction → cleaning → dedupe → metadata → language → storage.
  - Define lightweight pipeline stages and storage schema.
- **Phase 5 — Knowledge Base**
  - Chunking strategy, embeddings generation, vector store options feasible on shared host.
- **Phase 6 — AI Integration**
  - RAG flow, provider routing, cost‑aware fallback, token budgeting.
- **Phase 7 — Performance**
  - Identify bottlenecks in scraping, API latency, DB, queues; suggest cache/batch/async improvements.
- **Phase 8 — Security**
  - Enumerate risks: input validation, upload handling, API key exposure, CSRF/XSS, SSRF from scraping.
- **Phase 9 — DevOps**
  - CI/CD recommendations compatible with shared hosting; deployment scripts review; monitoring/logging/rollback.
- **Phase 10 — Roadmap**
  - Prioritized issues and upgrades across architecture, scraping, AI, perf, and long‑term scaling.

**Public Interfaces / Schemas**
- Document any new internal APIs or data schemas required for scraping/knowledge ingestion.

**Test Plan (for future implementation)**
- Scraper throughput & retry tests
- Pipeline correctness & dedupe tests
- RAG latency and cost checks
- Security regression checks

**Assumptions**
- Shared hosting constraints (limited background workers, no Docker).
- Low scraping scale (<100 sources).
- Compliance‑first crawling (robots.txt respected).

