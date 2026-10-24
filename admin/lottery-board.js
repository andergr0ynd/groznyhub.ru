(function () {
  var selectedIndex = null;
  var editorSlot = null;
  var boardStage = '1';

  function storageKey() {
    return 'lottery_admin_cell_s' + boardStage;
  }

  function saveSelectedCell(index) {
    try {
      sessionStorage.setItem(storageKey(), String(index));
    } catch (e) { /* private mode */ }
  }

  function loadSelectedCell(fallback) {
    try {
      var n = parseInt(sessionStorage.getItem(storageKey()), 10);
      return n > 0 ? n : fallback;
    } catch (e) {
      return fallback;
    }
  }

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

  function getCell(index) {
    return document.querySelector('.lottery-cell[data-index="' + index + '"]');
  }

  function readCellInputs(cell) {
    if (!cell) return null;
    return {
      type: (cell.querySelector('select[name^="cell_type"]') || {}).value || 'empty',
      title: (cell.querySelector('input[name^="prize_title"]') || {}).value || '',
      icon: (cell.querySelector('input[name^="prize_icon"]') || {}).value || '',
    };
  }

  function renderTicketFace(ticketEl, data) {
    if (!ticketEl) return;
    var html = '';
    if (data.type === 'bomb') {
      html = '<div class="ticket-face bomb admin-face"><span class="bomb-icon">💣</span></div>';
    } else if (data.type === 'prize') {
      var src = iconUrl(data.icon);
      var title = data.title.trim() || 'Приз';
      html = '<div class="ticket-face prize admin-face">'
        + (src ? '<img class="prize-img" src="' + escapeHtml(src) + '" alt="">' : '')
        + '<div class="prize-title">' + escapeHtml(title) + '</div></div>';
    } else if (data.type === 'empty') {
      html = '<div class="ticket-face empty admin-face"><span class="empty-icon">Пусто</span></div>';
    } else {
      html = '<div class="ticket-closed"><span class="q">?</span></div>';
    }
    ticketEl.innerHTML = html;
  }

  function refreshTicketPreview(index) {
    var cell = getCell(index);
    if (!cell) return;
    var ticket = cell.querySelector('.admin-ticket');
    renderTicketFace(ticket, readCellInputs(cell));
  }

  function returnFieldsToCell(index) {
    if (index === null || !editorSlot) return;
    var cell = getCell(index);
    var holder = cell && cell.querySelector('.cell-fields');
    if (!holder) return;
    while (editorSlot.firstChild) {
      holder.appendChild(editorSlot.firstChild);
    }
  }

  function mountEditor(index) {
    var editor = $('ticketEditor');
    var editorSlotEl = $('ticketEditorFields');
    var label = $('ticketEditorLabel');
    if (!editor || !editorSlotEl) return;

    if (selectedIndex !== null && selectedIndex !== index) {
      returnFieldsToCell(selectedIndex);
      refreshTicketPreview(selectedIndex);
    }

    selectedIndex = index;
    editorSlot = editorSlotEl;

    var cell = getCell(index);
    if (!cell) return;

    document.querySelectorAll('.admin-ticket.is-selected').forEach(function (t) {
      t.classList.remove('is-selected');
    });
    var ticket = cell.querySelector('.admin-ticket');
    if (ticket) ticket.classList.add('is-selected');

    var holder = cell.querySelector('.cell-fields');
    if (holder) {
      while (holder.firstChild) {
        editorSlotEl.appendChild(holder.firstChild);
      }
    }

    if (label) {
      label.textContent = index + '-билет';
    }
    editor.hidden = false;

    saveSelectedCell(index);
    bindEditorInputs();
    refreshTicketPreview(index);
    document.dispatchEvent(new CustomEvent('lottery:editor-mounted'));
  }

  function bindEditorInputs() {
    if (!editorSlot) return;

    var typeSelect = editorSlot.querySelector('select[name^="cell_type"]');
    var titleInput = editorSlot.querySelector('input[name^="prize_title"]');
    var iconInput = editorSlot.querySelector('input[name^="prize_icon"]');
    var previewImg = editorSlot.querySelector('.icon-preview');

    function sync() {
      if (selectedIndex !== null) {
        refreshTicketPreview(selectedIndex);
      }
    }

    if (typeSelect && !typeSelect.dataset.boardBound) {
      typeSelect.dataset.boardBound = '1';
      typeSelect.addEventListener('change', sync);
    }
    if (titleInput && !titleInput.dataset.boardBound) {
      titleInput.dataset.boardBound = '1';
      titleInput.addEventListener('input', sync);
    }
    if (iconInput && !iconInput.dataset.boardBound) {
      iconInput.dataset.boardBound = '1';
      iconInput.addEventListener('input', function () {
        if (previewImg) {
          var src = iconUrl(iconInput.value.trim());
          if (src) {
            previewImg.src = src;
            previewImg.hidden = false;
          } else {
            previewImg.hidden = true;
          }
        }
        sync();
      });
      if (previewImg && iconInput.value.trim()) {
        var src0 = iconUrl(iconInput.value.trim());
        if (src0) {
          previewImg.src = src0;
          previewImg.hidden = false;
        }
      }
    }
  }

  function init() {
    var form = document.getElementById('lotteryStageForm');
    if (form && form.getAttribute('data-stage')) {
      boardStage = form.getAttribute('data-stage');
    }

    document.querySelectorAll('.admin-ticket').forEach(function (btn) {
      if (btn.dataset.boardTicketBound) return;
      btn.dataset.boardTicketBound = '1';
      btn.addEventListener('click', function () {
        var cell = btn.closest('.lottery-cell');
        if (!cell) return;
        mountEditor(parseInt(cell.getAttribute('data-index'), 10));
      });
    });

    document.addEventListener('lottery:icon-updated', function () {
      if (selectedIndex !== null) {
        bindEditorInputs();
        refreshTicketPreview(selectedIndex);
      }
    });

    var startCell = loadSelectedCell(1);
    var startEl = getCell(startCell) || document.querySelector('.lottery-cell');
    if (startEl) {
      mountEditor(parseInt(startEl.getAttribute('data-index'), 10));
    }
  }

  window.lotteryBoardRefresh = function (index) {
    refreshTicketPreview(index || selectedIndex);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
