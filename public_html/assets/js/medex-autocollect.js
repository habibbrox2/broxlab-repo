'use strict';

function getStatusElement() {
  return document.getElementById('medexFetchStatus');
}

function createStatusElement() {
  const existing = getStatusElement();
  if (existing) return existing;

  const container = document.querySelector('.container') || document.body;
  const statusEl = document.createElement('div');
  statusEl.id = 'medexFetchStatus';
  statusEl.className = 'text-muted mb-3';
  statusEl.textContent = 'Preparing MedEx browser collection...';

  if (container.firstChild) {
    container.insertBefore(statusEl, container.firstChild);
  } else {
    container.appendChild(statusEl);
  }
  return statusEl;
}

function updateStatus(message) {
  const el = getStatusElement() || createStatusElement();
  if (!el) return;
  el.textContent = message;
}

function formatProgress(progress) {
  if (!progress || typeof progress !== 'object') {
    return '';
  }
  if (progress.phase === 'list') {
    return `Collecting list pages ${ progress.current } / ${ progress.total } (${ progress.found || 0 } companies)`;
  }
  if (progress.phase === 'details') {
    return `Collecting details ${ progress.current } / ${ progress.total } for ${ progress.company || 'company'}`;
  }
  return 'Working...';
}

async function autoCollectMedex() {
  if (!window.MedexScraper) {
    updateStatus('MedexScraper not loaded. Auto-collection cannot start.');
    return;
  }

  updateStatus('Starting MedEx browser collection...');
  const scraper = new window.MedexScraper({ rate: 380, });

  scraper.on('log', (msg) => {
    updateStatus(msg);
  });

  scraper.on('progress', (progress) => {
    updateStatus(formatProgress(progress));
  });

  scraper.on('complete', async (result) => {
    const count = result && result.count ? result.count : (result.data ? result.data.length : 0);
    updateStatus(`Collection complete: ${ count } companies. Saving to server...`);
    try {
      const saveResult = await scraper.saveToServer({ note: 'Auto-save from /medex page', }, { retries: 2, backoffBase: 1000, silent: false, });
      if (saveResult && saveResult.success) {
        updateStatus(`Data saved successfully: ${ saveResult.response && saveResult.response.saved ? saveResult.response.saved : count } companies.`);
      } else {
        updateStatus('Save completed with warnings. Check server logs or retry.');
      }
    } catch (saveError) {
      updateStatus(`Save failed: ${ saveError.message || 'unknown error' }.`);
      console.warn('MedEx auto-save failed:', saveError);
    }
  });

  scraper.on('error', (error) => {
    updateStatus(`Collection error: ${ error.message || error}`);
  });

  try {
    await scraper.run();
  } catch (runError) {
    updateStatus(`Auto-collection failed: ${ runError.message || 'unknown error'}`);
    console.error('MedEx auto-collection error:', runError);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const path = window.location.pathname.replace(/\/+$/, '');
  if (path !== '/medex' && !path.startsWith('/medex/')) {
    return;
  }

  setTimeout(() => {
    autoCollectMedex();
  }, 500);
});

export { autoCollectMedex };

