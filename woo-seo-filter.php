<?php
/**
 * Plugin Name: WooCommerce SEO Filter Claude
 * Description: SEO-фильтр для WooCommerce с категориями
 * Version: 1.0.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WSF_PATH', plugin_dir_path(__FILE__));
define('WSF_URL', plugin_dir_url(__FILE__));
define('WSF_VERSION', '1.0.0');

require_once WSF_PATH . 'includes/class-autoloader.php';

WSF_Autoloader::init();

function wsf_init() {
    WSF_Plugin::get_instance();

    // Инициализация синхронизации для мультисайта
    if (is_multisite()) {
        WSF_Multisite_Sync::get_instance();
    }
}
add_action('plugins_loaded', 'wsf_init');

register_activation_hook(__FILE__, ['WSF_Installer', 'activate']);
register_deactivation_hook(__FILE__, ['WSF_Installer', 'deactivate']);