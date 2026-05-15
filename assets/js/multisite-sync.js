/**
 * JavaScript для страницы синхронизации мультисайта
 * WooCommerce SEO Filter - Multisite Sync
 */

(function($) {
    'use strict';

    var WSFMultisiteSync = {

        /**
         * Инициализация
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Привязка событий
         */
        bindEvents: function() {
            $('#wsf-export-btn').on('click', this.exportSettings.bind(this));
            $('#wsf-sync-selected-btn').on('click', this.syncSelected.bind(this));
            $('#wsf-sync-all-btn').on('click', this.syncAll.bind(this));
        },

        /**
         * Показать статус
         */
        showStatus: function(message, type, details) {
            var $status = $('#wsf-sync-status');
            var typeClass = type || 'info';

            var html = '<h3>' + message + '</h3>';

            if (details && details.length > 0) {
                html += '<ul>';
                details.forEach(function(item) {
                    html += '<li>' + item + '</li>';
                });
                html += '</ul>';
            }

            $status
                .removeClass('success error warning')
                .addClass(typeClass)
                .html(html)
                .slideDown();
        },

        /**
         * Скрыть статус
         */
        hideStatus: function() {
            $('#wsf-sync-status').slideUp();
        },

        /**
         * Установить loading состояние для кнопки
         */
        setButtonLoading: function($button, loading) {
            if (loading) {
                $button.addClass('loading').prop('disabled', true);
            } else {
                $button.removeClass('loading').prop('disabled', false);
            }
        },

        /**
         * Экспорт настроек
         */
        exportSettings: function(e) {
            e.preventDefault();

            var $btn = $(e.currentTarget);
            var siteId = $('#wsf-export-site').val();

            this.hideStatus();
            this.setButtonLoading($btn, true);

            $.ajax({
                url: wsfMultisiteData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wsf_export_settings',
                    nonce: wsfMultisiteData.nonce,
                    site_id: siteId
                },
                success: function(response) {
                    if (response.success) {
                        // Создаём и скачиваем JSON файл
                        var data = response.data;
                        var filename = 'wsf-settings-site-' + data.site_id + '-' + data.timestamp.replace(/[:\s]/g, '-') + '.json';
                        var json = JSON.stringify(data.settings, null, 2);
                        var blob = new Blob([json], { type: 'application/json' });
                        var url = URL.createObjectURL(blob);

                        var a = document.createElement('a');
                        a.href = url;
                        a.download = filename;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);

                        this.showStatus(
                            'Настройки успешно экспортированы из сайта: ' + data.site_name,
                            'success'
                        );
                    } else {
                        this.showStatus(
                            'Ошибка экспорта: ' + (response.data.message || 'Unknown error'),
                            'error'
                        );
                    }
                }.bind(this),
                error: function(xhr, status, error) {
                    console.error('WSF: Ajax error:', {xhr: xhr, status: status, error: error});
                    console.error('WSF: Response text:', xhr.responseText);
                    this.showStatus(
                        'Ошибка Ajax запроса: ' + error,
                        'error'
                    );
                }.bind(this),
                complete: function() {
                    this.setButtonLoading($btn, false);
                }.bind(this)
            });
        },

        /**
         * Синхронизация выбранных сайтов
         */
        syncSelected: function(e) {
            e.preventDefault();
            console.log('WSF: syncSelected() called');

            var $btn = $(e.currentTarget);
            var sourceId = $('#wsf-source-site').val();
            var targetIds = [];

            $('input[name="wsf_target_sites[]"]:checked').each(function() {
                targetIds.push($(this).val());
            });

            console.log('WSF: Source ID:', sourceId);
            console.log('WSF: Target IDs:', targetIds);

            if (targetIds.length === 0) {
                this.showStatus('Выберите хотя бы один сайт для синхронизации', 'warning');
                return;
            }

            if (!confirm('Вы уверены, что хотите синхронизировать настройки на выбранные сайты? Текущие настройки будут перезаписаны.')) {
                return;
            }

            this.performSync(sourceId, targetIds, $btn);
        },

        /**
         * Синхронизация на все сайты
         */
        syncAll: function(e) {
            e.preventDefault();

            var $btn = $(e.currentTarget);
            var sourceId = $('#wsf-source-site').val();
            var targetIds = [];

            $('input[name="wsf_target_sites[]"]:not(:disabled)').each(function() {
                targetIds.push($(this).val());
            });

            if (targetIds.length === 0) {
                this.showStatus('Нет доступных сайтов для синхронизации', 'warning');
                return;
            }

            if (!confirm('Вы уверены, что хотите синхронизировать настройки на ВСЕ сайты? Текущие настройки будут перезаписаны.')) {
                return;
            }

            this.performSync(sourceId, targetIds, $btn);
        },

        /**
         * Выполнить синхронизацию
         */
        performSync: function(sourceId, targetIds, $btn) {
            console.log('WSF: performSync() called');
            console.log('WSF: Ajax URL:', wsfMultisiteData.ajaxurl);
            console.log('WSF: Nonce:', wsfMultisiteData.nonce);

            this.hideStatus();
            this.setButtonLoading($btn, true);

            $.ajax({
                url: wsfMultisiteData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wsf_sync_to_all',
                    nonce: wsfMultisiteData.nonce,
                    source_id: sourceId,
                    target_ids: targetIds
                },
                success: function(response) {
                    console.log('WSF: Ajax success:', response);
                    if (response.success) {
                        var data = response.data;
                        var type = data.failed > 0 ? 'warning' : 'success';

                        this.showStatus(
                            data.message,
                            type,
                            data.results
                        );

                        // Снимаем выделение с чекбоксов
                        $('input[name="wsf_target_sites[]"]').prop('checked', false);
                    } else {
                        this.showStatus(
                            'Ошибка синхронизации: ' + (response.data.message || 'Unknown error'),
                            'error'
                        );
                    }
                }.bind(this),
                error: function(xhr, status, error) {
                    console.error('WSF: Ajax error:', {xhr: xhr, status: status, error: error});
                    console.error('WSF: Response text:', xhr.responseText);
                    this.showStatus(
                        'Ошибка Ajax запроса: ' + error,
                        'error'
                    );
                }.bind(this),
                complete: function() {
                    this.setButtonLoading($btn, false);
                }.bind(this)
            });
        }
    };

    // Инициализация при загрузке DOM
    $(document).ready(function() {
        console.log('WSF Multisite Sync: DOM ready');

        // Проверяем, что мы на нужной странице
        if ($('#wsf-sync-selected-btn').length === 0) {
            console.log('WSF Multisite Sync: Not on sync page, skipping initialization');
            return;
        }

        console.log('WSF Multisite Sync: On sync page');
        console.log('WSF Multisite Sync: wsfMultisiteData =', window.wsfMultisiteData);

        if (typeof wsfMultisiteData === 'undefined') {
            console.error('WSF Multisite Sync: wsfMultisiteData is not defined!');
            alert('Ошибка: JavaScript переменные не загружены. Обновите страницу.');
            return;
        }

        WSFMultisiteSync.init();
        console.log('WSF Multisite Sync: Initialized successfully');
    });

})(jQuery);
