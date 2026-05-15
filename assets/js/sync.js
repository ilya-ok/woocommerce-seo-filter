/* global wsfSync */
jQuery(document).ready(function ($) {

    var currentSlug = null;

    // ---- Toggle subtrees (из diagnose.css) ----
    $(document).on('click', '.wsf-diag-toggle', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $btn     = $(this);
        var $subtree = $btn.closest('.wsf-diag-cat').find('> .wsf-diag-subtree');
        var isOpen   = !$subtree.hasClass('collapsed');

        $subtree.toggleClass('collapsed', isOpen);
        $btn.toggleClass('expanded', !isOpen);
    });

    // ---- Выбор категории ----
    $(document).on('click', '.wsf-diag-cat-link', function (e) {
        e.preventDefault();

        var slug = $(this).data('slug');
        currentSlug = slug;

        $('.wsf-diag-cat-link').removeClass('active');
        $(this).addClass('active');

        $('#wsf-sync-result').html('<div class="wsf-diag-loading">Загрузка данных...</div>');

        $.ajax({
            url: wsfSync.ajax_url,
            method: 'POST',
            data: {
                action: 'wsf_sync_get_products',
                nonce: wsfSync.nonce,
                category_slug: slug,
            },
            success: function (response) {
                if (!response.success) {
                    showError(response.data && response.data.message);
                    return;
                }
                renderProductsPanel(response.data);
            },
            error: function () {
                showError('Ошибка сетевого запроса');
            }
        });
    });

    // ---- Кнопка «Синхронизировать» ----
    $(document).on('click', '#wsf-sync-btn', function () {
        if (!currentSlug) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).addClass('loading');
        $btn.find('.wsf-sync-btn-text').text('Синхронизирую...');

        $.ajax({
            url: wsfSync.ajax_url,
            method: 'POST',
            data: {
                action: 'wsf_sync_category',
                nonce: wsfSync.nonce,
                category_slug: currentSlug,
            },
            success: function (response) {
                $btn.prop('disabled', false).removeClass('loading');
                $btn.find('.wsf-sync-btn-text').text('Синхронизировать');

                if (!response.success) {
                    showError(response.data && response.data.message);
                    return;
                }
                renderSyncResults(response.data);
            },
            error: function () {
                $btn.prop('disabled', false).removeClass('loading');
                $btn.find('.wsf-sync-btn-text').text('Синхронизировать');
                showError('Ошибка сетевого запроса');
            }
        });
    });

    // ---- Render: панель выбранной категории ----
    function renderProductsPanel(data) {
        var html = '';
        var cat  = data.category;

        html += '<h2>' + esc(cat.name) + ' <span class="wsf-diag-cat-slug">(' + esc(cat.slug) + ')</span></h2>';

        if (!data.binding) {
            html += '<div class="wsf-diag-no-binding">⚠️ Связка для этой категории не найдена. Синхронизация невозможна.</div>';
            html += renderProductListOnly(data.already_synced);
            $('#wsf-sync-result').html(html);
            return;
        }

        // Атрибуты связки
        html += '<div class="wsf-diag-binding-info">';
        html += '<h3>🔗 Атрибуты связки</h3>';
        html += '<div class="wsf-sync-binding-attrs">';
        data.binding.attributes.forEach(function (attr) {
            html += '<span class="wsf-sync-attr-tag"><strong>' + esc(attr.attribute) + '</strong>: ' + esc(attr.value) + '</span>';
        });
        html += '</div></div>';

        // Статистика
        html += '<div class="wsf-sync-stats">';
        html += '<div class="wsf-sync-stat wsf-sync-stat-needs">'
            + '<span class="wsf-sync-stat-count">' + data.needs_sync.length + '</span>'
            + '<span class="wsf-sync-stat-label">Нужна синхронизация</span>'
            + '</div>';
        html += '<div class="wsf-sync-stat wsf-sync-stat-ok">'
            + '<span class="wsf-sync-stat-count">' + data.already_synced.length + '</span>'
            + '<span class="wsf-sync-stat-label">Уже синхронизированы</span>'
            + '</div>';
        html += '</div>';

        if (data.needs_sync.length === 0) {
            html += '<div class="wsf-diag-block wsf-diag-block-ok">'
                + '<h3>✅ Все товары уже синхронизированы</h3>'
                + '<p class="wsf-diag-block-desc">Все товары категории имеют нужные атрибуты.</p>'
                + '</div>';
        } else {
            // Кнопка синхронизации
            html += '<button id="wsf-sync-btn" class="wsf-sync-btn" data-slug="' + esc(cat.slug) + '">'
                + '<span class="wsf-sync-spinner"></span>'
                + '<span class="wsf-sync-btn-text">Синхронизировать (' + data.needs_sync.length + ' товаров)</span>'
                + '</button>';

            // Список товаров для синхронизации
            html += '<div class="wsf-diag-block wsf-diag-block-warn">';
            html += '<h3>⚠️ Требуют синхронизации (' + data.needs_sync.length + ')</h3>';
            html += '<p class="wsf-diag-block-desc">Этим товарам будут добавлены отмеченные атрибуты:</p>';
            html += '<ul class="wsf-sync-needs-list">';
            data.needs_sync.forEach(function (p) {
                html += '<li>'
                    + '<a href="' + esc(p.edit_url) + '" target="_blank">' + esc(p.title)
                    + ' <span class="wsf-diag-id">#' + parseInt(p.id, 10) + '</span></a>';

                if (p.missing && p.missing.length) {
                    html += '<div class="wsf-sync-missing-tags">';
                    p.missing.forEach(function (m) {
                        html += '<span class="wsf-sync-missing-tag">+ ' + esc(m.attribute) + '=' + esc(m.value) + '</span>';
                    });
                    html += '</div>';
                }

                html += '</li>';
            });
            html += '</ul></div>';
        }

        // Уже синхронизированы (свёрнуто)
        if (data.already_synced.length > 0) {
            html += '<div class="wsf-diag-block wsf-diag-block-ok">';
            html += '<h3>✅ Уже синхронизированы (' + data.already_synced.length + ')</h3>';
            html += '<ul class="wsf-diag-product-list">';
            data.already_synced.forEach(function (p) {
                html += '<li><a href="' + esc(p.edit_url) + '" target="_blank">' + esc(p.title)
                    + '<span class="wsf-diag-id">#' + parseInt(p.id, 10) + '</span></a></li>';
            });
            html += '</ul></div>';
        }

        $('#wsf-sync-result').removeClass('wsf-diag-placeholder').html(html);
    }

    // ---- Render: результаты синхронизации ----
    function renderSyncResults(data) {
        var html = '<div class="wsf-sync-result-box">';
        html += '<h3>✅ Синхронизация завершена</h3>';

        html += '<div class="wsf-sync-result-summary">'
            + '<span>Всего товаров: <strong>' + data.total + '</strong></span>'
            + '<span>Синхронизировано: <strong style="color:#00a32a">' + data.synced + '</strong></span>'
            + '<span>Пропущено (уже были): <strong>' + data.skipped + '</strong></span>'
            + '</div>';

        if (data.results && data.results.length) {
            html += '<ul class="wsf-sync-result-list">';
            data.results.forEach(function (r) {
                var statusClass = 'wsf-status-' + r.status;
                var statusLabel = { synced: 'Добавлено', skipped: 'Пропущен', error: 'Ошибка' }[r.status] || r.status;

                html += '<li>'
                    + '<span class="wsf-sync-status ' + statusClass + '">' + statusLabel + '</span>'
                    + '<span class="wsf-sync-result-title">' + esc(r.title)
                    + ' <span class="wsf-diag-id">#' + parseInt(r.id, 10) + '</span></span>'
                    + '<span class="wsf-sync-result-msg">' + esc(r.message) + '</span>'
                    + '</li>';
            });
            html += '</ul>';
        }

        html += '</div>';

        // Кнопка обновить список
        html += '<button id="wsf-sync-refresh" class="button">'
            + '↺ Обновить список</button>';

        $('#wsf-sync-result').html(html);

        // Обновить список (повторно загружаем данные категории)
        $(document).one('click', '#wsf-sync-refresh', function () {
            $('.wsf-diag-cat-link[data-slug="' + currentSlug + '"]').trigger('click');
        });
    }

    function renderProductListOnly(products) {
        if (!products || products.length === 0) {
            return '<p class="wsf-diag-empty">Нет товаров в категории.</p>';
        }
        var html = '<div class="wsf-diag-block"><h3>Товары в категории (' + products.length + ')</h3><ul class="wsf-diag-product-list">';
        products.forEach(function (p) {
            html += '<li><a href="' + esc(p.edit_url) + '" target="_blank">' + esc(p.title)
                + '<span class="wsf-diag-id">#' + parseInt(p.id, 10) + '</span></a></li>';
        });
        html += '</ul></div>';
        return html;
    }

    function showError(msg) {
        var m = msg || 'Неизвестная ошибка';
        $('#wsf-sync-result').html('<div class="wsf-diag-error">Ошибка: ' + esc(m) + '</div>');
    }

    function esc(str) {
        if (str === null || str === undefined) {
            return '';
        }
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
});
