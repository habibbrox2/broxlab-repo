/* eslint-disable */

var d = document;
  function g(id) { return d.getElementById(id); }
  function csrf() {
    var tok = d.querySelector('meta[name="csrf-token"]');
    return tok ? tok.content : '';
  }
  function esc(s) {
    var e = d.createElement('div');
    e.textContent = s == null ? '' : String(s);
    return e.innerHTML;
  }
  function fmt(n) {
    return String(n || 0);
  }

  var s = g('step1');
  var sc = g('step3Card');
  var s4 = g('step4Card');
  var dz = g('csvDropzone');
  var fi = g('csvFileInput');
  var ps = g('csvPreviewSection');
  var pb = g('csvPreviewBody');
  var tc = g('csvTopicCount');
  var es = g('csvEmptyState');
  var cb = g('clearCsvBtn');
  var gs = g('generateAllBtn');
  var pl = g('progressLabel');
  var pc = g('progressCount');
  var pr = g('progressBar');
  var pcr = g('progressCurrentArticle');
  var pct = g('progressCurrentText');
  var al = g('articlesList');
  var rs = g('resultsSummary');
  var rst = g('resultsStats');
  var ra = g('resultsActions');
  var pb2 = g('publishSelectedBtn');
  var sa = g('selectAllResultsBtn');
  var da = g('deselectAllResultsBtn');
  var bu = g('backToUploadBtn');
  var pp = g('publishProgress');
  var ppb = g('publishProgressBar');
  var pst = g('publishStatus');
  var st = g('bulkWriterStatus');
  var dsl = g('downloadSampleCsv');

  var topics = [];
  var articles = [];

  function up(n) {
    for (var i = 1; i <= 4; i++) {
      var b = d.querySelector('.step-badge[data-step="' + i + '"]');
      if (!b) continue;
      b.classList.remove('active', 'done');
      if (i < n) b.classList.add('done');
      else if (i === n) b.classList.add('active');
    }
  }

  function flash(msg, type) {
    if (!st) return;
    st.classList.remove('hidden', 'bg-emerald-50', 'bg-red-50', 'bg-sky-50', 'bg-amber-50');
    st.className = 'alert p-4 rounded-lg border ' + (type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : type === 'danger' ? 'bg-red-50 border-red-200 text-red-700' : type === 'warning' ? 'bg-amber-50 border-amber-200 text-amber-700' : 'bg-sky-50 border-sky-200 text-sky-700');
    st.textContent = msg;
    st.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(function () {
      st.classList.add('hidden');
    }, 8000);
  }

  function status(msg, type) { flash(msg, type || 'info'); }
  function err(msg) { flash(msg, 'danger'); }

  function showLoading(isLoading) {
    if (!gs) return;
    gs.disabled = isLoading;
    gs.innerHTML = isLoading
      ? '<span class="inline-spinner inline-spinner-sm mr-1"></span> Generating...'
      : '<i class="lucide lucide-wand-sparkles mr-1"></i> Generate All Articles';
  }

  function renderPreview(rows) {
    if (!pb) return;
    pb.innerHTML = '';
    for (var i = 0; i < rows.length; i++) {
      var r = rows[i] || {};
      var tr = d.createElement('tr');
      tr.innerHTML =
        '<td>' + (i + 1) + '</td>' +
        '<td>' + esc(r.topic) + '</td>' +
        '<td>' + esc(r.tone || '-') + '</td>' +
        '<td>' + esc(r.length || '-') + '</td>' +
        '<td>' + esc(r.language || '-') + '</td>' +
        '<td>' + esc(r.keywords || '-') + '</td>' +
        '<td>' + esc(r.style || '-') + '</td>';
      pb.appendChild(tr);
    }

    if (tc) tc.textContent = fmt(rows.length);
    if (ps) ps.classList.remove('hidden');
    if (es) es.classList.add('hidden');
    if (gs) gs.classList.remove('hidden');
  }

  function renderResults(results, summary) {
    if (!al) return;
    al.innerHTML = '';

    var successCount = 0;
    var failCount = 0;

    for (var i = 0; i < results.length; i++) {
      var a = results[i] || {};
      if (a.success) successCount++;
      else failCount++;

      var div = d.createElement('div');
      div.className = 'article-result-card ' + (a.success ? 'success' : 'failed');

      var header = d.createElement('div');
      header.className = 'article-result-header';
      header.innerHTML =
        '<label class="flex items-center gap-2 mb-0" style="cursor:pointer;flex:1">' +
        '<input type="checkbox" class="publish-article-checkbox" data-index="' + esc(a.index) + '"' +
        (a.success ? ' checked' : ' disabled') + '>' +
        '<span class="article-result-title">' + esc(a.title || a.topic || 'Untitled') + '</span>' +
        '</label>' +
        '<span class="article-result-meta">' +
        (a.success
          ? '<i class="lucide lucide-check-circle text-emerald-600 h-4 w-4"></i> Success'
          : '<i class="lucide lucide-x-circle text-red-600 h-4 w-4"></i> ' + esc(a.error || 'Failed')) +
        '</span>';

      var body = d.createElement('div');
      body.className = 'article-result-body';
      if (a.success && a.article) {
        body.innerHTML = '<div class="article-preview-content">' + (a.article.content || '') + '</div>';
      }

      header.addEventListener('click', function (e) {
        if (e.target && e.target.type === 'checkbox') return;
        var next = this.nextElementSibling;
        if (next) next.classList.toggle('open');
      });

      div.appendChild(header);
      div.appendChild(body);
      al.appendChild(div);
    }

    if (rst) {
      rst.innerHTML =
        '<div class="stat-card success"><div class="stat-value">' + successCount + '</div><div class="stat-label">Succeeded</div></div>' +
        '<div class="stat-card failed"><div class="stat-value">' + failCount + '</div><div class="stat-label">Failed</div></div>' +
        '<div class="stat-card total"><div class="stat-value">' + results.length + '</div><div class="stat-label">Total</div></div>' +
        (summary && summary.elapsed_formatted
          ? '<div class="stat-card time"><div class="stat-value">' + esc(summary.elapsed_formatted) + '</div><div class="stat-label">Time</div></div>'
          : '');
    }

    if (rs) rs.classList.remove('hidden');
    if (ra) ra.classList.remove('hidden');
    if (sc) sc.classList.add('hidden');
  }

  function setPublishControlsDisabled(disabled) {
    if (pb2) pb2.disabled = disabled;
  }

  function handleFile(file) {
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function (e) {
      parseCSV((e && e.target && e.target.result) || '');
    };
    reader.readAsText(file);
  }

  function parseCSV(txt) {
    fetch('/api/admin/ai/bulk-article-writer/parse-csv', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf()
      },
      body: JSON.stringify({ csv: txt })
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.success && d.rows) {
          topics = d.rows;
          renderPreview(d.rows);
          up(2);
          status('CSV parsed successfully', 'success');
        } else {
          err(d.error || 'Failed to parse CSV');
        }
      })
      .catch(function () {
        err('Network error while parsing CSV');
      });
  }

  function generateAll() {
    if (!topics.length) {
      err('No topics to generate. Upload a CSV first.');
      return;
    }

    articles = [];
    up(3);
    if (sc) sc.classList.remove('hidden');
    if (s4) s4.classList.add('hidden');
    if (ra) ra.classList.add('hidden');
    if (rs) rs.classList.add('hidden');
    if (al) al.innerHTML = '';
    if (s) s.classList.remove('hidden');
    if (gs) gs.classList.add('hidden');
    if (pl) pl.textContent = 'Generating articles...';
    if (pct) pct.textContent = 'Preparing...';
    if (pc) pc.textContent = '0 / ' + topics.length;
    if (pr) pr.style.width = '0%';
    if (pcr) pcr.classList.remove('hidden');
    showLoading(true);

    fetch('/api/admin/ai/bulk-article-writer/generate', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf()
      },
      body: JSON.stringify({ topics: topics })
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (pcr) pcr.classList.add('hidden');
        showLoading(false);

        if (d.success && d.articles) {
          articles = d.articles;
          renderResults(d.articles, d.summary);
          up(4);
          status('Articles generated successfully', 'success');
        } else {
          err(d.error || 'Generation failed');
        }
      })
      .catch(function () {
        if (pcr) pcr.classList.add('hidden');
        showLoading(false);
        err('Network error while generating articles');
      });
  }

  function selectAll(checked) {
    if (!al) return;
    al.querySelectorAll('.publish-article-checkbox').forEach(function (c) {
      if (!c.disabled) c.checked = checked;
    });
  }

  function publishSelected() {
    var selected = [];
    if (!al) return;

    al.querySelectorAll('.publish-article-checkbox:checked').forEach(function (c) {
      var idx = parseInt(c.getAttribute('data-index') || '0', 10);
      for (var i = 0; i < articles.length; i++) {
        if (articles[i] && articles[i].index === idx) {
          selected.push(articles[i]);
          break;
        }
      }
    });

    if (!selected.length) {
      err('Select at least one article to publish');
      return;
    }

    var totalSel = selected.length;
    if (pp) pp.classList.remove('hidden');
    if (ppb) ppb.style.width = '0%';
    if (pst) pst.classList.add('hidden');
    setPublishControlsDisabled(true);

    fetch('/api/admin/ai/bulk-article-writer/publish', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf()
      },
      body: JSON.stringify({
        articles: selected,
        publish: true
      })
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (pp) pp.classList.add('hidden');
        setPublishControlsDisabled(false);

        if (d.success && d.results) {
          var pub = 0;
          var fail = 0;
          d.results.forEach(function (r) {
            if (r.success) {
              pub++;
              var cbx = al.querySelector('.publish-article-checkbox[data-index="' + esc(r.index) + '"]');
              if (cbx) {
                cbx.disabled = true;
                cbx.checked = false;
                var card = cbx.closest('.article-result-card');
                if (card) card.style.opacity = '0.6';
              }
            } else {
              fail++;
            }
          });

          var msg = pub + ' article' + (pub === 1 ? '' : 's') + ' published';
          if (fail) msg += ', ' + fail + ' failed';
          if (d.summary && typeof d.summary.total !== 'undefined') {
            msg += ' (' + (d.summary.published || pub) + '/' + d.summary.total + ')';
          } else {
            msg += ' (' + pub + '/' + totalSel + ')';
          }

          if (pst) {
            pst.textContent = msg;
            pst.classList.remove('hidden');
          }
          status(msg, fail ? 'warning' : 'success');
        } else {
          err(d.error || 'Publish failed');
        }
      })
      .catch(function () {
        if (pp) pp.classList.add('hidden');
        setPublishControlsDisabled(false);
        err('Network error while publishing');
      });
  }

  function downloadSampleCsv() {
    var csv = [
      'topic,tone,length,language,keywords,style',
      '"Top 10 AI Trends in 2025",professional,medium,en,"AI, trends, technology",',
      '"How to Start a Blog in 2025",casual,short,en,"blogging, tips, beginner",',
      '"Deep Learning vs Machine Learning",informative,long,en,"deep learning, ML, comparison",',
      '"Building REST APIs with PHP",professional,medium,en,"PHP, REST, API",',
      '"SEO Best Practices 2025",informative,medium,en,"SEO, optimization, ranking",'
    ].join('\n');
    var blob = new Blob([csv], { type: 'text/csv' });
    var a = d.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'sample-topics.csv';
    a.click();
    URL.revokeObjectURL(a.href);
  }

  if (dz) dz.addEventListener('click', function () { fi && fi.click(); });
  if (dz) dz.addEventListener('dragover', function (e) { e.preventDefault(); dz.classList.add('dragover'); });
  if (dz) dz.addEventListener('dragleave', function () { dz.classList.remove('dragover'); });
  if (dz) dz.addEventListener('drop', function (e) {
    e.preventDefault();
    dz.classList.remove('dragover');
    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
      handleFile(e.dataTransfer.files[0]);
    }
  });
  if (fi) fi.addEventListener('change', function () {
    if (fi.files && fi.files.length) handleFile(fi.files[0]);
  });
  if (cb) cb.addEventListener('click', function () {
    topics = [];
    if (pb) pb.innerHTML = '';
    if (ps) ps.classList.add('hidden');
    if (es) es.classList.remove('hidden');
    if (gs) gs.classList.add('hidden');
    up(1);
  });
  if (dsl) dsl.addEventListener('click', downloadSampleCsv);
  if (gs) gs.addEventListener('click', generateAll);
  if (sa) sa.addEventListener('click', function () { selectAll(true); });
  if (da) da.addEventListener('click', function () { selectAll(false); });
  if (pb2) pb2.addEventListener('click', publishSelected);
  if (bu) bu.addEventListener('click', function () {
    if (sc) sc.classList.add('hidden');
    if (s4) s4.classList.add('hidden');
    if (rs) rs.classList.add('hidden');
    if (ra) ra.classList.add('hidden');
    if (ps) ps.classList.remove('hidden');
    up(1);
  });

export { generateAll, publishSelected, parseCSV };

