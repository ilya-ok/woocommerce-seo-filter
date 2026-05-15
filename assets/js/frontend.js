jQuery(document).ready(function($) {

    $('.wsf-filter-dropdown').on('change', function() {
        const url = $(this).val();
        if (url) {
            window.location.href = url;
        }
    });

    // Убрали проверку на активный фильтр, теперь это обрабатывается на сервере
    // Клик по любому фильтру переходит по ссылке

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