// combobox.js — keep list closed after programmatic selection; user interactions still open it
(function () {
    'use strict';

    function $(sel, ctx) {
        return (ctx || document).querySelector(sel);
    }

    function $All(sel, ctx) {
        return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function debounce(fn, wait) {
        var t;
        return function () {
            var args = arguments;
            var ctx = this;
            clearTimeout(t);
            t = setTimeout(function () {
                fn.apply(ctx, args);
            }, wait);
        };
    }

    var DEBOUNCE_MS = 250;
    var AJAX_BASE =
        '/index.php?option=com_ajax&plugin=combobox&group=fields&action=options&format=json';

    var optionsCache = {}; // fieldId -> rows

    function renderSuggestionsList(items, container, activeIndex) {
        if (!items || !items.length) {
            container.innerHTML = '<div class="cb-empty">No matches</div>';
            return;
        }

        var html = '<ul role="listbox" class="cb-list" tabindex="-1">';
        items.forEach(function (it, i) {
            var id = 'cb-item-' + i;
            var cls = i === activeIndex ? ' cb-item--active' : '';
            html +=
                '<li id="' +
                id +
                '" role="option" data-value="' +
                escapeHtml(it.value) +
                '" class="cb-item' +
                cls +
                '" tabindex="-1">' +
                '<span class="cb-item-label">' +
                escapeHtml(it.value) +
                '</span>' +
                '</li>';
        });
        html += '</ul>';

        container.innerHTML = html;
    }

    function openSuggestions(container, input) {
        container.hidden = false;
        container.setAttribute('aria-hidden', 'false');
        input.setAttribute('aria-expanded', 'true');
    }

    function closeSuggestions(container, input) {
        container.hidden = true;
        container.setAttribute('aria-hidden', 'true');
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
    }

    function bindKeyboard(container, input) {
        var list = container.querySelector('.cb-list');
        if (!list) {
            return {
                setActive: function () {}
            };
        }

        var items = Array.prototype.slice.call(list.querySelectorAll('.cb-item'));
        var active = -1;

        var prev = input._comboboxKeyHandler;
        if (prev && typeof prev === 'function') {
            input.removeEventListener('keydown', prev);
            input._comboboxKeyHandler = null;
        }

        function setActive(index) {
            if (active >= 0 && items[active]) {
                items[active].classList.remove('cb-item--active');
            }

            active = Math.max(-1, Math.min(items.length - 1, index));

            if (active >= 0) {
                items[active].classList.add('cb-item--active');
                input.setAttribute('aria-activedescendant', items[active].id);
                items[active].scrollIntoView({ block: 'nearest' });
            } else {
                input.removeAttribute('aria-activedescendant');
            }
        }

        var keyHandler = function (ev) {
            if (ev.key === 'ArrowDown') {
                ev.preventDefault();
                if (active === -1) {
                    setActive(0);
                } else {
                    setActive(active + 1);
                }
            } else if (ev.key === 'ArrowUp') {
                ev.preventDefault();
                if (active === -1) {
                    setActive(items.length - 1);
                } else {
                    setActive(active - 1);
                }
            } else if (ev.key === 'Enter') {
                if (active >= 0 && items[active]) {
                    ev.preventDefault();
                    var v = items[active].getAttribute('data-value');
                    input.value = v;
                    // close and do NOT re-open because the programmatic input we dispatch is not trusted
                    closeSuggestions(container, input);
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            } else if (ev.key === 'Escape') {
                closeSuggestions(container, input);
            }
        };

        input._comboboxKeyHandler = keyHandler;
        input.addEventListener('keydown', keyHandler);

        if (container._comboboxClickHandler) {
            container.removeEventListener('click', container._comboboxClickHandler);
            container._comboboxClickHandler = null;
        }

        var clickHandler = function (ev) {
            var li = ev.target.closest('.cb-item');
            if (!li || !container.contains(li)) return;
            var v = li.getAttribute('data-value');
            input.value = v;
            closeSuggestions(container, input);
            input.focus();
            input.dispatchEvent(new Event('input', { bubbles: true })); // programmatic — isTrusted=false
        };
        container.addEventListener('click', clickHandler);
        container._comboboxClickHandler = clickHandler;

        if (container._comboboxMouseOverHandler) {
            container.removeEventListener('mouseover', container._comboboxMouseOverHandler);
            container._comboboxMouseOverHandler = null;
        }
        var mouseOverHandler = function (ev) {
            var li = ev.target.closest('.cb-item');
            if (!li || !container.contains(li)) return;
            var idx = items.indexOf(li);
            if (idx >= 0) {
                items.forEach(function (it) { it.classList.remove('cb-item--hover'); });
                li.classList.add('cb-item--hover');
            }
        };
        container.addEventListener('mouseover', mouseOverHandler);
        container._comboboxMouseOverHandler = mouseOverHandler;

        return {
            setActive: setActive
        };
    }

    function fetchOptions(fieldId, callback) {
        if (!fieldId) {
            callback([]);
            return;
        }

        if (optionsCache[fieldId]) {
            callback(optionsCache[fieldId]);
            return;
        }

        var url =
            AJAX_BASE +
            '&field_id=' +
            encodeURIComponent(fieldId) +
            '&limit=200';

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) {
                return r.json();
            })
            .then(function (json) {
                var rows = json && json.results ? json.results : [];
                optionsCache[fieldId] = rows;
                callback(rows);
            })
            .catch(function () {
                optionsCache[fieldId] = [];
                callback([]);
            });
    }

    function ensureToggleButton(container, input) {
        var existing = container.querySelector('.combobox-toggle');
        if (existing) return existing;

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'combobox-toggle';
        btn.setAttribute('aria-label', 'Open options');
        btn.innerHTML = '<span class="combobox-toggle-icon" aria-hidden="true">▾</span>';
        input.parentNode.insertBefore(btn, input.nextSibling);
        return btn;
    }

    function initCombobox(container) {
        var input = $('.combobox-input', container);
        var sugg = $('.combobox-suggestions', container);

        if (!input || !sugg) return;

        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');

        sugg.hidden = true;
        sugg.setAttribute('aria-hidden', 'true');

        var toggleBtn = ensureToggleButton(container, input);

        var fieldId =
            container.getAttribute('data-field-id') ||
            input.getAttribute('data-field-id') ||
            '';

        var currentList = [];
        var keyboardCtrl = null;

        fetchOptions(fieldId, function (rows) {
            currentList = rows || [];
            renderSuggestionsList(currentList, sugg, -1);
        });

        var onType = debounce(function () {
            var q = input.value.trim();

            var filtered = currentList.filter(function (it) {
                return it.value.toLowerCase().indexOf(q.toLowerCase()) !== -1;
            });

            renderSuggestionsList(filtered, sugg, -1);
            openSuggestions(sugg, input);
            keyboardCtrl = bindKeyboard(sugg, input);
        }, DEBOUNCE_MS);

        // Only respond to user-generated input events (event.isTrusted === true)
        input.addEventListener('input', function (ev) {
            if (ev && ev.isTrusted === false) {
                // Programmatic change — do not trigger open/filter
                return;
            }

            if (!optionsCache[fieldId]) {
                fetchOptions(fieldId, function (rows) {
                    currentList = rows;
                    onType();
                });
                return;
            }
            onType();
        });

        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'ArrowDown') {
                ev.preventDefault();
                if (sugg.hidden) {
                    renderSuggestionsList(currentList, sugg, -1);
                    openSuggestions(sugg, input);
                    keyboardCtrl = bindKeyboard(sugg, input);
                    if (keyboardCtrl && keyboardCtrl.setActive) {
                        keyboardCtrl.setActive(0);
                    }
                }
            }
        });

        toggleBtn.addEventListener('click', function (ev) {
            ev.preventDefault();
            if (sugg.hidden) {
                renderSuggestionsList(currentList, sugg, -1);
                openSuggestions(sugg, input);
                keyboardCtrl = bindKeyboard(sugg, input);
            } else {
                closeSuggestions(sugg, input);
            }
        });

        document.addEventListener('click', function (ev) {
            if (!container.contains(ev.target)) {
                closeSuggestions(sugg, input);
            }
        });

        input.addEventListener('blur', function () {
            setTimeout(function () {
                if (document.activeElement && sugg.contains(document.activeElement)) return;
                closeSuggestions(sugg, input);
            }, 150);
        });
    }

    function initAll() {
        $All('.combobox-field').forEach(initCombobox);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();