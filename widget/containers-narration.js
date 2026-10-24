/**
 * Озвучка дуэли контейнеров для OBS: MP3 через <audio> (микшер OBS) + fallback Web Speech.
 */
(function (global) {
    'use strict';

    var russianVoice = null;
    var voicesReady = null;
    var currentAudio = null;

    function supportsSpeech() {
        return typeof global.speechSynthesis !== 'undefined'
            && typeof global.SpeechSynthesisUtterance !== 'undefined';
    }

    function supportsObsAudio() {
        return typeof global.Audio !== 'undefined';
    }

    function loadVoices() {
        if (!supportsSpeech()) {
            return Promise.resolve([]);
        }
        if (voicesReady) {
            return voicesReady;
        }
        voicesReady = new Promise(function (resolve) {
            function pick() {
                var list = global.speechSynthesis.getVoices() || [];
                russianVoice = list.find(function (v) { return /^ru/i.test(v.lang); })
                    || list.find(function (v) { return /russian/i.test(v.name); })
                    || list[0]
                    || null;
                resolve(list);
            }
            pick();
            if (!global.speechSynthesis.getVoices().length) {
                global.speechSynthesis.onvoiceschanged = pick;
                setTimeout(pick, 400);
            }
        });
        return voicesReady;
    }

    function cancel() {
        if (currentAudio) {
            try {
                currentAudio.pause();
                currentAudio.src = '';
            } catch (e) { /* ignore */ }
            currentAudio = null;
        }
        if (supportsSpeech()) {
            global.speechSynthesis.cancel();
        }
    }

    function speakViaObsAudio(speechApiUrl, text, volume) {
        if (!speechApiUrl || !supportsObsAudio()) {
            return Promise.resolve(false);
        }
        return new Promise(function (resolve) {
            cancel();
            var audio = new Audio(speechApiUrl + encodeURIComponent(text));
            audio.preload = 'auto';
            if (typeof volume === 'number' && !isNaN(volume)) {
                audio.volume = Math.max(0, Math.min(1, volume));
            }
            currentAudio = audio;
            var done = false;
            function finish(ok) {
                if (done) return;
                done = true;
                if (currentAudio === audio) {
                    currentAudio = null;
                }
                resolve(ok);
            }
            audio.onended = function () { finish(true); };
            audio.onerror = function () { finish(false); };
            var playPromise = audio.play();
            if (playPromise && typeof playPromise.then === 'function') {
                playPromise.catch(function () { finish(false); });
            }
            setTimeout(function () { finish(false); }, Math.max(18000, text.length * 160));
        });
    }

    function speakViaWebSpeech(text) {
        if (!text || !supportsSpeech()) {
            return Promise.resolve(false);
        }
        return loadVoices().then(function () {
            return new Promise(function (resolve) {
                var utter = new global.SpeechSynthesisUtterance(text);
                utter.lang = 'ru-RU';
                utter.rate = 0.92;
                utter.pitch = 1;
                if (russianVoice) {
                    utter.voice = russianVoice;
                }
                var done = false;
                function finish(ok) {
                    if (done) return;
                    done = true;
                    resolve(ok);
                }
                utter.onend = function () { finish(true); };
                utter.onerror = function () { finish(false); };
                global.speechSynthesis.speak(utter);
                setTimeout(function () { finish(false); }, Math.max(12000, text.length * 120));
            });
        });
    }

    function speak(text, options) {
        options = options || {};
        if (!text) {
            return Promise.resolve(false);
        }

        if (options.useObsAudio !== false && options.speechApiUrl) {
            return speakViaObsAudio(options.speechApiUrl, text, options.voiceVolume).then(function (ok) {
                if (ok) return true;
                return speakViaWebSpeech(text);
            });
        }

        return speakViaWebSpeech(text);
    }

    function stripAt(name) {
        return String(name || '').replace(/^@+/, '').trim();
    }

    function coinFallback(facts) {
        var side = facts.coin_side || 'орёл';
        var who = stripAt(facts.first_player) || 'зритель';
        if (facts.is_pvp) {
            return 'Выпало ' + side + '. Первым ходит ' + who + '.';
        }
        if (facts.guessed) {
            return 'Выпало ' + side + '. ' + who + ' угадал и ходит первым.';
        }
        return 'Выпало ' + side + '. Не угадал — первым ходит бот.';
    }

    function victoryFallback(facts) {
        var winner = facts.winner || 'draw';
        var player = stripAt(facts.player_name) || 'зритель';
        var opponent = stripAt(facts.opponent_name) || 'бот';
        var prize = facts.prize_text || '';

        if (winner === 'player') {
            return player + ' победил' + (prize ? '! Приз: ' + prize : '!');
        }
        if (winner === 'bot') {
            if (facts.is_pvp) {
                return opponent + ' победил' + (prize ? '! Приз: ' + prize : '!');
            }
            return 'Победил бот. Ставка сгорела.';
        }
        return facts.is_pvp ? 'Ничья! Обе ставки возвращены.' : 'Ничья! Ставка возвращена.';
    }

    function fetchLine(apiUrl, type, facts, fallback) {
        if (!apiUrl) {
            return Promise.resolve(fallback);
        }
        return fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: type, facts: facts }),
        }).then(function (res) {
            if (!res.ok) return fallback;
            return res.json();
        }).then(function (data) {
            if (data && data.text) return data.text;
            return fallback;
        }).catch(function () {
            return fallback;
        });
    }

    function create(options) {
        options = options || {};
        var enabled = !!options.enabled;
        var useDeepSeek = options.useDeepSeek !== false;
        var apiUrl = options.apiUrl || '';
        var speechApiUrl = options.speechApiUrl || '';
        var useObsAudio = options.useObsAudio !== false && !/[?&]voice=system/.test(global.location.search);
        var voiceVolume = typeof options.voiceVolume === 'number' ? options.voiceVolume : 1;

        function announce(type, facts, fallbackFn) {
            if (!enabled) {
                return Promise.resolve(false);
            }
            var fallback = fallbackFn(facts);
            var linePromise = useDeepSeek
                ? fetchLine(apiUrl, type, facts, fallback)
                : Promise.resolve(fallback);
            return linePromise.then(function (text) {
                if (options.debug && options.debugEl) {
                    options.debugEl.textContent = '🔊 ' + text;
                }
                return speak(text, {
                    speechApiUrl: speechApiUrl,
                    useObsAudio: useObsAudio,
                    voiceVolume: voiceVolume,
                });
            });
        }

        return {
            enabled: enabled,
            cancel: cancel,
            speak: function (text) {
                return speak(text, {
                    speechApiUrl: speechApiUrl,
                    useObsAudio: useObsAudio,
                    voiceVolume: voiceVolume,
                });
            },
            announceCoin: function (facts) {
                return announce('coin', facts, coinFallback);
            },
            announceVictory: function (facts) {
                return announce('victory', facts, victoryFallback);
            },
        };
    }

    global.ContainersNarration = {
        create: create,
        supportsSpeech: supportsSpeech,
        supportsObsAudio: supportsObsAudio,
        loadVoices: loadVoices,
    };

    loadVoices();
})(window);
