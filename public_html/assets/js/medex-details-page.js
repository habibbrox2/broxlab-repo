/**
 * MedEx Details Page — Server refresh + browser scraper UI + auto-collection
 * Extracted from details.twig inline <script>
 *
 * @package BroxLab MedEx
 */

(function () {
    'use strict';

    // ===== Server Refresh Button =====

    window.sendMedexRefreshRequest = async function (button) {
        button.disabled = true;
        var originalText = button.innerHTML;
        button.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Refreshing...';

        var feedback = document.getElementById('medex-refresh-feedback');
        if (!feedback) return;

        feedback.textContent = '';

        try {
            var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            var body = new URLSearchParams({ csrf_token: csrfToken }).toString();

            var response = await fetch('/api/medex/refresh', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken,
                },
                body: body,
            });

            var data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Refresh failed');
            }

            feedback.textContent = 'Server refresh completed.';
            setTimeout(function () { feedback.textContent = ''; }, 5000);
        } catch (error) {
            feedback.textContent = String(error.message || 'Refresh failed');
            console.error('MedEx server refresh failed:', error);
        } finally {
            button.disabled = false;
            button.innerHTML = originalText;
        }
    };

    // ===== JS Scraper UI =====

    var scraper = null;
    var collectedData = null;

    function logToUI(msg) {
        var ta = document.getElementById('js-scrape-log');
        if (!ta) return;
        var time = new Date().toLocaleTimeString();
        ta.value = '[' + time + '] ' + msg + '\n' + ta.value;
        ta.scrollTop = 0;
    }

    function updateProgress(p) {
        var bar = document.getElementById('js-scrape-progress');
        var status = document.getElementById('js-scrape-status');
        var count = document.getElementById('js-scrape-count');

        if (!bar || !status || !count) return;

        if (p.phase === 'list') {
            status.textContent = 'Fetching list pages (' + p.current + '/' + p.total + ')';
            var listPct = Math.round((p.current / p.total) * 100);
            bar.style.width = listPct + '%';
            bar.textContent = listPct + '%';
            count.textContent = (p.found || 0) + ' companies listed';
        } else if (p.phase === 'details') {
            var detailPct = Math.round((p.current / p.total) * 100);
            bar.style.width = detailPct + '%';
            bar.textContent = detailPct + '%';
            status.textContent = 'Enriching: ' + p.company;
            count.textContent = p.current + ' / ' + p.total;
        } else if (p.phase === 'init') {
            status.textContent = 'Initializing...';
            bar.style.width = '0%';
        }
    }

    function setControls(state) {
        var start = document.getElementById('js-scrape-start');
        var pause = document.getElementById('js-scrape-pause');
        var resume = document.getElementById('js-scrape-resume');
        var stop = document.getElementById('js-scrape-stop');
        var upload = document.getElementById('js-scrape-upload');

        if (!start || !pause || !resume || !stop || !upload) return;

        start.disabled = state.running === true;
        pause.disabled = !state.running || state.paused === true;
        resume.disabled = !state.running || state.paused !== true;
        stop.disabled = !state.running;
        upload.disabled = !collectedData || state.running === true;
    }

    function initScraperUI() {
        var startBtn = document.getElementById('js-scrape-start');
        var pauseBtn = document.getElementById('js-scrape-pause');
        var resumeBtn = document.getElementById('js-scrape-resume');
        var stopBtn = document.getElementById('js-scrape-stop');
        var uploadBtn = document.getElementById('js-scrape-upload');
        var statusEl = document.getElementById('js-scrape-status');

        if (!window.MedexScraper) {
            if (statusEl) statusEl.textContent = 'ERROR: medex-scraper.js failed to load';
            return;
        }

        scraper = new window.MedexScraper({ rate: 380 });

        scraper.on('log', function (msg) { logToUI(msg); });
        scraper.on('progress', function (p) { updateProgress(p); });
        scraper.on('complete', function (result) {
            collectedData = result.data;
            logToUI('Collection complete! ' + result.count + ' companies ready for upload.');
            var bar = document.getElementById('js-scrape-progress');
            if (bar) {
                bar.style.width = '100%';
                bar.classList.remove('progress-bar-striped');
                bar.classList.add('bg-success');
            }
            document.getElementById('js-scrape-status').textContent = 'Collection finished — auto-saving to server...';
            setControls({ running: false });

            // Auto-save to server after collection completes
            scraper.saveToServer(
                { note: 'auto-save after browser collection' },
                { retries: 2, backoffBase: 1500, silent: true }
            ).then(function (saveResult) {
                logToUI('Auto-save SUCCESS: ' + (saveResult.saved || '?') + ' companies saved. Backup: ' + (saveResult.backup || 'none') + '.');
                document.getElementById('js-scrape-status').textContent = 'Collection complete — ' + (saveResult.saved || 'all') + ' companies saved to server.';
            }).catch(function (saveErr) {
                logToUI('Auto-save NOTE: ' + saveErr.message + '. Click Upload to retry.');
                document.getElementById('js-scrape-status').textContent = 'Collection finished — click Upload to persist.';
                var ub = document.getElementById('js-scrape-upload');
                if (ub) ub.disabled = false;
            });
        });
        scraper.on('error', function (e) {
            logToUI('ERROR: ' + e.message);
            setControls({ running: false });
        });
        scraper.on('paused', function () { setControls({ running: true, paused: true }); });
        scraper.on('resumed', function () { setControls({ running: true, paused: false }); });
        scraper.on('stopped', function () { setControls({ running: false }); });

        startBtn.addEventListener('click', function () {
            collectedData = null;
            document.getElementById('js-scrape-log').value = '';
            document.getElementById('js-scrape-progress').style.width = '0%';
            document.getElementById('js-scrape-progress').classList.add('progress-bar-striped');
            document.getElementById('js-scrape-progress').classList.remove('bg-success');
            scraper.run();
            setControls({ running: true, paused: false });
        });

        pauseBtn.addEventListener('click', function () { scraper.pause(); });
        resumeBtn.addEventListener('click', function () { scraper.resume(); });
        stopBtn.addEventListener('click', function () { scraper.stop(); setControls({ running: false }); });

        uploadBtn.addEventListener('click', async function () {
            if (!collectedData) return;
            uploadBtn.disabled = true;
            var orig = uploadBtn.innerHTML;
            uploadBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Uploading...';

            try {
                var result = await scraper.saveToServer();
                logToUI('SUCCESS: ' + (result.saved || '?') + ' companies saved. Backup: ' + (result.backup || 'none'));
                alert('Data uploaded successfully! The /medex pages will now use the fresh dataset.');
            } catch (err) {
                logToUI('Upload failed: ' + err.message);
                alert('Upload failed: ' + err.message);
            } finally {
                uploadBtn.innerHTML = orig;
                uploadBtn.disabled = false;
            }
        });

        setControls({ running: false });
        logToUI('JS Scraper ready. Click Start Collection to begin (runs entirely in this browser tab).');
    }

    async function initDetailsRouteCollection() {
        var feedback = document.getElementById('medex-refresh-feedback');
        if (!feedback || window.location.pathname !== '/medex/details') {
            return;
        }

        var stateKey = 'medex-details-collection-last-run';
        var cooldownMs = 12 * 60 * 60 * 1000; // 12 hours
        var lastRun = parseInt(localStorage.getItem(stateKey) || '0', 10);
        if (Number.isFinite(lastRun) && Date.now() - lastRun < cooldownMs) {
            return;
        }
        localStorage.setItem(stateKey, Date.now().toString());

        try {
            var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            var body = new URLSearchParams({ step: 'drug-details' });
            if (csrfToken) {
                body.append('csrf_token', csrfToken);
            }

            var response = await fetch('/api/medex/refresh-route', {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken,
                },
                body: body.toString(),
            });

            var json = await response.json();
            if (!response.ok || !json.success) {
                throw new Error(json.error || 'Unable to start drug details collection');
            }
            feedback.textContent = 'Drug details collection started in background.';
        } catch (err) {
            feedback.textContent = 'Drug details collection failed: ' + (err.message || err);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var legacyBtn = document.getElementById('medex-refresh-button');
        if (legacyBtn) {
            legacyBtn.addEventListener('click', function () {
                window.sendMedexRefreshRequest(this);
            });
        }

        initScraperUI();
        initDetailsRouteCollection();
    });
})();
