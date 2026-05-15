/* global wsfMps */
jQuery(document).ready(function ($) {

    var currentSlug     = null;  // '' = all products
    var sourceProducts  = [];

    // ---- Toggle subtrees (дерево категорий) ----
    $(document).on('click', '.wsf-diag-toggle', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $btn     = $(this);
        var $subtree = $btn.closest('.wsf-diag-cat').find('> .wsf-diag-subtree');
        var isOpen   = !$subtree.hasClass('collapsed');

        $subtree.toggleClass('collapsed', isOpen);
        $btn.toggleClass('expanded', !isOpen);
    });

    // ---- Клик на категорию / "Все товары" ----
    $(document).on('click', '.wsf-diag-cat-link, .wsf-mps-cat-link', function (e) {
        e.preventDefault();

        var slug = $(this).data('slug');
        currentSlug = (slug === undefined || slug === null) ? '' : String(slug);

        $('.wsf-diag-cat-link, .wsf-mps-cat-link').removeClass('active');
        $(this).addClass('active');

        loadCategoryProducts(currentSlug);
    });

    // ---- Клик «Синхронизировать» ----
    $(document).on('click', '#wsf-mps-sync-btn', function () {
        var targetSites = getTargetSites();

        if (targetSites.length === 0) {
            alert('Выберите хотя бы один целевой сайт.');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).addClass('loading');
        $btn.find('.wsf-mps-btn-text').text('Синхронизирую...');

        $.ajax({
            url: wsfMps.ajax_url,
            method: 'POST',
            timeout: 120000,
            data: {
                action:          'wsf_mps_sync',
                nonce:           wsfMps.nonce,
                category_slug:   currentSlug,
                target_sites:    targetSites,
                sync_categories: $('#wsf-mps-sync-cats').is(':checked') ? 1 : 0,
                sync_attributes: $('#wsf-mps-sync-attrs').is(':checked') ? 1 : 0,
            },
            success: function (response) {
                $btn.prop('disabled', false).removeClass('loading');
                $btn.find('.wsf-mps-btn-text').text('Синхронизировать');

                if (!response.success) {
                    showError(response.data && response.data.message);
                    return;
                }
                renderSyncResults(response.data);
            },
            error: function () {
                $btn.prop('disabled', false).removeClass('loading');
                $btn.find('.wsf-mps-btn-text').text('Синхронизировать');
                showError('Ошибка сетевого запроса (возможно, превышен таймаут)');
            }
        });
    });

    // ---- Аккордеон сайтов в результатах ----
    $(document).on('click', '.wsf-mps-site-header', function () {
        var $block = $(this).closest('.wsf-mps-site-block');
        $block.toggleClass('open');
    });

    // ---- Загрузка продуктов категории ----
    function loadCategoryProducts(slug) {
        $('#wsf-mps-result').html('<div class="wsf-diag-loading">Загрузка товаров...</div>');

        $.ajax({
            url: wsfMps.ajax_url,
            method: 'POST',
            data: {
                action:        'wsf_mps_load_category',
                nonce:         wsfMps.nonce,
                category_slug: slug,
            },
            success: function (response) {
                if (!response.success) {
                    showError(response.data && response.data.message);
                    return;
                }
                sourceProducts = response.data.products;
                renderCategoryPanel(response.data);
            },
            error: function () {
                showError('Ошибка сетевого запроса');
            }
        });
    }

    // ---- Render: панель загруженной категории ----
    function renderCategoryPanel(data) {
        var html = '';

        // Заголовок: название категории со ссылкой
        var catLabel;
        if (data.category_slug) {
            var catName = data.category_name || data.category_slug;
            catLabel = data.category_url
                ? '<a href="' + esc(data.category_url) + '" target="_blank" class="wsf-mps-cat-view-link">'
                  + esc(catName) + ' <span class="wsf-mps-ext-icon">↗</span></a>'
                : esc(catName);
        } else {
            catLabel = 'Все товары';
        }

        // Панель синхронизации
        html += '<div class="wsf-mps-sync-bar">';
        html += '<div class="wsf-mps-info">'
            + 'Категория: <strong>' + catLabel + '</strong>'
            + ' — товаров: <strong>' + data.total + '</strong>';
        if (data.no_sku > 0) {
            html += ' <span style="color:#d63638;">(без SKU: ' + data.no_sku + ' — будут пропущены)</span>';
        }
        html += '</div>';

        html += '<button id="wsf-mps-sync-btn" class="wsf-mps-btn" type="button">'
            + '<span class="wsf-mps-spinner"></span>'
            + '<span class="wsf-mps-btn-text">Синхронизировать</span>'
            + '</button>';
        html += '</div>';

        // Список товаров
        html += '<div class="wsf-diag-block">';
        html += '<h3>Товары источника (' + data.total + ')</h3>';

        if (data.products && data.products.length > 0) {
            html += '<ul class="wsf-mps-source-list">';
            data.products.forEach(function (p) {
                var skuClass = p.sku ? '' : ' no-sku';
                var skuText  = p.sku || 'Нет SKU';

                // Категории — ссылки
                var catLinks = '';
                if (p.categories && p.categories.length) {
                    catLinks = p.categories.map(function (c) {
                        if (c.url) {
                            return '<a href="' + esc(c.url) + '" target="_blank" class="wsf-mps-cat-tag">'
                                + esc(c.name) + '</a>';
                        }
                        return '<span class="wsf-mps-cat-tag">' + esc(c.name) + '</span>';
                    }).join('');
                }

                html += '<li>'
                    + '<span class="wsf-mps-sku' + skuClass + '">' + esc(skuText) + '</span>'
                    + '<div class="wsf-mps-product-info">'
                    +   '<span class="wsf-mps-product-title">' + esc(p.title) + '</span>';

                if (catLinks) {
                    html += '<div class="wsf-mps-cat-links">' + catLinks + '</div>';
                }

                html += '</div>'
                    + '<span class="wsf-mps-product-meta">атр.: ' + p.attributes.length + '</span>'
                    + '</li>';
            });
            html += '</ul>';
        } else {
            html += '<p class="wsf-diag-empty">Нет товаров в данной категории.</p>';
        }

        html += '</div>';

        $('#wsf-mps-result').removeClass('wsf-diag-placeholder').html(html);
    }

    // ---- Render: результаты синхронизации ----
    function renderSyncResults(data) {
        var html = '<h2>Результаты синхронизации</h2>';

        // Итоговая статистика по всем сайтам
        var totalSynced  = 0;
        var totalSkipped = 0;
        var totalNf      = 0;

        data.results.forEach(function (s) {
            totalSynced  += s.synced;
            totalSkipped += s.skipped;
            totalNf      += s.not_found;
        });

        html += '<div class="wsf-mps-results-summary">';
        html += statBox(data.total_source, '888',      'Товаров в источнике');
        html += statBox(totalSynced,       '00a32a',   'Синхронизировано', 'wsf-mps-stat-synced');
        html += statBox(totalSkipped,      '888',      'Пропущено (актуальны)', 'wsf-mps-stat-skipped');
        html += statBox(totalNf,           'd63638',   'SKU не найден', 'wsf-mps-stat-nf');
        if (data.skipped_no_sku > 0) {
            html += statBox(data.skipped_no_sku, 'd63638', 'Без SKU (пропущены)', 'wsf-mps-stat-nf');
        }
        html += '</div>';

        // Детали по каждому сайту
        data.results.forEach(function (site) {
            html += '<div class="wsf-mps-site-block open">';

            html += '<div class="wsf-mps-site-header">'
                + '<span class="wsf-mps-site-name">' + esc(site.site_name) + ' (ID ' + site.site_id + ')</span>';

            if (site.synced > 0) {
                html += '<span class="wsf-mps-site-badge wsf-mps-badge-synced">✓ ' + site.synced + ' синхр.</span>';
            }
            if (site.skipped > 0) {
                html += '<span class="wsf-mps-site-badge wsf-mps-badge-skipped">— ' + site.skipped + ' пропущ.</span>';
            }
            if (site.not_found > 0) {
                html += '<span class="wsf-mps-site-badge wsf-mps-badge-nf">✗ ' + site.not_found + ' не найдено</span>';
            }

            html += '<span class="wsf-mps-site-toggle">▶</span>'
                + '</div>'; // .wsf-mps-site-header

            // Детальная таблица
            html += '<div class="wsf-mps-site-details">';
            html += '<table class="wsf-mps-detail-table">';
            html += '<thead><tr>'
                + '<th>SKU</th>'
                + '<th>Название</th>'
                + '<th>Статус</th>'
                + '<th>Что сделано</th>'
                + '</tr></thead>';
            html += '<tbody>';

            site.details.forEach(function (row) {
                var statusLabels = { synced: 'Синхр.', skipped: 'Пропущен', not_found: 'Не найден' };
                var statusColors = { synced: 'wsf-mps-badge-synced', skipped: 'wsf-mps-badge-skipped', not_found: 'wsf-mps-badge-nf' };
                var statusLabel  = statusLabels[row.status]  || row.status;
                var statusColor  = statusColors[row.status]  || '';

                html += '<tr class="wsf-mps-row-' + row.status + '">'
                    + '<td><span class="wsf-mps-detail-sku">' + esc(row.sku) + '</span></td>'
                    + '<td>' + esc(row.title) + '</td>'
                    + '<td><span class="wsf-mps-detail-status wsf-mps-site-badge ' + statusColor + '">' + statusLabel + '</span></td>'
                    + '<td class="wsf-mps-detail-msg">' + esc(row.msg) + '</td>'
                    + '</tr>';
            });

            html += '</tbody></table>';
            html += '</div>'; // .wsf-mps-site-details
            html += '</div>'; // .wsf-mps-site-block
        });

        // Кнопка повторить
        html += '<div style="margin-top:15px;">'
            + '<button id="wsf-mps-run-again" class="button">↺ Синхронизировать ещё раз</button>'
            + '</div>';

        $('#wsf-mps-result').html(html);

        $(document).one('click', '#wsf-mps-run-again', function () {
            renderCategoryPanel({ category_slug: currentSlug, total: sourceProducts.length, no_sku: 0, products: sourceProducts });
        });
    }

    function statBox(count, colorHex, label, extraClass) {
        var cls = extraClass || '';
        return '<div class="wsf-mps-stat-box ' + cls + '">'
            + '<span class="wsf-mps-stat-num" style="color:#' + colorHex + '">' + count + '</span>'
            + '<span class="wsf-mps-stat-lbl">' + label + '</span>'
            + '</div>';
    }

    function getTargetSites() {
        var ids = [];
        $('.wsf-mps-site-checkbox:checked').each(function () {
            var id = parseInt($(this).val(), 10);
            if (id && id !== wsfMps.current_site) {
                ids.push(id);
            }
        });
        return ids;
    }

    function showError(msg) {
        var m = msg || 'Неизвестная ошибка';
        $('#wsf-mps-result').html('<div class="wsf-diag-error">Ошибка: ' + esc(m) + '</div>');
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
