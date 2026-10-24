(function () {
  var API = '../api/lottery_layout.php';
  var stage = 1;
  var autoTimer = null;

  function $(id) {
    return document.getElementById(id);
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function iconUrl(icon) {
    if (!icon) return '';
    icon = String(icon).trim();
    if (!icon) return '';
    if (/^https?:\/\//i.test(icon)) return icon;
    if (/^\d+\.(?:png|gif|webp|jpe?g)$/i.test(icon)) return '../icons/' + icon;
    if (icon.indexOf('local:') === 0) return '../icons/' + icon.slice(6);
    if (icon.indexOf('wiki:') === 0 || icon.indexOf('case:') === 0 || icon.indexOf('/images/') === 0) {
      return '../api/lottery_wiki_image.php?icon=' + encodeURIComponent(icon);
    }
    return '';
  }

  function cellLabel(cell) {
    var type = cell.cell_type || 'empty';
    if (type === 'bomb') return '💣 Бомба';
    if (type === 'empty') return 'Пусто';
    return cell.prize_title || 'Приз';
  }

  function renderLayout(data) {
    var grid = $('lotteryLayoutGrid');
    var meta = $('lotteryLayoutMeta');
    if (!grid) return;

    if (!data || !data.success) {
      grid.innerHTML = '<p class="layout-empty">' + escapeHtml((data && data.error) || 'Нет данных') + '</p>';
      if (meta) meta.textContent = '';
      return;
    }

    stage = data.stage || stage;
    var cols = data.cols || 3;
    grid.className = 'lottery-layout-grid cols-' + cols;

    grid.innerHTML = (data.board || []).map(function (cell) {
      var type = cell.cell_type || 'empty';
      var idx = cell.cell_index;
      var src = iconUrl(cell.prize_icon_url || cell.prize_icon);
      var opened = (data.opened || []).indexOf(idx) !== -1;
      return (
        '<div class="layout-cell type-' + escapeHtml(type) + (opened ? ' is-opened' : '') + '">' +
          '<span class="layout-num">' + idx + '</span>' +
          (src ? '<img class="layout-icon" src="' + escapeHtml(src) + '" alt="" loading="lazy">' : '') +
          '<span class="layout-label">' + escapeHtml(cellLabel(cell)) + '</span>' +
          (opened ? '<span class="layout-opened">открыт</span>' : '') +
        '</div>'
      );
    }).join('');

    if (meta) {
      meta.textContent = 'Игра #' + (data.game_id || '—')
        + ' · ' + (data.label || ('Этап ' + stage))
        + (data.is_ended ? ' · завершена' : ' · активна')
        + ' · открыто: ' + (data.opened ? data.opened.length : 0);
    }
  }

  function loadLayout(create) {
    var url = API + '?stage=' + encodeURIComponent(stage) + '&_=' + Date.now();
    if (create) url += '&create=1';
    return fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(renderLayout)
      .catch(function () {
        renderLayout({ success: false, error: 'Не удалось загрузить раскладку' });
      });
  }

  function newGame() {
    return fetch(API, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ stage: stage }),
    })
      .then(function (r) { return r.json(); })
      .then(renderLayout);
  }

  function init() {
    var form = $('lotteryStageForm');
    if (form && form.getAttribute('data-stage')) {
      stage = parseInt(form.getAttribute('data-stage'), 10) || 1;
    }

    var refreshBtn = $('lotteryLayoutRefresh');
    var newBtn = $('lotteryLayoutNewGame');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', function () {
        loadLayout(false);
      });
    }
    if (newBtn) {
      newBtn.addEventListener('click', function () {
        if (!confirm('Создать новую игру? Раскладка призов перемешается.')) return;
        newGame();
      });
    }

    loadLayout(true);
    autoTimer = setInterval(function () {
      loadLayout(false);
    }, 12000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
