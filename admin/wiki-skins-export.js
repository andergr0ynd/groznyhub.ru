/**

 * Экспорт каталога wiki.radmir.online в два файла:

 * · skins.json — скины (Страница_1…)

 * · cases.json — кейсы (страница «Кейсы», только названия)

 *

 * Запускать в консоли (F12) НА САЙТЕ wiki.radmir.online после DDoS.

 */

(async function exportRadmirWikiSkins() {

  var SOURCES = [

    { type: 'paged', prefix: 'Скины: Страница_', max: 250, category: 'skins', label: 'Скины', group: 'skins' },

    { type: 'page', title: 'Автосалон_эконом_класса', category: 'economy', label: 'Автосалон · эконом', group: 'cars' },

    { type: 'page', title: 'Автосалон_среднего_класса', category: 'middle', label: 'Автосалон · средний', group: 'cars' },

    { type: 'page', title: 'Автосалон_премиум_класса', category: 'premium', label: 'Автосалон · премиум', group: 'cars' },

    { type: 'page', title: 'Мото-вело_салон', category: 'moto', label: 'Мото-вело салон', group: 'cars' },

    { type: 'page', title: 'Салон_грузовых_автомобилей', category: 'truck', label: 'Грузовые авто', group: 'cars' },

    { type: 'cases', title: 'Кейсы', category: 'cases', label: 'Кейсы', group: 'cases' },

  ];



  var buckets = { skins: [], cars: [], cases: [] };

  var seen = {};



  function decodeHtml(s) {

    var el = document.createElement('textarea');

    el.innerHTML = s;

    return el.value;

  }



  function cleanCarName(raw) {
    if (!raw) return '';
    raw = String(raw);

    var boldMatch = /<b[^>]*>([\s\S]*?)<\/b>/i.exec(raw);
    if (boldMatch && boldMatch[1]) {
      raw = boldMatch[1];
    }

    raw = decodeHtml(raw).replace(/<[^>]+>/g, ' ').trim();

    var priceMatch = /^([\s\S]*?)\s*Цена\s*:/i.exec(raw);
    if (priceMatch) {
      raw = priceMatch[1];
    } else if (/\|\s*Привод\s*:/i.test(raw)) {
      raw = raw.split('|')[0];
    }

    raw = raw.replace(/^(?:File|Файл):/i, '');
    raw = raw.replace(/\.[^.]+$/i, '');
    raw = raw.replace(/_/g, ' ').replace(/\s+/g, ' ').trim();
    return raw;
  }

  function cleanSkinName(raw) {
    if (!raw) return '';
    raw = decodeHtml(String(raw)).replace(/<[^>]+>/g, ' ').trim();
    raw = raw.replace(/^(?:File|Файл):/i, '');
    raw = raw.replace(/\.[^.]+$/i, '');
    raw = raw.replace(/_/g, ' ').replace(/\s+/g, ' ').trim();
    return raw;
  }

  function cleanDisplayName(raw, group) {
    if (group === 'cars' || /<b[^>]*>/i.test(raw) || /\bЦена\s*:/i.test(raw)) {
      return cleanCarName(raw);
    }
    return cleanSkinName(raw);
  }

  function extractSkinName(html, pos, tag, file, group) {
    var clean = function (raw) { return cleanDisplayName(raw, group); };
    var chunk = html.substring(Math.max(0, pos - 500), pos + 1500);
    var galleryMatch = null;

    if (group === 'cars') {
      galleryMatch = /<div[^>]*class="[^"]*gallerytext[^"]*"[^>]*>([\s\S]*?)<\/div>/i.exec(chunk);
      if (galleryMatch && galleryMatch[1]) {
        var galleryName = clean(galleryMatch[1]);
        if (galleryName) return galleryName;
      }

      var fontMatch = /<font[^>]*>([\s\S]*?)<\/font>/i.exec(chunk);
      if (fontMatch && fontMatch[1]) {
        var fontName = clean(fontMatch[1]);
        if (fontName) return fontName;
      }
    }

    var altMatch = /alt="([^"]*)"/i.exec(tag);
    var titleMatch = /title="([^"]*)"/i.exec(tag);
    if (altMatch && altMatch[1]) {
      var altName = clean(altMatch[1]);
      if (altName && altName.toLowerCase() !== file.replace(/\.[^.]+$/i, '').toLowerCase()) {
        return altName;
      }
    }
    if (titleMatch && titleMatch[1]) {
      var titleName = clean(titleMatch[1]);
      if (titleName) return titleName;
    }

    var captionMatch = /<div[^>]*class="[^"]*thumbcaption[^"]*"[^>]*>([\s\S]*?)<\/div>/i.exec(chunk);
    if (captionMatch && captionMatch[1]) {
      var captionName = clean(captionMatch[1]);
      if (captionName) return captionName;
    }

    if (!galleryMatch) {
      galleryMatch = /<div[^>]*class="[^"]*gallerytext[^"]*"[^>]*>([\s\S]*?)<\/div>/i.exec(chunk);
    }
    if (galleryMatch && galleryMatch[1]) {
      var galleryName2 = clean(galleryMatch[1]);
      if (galleryName2) return galleryName2;
    }

    var linkMatch = /<a[^>]+title="([^"]+)"[^>]*>[\s\S]{0,120}?<img[^>]+src="/i.exec(chunk);
    if (linkMatch && linkMatch[1]) {
      var linkName = clean(linkMatch[1]);
      if (linkName) return linkName;
    }

    if (altMatch && altMatch[1]) {
      return clean(altMatch[1]);
    }

    return clean(file);
  }



  function cleanCaseName(raw) {
    if (!raw) return '';
    raw = String(raw);
    var boldMatch = /<b[^>]*>([\s\S]*?)<\/b>/i.exec(raw);
    if (boldMatch && boldMatch[1]) {
      raw = boldMatch[1];
    }
    raw = decodeHtml(raw).replace(/<[^>]+>/g, ' ').trim();
    raw = raw.replace(/^\d+\.\s*/, '');
    raw = raw.replace(/\s+/g, ' ').trim();
    return raw;
  }



  function caseStoreFromName(name) {
    var slug = name.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9а-яё«»"-]/gi, '');
    return 'case:' + (slug || ('item-' + Math.abs(name.split('').reduce(function (a, c) { return a + c.charCodeAt(0); }, 0))));
  }



  function parseCasesHtml(html, meta) {
    var added = 0;
    var re = /<p[^>]*>\s*<b[^>]*>([\s\S]*?)<\/b>\s*<\/p>/gi;
    var match;
    while ((match = re.exec(html)) !== null) {
      var rawLine = decodeHtml(match[1]).replace(/<[^>]+>/g, ' ').trim();
      if (!/^\d+\.\s/.test(rawLine)) continue;
      var name = cleanCaseName(match[1]);
      if (!name) continue;
      var key = name.toLowerCase();
      if (seen['case:' + key]) continue;
      seen['case:' + key] = true;
      buckets.cases.push({
        file: '',
        store: caseStoreFromName(name),
        name: name,
        category: meta.category || 'cases',
        category_label: meta.label || 'Кейсы',
        group: 'cases',
      });
      added++;
    }
    return added;
  }



  function parseHtml(html, meta) {

    var re = /<img[^>]+src="(\/images\/thumb\/[^"]+)"[^>]*>/gi;

    var match;

    var added = 0;

    var group = meta.group || 'skins';

    while ((match = re.exec(html)) !== null) {

      var thumb = match[1];

      var tag = match[0];

      var pos = match.index;

      var fileMatch = /\/images\/thumb\/([0-9a-f]\/[0-9a-f]{2}\/[^/]+)\//i.exec(thumb);

      var file = fileMatch ? fileMatch[1].split('/').pop() : '';

      if (!file || seen[file]) continue;

      seen[file] = true;

      buckets[group].push({

        file: file,

        store: 'wiki:' + file,

        thumb: location.origin + thumb,

        name: extractSkinName(html, pos, tag, file, group),

        category: meta.category,

        category_label: meta.label,

        group: group,

      });

      added++;

    }

    return added;

  }



  async function fetchWikiHtml(title) {

    var url = '/api.php?action=parse&page=' + encodeURIComponent(title) + '&format=json&prop=text';

    var response = await fetch(url);

    if (!response.ok) {

      throw new Error('HTTP ' + response.status);

    }

    var data = await response.json();

    return (data.parse && data.parse.text && data.parse.text['*']) || '';

  }



  async function fetchB64(url) {

    try {

      var response = await fetch(url);

      if (!response.ok) return null;

      var blob = await response.blob();

      return await new Promise(function (resolve) {

        var reader = new FileReader();

        reader.onload = function () {

          var data = String(reader.result || '');

          resolve(data.indexOf(',') !== -1 ? data.split(',')[1] : null);

        };

        reader.onerror = function () { resolve(null); };

        reader.readAsDataURL(blob);

      });

    } catch (e) {

      return null;

    }

  }



  async function runPool(items, limit, worker) {

    var index = 0;

    async function loop() {

      while (index < items.length) {

        var current = index++;

        await worker(items[current], current);

      }

    }

    var runners = [];

    for (var i = 0; i < limit; i++) {

      runners.push(loop());

    }

    await Promise.all(runners);

  }



  function downloadJson(filename, data) {

    var blob = new Blob([JSON.stringify(data)], { type: 'application/json' });

    var a = document.createElement('a');

    a.href = URL.createObjectURL(blob);

    a.download = filename;

    a.click();

    URL.revokeObjectURL(a.href);

  }



  for (var s = 0; s < SOURCES.length; s++) {

    var source = SOURCES[s];

    console.log('▶', source.label, '(' + source.group + ')');



    if (source.type === 'paged') {

      for (var page = 1; page <= source.max; page++) {

        var pageTitle = source.prefix + page;

        try {

          var html = await fetchWikiHtml(pageTitle);

          if (!html || html.indexOf('/images/thumb/') === -1) {

            console.log('  ' + source.label + ': конец на странице', page);

            break;

          }

          var n = parseHtml(html, source);

          console.log('  стр.', page, '· +' + n, '· всего', buckets.skins.length + buckets.cars.length);

        } catch (e) {

          console.warn('  стр.', page, e.message || e);

          break;

        }

      }

      continue;

    }



    if (source.type === 'cases') {

      try {

        var casesHtml = await fetchWikiHtml(source.title);

        if (!casesHtml) {

          console.warn('  пустая страница:', source.title);

          continue;

        }

        var addedCases = parseCasesHtml(casesHtml, source);

        console.log('  +' + addedCases + ' кейсов · всего', buckets.cases.length);

      } catch (casesErr) {

        console.warn('  ошибка', source.title, casesErr.message || casesErr);

      }

      continue;

    }



    try {

      var salonHtml = await fetchWikiHtml(source.title);

      if (!salonHtml) {

        console.warn('  пустая страница:', source.title);

        continue;

      }

      var addedSalon = parseHtml(salonHtml, source);

      console.log('  +' + addedSalon, '· всего', buckets.skins.length + buckets.cars.length);

    } catch (err) {

      console.warn('  ошибка', source.title, err.message || err);

    }

  }



  var allItems = buckets.skins.concat(buckets.cars);

  console.log('Скачиваю превью для кэша на сайте…');

  var downloaded = 0;

  await runPool(allItems, 8, async function (skin, idx) {

    skin.b64 = await fetchB64(skin.thumb);

    if (skin.b64) downloaded++;

    if (idx % 25 === 0) {

      console.log('Превью', idx + 1, '/', allItems.length, '· готово', downloaded);

    }

  });



  var updatedAt = new Date().toISOString();

  var skinsPayload = {

    bundle: 'skins',

    label: 'Скины',

    updated_at: updatedAt,

    skins: buckets.skins,

  };

  var carsPayload = {

    bundle: 'cars',

    label: 'Машины',

    updated_at: updatedAt,

    skins: buckets.cars,

  };



  downloadJson('skins.json', skinsPayload);

  downloadJson('cars.json', carsPayload);

  var casesPayload = {

    bundle: 'cases',

    label: 'Кейсы',

    updated_at: updatedAt,

    skins: buckets.cases,

  };

  downloadJson('cases.json', casesPayload);



  alert(

    'Готово!\n'

    + 'Скины: ' + buckets.skins.length + '\n'

    + 'Машины: ' + buckets.cars.length + '\n'

    + 'Кейсы: ' + buckets.cases.length + '\n'

    + 'Превью: ' + downloaded + '\n\n'

    + 'Скачаны skins.json, cars.json и cases.json — загрузите в папку Websait/json-wiki/.\n'

    + 'В админке: «Подгрузить скины» / «машины» / «кейсы».'

  );



  return { skins: skinsPayload, cars: carsPayload, cases: casesPayload };

})();

