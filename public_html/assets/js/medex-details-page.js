/**
 * MedEx Details Page — Server refresh + browser scraper UI + auto-collection
 * Extracted from details.twig inline <script>
 *
 * @package BroxLab MedEx
 */

'use strict';

// ===== Server Refresh Button =====

window.sendMedexRefreshRequest = async function (button) {
  button.disabled = true;
  const originalText = button.innerHTML;
  button.innerHTML = '<i class="lucide lucide-refresh-cw animate-spin h-4 w-4"></i> Refreshing...';

  const feedback = document.getElementById('medex-refresh-feedback');
  if (!feedback) return;

  feedback.textContent = '';

  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const body = new URLSearchParams({ csrf_token: csrfToken, }).toString();

    const response = await fetch('/api/medex/refresh', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': csrfToken,
      },
      body: body,
    });

    const data = await response.json();
    if (!response.ok || !data.success) {
      throw new Error(data.error || 'Refresh failed');
    }

    feedback.textContent = 'Server refresh completed.';
    setTimeout(() => { feedback.textContent = ''; }, 5000);
  } catch (error) {
    feedback.textContent = String(error.message || 'Refresh failed');
    console.error('MedEx server refresh failed:', error);
  } finally {
    button.disabled = false;
    button.innerHTML = originalText;
  }
};

// ===== JS Scraper UI =====

let scraper = null;
let collectedData = null;

function logToUI(msg) {
  const ta = document.getElementById('js-scrape-log');
  if (!ta) return;
  const time = new Date().toLocaleTimeString();
  ta.value = `[${ time }] ${ msg }\n${ ta.value}`;
  ta.scrollTop = 0;
}

function updateProgress(p) {
  const bar = document.getElementById('js-scrape-progress');
  const status = document.getElementById('js-scrape-status');
  const count = document.getElementById('js-scrape-count');

  if (!bar || !status || !count) return;

  if (p.phase === 'list') {
    status.textContent = `Fetching list pages (${ p.current }/${ p.total })`;
    const listPct = Math.round((p.current / p.total) * 100);
    bar.style.width = `${listPct }%`;
    bar.textContent = `${listPct }%`;
    count.textContent = `${p.found || 0 } companies listed`;
  } else if (p.phase === 'details') {
    const detailPct = Math.round((p.current / p.total) * 100);
    bar.style.width = `${detailPct }%`;
    bar.textContent = `${detailPct }%`;
    status.textContent = `Enriching: ${ p.company}`;
    count.textContent = `${p.current } / ${ p.total}`;
  } else if (p.phase === 'init') {
    status.textContent = 'Initializing...';
    bar.style.width = '0%';
  }
}

function setControls(state) {
  const start = document.getElementById('js-scrape-start');
  const pause = document.getElementById('js-scrape-pause');
  const resume = document.getElementById('js-scrape-resume');
  const stop = document.getElementById('js-scrape-stop');
  const upload = document.getElementById('js-scrape-upload');

  if (!start || !pause || !resume || !stop || !upload) return;

  start.disabled = state.running === true;
  pause.disabled = !state.running || state.paused === true;
  resume.disabled = !state.running || state.paused !== true;
  stop.disabled = !state.running;
  upload.disabled = !collectedData || state.running === true;
}

function initScraperUI() {
  const startBtn = document.getElementById('js-scrape-start');
  const pauseBtn = document.getElementById('js-scrape-pause');
  const resumeBtn = document.getElementById('js-scrape-resume');
  const stopBtn = document.getElementById('js-scrape-stop');
  const uploadBtn = document.getElementById('js-scrape-upload');
  const statusEl = document.getElementById('js-scrape-status');

  if (!window.MedexScraper) {
    if (statusEl) statusEl.textContent = 'ERROR: medex-scraper.js failed to load';
    return;
  }

  scraper = new window.MedexScraper({ rate: 380, });

  scraper.on('log', (msg) => { logToUI(msg); });
  scraper.on('progress', (p) => { updateProgress(p); });
  scraper.on('complete', (result) => {
    collectedData = result.data;
    logToUI(`Collection complete! ${ result.count } companies ready for upload.`);
    const bar = document.getElementById('js-scrape-progress');
    if (bar) {
      bar.style.width = '100%';
      bar.classList.remove('animate-pulse');
      bar.classList.add('bg-emerald-500');
    }
    document.getElementById('js-scrape-status').textContent = 'Collection finished — auto-saving to server...';
    setControls({ running: false, });

    // Auto-save to server after collection completes
    scraper.saveToServer(
      { note: 'auto-save after browser collection', },
      { retries: 2, backoffBase: 1500, silent: true, }
    ).then((saveResult) => {
      logToUI(`Auto-save SUCCESS: ${ saveResult.saved || '?' } companies saved. Backup: ${ saveResult.backup || 'none' }.`);
      document.getElementById('js-scrape-status').textContent = `Collection complete — ${ saveResult.saved || 'all' } companies saved to server.`;
    }).catch((saveErr) => {
      logToUI(`Auto-save NOTE: ${ saveErr.message }. Click Upload to retry.`);
      document.getElementById('js-scrape-status').textContent = 'Collection finished — click Upload to persist.';
      const ub = document.getElementById('js-scrape-upload');
      if (ub) ub.disabled = false;
    });
  });
  scraper.on('error', (e) => {
    logToUI(`ERROR: ${ e.message}`);
    setControls({ running: false, });
  });
  scraper.on('paused', () => { setControls({ running: true, paused: true, }); });
  scraper.on('resumed', () => { setControls({ running: true, paused: false, }); });
  scraper.on('stopped', () => { setControls({ running: false, }); });

  startBtn.addEventListener('click', () => {
    collectedData = null;
    document.getElementById('js-scrape-log').value = '';
    document.getElementById('js-scrape-progress').style.width = '0%';
    document.getElementById('js-scrape-progress').classList.add('animate-pulse');
    document.getElementById('js-scrape-progress').classList.remove('bg-emerald-500');
    scraper.run();
    setControls({ running: true, paused: false, });
  });

  pauseBtn.addEventListener('click', () => { scraper.pause(); });
  resumeBtn.addEventListener('click', () => { scraper.resume(); });
  stopBtn.addEventListener('click', () => { scraper.stop(); setControls({ running: false, }); });

  uploadBtn.addEventListener('click', async () => {
    if (!collectedData) return;
    uploadBtn.disabled = true;
    const orig = uploadBtn.innerHTML;
    uploadBtn.innerHTML = '<i class="lucide lucide-hourglass h-4 w-4"></i> Uploading...';

    try {
      const result = await scraper.saveToServer();
      logToUI(`SUCCESS: ${ result.saved || '?' } companies saved. Backup: ${ result.backup || 'none'}`);
      alert('Data uploaded successfully! The /medex pages will now use the fresh dataset.');
    } catch (err) {
      logToUI(`Upload failed: ${ err.message}`);
      alert(`Upload failed: ${ err.message}`);
    } finally {
      uploadBtn.innerHTML = orig;
      uploadBtn.disabled = false;
    }
  });

  setControls({ running: false, });
  logToUI('JS Scraper ready. Click Start Collection to begin (runs entirely in this browser tab).');
}

document.addEventListener('DOMContentLoaded', () => {
  const legacyBtn = document.getElementById('medex-refresh-button');
  if (legacyBtn) {
    legacyBtn.addEventListener('click', function () {
      window.sendMedexRefreshRequest(this);
    });
  }

  initScraperUI();
});

export {};
