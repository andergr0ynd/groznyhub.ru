(function () {
  var API = (function () {
    var p = String(location.pathname || '/');
    if (p.indexOf('/lotereya') !== -1) {
      return p.replace(/\/lotereya\/?.*$/, '') + '/api';
    }
    return '/api';
  })();
  var STAGES = {
    1: { id: 1, cols: 3, rows: 3, count: 9, bombs: false, label: 'Этап 1 — сетка 3×3' },
    2: { id: 2, cols: 4, rows: 4, count: 16, bombs: true, label: 'Этап 2 — сетка 4×4 · бомбы' },
    3: { id: 3, cols: 5, rows: 5, count: 25, bombs: true, label: 'Этап 3 — сетка 5×5 · бомбы' },
  };

  var els = {
    lottery: document.getElementById('lottery'),
    grid: document.getElementById('ticketGrid'),
    stageTitle: document.getElementById('stageTitle'),
    gameStatus: document.getElementById('gameStatus'),
    resetBtn: document.getElementById('resetGameBtn'),
    stageButtons: document.querySelectorAll('.stage-btn'),
  };

  var currentStage = 1;
  var gameId = null;
  var isEnded = false;
  var endReason = null;
  var opened = [];
  var reveals = {};
  var busy = false;
  var activeScratch = null;
  var SCRATCH_THRESHOLD = 0.42;
  var REVEAL_DELAY_MS = 5000;

  function storageKey(stage) {
    return 'lottery_game_' + stage;
  }

  function saveGameId(stage, id) {
    try {
      if (id) localStorage.setItem(storageKey(stage), id);
      else localStorage.removeItem(storageKey(stage));
    } catch (e) { /* private mode */ }
  }

  function loadGameId(stage) {
    try {
      return localStorage.getItem(storageKey(stage)) || '';
    } catch (e) {
      return '';
    }
  }

  function setStatus(text, kind) {
    if (!els.gameStatus) return;
    els.gameStatus.textContent = text || '';
    els.gameStatus.className = 'game-status' + (kind ? ' ' + kind : '');
  }

  function formatApiError(err) {
    if (!err) return 'Нет связи с сервером';
    if (err.detail) return err.message + ' — ' + err.detail;
    return err.message || 'Нет связи с сервером';
  }

  function parseApiResponse(response) {
    return response.text().then(function (text) {
      var data = null;
      try {
        data = text ? JSON.parse(text) : null;
      } catch (e) {
        var err = new Error('Сервер вернул не JSON (HTTP ' + response.status + ')');
        err.detail = String(text || '').replace(/\s+/g, ' ').slice(0, 140);
        throw err;
      }
      if (!data || typeof data !== 'object') {
        var emptyErr = new Error('Пустой ответ сервера');
        emptyErr.detail = 'HTTP ' + response.status;
        throw emptyErr;
      }
      return data;
    });
  }

  function apiPost(url, body) {
    return fetch(API + url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body || {}),
    }).then(parseApiResponse);
  }

  function apiGet(url) {
    return fetch(API + url + (url.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now())
      .then(parseApiResponse);
  }

  function clearStoredGame(stage) {
    saveGameId(stage || currentStage, null);
    gameId = null;
  }

  function recoverStaleGame(stage, message) {
    clearStoredGame(stage);
    return startStage(stage).then(function () {
      setStatus(message || 'Сессия устарела — начата новая игра');
    });
  }

  function applyState(data) {
    if (!data || !data.success) return false;
    gameId = data.game_id || null;
    isEnded = !!data.is_ended;
    endReason = data.end_reason || null;
    opened = Array.isArray(data.opened) ? data.opened.slice() : [];
    reveals = data.reveals || {};
    if (gameId) saveGameId(currentStage, gameId);
    if (data.status) setStatus(data.status, isEnded && endReason === 'triple_match' ? 'win' : (isEnded ? 'lose' : ''));
    return true;
  }

  var WIKI_ORIGIN = 'https://wiki.radmir.online';

  function wikiThumbFromIcon(icon, width) {
    if (!icon) return '';
    icon = String(icon).trim();
    if (!icon) return '';
    if (/^https?:\/\//i.test(icon)) return icon;
    if (icon.indexOf('/images/thumb/') === 0) return WIKI_ORIGIN + icon;
    if (icon.indexOf('wiki:') === 0) icon = icon.slice(5);
    if (/^[0-9a-f]\/[0-9a-f]{2}\//i.test(icon)) {
      var fn = icon.split('/').pop();
      return WIKI_ORIGIN + '/images/thumb/' + icon + '/' + (width || 96) + 'px-' + encodeURIComponent(fn);
    }
    return '';
  }

  function prizeIconSrc(reveal) {
    if (!reveal) return '';
    if (reveal.prize_icon_url) return reveal.prize_icon_url;
    var icon = reveal.prize_icon;
    if (!icon) {
      return '';
    }
    if (icon.indexOf('case:') === 0 || icon.indexOf('local:') === 0) {
      return '../api/lottery_wiki_image.php?icon=' + encodeURIComponent(icon);
    }
    if (icon.indexOf('wiki:') === 0 || icon.indexOf('/images/') === 0) {
      return '../api/lottery_wiki_image.php?icon=' + encodeURIComponent(icon);
    }
    var wiki = wikiThumbFromIcon(icon, 96);
    if (wiki) return wiki;
    if (/\.(png|jpe?g|gif|webp)$/i.test(icon) && icon.indexOf('/') === -1) {
      return '../icons/' + icon;
    }
    return '';
  }

  function prizeTitleHtml(reveal) {
    var title = (reveal && reveal.prize_title) ? reveal.prize_title : 'Приз';
    var src = prizeIconSrc(reveal);
    var img = src
      ? '<img class="prize-img" src="' + escapeAttr(src) + '" alt="" loading="lazy" onerror="this.style.display=\'none\'">'
      : '';
    return img + '<div class="prize-title">' + escapeHtml(title) + '</div>';
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function escapeAttr(s) {
    return String(s).replace(/"/g, '&quot;');
  }

  function buildTicketFace(reveal) {
    if (!reveal) return '';
    var type = reveal.cell_type || 'empty';
    if (type === 'bomb') {
      return '<div class="ticket-face bomb"><span class="bomb-icon">💣</span></div>';
    }
    if (type === 'prize') {
      return '<div class="ticket-face prize">' + prizeTitleHtml(reveal) + '</div>';
    }
    return '<div class="ticket-face empty"><span class="empty-icon">Пусто</span></div>';
  }

  function renderGrid() {
    var stage = STAGES[currentStage];
    if (!stage || !els.grid) return;

    els.lottery.className = 'lottery stage-' + currentStage;
    els.grid.className = 'grid cols-' + stage.cols;
    els.grid.style.gridTemplateRows = 'repeat(' + stage.rows + ', 1fr)';
    if (els.stageTitle) els.stageTitle.textContent = stage.label;

    var html = '';
    for (var i = 1; i <= stage.count; i++) {
      var rev = reveals[String(i)];
      var isOpen = opened.indexOf(i) !== -1;
      var classes = 'ticket';
      if (isOpen) classes += ' is-open';
      if (isEnded) classes += ' is-disabled';
      if (rev && rev.cell_type === 'bomb' && isOpen) classes += ' bomb-revealed';

      html +=
        '<div class="cell" role="listitem">' +
          '<span class="label">' + i + '-БИЛЕТ</span>' +
          '<div class="' + classes + '" data-index="' + i + '" role="button" tabindex="0">' +
            '<div class="ticket-closed"><span class="q">?</span></div>' +
            (isOpen ? buildTicketFace(rev) : '') +
          '</div>' +
        '</div>';
    }
    els.grid.innerHTML = html;

    els.grid.querySelectorAll('.ticket').forEach(function (ticket) {
      ticket.addEventListener('pointerdown', onTicketPointerDown);
      ticket.addEventListener('pointermove', onTicketPointerMove);
      ticket.addEventListener('pointerup', onTicketPointerUp);
      ticket.addEventListener('pointercancel', onTicketPointerUp);
      ticket.addEventListener('pointerleave', onTicketPointerLeave);
    });

    els.stageButtons.forEach(function (btn) {
      btn.classList.toggle('active', Number(btn.dataset.stage) === currentStage);
    });
  }

  function getBrushRadius(el) {
    var w = el.clientWidth || 80;
    return Math.max(12, Math.min(28, w * 0.18));
  }

  function drawScratchCoating(ctx, width, height, dpr) {
    ctx.save();
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.scale(dpr, dpr);
    var cw = width / dpr;
    var ch = height / dpr;
    var grad = ctx.createLinearGradient(0, 0, cw, ch);
    grad.addColorStop(0, '#d4d8de');
    grad.addColorStop(0.5, '#9ca3af');
    grad.addColorStop(1, '#e5e7eb');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, cw, ch);
    ctx.fillStyle = 'rgba(255, 255, 255, 0.28)';
    for (var i = 0; i < 48; i++) {
      ctx.fillRect(Math.random() * cw, Math.random() * ch, 2, 2);
    }
    ctx.fillStyle = 'rgba(30, 30, 30, 0.55)';
    ctx.font = '900 italic ' + Math.max(22, Math.min(cw * 0.42, 58)) + 'px Segoe UI, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('?', cw / 2, ch / 2);
    ctx.restore();
  }

  function initScratchCanvas(ticketEl, session) {
    var closed = ticketEl.querySelector('.ticket-closed');
    var rect = ticketEl.getBoundingClientRect();
    var w = Math.max(1, Math.round(rect.width));
    var h = Math.max(1, Math.round(rect.height));
    var dpr = window.devicePixelRatio || 1;
    var canvas = document.createElement('canvas');
    canvas.className = 'scratch-canvas';
    canvas.width = Math.floor(w * dpr);
    canvas.height = Math.floor(h * dpr);
    canvas.style.width = w + 'px';
    canvas.style.height = h + 'px';
    var ctx = canvas.getContext('2d');
    if (!ctx) return;
    drawScratchCoating(ctx, canvas.width, canvas.height, dpr);
    if (closed) closed.remove();
    ticketEl.appendChild(canvas);
    session.canvas = canvas;
    session.ctx = ctx;
    session.dpr = dpr;
    session.brush = getBrushRadius(ticketEl);
  }

  function scratchAt(session, clientX, clientY) {
    var canvas = session.canvas;
    var ctx = session.ctx;
    if (!canvas || !ctx) return;
    var rect = canvas.getBoundingClientRect();
    var dpr = session.dpr || 1;
    var x = (clientX - rect.left) * dpr;
    var y = (clientY - rect.top) * dpr;
    var r = session.brush * dpr;
    ctx.globalCompositeOperation = 'destination-out';
    ctx.beginPath();
    ctx.arc(x, y, r, 0, Math.PI * 2);
    ctx.fill();
  }

  function scratchLine(session, x0, y0, x1, y1) {
    var dist = Math.hypot(x1 - x0, y1 - y0);
    var steps = Math.max(1, Math.ceil(dist / 3));
    for (var i = 0; i <= steps; i++) {
      var t = i / steps;
      scratchAt(session, x0 + (x1 - x0) * t, y0 + (y1 - y0) * t);
    }
  }

  function scratchPercent(session) {
    var canvas = session.canvas;
    var ctx = session.ctx;
    if (!canvas || !ctx) return 0;
    var data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
    var transparent = 0;
    var samples = 0;
    var step = 16;
    for (var i = 3; i < data.length; i += step * 4) {
      samples++;
      if (data[i] < 128) transparent++;
    }
    return samples ? transparent / samples : 0;
  }

  function preloadRevealImage(reveal) {
    return new Promise(function (resolve) {
      var src = prizeIconSrc(reveal);
      if (!src) {
        resolve();
        return;
      }
      var img = new Image();
      var done = false;
      function finish() {
        if (done) return;
        done = true;
        resolve();
      }
      img.onload = finish;
      img.onerror = finish;
      img.src = src;
      setTimeout(finish, REVEAL_DELAY_MS + 500);
    });
  }

  function clearRevealTimer(session) {
    if (!session || !session.revealTimer) return;
    clearTimeout(session.revealTimer);
    session.revealTimer = null;
  }

  function revealScratchFace(session) {
    if (!session || session.completed) return;
    var ticketEl = session.ticketEl;
    if (ticketEl) ticketEl.classList.remove('is-reveal-wait');
    if (session.reveal && ticketEl) {
      var existing = ticketEl.querySelector('.ticket-face');
      if (existing) existing.remove();
      ticketEl.insertAdjacentHTML('beforeend', buildTicketFace(session.reveal));
    }
    session.apiDone = true;
    if (session.pendingComplete || scratchPercent(session) >= SCRATCH_THRESHOLD) {
      completeScratch(session);
      return;
    }
    checkScratchComplete(session);
  }

  function scheduleRevealFace(session) {
    clearRevealTimer(session);
    if (session.ticketEl) session.ticketEl.classList.add('is-reveal-wait');
    setStatus('Подгрузка приза…');
    preloadRevealImage(session.reveal);
    session.revealTimer = setTimeout(function () {
      session.revealTimer = null;
      revealScratchFace(session);
    }, REVEAL_DELAY_MS);
  }

  function restoreTicketClosed(ticketEl) {
    if (!ticketEl || ticketEl.querySelector('.ticket-closed')) return;
    ticketEl.insertAdjacentHTML('afterbegin', '<div class="ticket-closed"><span class="q">?</span></div>');
  }

  function abortScratch(session) {
    if (!session) return;
    clearRevealTimer(session);
    var ticketEl = session.ticketEl;
    if (ticketEl) {
      ticketEl.classList.remove('is-scratching', 'is-reveal-wait');
      if (session.canvas) session.canvas.remove();
      if (!ticketEl.classList.contains('is-open')) {
        restoreTicketClosed(ticketEl);
      }
      var face = ticketEl.querySelector('.ticket-face');
      if (face && !ticketEl.classList.contains('is-open')) face.remove();
    }
    if (activeScratch === session) activeScratch = null;
  }

  function completeScratch(session) {
    if (!session || session.completed) return;
    session.completed = true;
    clearRevealTimer(session);
    var ticketEl = session.ticketEl;
    if (ticketEl) {
      ticketEl.classList.remove('is-scratching', 'is-reveal-wait');
      ticketEl.classList.add('is-open');
      if (session.canvas) session.canvas.remove();
      var rev = session.reveal;
      if (rev && rev.cell_type === 'bomb') ticketEl.classList.add('bomb-revealed');
    }
    if (activeScratch === session) activeScratch = null;
    if (session.finishTurn) session.finishTurn();
  }

  function checkScratchComplete(session) {
    if (!session || session.completed) return;
    if (scratchPercent(session) >= SCRATCH_THRESHOLD) {
      if (!session.apiDone) {
        session.pendingComplete = true;
        return;
      }
      completeScratch(session);
    }
  }

  function buildFinishTurn(session) {
    var data = session.pendingData;
    var index = session.index;
    return function () {
      if (data.is_ended) {
        if (data.end_reason === 'triple_match') {
          setStatus(data.message || '3 одинаковых! Лотерея остановлена', 'win');
        } else {
          setStatus(data.message || 'Игра окончена', 'lose');
        }
        renderGrid();
      } else if (data.message) {
        setStatus(data.message, '');
      } else {
        setStatus('Открыт билет #' + index);
      }
      busy = false;
    };
  }

  function beginScratchReveal(index, ticketEl) {
    busy = true;
    ticketEl.classList.add('is-scratching');

    var session = {
      index: index,
      ticketEl: ticketEl,
      apiDone: false,
      reveal: null,
      pendingData: null,
      finishTurn: null,
      lastX: null,
      lastY: null,
      scratching: false,
      pendingComplete: false,
      completed: false,
      revealTimer: null,
    };
    activeScratch = session;
    initScratchCanvas(ticketEl, session);

    apiPost('/lottery_reveal.php', { game_id: gameId, cell_index: index })
      .then(function (data) {
        if (session.completed) return;

        if (!data.success) {
          if (data.error === 'Игра не найдена') {
            abortScratch(session);
            busy = false;
            return recoverStaleGame(currentStage, 'Старая игра сброшена — стирай билет ещё раз');
          }
          abortScratch(session);
          setStatus(data.error || 'Ошибка', 'lose');
          busy = false;
          return;
        }

        applyState({
          success: true,
          game_id: data.game_id,
          is_ended: data.is_ended,
          end_reason: data.end_reason,
          matched_prize: data.matched_prize,
          opened: data.opened,
          reveals: data.reveals,
        });

        session.apiDone = false;
        session.pendingData = data;
        session.reveal = data.reveal || (data.reveals && data.reveals[String(index)]) || null;
        session.finishTurn = buildFinishTurn(session);
        scheduleRevealFace(session);
      })
      .catch(function (err) {
        if (session.completed) return;
        abortScratch(session);
        if (err && err.message === 'Игра не найдена') {
          busy = false;
          return recoverStaleGame(currentStage, 'Старая игра сброшена — стирай билет ещё раз');
        }
        setStatus(formatApiError(err), 'lose');
        busy = false;
      });
  }

  function beginScratchFromPointer(index, ticketEl, e) {
    if (activeScratch && activeScratch.index !== index) return;

    if (!activeScratch) {
      beginScratchReveal(index, ticketEl);
    }

    var session = activeScratch;
    if (!session) return;

    session.scratching = true;
    try {
      ticketEl.setPointerCapture(e.pointerId);
    } catch (err) { /* ignore */ }

    scratchAt(session, e.clientX, e.clientY);
    session.lastX = e.clientX;
    session.lastY = e.clientY;
    checkScratchComplete(session);
  }

  function onTicketPointerDown(e) {
    if (e.button !== undefined && e.button !== 0) return;
    var ticketEl = e.currentTarget;
    var index = parseInt(ticketEl.dataset.index, 10);
    var continuing = ticketEl.classList.contains('is-scratching')
      || (activeScratch && activeScratch.index === index);
    if (isEnded || ticketEl.classList.contains('is-open')) return;
    if (opened.indexOf(index) !== -1 && !continuing) return;
    if (busy && (!activeScratch || activeScratch.index !== index)) return;

    e.preventDefault();

    if (!gameId) {
      setStatus('Загрузка игры…');
      busy = true;
      startStage(currentStage)
        .then(function () {
          busy = false;
          var el = els.grid && els.grid.querySelector('.ticket[data-index="' + index + '"]');
          if (el) beginScratchFromPointer(index, el, e);
        })
        .catch(function (err) {
          busy = false;
          setStatus(formatApiError(err), 'lose');
        });
      return;
    }

    beginScratchFromPointer(index, ticketEl, e);
  }

  function onTicketPointerMove(e) {
    if (!activeScratch || activeScratch.ticketEl !== e.currentTarget || !activeScratch.scratching) return;
    e.preventDefault();
    var session = activeScratch;
    if (session.lastX != null && session.lastY != null) {
      scratchLine(session, session.lastX, session.lastY, e.clientX, e.clientY);
    }
    session.lastX = e.clientX;
    session.lastY = e.clientY;
    checkScratchComplete(session);
  }

  function onTicketPointerUp(e) {
    if (!activeScratch || activeScratch.ticketEl !== e.currentTarget) return;
    activeScratch.scratching = false;
    activeScratch.lastX = null;
    activeScratch.lastY = null;
    try {
      e.currentTarget.releasePointerCapture(e.pointerId);
    } catch (err) { /* ignore */ }
    checkScratchComplete(activeScratch);
  }

  function onTicketPointerLeave(e) {
    if (!activeScratch || activeScratch.ticketEl !== e.currentTarget) return;
    activeScratch.scratching = false;
    activeScratch.lastX = null;
    activeScratch.lastY = null;
  }

  function fetchState(stage, gid) {
    var url = '/lottery_state.php?stage=' + stage;
    if (gid) url += '&game_id=' + encodeURIComponent(gid);
    return apiGet(url);
  }

  function startStage(stage) {
    return apiPost('/lottery_start.php', { stage: stage }).then(function (data) {
      if (!applyState(data)) {
        setStatus(data.error || 'Не удалось начать игру', 'lose');
        return data;
      }
      setStatus('Собери 3 одинаковых приза · стирай билеты');
      renderGrid();
      return data;
    });
  }

  function resetStage() {
    if (activeScratch) {
      abortScratch(activeScratch);
    }
    busy = true;
    return apiPost('/lottery_reset.php', { stage: currentStage }).then(function (data) {
      busy = false;
      if (!applyState(data)) {
        setStatus(data.error || 'Ошибка сброса', 'lose');
        return;
      }
      setStatus('Новая игра');
      renderGrid();
    }).catch(function (err) {
      busy = false;
      setStatus(formatApiError(err), 'lose');
    });
  }

  function switchStage(stageId) {
    if (!STAGES[stageId] || stageId === currentStage) return;
    if (activeScratch) {
      abortScratch(activeScratch);
    }
    busy = false;
    currentStage = stageId;
    gameId = loadGameId(stageId);
    isEnded = false;
    endReason = null;
    opened = [];
    reveals = {};
    setStatus('');

    fetchState(stageId, gameId).then(function (data) {
      if (data.success && data.game_id) {
        applyState(data);
        if (isEnded) {
          setStatus(data.status || (endReason === 'triple_match' ? '3 одинаковых!' : 'Игра окончена'), endReason === 'triple_match' ? 'win' : 'lose');
        } else if (opened.length) {
          setStatus('Собери 3 одинаковых · открыто: ' + opened.length);
        } else {
          setStatus(data.rule || 'Собери 3 одинаковых приза');
        }
      } else {
        return startStage(stageId);
      }
      renderGrid();
    }).catch(function (err) {
      setStatus(formatApiError(err), 'lose');
      startStage(stageId);
    });
  }

  function init() {
    if (/[?&]obs=1/.test(location.search)) {
      document.body.classList.add('obs-mode');
    }

    els.stageButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        switchStage(Number(btn.dataset.stage));
      });
    });

    if (els.resetBtn) {
      els.resetBtn.addEventListener('click', function () {
        resetStage();
      });
    }

    gameId = loadGameId(1);
    fetchState(1, gameId).then(function (data) {
      if (data.success && data.game_id) {
        applyState(data);
        if (isEnded) setStatus(data.status || 'Игра окончена', data.end_reason === 'triple_match' ? 'win' : 'lose');
        else setStatus(data.rule || 'Собери 3 одинаковых приза');
      } else {
        return startStage(1);
      }
      renderGrid();
    }).catch(function (err) {
      setStatus(formatApiError(err), 'lose');
      startStage(1);
    });
  }

  init();
})();
