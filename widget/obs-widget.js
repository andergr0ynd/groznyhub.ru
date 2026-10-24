/**
 * OBS Browser Source: скрыть всё до явного показа, восстановление после разморозки вкладки.
 */
(function (global) {
    'use strict';

    var SELECTORS = '.widget,.gh-widget,#widget,#megaphoneWidget,.obs-standby,.sky,.fly-toast,#flyToast,#confetti,.confetti';
    var ACTIVE_SELECTOR = '.widget.visible,.widget.showing,.gh-widget.visible,.gh-widget.showing,.sky.showing,#megaphoneWidget.showing';

    function nodeShouldStayVisible(el) {
        return el.classList.contains('visible')
            || el.classList.contains('showing')
            || (el.classList.contains('obs-standby') && !el.classList.contains('hidden'));
    }

    function restoreActiveWidgets() {
        var active = document.querySelectorAll(ACTIVE_SELECTOR);
        if (!active.length) {
            return;
        }
        allowShow();
        for (var i = 0; i < active.length; i++) {
            var el = active[i];
            el.style.removeProperty('display');
            el.style.removeProperty('visibility');
            el.style.removeProperty('opacity');
        }
    }

    function forceHideAll() {
        if (!document.querySelector(ACTIVE_SELECTOR)) {
            document.body.classList.remove('obs-allow');
        }
        var nodes = document.querySelectorAll(SELECTORS);
        for (var i = 0; i < nodes.length; i++) {
            var el = nodes[i];
            if (nodeShouldStayVisible(el)) {
                continue;
            }
            el.style.setProperty('display', 'none', 'important');
            el.style.setProperty('visibility', 'hidden', 'important');
            el.style.setProperty('opacity', '0', 'important');
        }
    }

    function allowShow() {
        document.body.classList.add('obs-allow');
    }

    function markShown(el) {
        if (!el) return;
        allowShow();
        el.style.removeProperty('display');
        el.style.removeProperty('visibility');
        el.style.removeProperty('opacity');
    }

    function markHidden(el) {
        if (!el) return;
        el.classList.remove('visible', 'showing');
        el.style.setProperty('display', 'none', 'important');
        el.style.setProperty('visibility', 'hidden', 'important');
        el.style.setProperty('opacity', '0', 'important');
        if (!document.querySelector(ACTIVE_SELECTOR)) {
            document.body.classList.remove('obs-allow');
            forceHideAll();
        }
    }

    function onResume() {
        restoreActiveWidgets();
        document.body.dispatchEvent(new CustomEvent('obs-widget-resume'));
    }

    global.obsWidgetBoot = {
        forceHideAll: forceHideAll,
        allowShow: allowShow,
        markShown: markShown,
        markHidden: markHidden,
        restoreActiveWidgets: restoreActiveWidgets,
    };

    if (document.body) {
        forceHideAll();
    } else {
        document.addEventListener('DOMContentLoaded', forceHideAll);
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            forceHideAll();
            return;
        }
        onResume();
    });

    global.addEventListener('pageshow', function () {
        forceHideAll();
        onResume();
    });
})(window);
