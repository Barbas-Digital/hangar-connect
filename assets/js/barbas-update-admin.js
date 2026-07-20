(function ($) {
    'use strict';

    var i18n = barbasUpdateAdmin.i18n || {};
    var loadingTabId = null;

    function escapeHtml(text) {
        return $('<div/>').text(text).html();
    }

    function formatCount(visible, total) {
        var template = i18n.pluginsCount || 'Showing %1$d of %2$d plugins';
        return template.replace('%1$d', visible).replace('%2$d', total);
    }

    function getPanel($el) {
        return $el.closest('.barbas-update-panel');
    }

    function setSpinner($panel, active) {
        $panel.find('.barbas-update-spinner').toggleClass('is-active', !!active);
    }

    function showToast(message, type) {
        var $stack = $('[data-role="toast-stack"]');
        if (!$stack.length) {
            return;
        }
        var $toast = $('<div class="barbas-update-toast"/>')
            .addClass(type === 'error' ? 'is-error' : '')
            .text(message);
        $stack.append($toast);
        setTimeout(function () {
            $toast.addClass('is-leaving');
            setTimeout(function () {
                $toast.remove();
            }, 220);
        }, 3200);
    }

    function helpLinkHtml(data) {
        var url = (data && data.help_url) || barbasUpdateAdmin.githubStatusUrl || '';
        if (!url) {
            return '';
        }
        var label =
            (data && data.help_label) ||
            i18n.githubStatus ||
            'GitHub Status';
        return (
            '<p class="barbas-update-msg__help">' +
            '<a href="' +
            escapeHtml(url) +
            '" target="_blank" rel="noopener noreferrer">' +
            escapeHtml(label) +
            '</a></p>'
        );
    }

    function payloadFromXhr(xhr, fallbackMessage) {
        if (xhr && xhr.responseJSON && xhr.responseJSON.data) {
            return xhr.responseJSON.data;
        }
        return { message: fallbackMessage || i18n.requestFailed || 'Request failed. Please try again.' };
    }

    function looksLikeGithubOutage(data) {
        if (!data) {
            return false;
        }
        if (data.error_type === 'github_unavailable' || data.error_type === 'github_unreachable' || data.error_type === 'rate_limit') {
            return true;
        }
        var code = parseInt(data.http_status, 10);
        return code === 429 || code === 500 || code === 502 || code === 503 || code === 504;
    }

    function renderFeedback($panel, response, isError) {
        var $fb = $panel.find('[data-role="feedback"]');
        var data = (response && response.data) || {};
        var msg = data.message ? data.message : isError ? i18n.requestFailed || '' : '';
        var html =
            '<p class="barbas-update-msg ' +
            (isError ? 'is-error' : 'is-success') +
            '">' +
            escapeHtml(msg) +
            '</p>';

        if (data.repos && data.repos.length) {
            html += '<ul>';
            data.repos.forEach(function (line) {
                html += '<li>' + escapeHtml(line) + '</li>';
            });
            html += '</ul>';
        }

        if (isError && (data.help_url || looksLikeGithubOutage(data))) {
            if (!data.help_url && barbasUpdateAdmin.githubStatusUrl) {
                data.help_url = barbasUpdateAdmin.githubStatusUrl;
                data.help_label = data.help_label || i18n.githubStatus;
            }
            html += helpLinkHtml(data);
        }

        $fb.html(html);
    }

    function updatePanelState($panel, data) {
        if (!data) {
            return;
        }

        var $badge = $panel.find('[data-role="badge"]');
        var $hint = $panel.find('[data-role="hint"]');
        var $masked = $panel.find('[data-role="masked"]');
        var $clear = $panel.find('.barbas-update-clear');
        var $wpNotice = $panel.find('[data-role="wpconfig-notice"]');

        if (typeof data.has_token !== 'undefined') {
            if (data.has_token) {
                $badge.addClass('is-active').text(i18n.licenseActive || 'License active');
                $clear.removeAttr('hidden');
            } else {
                $badge.removeClass('is-active').text(i18n.licenseMissing || 'No license');
                $clear.attr('hidden', 'hidden').attr('data-has-wpconfig', '0');
                $panel.find('.barbas-update-token-input').val('');
                $wpNotice.attr('hidden', 'hidden').text('');
            }
        }

        if (data.has_token && data.masked_token) {
            $clear.removeAttr('hidden');
            $hint.removeAttr('hidden');
            $masked.text(data.masked_token);
        } else if (data.has_token === false) {
            $hint.attr('hidden', 'hidden');
            $masked.text('');
        }

        if (data.wpconfig_removed) {
            $clear.attr('data-has-wpconfig', '0');
            $wpNotice.attr('hidden', 'hidden').text('');
        }
    }

    function updateNavState(tabId) {
        $('.barbas-update-tab, .barbas-update-picker-item').each(function () {
            var $item = $(this);
            var active = $item.data('tab-id') === tabId;
            $item.toggleClass('is-active', active).attr('aria-selected', active ? 'true' : 'false');
        });

        var $activePicker = $('.barbas-update-picker-item.is-active');
        if ($activePicker.length && $activePicker[0].scrollIntoView) {
            $activePicker[0].scrollIntoView({ block: 'nearest' });
        }

        var $panels = $('.barbas-update-panels');
        $panels.attr('data-active-tab', tabId);

        // Flush card corners with the first/last horizontal tab when that edge is active.
        var $tabs = $('.barbas-update-tab');
        $panels.removeClass('is-edge-first is-edge-last');
        if ($tabs.length) {
            var $activeTab = $tabs.filter('.is-active').first();
            if ($activeTab.length) {
                if ($activeTab.is($tabs.first())) {
                    $panels.addClass('is-edge-first');
                }
                if ($activeTab.is($tabs.last())) {
                    $panels.addClass('is-edge-last');
                }
            }
        }
    }

    function showPanel(tabId, $panel) {
        $('.barbas-update-panel').not($panel).removeClass('is-active').prop('hidden', true);
        $('.barbas-update-panel--loading').remove();
        $panel.addClass('is-active').prop('hidden', false);
        updateNavState(tabId);

        if (window.history && window.history.replaceState) {
            var url = barbasUpdateAdmin.settingsBase + (tabId ? '&tab=' + encodeURIComponent(tabId) : '');
            window.history.replaceState({ tab: tabId }, '', url);
        }
    }

    function ensurePanel(tabId, done) {
        var $existing = $('.barbas-update-panel[data-tab-id="' + tabId + '"]');
        if ($existing.length) {
            done($existing);
            return;
        }

        if (loadingTabId === tabId) {
            return;
        }

        loadingTabId = tabId;
        var $container = $('.barbas-update-panels');
        $('.barbas-update-panel').removeClass('is-active').prop('hidden', true);
        $('.barbas-update-panel--loading').remove();

        var $loader = $(
            '<div class="barbas-update-panel barbas-update-panel--loading is-active" aria-busy="true">' +
                '<div class="barbas-update-card barbas-update-card--loading">' +
                    '<p class="barbas-update-loading">' + escapeHtml(i18n.loadingPanel || 'Loading…') + '</p>' +
                    '<span class="spinner is-active barbas-update-spinner" aria-hidden="true"></span>' +
                '</div>' +
            '</div>'
        );
        $container.append($loader);

        $.post(barbasUpdateAdmin.ajaxUrl, {
            action: 'barbas_update_load_panel',
            nonce: barbasUpdateAdmin.nonce,
            tab_id: tabId
        })
            .done(function (response) {
                $loader.remove();
                if (response.success && response.data && response.data.html) {
                    $container.append(response.data.html);
                    done($('.barbas-update-panel[data-tab-id="' + tabId + '"]'));
                } else {
                    showToast(i18n.requestFailed, 'error');
                }
            })
            .fail(function () {
                $loader.remove();
                showToast(i18n.requestFailed, 'error');
            })
            .always(function () {
                loadingTabId = null;
            });
    }

    function switchTab(tabId) {
        if (!tabId) {
            return;
        }

        ensurePanel(tabId, function ($panel) {
            $panel.find('[data-role="feedback"]').empty();
            showPanel(tabId, $panel);
        });
    }

    function filterPicker(query) {
        var total = barbasUpdateAdmin.tabCount || $('.barbas-update-picker-item').length;
        var visible = 0;
        query = (query || '').toLowerCase().trim();

        $('.barbas-update-picker-list__item').each(function () {
            var $item = $(this);
            var label = $item.find('.barbas-update-picker-item').text().toLowerCase();
            var match = !query || label.indexOf(query) !== -1;
            $item.toggle(match);
            if (match) {
                visible += 1;
            }
        });

        $('[data-role="picker-meta"]').text(formatCount(visible, total));
        $('[data-role="picker-empty"]').prop('hidden', visible > 0);
    }

    $(document).on('click', '.barbas-update-tab, .barbas-update-picker-item', function (e) {
        e.preventDefault();
        switchTab($(this).data('tab-id'));
    });

    $(document).on('input', '.barbas-update-picker-search', function () {
        filterPicker($(this).val());
    });

    function refreshPluginsStatus(html) {
        var $list = $('[data-role="plugins-status-list"]');
        if (!$list.length) {
            return;
        }
        if (typeof html === 'string' && html !== '') {
            $list.html(html);
            return;
        }
        $.post(barbasUpdateAdmin.ajaxUrl, {
            action: 'barbas_update_plugins_status',
            nonce: barbasUpdateAdmin.nonce
        }).done(function (response) {
            if (response.success && response.data && response.data.html) {
                $list.html(response.data.html);
            }
        });
    }

    function renderForceCheckFeedback(message, isError, updates, data) {
        var cls = 'barbas-update-msg ' + (isError ? 'is-error' : 'is-success');
        var lead =
            i18n.updateCheckComplete ||
            'Update check complete.';
        var listHtml = '';
        var help = '';

        if (data && (data.help_url || looksLikeGithubOutage(data))) {
            if (!data.help_url && barbasUpdateAdmin.githubStatusUrl) {
                data.help_url = barbasUpdateAdmin.githubStatusUrl;
            }
            help = helpLinkHtml(data);
        }

        if (!isError && updates && updates.length) {
            listHtml =
                '<ul class="barbas-update-msg__list">' +
                updates
                    .map(function (item) {
                        return '<li>' + escapeHtml(String(item)) + '</li>';
                    })
                    .join('') +
                '</ul>';
            lead =
                i18n.updatesAvailableLead ||
                'Update check complete. Updates available:';
            return (
                '<div class="' +
                cls +
                '"><p class="barbas-update-msg__lead">' +
                escapeHtml(lead) +
                '</p>' +
                listHtml +
                help +
                '</div>'
            );
        }

        return (
            '<div class="' +
            cls +
            '"><p class="barbas-update-msg__lead">' +
            escapeHtml(message || '') +
            '</p>' +
            help +
            '</div>'
        );
    }

    $(document).on('click', '.barbas-update-force-check', function () {
        var $btn = $(this);
        var $spinner = $('.barbas-update-force-check-spinner');
        var $fb = $('[data-role="force-check-feedback"]');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $fb.html(
            '<div class="barbas-update-msg"><p class="barbas-update-msg__lead">' +
                escapeHtml(i18n.checkingUpdates || 'Checking for updates…') +
                '</p></div>'
        );

        $.post(barbasUpdateAdmin.ajaxUrl, {
            action: 'barbas_update_force_check',
            nonce: barbasUpdateAdmin.nonce
        })
            .done(function (response) {
                var data = (response && response.data) || {};
                var msg = data.message
                        ? data.message
                        : response.success
                          ? ''
                          : i18n.requestFailed;
                var isError = !response.success;
                var updates = $.isArray(data.updates) ? data.updates : [];
                $fb.html(renderForceCheckFeedback(msg, isError, updates, data));
                if (msg) {
                    showToast(msg, isError ? 'error' : 'success');
                }
                if (data.html) {
                    refreshPluginsStatus(data.html);
                } else {
                    refreshPluginsStatus();
                }
            })
            .fail(function (xhr) {
                var data = payloadFromXhr(xhr, i18n.requestFailed);
                if (looksLikeGithubOutage(data) && !data.help_url && barbasUpdateAdmin.githubStatusUrl) {
                    data.help_url = barbasUpdateAdmin.githubStatusUrl;
                    data.help_label = i18n.githubStatus;
                    if (!data.message || data.message === i18n.requestFailed) {
                        data.message =
                            i18n.githubUnavailableHint ||
                            data.message;
                    }
                }
                $fb.html(renderForceCheckFeedback(data.message, true, [], data));
                showToast(data.message, 'error');
            })
            .always(function () {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
            });
    });

    $(document).on('click', '.barbas-update-plugin-update', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var plugin = $btn.data('plugin');

        if (!plugin || $btn.prop('disabled')) {
            return;
        }

        $btn.prop('disabled', true).text(i18n.updatingPlugin || 'Updating…');

        $.post(barbasUpdateAdmin.ajaxUrl, {
            action: 'barbas_update_install_plugin',
            nonce: barbasUpdateAdmin.nonce,
            plugin: plugin
        })
            .done(function (response) {
                if (response && response.success) {
                    showToast(
                        (response.data && response.data.message) ||
                            i18n.updateSuccess ||
                            'Plugin updated.'
                    );
                    if (response.data && response.data.html) {
                        refreshPluginsStatus(response.data.html);
                        // If the updated plugin vanished from server HTML, reload so tabs re-register.
                        if (
                            plugin &&
                            response.data.html.indexOf('data-plugin="' + plugin + '"') === -1
                        ) {
                            window.location.reload();
                        }
                    } else {
                        refreshPluginsStatus();
                    }
                    return;
                }

                var errMsg =
                    (response && response.data && response.data.message) ||
                    i18n.updateFailed ||
                    'Could not update the plugin.';
                showToast(errMsg, 'error');
                if (response && response.data && response.data.html) {
                    refreshPluginsStatus(response.data.html);
                } else {
                    $btn.prop('disabled', false).text(i18n.updatePlugin || 'Update');
                }
            })
            .fail(function (xhr) {
                var data = payloadFromXhr(xhr, i18n.updateFailed || 'Could not update the plugin.');
                if (looksLikeGithubOutage(data) && !data.message) {
                    data.message = i18n.githubUnavailableHint || data.message;
                }
                var errMsg = data.message || i18n.updateFailed || 'Could not update the plugin.';
                showToast(errMsg, 'error');
                $btn.prop('disabled', false).text(i18n.updatePlugin || 'Update');
            });
    });

    $(document).on('click', '.barbas-update-validate-token', function () {
        var $btn = $(this);
        var tabId = $btn.data('tab-id');
        var $panel = getPanel($btn);
        var token = $panel.find('.barbas-update-token-input').val();

        $btn.prop('disabled', true);
        setSpinner($panel, true);
        $panel.find('[data-role="feedback"]').html('<p class="barbas-update-msg">' + escapeHtml(i18n.validating || '') + '</p>');

        $.post(barbasUpdateAdmin.ajaxUrl, {
            action: 'barbas_update_validate_token',
            nonce: barbasUpdateAdmin.nonce,
            tab_id: tabId,
            token: token
        })
            .done(function (response) {
                renderFeedback($panel, response, !response.success);
                if (response.success && response.data.message) {
                    showToast(response.data.message);
                }
            })
            .fail(function (xhr) {
                var data = payloadFromXhr(xhr, i18n.requestFailed);
                if (looksLikeGithubOutage(data) && !data.help_url && barbasUpdateAdmin.githubStatusUrl) {
                    data.help_url = barbasUpdateAdmin.githubStatusUrl;
                    data.help_label = i18n.githubStatus;
                }
                renderFeedback($panel, { data: data }, true);
            })
            .always(function () {
                $btn.prop('disabled', false);
                setSpinner($panel, false);
            });
    });

    $(document).on('click', '.barbas-update-save', function () {
        var $btn = $(this);
        var tabId = $btn.data('tab-id');
        var $panel = getPanel($btn);
        var token = $panel.find('.barbas-update-token-input').val();

        $btn.prop('disabled', true);
        setSpinner($panel, true);

        $.post(barbasUpdateAdmin.ajaxUrl, {
            action: 'barbas_update_save_token',
            nonce: barbasUpdateAdmin.nonce,
            tab_id: tabId,
            token_action: 'save',
            token: token
        })
            .done(function (response) {
                if (response.success) {
                    if (tabId === 'suite' || (response.data && response.data.reload)) {
                        if (response.data && response.data.message) {
                            showToast(response.data.message);
                        }
                        window.location.reload();
                        return;
                    }
                    updatePanelState($panel, response.data);
                    $panel.find('.barbas-update-token-input').val('');
                    $panel.find('[data-role="feedback"]').empty();
                    showToast(response.data.message);
                } else {
                    renderFeedback($panel, response, true);
                }
            })
            .fail(function () {
                showToast(i18n.requestFailed, 'error');
            })
            .always(function () {
                $btn.prop('disabled', false);
                setSpinner($panel, false);
            });
    });

    $(document).on('click', '.barbas-update-clear', function () {
        var $btn = $(this);
        var confirmMsg = $btn.data('has-wpconfig') === 1 || $btn.data('has-wpconfig') === '1'
            ? (i18n.confirmRemoveWpConfig || i18n.confirmRemove)
            : (i18n.confirmRemove || 'Remove the license for this plugin?');

        if (!window.confirm(confirmMsg)) {
            return;
        }
        var tabId = $btn.data('tab-id');
        var $panel = getPanel($btn);

        $btn.prop('disabled', true);
        setSpinner($panel, true);

        $.post(barbasUpdateAdmin.ajaxUrl, {
            action: 'barbas_update_save_token',
            nonce: barbasUpdateAdmin.nonce,
            tab_id: tabId,
            token_action: 'clear'
        })
            .done(function (response) {
                if (response.success) {
                    if (tabId === 'suite' || (response.data && response.data.reload)) {
                        if (response.data && response.data.message) {
                            showToast(response.data.message);
                        }
                        window.location.reload();
                        return;
                    }
                    updatePanelState($panel, response.data);
                    $panel.find('[data-role="feedback"]').empty();
                    showToast(response.data.message);
                } else {
                    renderFeedback($panel, response, true);
                }
            })
            .fail(function () {
                showToast(i18n.requestFailed, 'error');
            })
            .always(function () {
                $btn.prop('disabled', false);
                setSpinner($panel, false);
            });
    });
})(jQuery);
