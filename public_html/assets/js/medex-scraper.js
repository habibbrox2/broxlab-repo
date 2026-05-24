/**
 * MedexScraper - Client-side non-blocking data collector for /medex
 *
 * Usage (from details page):
 *   const scraper = new MedexScraper({ rate: 400 });
 *   scraper.on('progress', (p) => updateUI(p));
 *   scraper.on('log', (msg) => log(msg));
 *   scraper.on('complete', (result) => { collected = result.data; });
 *   scraper.run();
 *
 * Then scraper.saveToServer() to POST the result via the protected /api/medex/save-data
 *
 * All external fetches go through our CSRF-protected proxy to bypass CORS and keep
 * the main /medex pages completely non-blocking.
 */

(function () {
  'use strict';

  class MedexScraper {
    constructor(opts = {}) {
      this.proxyUrl = opts.proxyUrl || '/api/medex/proxy';
      this.saveUrl = opts.saveUrl || '/api/medex/save-data';
      this.rate = Number(opts.rate) || 350; // ms between requests (polite)
      this.token = opts.token || '';
      this.data = [];
      this.running = false;
      this.paused = false;
      this.abortController = null;
      this.listeners = {};
      this.totalCompanies = 0;
      this.processed = 0;
    }

    on(event, callback) {
      if (!this.listeners[event]) this.listeners[event] = [];
      this.listeners[event].push(callback);
    }

    emit(event, payload) {
      const cbs = this.listeners[event] || [];
      cbs.forEach((cb) => {
        try { cb(payload); } catch (e) { console.warn('MedexScraper listener error', e); }
      });
    }

    log(msg) {
      this.emit('log', msg);
    }

    delay(ms) {
      return new Promise((resolve) => setTimeout(resolve, ms));
    }

    async waitWhilePaused() {
      while (this.paused && this.running) {
        await this.delay(200);
      }
    }

    getCsrfToken() {
      const meta = document.querySelector('meta[name="csrf-token"]');
      return meta ? meta.getAttribute('content') || '' : '';
    }

    getSaveToken() {
      if (this.token) {
        return String(this.token).trim();
      }

      if (typeof window !== 'undefined' && window.MEDEX_REFRESH_TOKEN) {
        return String(window.MEDEX_REFRESH_TOKEN).trim();
      }

      const meta = document.querySelector('meta[name="medex-refresh-token"]');
      if (meta) {
        return meta.getAttribute('content') || '';
      }

      const input = document.querySelector('input[name="medex_refresh_token"], input[name="token"]');
      return input ? String(input.value || '').trim() : '';
    }

    async fetchViaProxy(targetUrl) {
      if (!targetUrl) throw new Error('Empty URL');

      const csrf = this.getCsrfToken();
      const body = new URLSearchParams({
        url: targetUrl,
        csrf_token: csrf,
      });

      const res = await fetch(this.proxyUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-CSRF-Token': csrf,
        },
        body: body.toString(),
      });

      const json = await res.json().catch(() => ({}));

      if (!res.ok || !json.success) {
        const err = json.error || `HTTP ${res.status}`;
        throw new Error(`Proxy fetch failed for ${targetUrl}: ${err}`);
      }
      return json.html;
    }

    // Port of PHP getTotalPages() - looks for nav links with page=
    getTotalPages(html) {
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const links = doc.querySelectorAll('nav a[href*="page="]');
      let max = 1;
      links.forEach((a) => {
        const href = a.getAttribute('href') || '';
        const m = href.match(/page=(\d+)/);
        if (m) {
          const n = parseInt(m[1], 10);
          if (n > max) max = n;
        }
      });
      return max || 1;
    }

    // Exact port of PHP parseMainPage()
    parseMainPage(html) {
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const rows = doc.querySelectorAll('div.data-row');
      const companies = [];

      rows.forEach((row) => {
        const nameDiv = row.querySelector('div.data-row-top');
        if (!nameDiv) return;

        const link = nameDiv.querySelector('a');
        if (!link) return;

        const name = (link.textContent || '').trim();
        const href = link.getAttribute('href') || '';
        const countDiv = row.querySelector('div:not(.data-row-top)');
        const countText = countDiv ? countDiv.textContent.trim() : '';

        let gen = 0;
        let brand = 0;
        const gm = countText.match(/(\d+)\s+generics/i);
        if (gm) gen = parseInt(gm[1], 10);
        const bm = countText.match(/(\d+)\s+brand\s+names?/i);
        if (bm) brand = parseInt(bm[1], 10);

        companies.push({
          name: name,
          url: href,
          generics: gen,
          brands: brand,
        });
      });

      return companies;
    }

    // Port of PHP parseCompanyOverview()
    parseCompanyOverview(html) {
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const details = {};

      // Overview text
      const ov = doc.querySelector('div.ov-data.mb-50');
      if (ov) {
        details.overview = (ov.textContent || '').trim();
      }

      // Table rows (Established, Market Share, etc.)
      const tableRows = doc.querySelectorAll('table.hl-data-table tr');
      tableRows.forEach((tr) => {
        const tds = tr.querySelectorAll('td');
        if (tds.length < 2) return;
        const label = (tds[0].textContent || '').trim();
        const value = (tds[1].textContent || '').trim();

        switch (label) {
          case 'Established':
            details.established = value;
            break;
          case 'Market Share':
            details.market_share = value;
            break;
          case 'Growth':
            details.growth = value;
            break;
          case 'Total generics':
            details.total_generics = value;
            break;
          case 'Total brands':
            details.total_brands = value;
            break;
          case 'Headquarters':
            details.headquarter = value;
            break;
          case 'Contact':
            details.contact = value;
            break;
          case 'Email':
            details.email = value;
            break;
          case 'Website':
            details.website = value;
            break;
          case 'Social':
            details.social = value;
            break;
          default:
            // store any other label as-is (lowercase snake)
            if (label) {
              const key = label.toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '');
              details[key] = value;
            }
        }
      });

      return details;
    }

    async run() {
      if (this.running) return;
      this.running = true;
      this.paused = false;
      this.data = [];
      this.processed = 0;
      this.abortController = new AbortController();

      this.log('=== MedEx JS Collection Started (client-side, non-blocking) ===');
      this.emit('progress', { phase: 'init', current: 0, total: 0 });

      const baseUrl = 'https://medex.com.bd';
      const listUrl = baseUrl + '/companies?herbal=1';

      try {
        // Step 1: first page + total pages
        this.log('Fetching company list page 1...');
        const firstHtml = await this.fetchViaProxy(listUrl);
        const totalPages = this.getTotalPages(firstHtml);
        this.log(`Detected ${totalPages} pages of companies.`);

        let all = this.parseMainPage(firstHtml);
        this.emit('progress', { phase: 'list', current: 1, total: totalPages });

        // Step 2: remaining list pages
        for (let p = 2; p <= totalPages; p++) {
          await this.waitWhilePaused();
          if (!this.running) break;

          const pageUrl = listUrl + '&page=' + p;
          this.log(`Fetching list page ${p}/${totalPages}...`);
          const pageHtml = await this.fetchViaProxy(pageUrl);
          const more = this.parseMainPage(pageHtml);
          all = all.concat(more);
          this.emit('progress', { phase: 'list', current: p, total: totalPages, found: all.length });
          await this.delay(this.rate);
        }

        this.totalCompanies = all.length;
        this.log(`List complete. ${this.totalCompanies} companies found. Now fetching details...`);

        // Step 3: detail pages (overview enrichment)
        for (let i = 0; i < all.length; i++) {
          await this.waitWhilePaused();
          if (!this.running) break;

          const company = all[i];
          const fullUrl = company.url.startsWith('http') ? company.url : baseUrl + company.url;

          try {
            const detailHtml = await this.fetchViaProxy(fullUrl);
            const extra = this.parseCompanyOverview(detailHtml);
            this.data.push(Object.assign({}, company, extra));
          } catch (detailErr) {
            this.log(`Warning: failed to enrich "${company.name}" - ${detailErr.message}`);
            this.data.push(company); // keep what we have
          }

          this.processed = i + 1;
          this.emit('progress', {
            phase: 'details',
            current: this.processed,
            total: this.totalCompanies,
            company: company.name,
          });

          await this.delay(this.rate * 1.2);
        }

        this.log(`Collection finished. ${this.data.length} companies with details.`);
        this.emit('complete', {
          data: this.data,
          count: this.data.length,
          collected_at: new Date().toISOString(),
        });
      } catch (err) {
        this.log('FATAL: ' + err.message);
        this.emit('error', { message: err.message, stack: err.stack });
      } finally {
        this.running = false;
        this.paused = false;
      }
    }

    pause() {
      if (!this.running) return;
      this.paused = true;
      this.log('Paused by user.');
      this.emit('paused', true);
    }

    resume() {
      if (!this.running) return;
      this.paused = false;
      this.log('Resumed.');
      this.emit('resumed', true);
    }

    stop() {
      this.running = false;
      this.paused = false;
      if (this.abortController) this.abortController.abort();
      this.log('Stopped by user.');
      this.emit('stopped', true);
    }

    getData() {
      return this.data;
    }

    async saveToServer(extraMeta = {}, options = {}) {
      // options: { retries: number, backoffBase: ms, silent: bool, token: string }
      if (!this.data.length) throw new Error('No data to save');

      const retries = Number(options.retries || 3);
      const backoffBase = Number(options.backoffBase || 500);
      const silent = !!options.silent;
      const saveToken = String(options.token || extraMeta.token || this.getSaveToken() || '').trim();

      const csrf = this.getCsrfToken();

      const payload = {
        data: this.data,
        meta: Object.assign({
          collected_at: new Date().toISOString(),
          source: 'js-scraper-v1',
          count: this.data.length,
          user_agent: navigator.userAgent,
        }, extraMeta),
        csrf_token: csrf,
      };

      if (saveToken) {
        payload.token = saveToken;
      }

      const body = JSON.stringify(payload);

      let attempt = 0;
      let lastErr = null;
      while (attempt <= retries) {
        attempt += 1;
        try {
          this.log(`Save attempt ${attempt}/${retries}`);
          const res = await fetch(this.saveUrl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': csrf,
            },
            body: body,
          });

          const json = await res.json().catch(() => ({}));

          if (res.ok && json && json.success) {
            this.log('Data successfully saved on server: ' + (json.saved || 'unknown') + ' companies.');
            return { success: true, attempts: attempt, response: json };
          }

          // not OK
          lastErr = json.error || `HTTP ${res.status}`;
          this.log(`Save failed (server): ${lastErr}`);
          if (attempt > retries) break;
        } catch (err) {
          lastErr = err.message || String(err);
          this.log(`Save attempt error: ${lastErr}`);
          if (attempt > retries) break;
        }

        // backoff before next attempt
        const waitMs = backoffBase * Math.pow(2, attempt - 1);
        this.log(`Waiting ${waitMs}ms before retry...`);
        await this.delay(waitMs);
      }

      const errMsg = `Save failed after ${attempt} attempts: ${lastErr || 'unknown'}`;
      this.log(errMsg);
      if (!silent) {
        // Only throw when not silent; caller can handle structured result when silent
        throw new Error(errMsg);
      }
      return { success: false, attempts: attempt, error: lastErr };
    }
  }

  // Expose globally for the Twig page to consume
  window.MedexScraper = MedexScraper;

  // Also support module-style if ever needed
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = MedexScraper;
  }
})();
