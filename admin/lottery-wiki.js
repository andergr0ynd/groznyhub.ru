(function () {
  var WIKI_ORIGIN = 'https://wiki.radmir.online';
  var API_SKINS = '../api/lottery_wiki_skins.php';
  var API_BUNDLE = '../api/lottery_wiki_bundle.php';
  var loadedSkins = [];
  var wikiCategories = {};
  var wikiGroups = { skins: 'Скины', cars: 'Машины' };
  var wikiBundles = {};
  var cacheUpdatedAt = null;
  var skinsUpdatedAt = null;
  var carsUpdatedAt = null;
  var casesUpdatedAt = null;
  var skinsCount = 0;
  var carsCount = 0;
  var casesCount = 0;
  var activeIconInput = null;
  var cacheLoading = false;
  var pickerOpen = false;
  var bundleLoading = false;
  var uiBound = false;

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

  function iconPreviewUrl(icon, width) {
    return previewImgSrc(icon);
  }

  function caseIconPreview(s) {
    if (!s) return '';
    if (s.thumb) return s.thumb;
    if (s.icon) return previewImgSrc(s.icon);
    if (typeof s.case_index === 'number' && s.case_index >= 0 && s.case_index <= 16) {
      return '../icons/' + s.case_index + '.png';
    }
    return '';
  }

  function previewImgSrc(icon) {
    if (!icon) return '';
    icon = String(icon).trim();
    if (!icon) return '';
    if (/^https?:\/\//i.test(icon)) return icon;
    if (/^\d+\.(?:png|gif|webp|jpe?g)$/i.test(icon)) return '../icons/' + icon;
    if (icon.indexOf('local:') === 0) return '../icons/' + icon.slice(6);
    if (icon.indexOf('case:') === 0) {
      return '../api/lottery_wiki_image.php?icon=' + encodeURIComponent(icon);
    }
    if (icon.indexOf('/images/thumb/') === 0) return WIKI_ORIGIN + icon;
    if (icon.indexOf('wiki:') === 0 || icon.indexOf('/images/') === 0 || /^[0-9a-f]\/[0-9a-f]{2}\//i.test(icon)) {
      return '../api/lottery_wiki_image.php?icon=' + encodeURIComponent(icon);
    }
    return '';
  }

  function resolvePreviewUrl(icon, img) {
    var src = previewImgSrc(icon);
    if (!src) {
      img.hidden = true;
      img.removeAttribute('src');
      return;
    }
    img.src = src;
    img.hidden = false;
    img.onerror = function () {
      img.hidden = true;
    };
  }

  function editorPreviewImg(input) {
    var editorFields = $('ticketEditorFields');
    if (editorFields && editorFields.contains(input)) {
      return editorFields.querySelector('.icon-preview');
    }
    var cell = input.closest('.lottery-cell');
    return cell ? cell.querySelector('.icon-preview') : null;
  }

  function setPickButtonsDisabled(disabled) {
    document.querySelectorAll('.wiki-pick-btn').forEach(function (btn) {
      btn.disabled = !!disabled;
      btn.classList.toggle('is-disabled', !!disabled);
    });
  }

  function setBundleButtonsDisabled(disabled) {
    ['wikiLoadSkinsBtn', 'wikiLoadCarsBtn', 'wikiLoadCasesBtn'].forEach(function (id) {
      var btn = $(id);
      if (btn) btn.disabled = !!disabled;
    });
  }

  function bindIconInputs() {
    document.querySelectorAll('input[name^="prize_icon"]').forEach(function (input) {
      if (input.dataset.wikiIconBound) return;
      input.dataset.wikiIconBound = '1';
      input.addEventListener('input', function () {
        var img = editorPreviewImg(input);
        if (img) resolvePreviewUrl(input.value.trim(), img);
        document.dispatchEvent(new CustomEvent('lottery:icon-updated'));
      });
      input.addEventListener('change', function () {
        var img = editorPreviewImg(input);
        if (img) resolvePreviewUrl(input.value.trim(), img);
        document.dispatchEvent(new CustomEvent('lottery:icon-updated'));
      });
    });
  }

  function bindWikiUiOnce() {
    if (uiBound) return;
    uiBound = true;

    document.addEventListener('click', function (e) {
      var pickBtn = e.target.closest('.wiki-pick-btn');
      if (pickBtn) {
        if (pickBtn.disabled || pickerOpen) {
          e.preventDefault();
          return;
        }
        var idx = pickBtn.getAttribute('data-cell');
        activeIconInput = document.querySelector('input[name="prize_icon[' + idx + ']"]');
        openPicker();
        return;
      }

      var skinBtn = e.target.closest('.wiki-skin-item');
      if (skinBtn && pickerOpen) {
        if (skinBtn.disabled) return;
        skinBtn.disabled = true;
        if (!activeIconInput) {
          closePicker();
          return;
        }
        var store = skinBtn.getAttribute('data-store') || '';
        var name = skinBtn.getAttribute('data-name') || '';
        var iconRef = skinBtn.getAttribute('data-icon') || '';
        activeIconInput.value = iconRef || store;

        var nameMatch = /prize_icon\[(\d+)\]/.exec(activeIconInput.name || '');
        if (nameMatch && name) {
          var titleInput = document.querySelector('input[name="prize_title[' + nameMatch[1] + ']"]');
          if (titleInput) titleInput.value = name;
        }

        activeIconInput.dispatchEvent(new Event('input', { bubbles: true }));
        document.dispatchEvent(new CustomEvent('lottery:icon-updated'));
        closePicker();
      }
    });
  }

  function setSkins(skins, meta) {
    loadedSkins = Array.isArray(skins) ? skins.slice() : [];
    cacheUpdatedAt = (meta && meta.updated_at) || null;
    skinsUpdatedAt = (meta && meta.skins_updated_at) || null;
    carsUpdatedAt = (meta && meta.cars_updated_at) || null;
    casesUpdatedAt = (meta && meta.cases_updated_at) || null;
    skinsCount = (meta && meta.skins_count) != null ? meta.skins_count : loadedSkins.filter(function (s) { return (s.group || 'skins') === 'skins'; }).length;
    carsCount = (meta && meta.cars_count) != null ? meta.cars_count : loadedSkins.filter(function (s) { return s.group === 'cars'; }).length;
    casesCount = (meta && meta.cases_count) != null ? meta.cases_count : loadedSkins.filter(function (s) { return s.group === 'cases'; }).length;
    loadedSkins.sort(function (a, b) {
      return String(a.name || '').localeCompare(String(b.name || ''), 'ru');
    });
    updateCacheStatus();
    renderBundleStatus();
  }

  function formatUpdatedAt(iso) {
    if (!iso) return '';
    try {
      return new Date(iso).toLocaleString('ru-RU');
    } catch (e) {
      return iso;
    }
  }

  function formatFileSize(bytes) {
    bytes = Number(bytes) || 0;
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  function updateCacheStatus() {
    var el = $('wikiCacheStatus');
    if (!el) return;
    if (loadedSkins.length === 0) {
      el.textContent = 'Каталог пуст. Нажмите «Подгрузить скины», «машины» или «кейсы».';
      el.className = 'wiki-cache-status is-empty';
      return;
    }
    var thumbs = window.__wikiThumbsSaved || 0;
    el.textContent = 'В каталоге: ' + skinsCount + ' скинов · ' + carsCount + ' машин · ' + casesCount + ' кейсов'
      + (thumbs ? (' · превью на сервере: ' + thumbs) : ' · превью нет — переимпортируйте с новым скриптом')
      + (skinsUpdatedAt ? (' · скины ' + formatUpdatedAt(skinsUpdatedAt)) : '')
      + (carsUpdatedAt ? (' · машины ' + formatUpdatedAt(carsUpdatedAt)) : '')
      + (casesUpdatedAt ? (' · кейсы ' + formatUpdatedAt(casesUpdatedAt)) : '');
    el.className = thumbs ? 'wiki-cache-status' : 'wiki-cache-status is-empty';
  }

  function renderBundleStatus() {
    var el = $('wikiBundleStatus');
    if (!el) return;
    var parts = [];
    ['skins', 'cars', 'cases'].forEach(function (key) {
      var b = wikiBundles[key] || {};
      var label = wikiGroups[key] || key;
      if (b.exists) {
        parts.push(label + ': json-wiki/' + (b.filename || key + '.json') + ' (' + formatFileSize(b.size) + ')');
      } else {
        parts.push(label + ': файл не загружен на сервер');
      }
    });
    el.textContent = parts.join(' · ');
    el.className = 'wiki-bundle-status' + (parts.some(function (p) { return p.indexOf('не загружен') !== -1; }) ? ' is-warn' : '');
  }

  function loadSkinsFromCache() {
    if (cacheLoading) {
      return Promise.resolve(loadedSkins);
    }
    cacheLoading = true;
    return fetch(API_SKINS + '?_=' + Date.now())
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success) {
          throw new Error(data.error || 'cache error');
        }
        setSkins(data.skins || [], data);
        wikiCategories = data.categories || {};
        wikiGroups = data.groups || wikiGroups;
        wikiBundles = data.bundles || {};
        window.__wikiThumbsSaved = data.thumbs_saved || 0;
        renderCategoryFilter();
        renderGroupFilter();
        updateCacheStatus();
        renderBundleStatus();
        return loadedSkins;
      })
      .finally(function () {
        cacheLoading = false;
      });
  }

  function filterSkinsList(q, category, group) {
    var list = loadedSkins;
    if (group) {
      list = list.filter(function (s) {
        return String(s.group || 'skins') === group;
      });
    }
    if (category) {
      list = list.filter(function (s) {
        return String(s.category || '') === category;
      });
    }
    q = (q || '').trim().toLowerCase();
    if (q) {
      list = list.filter(function (s) {
        return String(s.name || '').toLowerCase().indexOf(q) !== -1
          || String(s.file || '').toLowerCase().indexOf(q) !== -1
          || String(s.category_label || '').toLowerCase().indexOf(q) !== -1;
      });
    }
    return list;
  }

  function renderGroupFilter() {
    var select = $('wikiGroupFilter');
    if (!select) return;
    var current = select.value;
    var html = '<option value="">Все</option>';
    Object.keys(wikiGroups).forEach(function (key) {
      html += '<option value="' + escapeHtml(key) + '">' + escapeHtml(wikiGroups[key] || key) + '</option>';
    });
    select.innerHTML = html;
    if (current) select.value = current;
  }

  function renderCategoryFilter() {
    var select = $('wikiCategoryFilter');
    if (!select) return;

    var keys = Object.keys(wikiCategories);
    if (keys.length === 0) {
      var seen = {};
      loadedSkins.forEach(function (s) {
        if (s.category) seen[s.category] = s.category_label || s.category;
      });
      keys = Object.keys(seen);
      keys.forEach(function (k) { wikiCategories[k] = seen[k]; });
    }

    var current = select.value;
    var html = '<option value="">Все категории</option>';
    keys.forEach(function (key) {
      html += '<option value="' + escapeHtml(key) + '">' + escapeHtml(wikiCategories[key] || key) + '</option>';
    });
    select.innerHTML = html;
    if (current) select.value = current;
  }

  function renderPickerGrid(filter) {
    var grid = $('wikiSkinGrid');
    var status = $('wikiPickerStatus');
    if (!grid) return;

    var search = $('wikiSkinSearch');
    var categorySelect = $('wikiCategoryFilter');
    var groupSelect = $('wikiGroupFilter');
    var q = filter !== undefined ? filter : (search ? search.value : '');
    var category = categorySelect ? categorySelect.value : '';
    var group = groupSelect ? groupSelect.value : '';
    var list = filterSkinsList(q, category, group);

    if (status) {
      if (loadedSkins.length === 0) {
        status.innerHTML = 'Каталог пуст. '
          + '<a href="#wikiCatalogPanel">Подгрузите скины или машины</a> из json-wiki/.';
      } else {
        status.textContent = 'Найдено: ' + list.length + ' из ' + loadedSkins.length
          + (cacheUpdatedAt ? (' · кэш от ' + formatUpdatedAt(cacheUpdatedAt)) : '');
      }
    }

    if (list.length === 0) {
      grid.innerHTML = '<p class="wiki-picker-empty">Нет позиций для отображения.</p>';
      return;
    }

    grid.innerHTML = list.map(function (s) {
      var store = s.store || ('wiki:' + s.file);
      var name = s.name || s.file;
      var cat = s.category_label || wikiCategories[s.category] || '';
      var grp = wikiGroups[s.group] || '';
      var caseSrc = store.indexOf('case:') === 0 ? caseIconPreview(s) : '';
      var imgHtml = caseSrc
        ? '<img src="' + escapeHtml(caseSrc) + '" alt="" loading="lazy">'
        : '<img src="../api/lottery_wiki_image.php?icon=' + encodeURIComponent(store) + '" alt="" loading="lazy">';
      return (
        '<button type="button" class="wiki-skin-item" data-store="' + escapeHtml(store) + '" data-name="' + escapeHtml(name) + '" data-icon="' + escapeHtml(s.icon || '') + '" title="' + escapeHtml(name + (grp ? ' · ' + grp : '') + (cat ? ' · ' + cat : '')) + '">' +
          imgHtml +
          '<span class="wiki-skin-cat">' + escapeHtml(grp + (cat ? ' · ' + cat : '')) + '</span>' +
          '<span>' + escapeHtml(name) + '</span>' +
        '</button>'
      );
    }).join('');
  }

  function importSkinsPayload(payload, replace, bundle) {
    var skins = payload && payload.skins ? payload.skins : payload;
    if (!Array.isArray(skins)) {
      return Promise.reject(new Error('В JSON должен быть массив skins'));
    }
    if (!bundle && payload && payload.bundle) {
      bundle = payload.bundle;
    }
    return fetch(API_SKINS, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ skins: skins, replace: !!replace, bundle: bundle || undefined }),
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success) {
          throw new Error(data.error || 'Ошибка импорта');
        }
        return loadSkinsFromCache().then(function () {
          return data;
        });
      });
  }

  function loadBundle(bundle) {
    if (bundleLoading) {
      return Promise.resolve();
    }
    bundleLoading = true;
    setBundleButtonsDisabled(true);

    var result = $('wikiImportResult');
    var label = wikiGroups[bundle] || bundle;
    if (result) {
      result.textContent = 'Подгрузка ' + label.toLowerCase() + ' из json-wiki/' + bundle + '.json…';
      result.className = 'wiki-import-result';
    }
    return fetch(API_BUNDLE, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ bundle: bundle }),
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success) {
          throw new Error(data.error || 'Ошибка подгрузки');
        }
        return loadSkinsFromCache().then(function () {
          if (result) {
            result.textContent = label + ' подгружены: ' + (data.count || 0) + ' позиций.'
              + (data.thumbs_saved ? (' Превью на сервере: ' + data.thumbs_saved + '.') : '');
            result.className = 'wiki-import-result is-ok';
          }
          renderPickerGrid($('wikiSkinSearch') ? $('wikiSkinSearch').value : '');
          return data;
        });
      })
      .catch(function (err) {
        if (result) {
          result.textContent = err.message || 'Ошибка подгрузки';
          result.className = 'wiki-import-result is-error';
        }
        throw err;
      })
      .finally(function () {
        bundleLoading = false;
        setBundleButtonsDisabled(false);
      });
  }

  function handleImportClick(replace) {
    var textarea = $('wikiImportJson');
    var result = $('wikiImportResult');
    var bundleSelect = $('wikiImportBundle');
    if (!textarea) return;

    var raw = textarea.value.trim();
    if (!raw) {
      if (result) {
        result.textContent = 'Вставьте JSON из буфера или загрузите файл.';
        result.className = 'wiki-import-result is-error';
      }
      return;
    }

    var payload;
    try {
      payload = JSON.parse(raw);
    } catch (e) {
      if (result) {
        result.textContent = 'Неверный JSON: ' + e.message;
        result.className = 'wiki-import-result is-error';
      }
      return;
    }

    var bundle = bundleSelect ? bundleSelect.value : '';
    if (!bundle && payload.bundle) {
      bundle = payload.bundle;
    }

    if (result) {
      result.textContent = 'Импорт…';
      result.className = 'wiki-import-result';
    }

    importSkinsPayload(payload, replace, bundle || undefined)
      .then(function (data) {
        if (result) {
          result.textContent = 'Импортировано. В каталоге ' + loadedSkins.length + ' позиций.'
            + (data && data.thumbs_saved ? (' Превью на сервере: ' + data.thumbs_saved + '.') : ' Для картинок перезапустите скрипт экспорта на wiki.');
          result.className = 'wiki-import-result is-ok';
        }
        textarea.value = '';
        renderPickerGrid($('wikiSkinSearch') ? $('wikiSkinSearch').value : '');
      })
      .catch(function (err) {
        if (result) {
          result.textContent = err.message || 'Ошибка импорта';
          result.className = 'wiki-import-result is-error';
        }
      });
  }

  function openPicker() {
    if (pickerOpen) return;
    var modal = $('wikiSkinModal');
    if (!modal) return;

    pickerOpen = true;
    setPickButtonsDisabled(true);
    modal.hidden = false;
    document.body.classList.add('wiki-modal-open');

    var status = $('wikiPickerStatus');
    if (status) status.textContent = 'Загрузка каталога…';

    loadSkinsFromCache()
      .then(function () {
        renderPickerGrid($('wikiSkinSearch') ? $('wikiSkinSearch').value : '');
      })
      .catch(function () {
        if (status) {
          status.textContent = 'Не удалось загрузить локальный каталог.';
        }
        renderPickerGrid('');
      });
  }

  function closePicker() {
    if (!pickerOpen) return;
    var modal = $('wikiSkinModal');
    pickerOpen = false;
    setPickButtonsDisabled(false);
    if (!modal) return;
    modal.hidden = true;
    document.body.classList.remove('wiki-modal-open');
    activeIconInput = null;
  }

  function init() {
    bindWikiUiOnce();
    document.addEventListener('lottery:editor-mounted', bindIconInputs);
    setTimeout(function () {
      bindIconInputs();
      var editor = $('ticketEditorFields');
      var iconInput = editor && editor.querySelector('input[name^="prize_icon"]');
      var img = editor && editor.querySelector('.icon-preview');
      if (iconInput && img && iconInput.value.trim()) {
        resolvePreviewUrl(iconInput.value.trim(), img);
      }
    }, 0);
    loadSkinsFromCache().catch(function () { /* ignore */ });

    var closeBtn = $('wikiPickerClose');
    var backdrop = $('wikiSkinModal');
    var search = $('wikiSkinSearch');
    var refreshBtn = $('wikiCacheRefresh');
    var importBtn = $('wikiImportBtn');
    var importReplaceBtn = $('wikiImportReplaceBtn');
    var fileInput = $('wikiImportFile');
    var copyScriptBtn = $('wikiCopyExportScript');
    var loadSkinsBtn = $('wikiLoadSkinsBtn');
    var loadCarsBtn = $('wikiLoadCarsBtn');
    var loadCasesBtn = $('wikiLoadCasesBtn');

    if (closeBtn) closeBtn.addEventListener('click', closePicker);
    if (backdrop) {
      backdrop.addEventListener('click', function (e) {
        if (e.target === backdrop) closePicker();
      });
    }
    if (search) {
      search.addEventListener('input', function () {
        renderPickerGrid(search.value);
      });
    }
    var categoryFilter = $('wikiCategoryFilter');
    if (categoryFilter) {
      categoryFilter.addEventListener('change', function () {
        renderPickerGrid(search ? search.value : '');
      });
    }
    var groupFilter = $('wikiGroupFilter');
    if (groupFilter) {
      groupFilter.addEventListener('change', function () {
        renderPickerGrid(search ? search.value : '');
      });
    }
    if (refreshBtn) {
      refreshBtn.addEventListener('click', function () {
        loadSkinsFromCache()
          .then(function () {
            renderPickerGrid(search ? search.value : '');
          })
          .catch(function () { /* ignore */ });
      });
    }
    if (loadSkinsBtn) {
      loadSkinsBtn.addEventListener('click', function () {
        loadBundle('skins').catch(function () { /* shown in result */ });
      });
    }
    if (loadCarsBtn) {
      loadCarsBtn.addEventListener('click', function () {
        loadBundle('cars').catch(function () { /* shown in result */ });
      });
    }
    if (loadCasesBtn) {
      loadCasesBtn.addEventListener('click', function () {
        loadBundle('cases').catch(function () { /* shown in result */ });
      });
    }
    if (importBtn) {
      importBtn.addEventListener('click', function () {
        handleImportClick(false);
      });
    }
    if (importReplaceBtn) {
      importReplaceBtn.addEventListener('click', function () {
        if (!confirm('Заменить выбранную группу импортом?')) return;
        handleImportClick(true);
      });
    }
    if (fileInput) {
      fileInput.addEventListener('change', function () {
        var file = fileInput.files && fileInput.files[0];
        if (!file) return;
        var bundleSelect = $('wikiImportBundle');
        if (bundleSelect) {
          if (/cars/i.test(file.name)) {
            bundleSelect.value = 'cars';
          } else if (/cases/i.test(file.name)) {
            bundleSelect.value = 'cases';
          } else if (/skins/i.test(file.name)) {
            bundleSelect.value = 'skins';
          }
        }
        var reader = new FileReader();
        reader.onload = function () {
          var textarea = $('wikiImportJson');
          if (textarea) textarea.value = String(reader.result || '');
        };
        reader.readAsText(file, 'utf-8');
        fileInput.value = '';
      });
    }
    if (copyScriptBtn) {
      copyScriptBtn.addEventListener('click', function () {
        fetch('wiki-skins-export.js?_=' + Date.now())
          .then(function (r) { return r.text(); })
          .then(function (text) {
            return navigator.clipboard.writeText(text);
          })
          .then(function () {
            copyScriptBtn.textContent = 'Скрипт скопирован!';
            setTimeout(function () {
              copyScriptBtn.textContent = 'Скопировать скрипт экспорта';
            }, 2500);
          })
          .catch(function () {
            alert('Не удалось скопировать. Откройте admin/wiki-skins-export.js и скопируйте вручную.');
          });
      });
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closePicker();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
