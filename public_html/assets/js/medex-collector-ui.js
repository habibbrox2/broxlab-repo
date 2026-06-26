/* medex-collector-ui.js
   Simple UI glue for MedexScraper (uses window.MedexScraper)
*/
(function () {
    'use strict';

    function id(s) { return document.getElementById(s); }

    const startBtn = id('startBtn');
    const pauseBtn = id('pauseBtn');
    const resumeBtn = id('resumeBtn');
    const stopBtn = id('stopBtn');
    const saveBtn = id('saveBtn');
    const downloadBtn = id('downloadBtn');
    const status = id('status');
    const progressBar = id('progressBar');

    // options
    const autoSaveCheckbox = id('autoSave');
    const silentSaveCheckbox = id('silentSave');
    const saveRetriesInput = id('saveRetries');
    const requestRateInput = id('requestRate');

    let scraper = null;
    let lastResult = null;

    function log(msg) {
        const t = new Date().toISOString();
        status.textContent = `[${t}] ${msg}\n` + status.textContent;
    }

    function setButtons(running) {
        startBtn.disabled = running;
        pauseBtn.disabled = !running;
        resumeBtn.disabled = !running;
        stopBtn.disabled = !running;
    }

    startBtn.addEventListener('click', async () => {
        if (typeof window.MedexScraper !== 'function') {
            alert('MedexScraper not loaded');
            return;
        }
        const rateVal = Number(requestRateInput ? requestRateInput.value : 300) || 300;
        scraper = new window.MedexScraper({ rate: rateVal });
        setButtons(true);
        saveBtn.disabled = true;
        downloadBtn.disabled = true;
        progressBar.value = 0;

        scraper.on('log', (m) => log(m));
        scraper.on('progress', (p) => {
            if (p.phase === 'list') {
                const pct = Math.min(99, Math.round((p.current / (p.total || 1)) * 100));
                progressBar.value = pct;
            } else if (p.phase === 'details') {
                const pct = Math.round((p.current / (p.total || 1)) * 100);
                progressBar.value = pct;
            }
        });
        scraper.on('complete', (res) => {
            log('Collection complete: ' + (res.count || 0) + ' items');
            lastResult = res;
            saveBtn.disabled = false;
            downloadBtn.disabled = false;
            setButtons(false);
            progressBar.value = 100;

            // Auto-save if enabled
            try {
                if (autoSaveCheckbox && autoSaveCheckbox.checked) {
                    const retries = Number(saveRetriesInput ? saveRetriesInput.value : 3) || 3;
                    const silent = !!(silentSaveCheckbox && silentSaveCheckbox.checked);
                    log('Auto-saving to server (silent=' + silent + ', retries=' + retries + ')...');
                    // call saveToServer but don't block UI
                    scraper.saveToServer({ note: 'auto-save via UI' }, { retries: retries, backoffBase: 1000, silent: silent })
                        .then((r) => {
                            log('Auto-save result: ' + JSON.stringify(r));
                            if (!silent) alert('Auto-save result: ' + (r.success ? 'OK' : 'FAILED'));
                        })
                        .catch((e) => {
                            log('Auto-save failed: ' + e.message);
                            if (!silent) alert('Auto-save failed: ' + e.message);
                        });
                }
            } catch (e) {
                log('Auto-save exception: ' + e.message);
            }
        });
        scraper.on('error', (e) => {
            log('ERROR: ' + (e.message || JSON.stringify(e)));
            setButtons(false);
        });

        try {
            await scraper.run();
        } catch (e) {
            log('Run failed: ' + e.message);
            setButtons(false);
        }
    });

    pauseBtn.addEventListener('click', () => {
        if (scraper) { scraper.pause(); log('Paused'); }
    });
    resumeBtn.addEventListener('click', () => {
        if (scraper) { scraper.resume(); log('Resumed'); }
    });
    stopBtn.addEventListener('click', () => {
        if (scraper) { scraper.stop(); log('Stopped'); setButtons(false); }
    });

    saveBtn.addEventListener('click', async () => {
        if (!scraper) { alert('No scraper instance'); return; }
        try {
            log('Saving to server...');
            const retries = Number(saveRetriesInput ? saveRetriesInput.value : 3) || 3;
            const silent = !!(silentSaveCheckbox && silentSaveCheckbox.checked);
            const res = await scraper.saveToServer({ note: 'collected via UI' }, { retries: retries, backoffBase: 1000, silent: silent });
            log('Save response: ' + JSON.stringify(res));
            if (!silent) alert('Saved: ' + (res.response && res.response.saved ? res.response.saved : (res.saved || 'unknown')));
        } catch (e) {
            log('Save failed: ' + e.message);
            alert('Save failed: ' + e.message);
        }
    });

    downloadBtn.addEventListener('click', () => {
        const data = scraper ? scraper.getData() : (lastResult && lastResult.data) || [];
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = 'medex_companies_collected.json';
        document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
    });

    // Initial UI state
    setButtons(false);
    saveBtn.disabled = true;
    downloadBtn.disabled = true;
    log('UI ready. Click Start to begin collection.');

})();
