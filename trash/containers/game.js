(function () {
    const IMG_CLOSE = '../icons/conteinerclose.png';
    const IMG_OPEN = '../icons/conteineropen.png';
    const OPEN_MS = 2400;
    const BETWEEN_MS = 700;

    const els = {
        betPanel: document.getElementById('betPanel'),
        gameArena: document.getElementById('gameArena'),
        coinPanel: document.getElementById('coinPanel'),
        coinResult: document.getElementById('coinResult'),
        coinButtons: document.querySelectorAll('.coin-btn'),
        playerStrip: document.getElementById('playerStrip'),
        botStrip: document.getElementById('botStrip'),
        playerTotal: document.getElementById('playerTotal'),
        botTotal: document.getElementById('botTotal'),
        turnBanner: document.getElementById('turnBanner'),
        salesWrap: document.getElementById('salesTableWrap'),
        salesBody: document.getElementById('salesTableBody'),
        resultBanner: document.getElementById('resultBannerGame'),
        betPill: document.getElementById('arenaBetPill'),
        balanceValue: document.getElementById('balanceValue'),
        playMessage: document.getElementById('playMessage'),
        playBtn: document.getElementById('playBtn'),
    };

    let duel = null;
    let busy = false;
    let rowNum = 0;

    function money(n) {
        return Number(n).toLocaleString('ru-RU') + ' ₽';
    }

    function formatBalance(seconds) {
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        return h + 'ч ' + m + 'м ' + s + 'с';
    }

    function formatBetMin(sec) {
        return Math.round(sec / 60) + ' мин';
    }

    function sleep(ms) {
        return new Promise(function (r) { setTimeout(r, ms); });
    }

    function showMessage(text, ok) {
        if (!els.playMessage) return;
        els.playMessage.textContent = text;
        els.playMessage.className = 'play-message show ' + (ok ? 'ok' : 'err');
    }

    function buildStrip(parent, prizes, side) {
        parent.innerHTML = '';
        for (let i = 0; i < 5; i++) {
            const car = prizes[i];
            const iconHtml = car.icon
                ? '<img class="c-car-icon" src="../icons/' + car.icon + '" alt="">'
                : '<span class="c-car-model">🚗</span>';
            const wearHtml = car.wear > 0 ? '<span class="c-car-wear">Износ −' + car.wear + '%</span>' : '';
            const govtHtml = '<span class="c-car-govt">Госка −' + (car.govt_cut || 20) + '%</span>';
            const slot = document.createElement('div');
            slot.className = 'c-slot';
            slot.dataset.side = side;
            slot.dataset.index = String(i);
            slot.innerHTML =
                '<div class="c-box">' +
                    '<div class="c-closed"><img src="' + IMG_CLOSE + '" alt=""></div>' +
                    '<div class="c-open-wrap"><img src="' + IMG_OPEN + '" alt=""></div>' +
                    '<div class="c-car">' +
                        iconHtml +
                        '<span class="c-car-title">' + car.name + '</span>' +
                        govtHtml +
                        wearHtml +
                        '<span class="c-car-price">' + money(car.value) + '</span>' +
                    '</div>' +
                '</div>';
            slot._car = car;
            parent.appendChild(slot);
        }
    }

    function buildTurnOrder(firstTurn) {
        const order = [];
        for (let i = 0; i < 5; i++) {
            if (firstTurn === 'player') {
                order.push({ side: 'player', index: i });
                order.push({ side: 'bot', index: i });
            } else {
                order.push({ side: 'bot', index: i });
                order.push({ side: 'player', index: i });
            }
        }
        return order;
    }

    function addSalesRow(side, car, runningPlayer, runningBot) {
        rowNum += 1;
        const tr = document.createElement('tr');
        tr.className = side === 'player' ? 'player-row' : 'bot-row';
        const who = side === 'player' ? '👤 Ты' : '🤖 Бот';
        tr.innerHTML =
            '<td>' + rowNum + '</td>' +
            '<td>' + who + '</td>' +
            '<td>' + car.name + ' <span class="wear-tag">госка −' + (car.govt_cut || 20) + '%</span>'
                + (car.wear > 0 ? ' <span class="wear-tag">износ −' + car.wear + '%</span>' : '') + '</td>' +
            '<td>' + money(car.base_value || car.value) + '</td>' +
            '<td class="price">' + money(car.value) + '</td>' +
            '<td class="price">' + money(runningPlayer) + ' / ' + money(runningBot) + '</td>';
        els.salesBody.appendChild(tr);
    }

    async function openSlot(side, index, label) {
        const parent = side === 'player' ? els.playerStrip : els.botStrip;
        const slot = parent.children[index];
        if (!slot) return;

        document.querySelectorAll('.c-slot').forEach(function (s) { s.classList.remove('active'); });
        slot.classList.add('active');
        els.turnBanner.innerHTML = 'Сейчас открывает <strong>' + label + '</strong> · контейнер #' + (index + 1);

        await sleep(350);
        slot.classList.add('opening');
        await sleep(500);
        slot.classList.remove('opening');
        slot.classList.add('opened');
        slot.classList.remove('active');

        await sleep(OPEN_MS);
    }

    async function runGame(data) {
        duel = data;
        busy = true;

        els.gameArena.classList.add('active');
        els.salesWrap.classList.add('active');
        els.salesBody.innerHTML = '';
        rowNum = 0;
        els.playerTotal.textContent = money(0);
        els.botTotal.textContent = money(0);
        els.resultBanner.className = 'result-banner-game';
        els.resultBanner.textContent = '';
        if (els.betPill) {
            els.betPill.textContent = 'Ставка: ' + formatBetMin(data.bet_seconds);
        }

        buildStrip(els.playerStrip, data.player_prizes, 'player');
        buildStrip(els.botStrip, data.bot_prizes, 'bot');

        const order = buildTurnOrder(data.first_turn);
        let pSum = 0;
        let bSum = 0;

        await sleep(800);

        for (let t = 0; t < order.length; t++) {
            const turn = order[t];
            const label = turn.side === 'player' ? 'Ты' : 'БОТ';
            await openSlot(turn.side, turn.index, label);

            const parent = turn.side === 'player' ? els.playerStrip : els.botStrip;
            const car = parent.children[turn.index]._car;
            if (turn.side === 'player') {
                pSum += car.value;
                els.playerTotal.textContent = money(pSum);
            } else {
                bSum += car.value;
                els.botTotal.textContent = money(bSum);
            }
            addSalesRow(turn.side, car, pSum, bSum);

            if (t < order.length - 1) await sleep(BETWEEN_MS);
        }

        els.turnBanner.textContent = 'Все контейнеры открыты';

        let complete;
        try {
            const res = await fetch('../api/containers_complete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ duel_id: data.id }),
            });
            complete = await res.json();
        } catch (e) {
            complete = { success: false };
        }

        const winner = complete.winner || (pSum > bSum ? 'player' : (bSum > pSum ? 'bot' : 'draw'));
        const mult = data.win_multiplier || 2;

        if (winner === 'player') {
            els.resultBanner.className = 'result-banner-game show win';
            els.resultBanner.textContent = '🏆 Победа! ' + money(pSum) + ' vs ' + money(bSum) + ' · +' + formatBetMin(data.bet_seconds * mult) + ' рефки';
        } else if (winner === 'bot') {
            els.resultBanner.className = 'result-banner-game show lose';
            els.resultBanner.textContent = '🤖 Бот победил: ' + money(bSum) + ' vs ' + money(pSum) + ' · ставка сгорела';
        } else {
            els.resultBanner.className = 'result-banner-game show draw';
            els.resultBanner.textContent = '🤝 Ничья ' + money(pSum) + ' · ставка возвращена';
        }

        busy = false;
    }

    async function flipCoin(pick) {
        if (!duel || busy) return;
        busy = true;
        els.coinButtons.forEach(function (b) { b.disabled = true; });

        els.coinResult.innerHTML = '<span class="coin-flip-anim">🪙</span>';

        try {
            const res = await fetch('../api/containers_coin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ duel_id: duel.id, pick: pick }),
            });
            const data = await res.json();

            await sleep(900);

            if (!data.success) {
                els.coinResult.textContent = data.error || 'Ошибка';
                els.coinButtons.forEach(function (b) { b.disabled = false; });
                busy = false;
                return;
            }

            const pickLabel = pick === 'heads' ? 'Орёл' : 'Решка';
            const resultLabel = data.coin_result === 'heads' ? 'Орёл' : 'Решка';
            const firstLabel = data.first_turn === 'player' ? 'Ты ходишь первым!' : 'Бот ходит первым!';

            els.coinResult.textContent = 'Твой выбор: ' + pickLabel + ' · Выпало: ' + resultLabel + ' · ' + firstLabel;

            await sleep(1500);
            els.coinPanel.classList.remove('active');
            await runGame(data);
        } catch (e) {
            els.coinResult.textContent = 'Сеть недоступна';
            els.coinButtons.forEach(function (b) { b.disabled = false; });
            busy = false;
        }
    }

    async function startBet(minutes) {
        if (busy) return;
        busy = true;
        if (els.playBtn) els.playBtn.disabled = true;
        if (els.playMessage) els.playMessage.className = 'play-message';

        try {
            const res = await fetch('../api/containers_bet.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ minutes: minutes }),
            });
            const data = await res.json();

            if (!data.success) {
                showMessage(data.error || 'Ошибка', false);
                busy = false;
                if (els.playBtn) els.playBtn.disabled = false;
                return;
            }

            duel = data;
            if (typeof data.balance_after === 'number' && els.balanceValue) {
                els.balanceValue.textContent = formatBalance(data.balance_after);
            }

            if (els.betPanel) els.betPanel.classList.add('hidden');
            els.coinPanel.classList.add('active');
            els.coinResult.textContent = '';
            els.coinButtons.forEach(function (b) {
                b.disabled = false;
                b.classList.remove('selected');
            });

            showMessage('Ставка принята! Выбери орёл или решку.', true);
            busy = false;
            if (els.playBtn) els.playBtn.disabled = false;
        } catch (e) {
            showMessage('Сеть недоступна', false);
            busy = false;
            if (els.playBtn) els.playBtn.disabled = false;
        }
    }

    window.ContainerGame = {
        startBet: startBet,
        flipCoin: flipCoin,
    };
})();
