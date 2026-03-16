// admin-inline.js — load manager JSON into plugin options when a field is selected
(function () {
    'use strict';

    function qs(sel, ctx) { return (ctx || document).querySelector(sel); }
    function qsa(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

    function makeMessageNode(text) {
        var el = document.createElement('div');
        el.className = 'combobox-admin-message';
        el.textContent = text;
        return el;
    }

    function setPlaceholder(container, text) {
        var placeholder = container.querySelector('[data-placeholder]');
        var panel = container.querySelector('[data-panel]');
        if (placeholder) placeholder.style.display = '';
        if (panel) panel.innerHTML = '';
    }

    function showLoading(container) {
        var panel = container.querySelector('[data-panel]');
        if (!panel) return;
        panel.innerHTML = '<div class="combobox-admin-loading">Loading…</div>';
        var placeholder = container.querySelector('[data-placeholder]');
        if (placeholder) placeholder.style.display = 'none';
    }

    function showError(container, msg) {
        var panel = container.querySelector('[data-panel]');
        if (!panel) return;
        panel.innerHTML = '<div class="combobox-admin-error">' + msg + '</div>';
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /**
     * showConfirm(message) -> Promise<boolean>
     * Tries to use Joomla modal if available, otherwise shows a lightweight custom modal.
     */
    function showConfirm(message) {
        return new Promise(function (resolve) {
            // 1) Try Joomla modal APIs (Joomla 4+ or other helpers)
            try {
                if (typeof window.Joomla !== 'undefined') {
                    // Joomla 4+: if Modal is available via Joomla.Modal (some templates expose different APIs)
                    if (window.Joomla.Modal && typeof window.Joomla.Modal.confirm === 'function') {
                        // some implementations accept (message, options). We try generic confirm
                        window.Joomla.Modal.confirm(message, function (result) {
                            resolve(!!result);
                        });
                        return;
                    }

                    // Joomla may expose a modal factory (try SqueezeBox for older cores)
                    if (typeof window.SqueezeBox !== 'undefined' && typeof window.SqueezeBox.confirm === 'function') {
                        // SqueezeBox.confirm(message, callback)
                        window.SqueezeBox.confirm(message, function (ok) {
                            resolve(!!ok);
                        });
                        return;
                    }
                }
            } catch (e) {
                // fall through to custom modal
            }

            // 2) Fallback: create a lightweight modal dialog
            var overlay = document.createElement('div');
            overlay.className = 'combobox-confirm-overlay';
            var dlg = document.createElement('div');
            dlg.className = 'combobox-confirm-dialog';
            dlg.setAttribute('role', 'dialog');
            dlg.setAttribute('aria-modal', 'true');
            dlg.innerHTML = '<div class="combobox-confirm-body"><p>' + escapeHtml(message) + '</p></div>';

            var controls = document.createElement('div');
            controls.className = 'combobox-confirm-controls';
            var btnOk = document.createElement('button');
            btnOk.type = 'button';
            btnOk.className = 'combobox-confirm-ok';
            btnOk.textContent = 'OK';
            var btnCancel = document.createElement('button');
            btnCancel.type = 'button';
            btnCancel.className = 'combobox-confirm-cancel';
            btnCancel.textContent = 'Cancel';

            controls.appendChild(btnCancel);
            controls.appendChild(btnOk);
            dlg.appendChild(controls);
            overlay.appendChild(dlg);
            document.body.appendChild(overlay);

            // simple styles injected once
            if (!document.getElementById('combobox-confirm-styles')) {
                var style = document.createElement('style');
                style.id = 'combobox-confirm-styles';
                style.textContent = '\
    .combobox-confirm-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.45);display:flex;align-items:center;justify-content:center;z-index:99999}\
    .combobox-confirm-dialog{background:#fff;padding:16px 18px;border-radius:6px;max-width:420px;width:90%;box-shadow:0 8px 24px rgba(0,0,0,0.2)}\
    .combobox-confirm-body p{margin:0 0 12px;color:#222}\
    .combobox-confirm-controls{display:flex;gap:8px;justify-content:flex-end}\
    .combobox-confirm-ok{background:#d9534f;color:#fff;border:0;padding:6px 10px;border-radius:4px;cursor:pointer}\
    .combobox-confirm-cancel{background:#f0f0f0;color:#222;border:0;padding:6px 10px;border-radius:4px;cursor:pointer}\
    ';
                document.head.appendChild(style);
            }

            // focus handling
            btnOk.focus();

            function cleanup() {
                btnOk.removeEventListener('click', onOk);
                btnCancel.removeEventListener('click', onCancel);
                document.body.removeChild(overlay);
            }

            function onOk(e) {
                e.preventDefault();
                cleanup();
                resolve(true);
            }
            function onCancel(e) {
                e.preventDefault();
                cleanup();
                resolve(false);
            }

            btnOk.addEventListener('click', onOk);
            btnCancel.addEventListener('click', onCancel);

            // handle escape key
            function onKey(e) {
                if (e.key === 'Escape') {
                    cleanup();
                    resolve(false);
                }
            }
            document.addEventListener('keydown', onKey, { once: true });
        });
    }

    function buildTableFromJson(rows) {
        if (!rows || !rows.length) {
            return '<div class="combobox-admin-empty">No options found.</div>';
        }

        var html = '<div class="combobox-admin-panel"><table class="com-table combobox-admin-table">';
        html += '<thead><tr>';
        html += '<th style="width:8%;">ID</th>';
        html += '<th>Value</th>';
        html += '<th style="width:20%;">Created</th>';
        html += '<th style="width:14%;">Action</th>';
        html += '</tr></thead><tbody>';

        rows.forEach(function (r) {
            var id = r.id ? (parseInt(r.id,10) || '') : '';
            var value = escapeHtml(r.value || '');
            var created = escapeHtml(r.created || '');

            var btnClass = 'combobox-btn combobox-btn--danger combobox-btn--sm';
            if (typeof window.Joomla !== 'undefined') {
                btnClass = 'btn btn-danger btn-sm';
            }

            html += '<tr>';
            html += '<td>' + id + '</td>';
            html += '<td>' + value + '</td>';
            html += '<td>' + created + '</td>';
            html += '<td class="combobox-admin-actions">';
            html += '<button type="button" class="' + btnClass + ' combobox-admin-delete" data-id="' + id + '">Delete</button>';
            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        return html;
    }

    function loadManagerJson(container, fieldId) {
        if (!fieldId) {
            setPlaceholder(container); return;
        }
        showLoading(container);

        var url = '/administrator/index.php?option=com_ajax&plugin=combobox&group=fields&action=adminList&format=json&field_id=' + encodeURIComponent(fieldId);

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error('Network'); return r.json(); })
            .then(function (json) {
                var rows = (json && json.results) ? json.results : [];
                var panel = container.querySelector('[data-panel]');
                if (!panel) return;
                panel.innerHTML = buildTableFromJson(rows);
                var placeholder = container.querySelector('[data-placeholder]');
                if (placeholder) placeholder.style.display = 'none';
            })
            .catch(function (err) {
                showError(container, 'Could not load options: ' + (err && err.message ? err.message : 'unknown'));
            });
    }

    function initContainer(container) {
        // The plugin param select is outside this container in the plugin form.
        // We'll search for it by name. Use the closest form as scope if available.
        var rootForm = container.closest('form') || document;
        var select = qs('select[name="jform[params][managed_field_id]"]', rootForm) || qs('select[name="params[managed_field_id]"]', rootForm) || qs('input[name="params[managed_field_id]"]', rootForm);

        // Accept both select and text input fallback (but primary is the SQL select)
        var placeholderText = (container.querySelector('[data-placeholder]') || makeMessageNode('No field selected')).textContent;

        // initial state: if no select found, show a warning in the panel
        if (!select) {
            var panel = container.querySelector('[data-panel]');
            if (panel) panel.innerHTML = '<div class="combobox-admin-error">Field selector not found in form.</div>';
            return;
        }

        // helper to get the chosen value
        var getValue = function () {
            return (select.value || '').trim();
        };

        // Attach delegated delete handler once (idempotent)
        (function attachDeleteHandlerOnce() {
            var panel = container.querySelector('[data-panel]');
            if (!panel) return;

            if (panel.__combobox_delete_handler_attached) return;
            panel.__combobox_delete_handler_attached = true;

            panel.addEventListener('click', async function (ev) {
                var btn = ev.target.closest('.combobox-admin-delete');
                if (!btn || !panel.contains(btn)) return;

                ev.preventDefault();
                ev.stopPropagation();

                var id = btn.getAttribute('data-id');
                if (!id) return;

                // showConfirm returns a Promise<boolean>
                var ok = await showConfirm('Delete option id ' + id + '?');
                if (!ok) return;

                // read the current selected field id from the form select
                var currentFieldId = getValue();
                if (!currentFieldId) {
                    alert('No field selected.');
                    return;
                }

                var form = new FormData();
                form.append('id', id);

                // include CSRF token if provided via data attr or global config
                var tokenName = container.getAttribute('data-token-name') || (window.plgComboBoxAdmin && window.plgComboBoxAdmin.tokenName);
                if (tokenName) {
                    form.append(tokenName, '1');
                }

                // disable the button to prevent double clicks
                btn.disabled = true;

                try {
                    var resp = await fetch('/administrator/index.php?option=com_ajax&plugin=combobox&group=fields&action=adminDelete&format=json', {
                        method: 'POST',
                        body: form,
                        credentials: 'same-origin'
                    }).then(function (r) { return r.json(); });

                    if (resp && resp.success) {
                        // refresh using the current selection
                        loadManagerJson(container, currentFieldId);
                    } else {
                        alert('Delete failed: ' + (resp && resp.error ? resp.error : 'unknown'));
                        btn.disabled = false;
                    }
                } catch (e) {
                    alert('Delete failed (network error)');
                    btn.disabled = false;
                }
            }, false);
        })();

        // bind change
        select.addEventListener('change', function () {
            var val = getValue();
            if (!val) {
                setPlaceholder(container, placeholderText);
                return;
            }
            loadManagerJson(container, val);
        });

        // if there's an initial value, load on init
        var initial = getValue();
        if (initial) {
            loadManagerJson(container, initial);
        } else {
            // ensure placeholder visible
            setPlaceholder(container, placeholderText);
        }
    }

    function initAll() {
        qsa('.combobox-admin-wrap').forEach(function (c) {
            // ensure we don't init twice
            if (c.__combobox_admin_init) return;
            c.__combobox_admin_init = true;
            initContainer(c);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();