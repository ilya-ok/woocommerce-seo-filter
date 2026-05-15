<?php
/**
 * Тестовый файл для проверки Ajax
 * Откройте: https://ural-floor.ru/wp-content/plugins/WooCommerce%20SEO%20Filter%20claude/test-ajax.php
 */

// Загружаем WordPress
require_once('../../../wp-load.php');

// Проверка прав
if (!current_user_can('manage_network')) {
    die('Access denied');
}

header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => 'Ajax test endpoint works!',
    'data' => [
        'is_multisite' => is_multisite(),
        'current_site_id' => get_current_blog_id(),
        'user_can_manage_network' => current_user_can('manage_network'),
        'class_exists' => class_exists('WSF_Multisite_Sync'),
        'wp_version' => get_bloginfo('version'),
        'php_version' => PHP_VERSION
    ]
]);
