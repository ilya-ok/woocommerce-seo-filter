jQuery(document).ready(function($) {

    // Сворачивание/разворачивание списка фильтров
    $(document).on('click', '.wsf-filter-toggle', function() {
        $(this).closest('.wsf-filter-collapsible').toggleClass('wsf-filter-collapsed');

        // После завершения CSS-перехода (300ms) пересчитываем sticky-сайдбар,
        // т.к. высота сайдбара изменилась
        setTimeout(function() {
            if (typeof StickySidebar !== 'undefined' && StickySidebar.recalculate) {
                StickySidebar.recalculate();
                StickySidebar.update();
            }
        }, 320);
    });

    $('.wsf-filter-dropdown').on('change', function() {
        const url = $(this).val();
        if (url) {
            window.location.href = url;
        }
    });

    // При снятии активного фильтра — убираем из URL другие активные значения с count=0
    // той же группы, чтобы не попадать на страницы без результатов
    $(document).on('click', '.wsf-filter-item.active > a, .wsf-badge.active', function(e) {
        // Определяем контейнер группы и кликнутый элемент в зависимости от типа виджета
        var $isBadge = $(this).hasClass('wsf-badge');
        var $section = $isBadge
            ? $(this).closest('.wsf-badge-group')
            : $(this).closest('.wsf-filter-collapsible');
        var $clickedItem = $isBadge ? $(this) : $(this).closest('.wsf-filter-item');

        // Собираем slugи других активных значений с count=0
        var zeroSlugsToRemove = [];
        var activeSelector = $isBadge ? '.wsf-badge.active' : '.wsf-filter-item.active';
        $section.find(activeSelector).each(function() {
            if ($(this).is($clickedItem)) return; // пропускаем кликнутый
            if (parseInt($(this).data('count'), 10) === 0) {
                zeroSlugsToRemove.push($(this).data('slug'));
            }
        });

        if (zeroSlugsToRemove.length === 0) return; // ничего корректировать не нужно

        e.preventDefault();

        var attr = $section.data('attribute');
        var filterKey = 'filter_' + attr;
        var qtKey = 'query_type_' + attr;
        var destUrl = $(this).attr('href'); // URL после снятия кликнутого значения

        // Убираем нулевые slugи из destUrl.
        // Разбиваем URL на путь + query string, манипулируем парами key=value вручную
        // чтобы не трогать кодировку запятых (избегаем URLSearchParams).
        var qPos = destUrl.indexOf('?');
        var basePart = qPos === -1 ? destUrl : destUrl.substring(0, qPos);
        var queryStr = qPos === -1 ? '' : destUrl.substring(qPos + 1);

        var pairs = queryStr ? queryStr.split('&') : [];
        var resultPairs = [];
        var filterKeyRemoved = false;

        pairs.forEach(function(pair) {
            var eqPos = pair.indexOf('=');
            var k = eqPos === -1 ? pair : pair.substring(0, eqPos);
            var v = eqPos === -1 ? '' : pair.substring(eqPos + 1);

            if (k === filterKey) {
                // Убираем нулевые slugи из значения
                var parts = v.split(',').filter(function(s) {
                    return zeroSlugsToRemove.indexOf(decodeURIComponent(s)) === -1 && s !== '';
                });
                if (parts.length === 0) {
                    filterKeyRemoved = true;
                    return; // пропускаем весь параметр
                }
                resultPairs.push(k + '=' + parts.join(','));
            } else if (k === qtKey && filterKeyRemoved) {
                return; // убираем query_type_ если сам параметр удалён
            } else {
                resultPairs.push(pair);
            }
        });

        var finalUrl = basePart + (resultPairs.length ? '?' + resultPairs.join('&') : '');
        window.location.href = finalUrl;
    });

    // Обработчик для виджета активных фильтров (.wsf-active-filter-item)
    // Та же логика: при снятии значения убираем из URL другие активные значения с count=0
    // того же атрибута (ищем их count в сайдбарных виджетах на странице)
    $(document).on('click', '.wsf-active-filter-item', function(e) {
        var attr = $(this).data('attribute');
        if (!attr) return;

        var filterKey = 'filter_' + attr;
        var qtKey = 'query_type_' + attr;

        // Ищем нулевые активные значения этого атрибута в filter-list или badge-group
        var zeroSlugsToRemove = [];
        var $filterSection = $('.wsf-filter-collapsible[data-attribute="' + attr + '"]');
        var $badgeSection  = $('.wsf-badge-group[data-attribute="' + attr + '"]');

        function collectZeros(activeSelector) {
            return function() {
                if (parseInt($(this).data('count'), 10) === 0) {
                    var slug = $(this).data('slug');
                    if (slug && zeroSlugsToRemove.indexOf(slug) === -1) {
                        zeroSlugsToRemove.push(slug);
                    }
                }
            };
        }

        $filterSection.find('.wsf-filter-item.active').each(collectZeros());
        $badgeSection.find('.wsf-badge.active').each(collectZeros());

        if (zeroSlugsToRemove.length === 0) return;

        e.preventDefault();

        var destUrl = $(this).attr('href');
        var qPos = destUrl.indexOf('?');
        var basePart = qPos === -1 ? destUrl : destUrl.substring(0, qPos);
        var queryStr = qPos === -1 ? '' : destUrl.substring(qPos + 1);

        var pairs = queryStr ? queryStr.split('&') : [];
        var resultPairs = [];
        var filterKeyRemoved = false;

        pairs.forEach(function(pair) {
            var eqPos = pair.indexOf('=');
            var k = eqPos === -1 ? pair : pair.substring(0, eqPos);
            var v = eqPos === -1 ? '' : pair.substring(eqPos + 1);

            if (k === filterKey) {
                var parts = v.split(',').filter(function(s) {
                    return zeroSlugsToRemove.indexOf(decodeURIComponent(s)) === -1 && s !== '';
                });
                if (parts.length === 0) {
                    filterKeyRemoved = true;
                    return;
                }
                resultPairs.push(k + '=' + parts.join(','));
            } else if (k === qtKey && filterKeyRemoved) {
                return;
            } else {
                resultPairs.push(pair);
            }
        });

        var finalUrl = basePart + (resultPairs.length ? '?' + resultPairs.join('&') : '');
        window.location.href = finalUrl;
    });

    // ====================================================================
    // Показать еще / Свернуть для меток (badges)
    // ====================================================================

    function initBadgeToggle() {
        $('.wsf-badge-group').each(function() {
            var $group = $(this);
            var $wrapper = $group.find('.wsf-badge-items-wrapper');
            var $items = $group.find('.wsf-badge-items');
            var $toggle = $wrapper.find('.wsf-badge-toggle');

            // Получаем высоту контейнера
            var fullHeight = $items[0].scrollHeight;
            var visibleHeight = 28; // Высота одной строки (примерно)

            // Проверяем, нужна ли кнопка
            if (fullHeight > visibleHeight) {
                // Добавляем класс collapsed и показываем кнопку
                $items.addClass('collapsed');
                $toggle.show();
            } else {
                // Все метки умещаются в одну строку
                $items.removeClass('collapsed');
                $toggle.hide();
            }
        });
    }

    // Инициализация при загрузке страницы
    initBadgeToggle();

    // Переинициализация при изменении размера окна
    $(window).on('resize', function() {
        initBadgeToggle();
    });

    // Обработка клика по кнопке "Показать еще" / "Свернуть"
    $(document).on('click', '.wsf-badge-toggle', function(e) {
        e.preventDefault();

        var $toggle = $(this);
        var $wrapper = $toggle.closest('.wsf-badge-items-wrapper');
        var $items = $wrapper.find('.wsf-badge-items');
        var action = $toggle.data('action');

        if (action === 'expand') {
            // Раскрываем
            $items.removeClass('collapsed');
            $wrapper.addClass('expanded');
            $toggle.html('Свернуть <i class="fas fa-chevron-up"></i>').data('action', 'collapse');
        } else {
            // Сворачиваем
            $items.addClass('collapsed');
            $wrapper.removeClass('expanded');
            $toggle.html('Показать еще <i class="fas fa-chevron-down"></i>').data('action', 'expand');
        }
    });
});