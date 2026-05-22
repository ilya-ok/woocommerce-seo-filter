<?php
/**
 * Класс для синхронизации настроек SEO Filter между сайтами мультисайта
 *
 * Синхронизирует:
 * - Filter Bindings (связки фильтров)
 * - Filter Attributes Settings (настройки атрибутов категорий)
 *
 * @package WooCommerce_SEO_Filter
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSF_Multisite_Sync {

    private static $instance = null;

    /**
     * WordPress options для синхронизации
     */
    private $sync_options = [
        'wsf_bindings',
        'wsf_category_attributes',
        'wsf_category_display_settings',
        'wsf_category_available_attributes',
        'wsf_category_badge_attributes',
        'wsf_collapsed_attributes'
    ];

    private function __construct() {
        // Только для мультисайта
        if (!is_multisite()) {
            return;
        }

        // Добавляем страницу в Network Admin
        add_action('network_admin_menu', [$this, 'add_network_admin_menu']);

        // Ajax обработчики
        add_action('wp_ajax_wsf_export_settings', [$this, 'ajax_export_settings']);
        add_action('wp_ajax_wsf_sync_to_site', [$this, 'ajax_sync_to_site']);
        add_action('wp_ajax_wsf_sync_to_all', [$this, 'ajax_sync_to_all']);

        // Подключение стилей и скриптов
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Добавление страницы в Network Admin
     */
    public function add_network_admin_menu() {
        // Добавляем страницу на верхнем уровне меню Network Admin
        add_menu_page(
            'SEO Filter Sync',
            'SEO Filter Sync',
            'manage_network',
            'wsf-multisite-sync',
            [$this, 'render_sync_page'],
            'dashicons-update',
            80
        );

        // Также добавляем в подменю Settings
        add_submenu_page(
            'settings.php',
            'SEO Filter Multisite Sync',
            'SEO Filter Sync',
            'manage_network',
            'wsf-multisite-sync',
            [$this, 'render_sync_page']
        );
    }

    /**
     * Подключение стилей и скриптов
     */
    public function enqueue_scripts($hook) {
        // Загружаем только в Network Admin
        if (!is_network_admin()) {
            return;
        }

        wp_enqueue_style(
            'wsf-multisite-sync',
            WSF_URL . 'assets/css/multisite-sync.css',
            [],
            WSF_VERSION
        );

        wp_enqueue_script(
            'wsf-multisite-sync',
            WSF_URL . 'assets/js/multisite-sync.js',
            ['jquery'],
            WSF_VERSION,
            true
        );

        wp_localize_script('wsf-multisite-sync', 'wsfMultisiteData', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wsf_multisite_sync')
        ]);
    }

    /**
     * Отрисовка страницы синхронизации
     */
    public function render_sync_page() {
        $current_site_id = get_current_blog_id();
        $sites = get_sites(['number' => 0]);

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <p class="description">
                Синхронизация настроек SEO Filter между сайтами мультисайта.
                Выберите источник (откуда копировать) и получателей (куда копировать).
            </p>

            <!-- Секция Export/Import -->
            <div class="wsf-sync-section">
                <h2>Экспорт настроек</h2>
                <p>Экспортируйте настройки текущего сайта в JSON файл для резервного копирования или переноса.</p>

                <table class="form-table">
                    <tr>
                        <th>Выберите сайт-источник:</th>
                        <td>
                            <select id="wsf-export-site" class="regular-text">
                                <?php foreach ($sites as $site) : ?>
                                    <option value="<?php echo esc_attr($site->blog_id); ?>" <?php selected($site->blog_id, $current_site_id); ?>>
                                        <?php echo esc_html(get_blog_option($site->blog_id, 'blogname')); ?>
                                        (<?php echo esc_url($site->domain . $site->path); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="button" class="button button-secondary" id="wsf-export-btn">
                        <span class="dashicons dashicons-download"></span> Экспортировать настройки
                    </button>
                </p>
            </div>

            <hr>

            <!-- Секция синхронизации -->
            <div class="wsf-sync-section">
                <h2>Синхронизация настроек</h2>
                <p>Копирование настроек с одного сайта на другие сайты мультисайта.</p>

                <table class="form-table">
                    <tr>
                        <th>Сайт-источник (откуда копировать):</th>
                        <td>
                            <select id="wsf-source-site" class="regular-text">
                                <?php foreach ($sites as $site) : ?>
                                    <option value="<?php echo esc_attr($site->blog_id); ?>" <?php selected($site->blog_id, $current_site_id); ?>>
                                        <?php echo esc_html(get_blog_option($site->blog_id, 'blogname')); ?>
                                        (<?php echo esc_url($site->domain . $site->path); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <h3>Выберите получателей:</h3>
                <div class="wsf-sites-grid">
                    <?php foreach ($sites as $site) : ?>
                        <label class="wsf-site-checkbox">
                            <input type="checkbox"
                                   name="wsf_target_sites[]"
                                   value="<?php echo esc_attr($site->blog_id); ?>"
                                   <?php disabled($site->blog_id, $current_site_id); ?>>
                            <strong><?php echo esc_html(get_blog_option($site->blog_id, 'blogname')); ?></strong>
                            <span class="wsf-site-url"><?php echo esc_url($site->domain . $site->path); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <p>
                    <button type="button" class="button button-primary" id="wsf-sync-selected-btn">
                        <span class="dashicons dashicons-update"></span> Синхронизировать выбранные
                    </button>

                    <button type="button" class="button button-secondary" id="wsf-sync-all-btn">
                        <span class="dashicons dashicons-admin-site"></span> Синхронизировать на все сайты
                    </button>
                </p>
            </div>

            <hr>

            <!-- Информация о синхронизируемых данных -->
            <div class="wsf-sync-section">
                <h2>Что синхронизируется</h2>
                <ul class="wsf-sync-info">
                    <li><span class="dashicons dashicons-yes"></span> <strong>Filter Bindings</strong> - связки категорий и атрибутов</li>
                    <li><span class="dashicons dashicons-yes"></span> <strong>Category Attributes Settings</strong> - источник атрибутов (parent/own)</li>
                    <li><span class="dashicons dashicons-yes"></span> <strong>Category Display Settings</strong> - режим отображения</li>
                    <li><span class="dashicons dashicons-yes"></span> <strong>Available Attributes</strong> - доступные атрибуты для фильтрации</li>
                    <li><span class="dashicons dashicons-yes"></span> <strong>Badge Attributes</strong> - атрибуты для виджета меток</li>
                </ul>

                <div class="notice notice-warning inline">
                    <p>
                        <strong>Важно:</strong>
                        Синхронизация работает по slug категорий и атрибутов.
                        Убедитесь, что на всех сайтах существуют одинаковые категории и атрибуты с одинаковыми slug.
                    </p>
                </div>
            </div>

            <!-- Статус синхронизации -->
            <div id="wsf-sync-status" class="wsf-sync-status" style="display:none;"></div>
        </div>
        <?php
    }

    /**
     * Ajax: Экспорт настроек
     */
    public function ajax_export_settings() {
        // Проверка nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wsf_multisite_sync')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        if (!current_user_can('manage_network')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $site_id = isset($_POST['site_id']) ? intval($_POST['site_id']) : get_current_blog_id();

        // Переключаемся на нужный сайт
        switch_to_blog($site_id);

        $settings = $this->get_site_settings();

        restore_current_blog();

        wp_send_json_success([
            'settings' => $settings,
            'site_id' => $site_id,
            'site_name' => get_blog_option($site_id, 'blogname'),
            'timestamp' => current_time('mysql')
        ]);
    }

    /**
     * Ajax: Синхронизация на выбранный сайт
     */
    public function ajax_sync_to_site() {
        // Проверка nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wsf_multisite_sync')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        if (!current_user_can('manage_network')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $source_id = isset($_POST['source_id']) ? intval($_POST['source_id']) : 0;
        $target_id = isset($_POST['target_id']) ? intval($_POST['target_id']) : 0;

        if (!$source_id || !$target_id) {
            wp_send_json_error(['message' => 'Invalid site IDs']);
        }

        if ($source_id === $target_id) {
            wp_send_json_error(['message' => 'Source and target must be different']);
        }

        // Получаем настройки с источника
        switch_to_blog($source_id);
        $settings = $this->get_site_settings();
        restore_current_blog();

        // Применяем на целевой сайт
        switch_to_blog($target_id);
        $result = $this->apply_settings($settings);
        restore_current_blog();

        if ($result) {
            wp_send_json_success([
                'message' => 'Settings synced successfully',
                'source' => get_blog_option($source_id, 'blogname'),
                'target' => get_blog_option($target_id, 'blogname')
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to sync settings']);
        }
    }

    /**
     * Ajax: Синхронизация на все сайты
     */
    public function ajax_sync_to_all() {
        $source_id = intval($_POST['source_id']);
        $target_ids = $_POST['target_ids'];

        $success = 0;
        $results = [];

        // Получаем options с источника
        switch_to_blog($source_id);
        $bindings = get_option('wsf_bindings', []);
        $cat_attrs = get_option('wsf_category_attributes', []);
        $cat_display = get_option('wsf_category_display_settings', []);
        $cat_available = get_option('wsf_category_available_attributes', []);
        $cat_badge = get_option('wsf_category_badge_attributes', []);
        restore_current_blog();

        // Копируем на каждый сайт
        foreach ($target_ids as $target_id) {
            $target_id = intval($target_id);

            if ($target_id === $source_id) {
                continue;
            }

            switch_to_blog($target_id);
            update_option('wsf_bindings', $bindings);
            update_option('wsf_category_attributes', $cat_attrs);
            update_option('wsf_category_display_settings', $cat_display);
            update_option('wsf_category_available_attributes', $cat_available);
            update_option('wsf_category_badge_attributes', $cat_badge);
            $site_name = get_bloginfo('name');
            restore_current_blog();

            $success++;
            $results[] = $site_name . ' ✓';
        }

        wp_send_json_success([
            'message' => 'Синхронизировано на ' . $success . ' сайтов',
            'success' => $success,
            'failed' => 0,
            'results' => $results
        ]);
    }

    /**
     * Получение всех настроек сайта
     */
    private function get_site_settings() {
        $settings = [];

        foreach ($this->sync_options as $option_name) {
            $settings[$option_name] = get_option($option_name, []);
        }

        return $settings;
    }

    /**
     * Применение настроек на сайт
     */
    private function apply_settings($settings) {
        if (empty($settings)) {
            return false;
        }

        foreach ($this->sync_options as $option_name) {
            if (isset($settings[$option_name])) {
                update_option($option_name, $settings[$option_name]);
            }
        }

        // Очистка кэша
        if (class_exists('WSF_Cache_Manager')) {
            WSF_Cache_Manager::get_instance()->clear_all();
        }

        return true;
    }
}
