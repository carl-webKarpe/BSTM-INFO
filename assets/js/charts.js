/* Chart.js helpers with the system colour palette. */
(function () {
  'use strict';
  var PALETTE = ['#2563EB', '#172554', '#16A34A', '#F59E0B', '#DC2626', '#7C3AED', '#0891B2', '#DB2777', '#65A30D', '#EA580C', '#64748B', '#0D9488'];
  window.CHART_PALETTE = PALETTE;

  if (window.Chart) {
    Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
    Chart.defaults.color = '#64748B';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 8;
    Chart.defaults.maintainAspectRatio = false;
  }

  /**
   * makeChart('canvasId', 'bar'|'hbar'|'doughnut'|'line', labels, values, {colors, label})
   */
  window.makeChart = function (id, kind, labels, values, opts) {
    opts = opts || {};
    var el = document.getElementById(id);
    if (!el || !window.Chart) return null;
    var total = values.reduce(function (a, b) { return a + Number(b); }, 0);
    if (!labels.length || total === 0) {
      el.parentElement.innerHTML = '<div class="chart-empty"><div><i class="bi bi-bar-chart" style="font-size:1.8rem;display:block"></i>No data yet</div></div>';
      return null;
    }
    var colors = opts.colors || (kind === 'doughnut' ? PALETTE : PALETTE[0]);
    var type = kind === 'hbar' ? 'bar' : kind;
    var ds = { label: opts.label || 'Participants', data: values, backgroundColor: colors, borderColor: colors, borderWidth: 0 };
    if (kind === 'bar' || kind === 'hbar') { ds.borderRadius = 6; ds.maxBarThickness = 38; }
    if (kind === 'line') {
      ds.borderWidth = 2.5; ds.tension = .3; ds.fill = true; ds.pointRadius = 3;
      ds.backgroundColor = 'rgba(37,99,235,.10)'; ds.borderColor = '#2563EB'; ds.pointBackgroundColor = '#2563EB';
    }
    var cfg = {
      type: type,
      data: { labels: labels, datasets: [ds] },
      options: {
        responsive: true,
        indexAxis: kind === 'hbar' ? 'y' : 'x',
        plugins: { legend: { display: kind === 'doughnut', position: 'bottom' } },
        scales: kind === 'doughnut' ? {} : {
          x: { grid: { display: kind === 'hbar' || kind === 'line', color: '#F1F5F9' }, ticks: { precision: 0, autoSkip: true, maxRotation: 0 }, beginAtZero: true },
          y: { grid: { color: '#F1F5F9', display: kind !== 'hbar' }, ticks: { precision: 0, callback: function (v) { var l = this.getLabelForValue ? this.getLabelForValue(v) : v; return kind === 'hbar' && String(l).length > 28 ? String(l).slice(0, 26) + '…' : l; } }, beginAtZero: true }
        }
      }
    };
    if (kind === 'doughnut') cfg.options.cutout = '62%';
    return new Chart(el, cfg);
  };
})();
