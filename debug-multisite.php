<?php
/**
 * Debug script для проверки мультисайта
 *
 * Загрузите этот файл на сервер в корень плагина и откройте:
 * https://ural-floor.ru/wp-content/plugins/WooCommerce%20SEO%20Filter%20claude/debug-multisite.php
 */

// Загружаем WordPress
require_once('../../../wp-load.php');

// Проверка прав
if (!current_user_can('manage_network')) {
    die('Access denied. You need Super Admin rights.');
}

echo '<h1>WooCommerce SEO Filter - Multisite Debug</h1>';
echo '<style>body{font-family:monospace;padding:20px;background:#f0f0f0;} .ok{color:green;font-weight:bold;} .error{color:red;font-weight:bold;} .info{color:blue;} pre{background:#fff;padding:10px;border:1px solid #ccc;}</style>';

echo '<h2>1. WordPress Multisite Check</h2>';
echo '<p>is_multisite(): <span class="' . (is_multisite() ? 'ok">TRUE ✓' : 'error">FALSE ✗') . '</span></p>';

if (is_multisite()) {
    echo '<p>Network ID: ' . get_current_network_id() . '</p>';
    echo '<p>Current Site ID: ' . get_current_blog_id() . '</p>';
    echo '<p>Sites in network: ' . count(get_sites(['number' => 0])) . '</p>';
}

echo '<h2>2. Class Check</h2>';
echo '<p>Class WSF_Multisite_Sync exists: <span class="' . (class_exists('WSF_Multisite_Sync') ? 'ok">TRUE ✓' : 'error">FALSE ✗') . '</span></p>';

if (class_exists('WSF_Multisite_Sync')) {
    echo '<p>Instance created: ';
    try {
        $instance = WSF_Multisite_Sync::get_instance();
        echo '<span class="ok">TRUE ✓</span></p>';
    } catch (Exception $e) {
        echo '<span class="error">FALSE ✗ - ' . $e->getMessage() . '</span></p>';
    }
}

echo '<h2>3. Network Admin Menu Hooks</h2>';
global $wp_filter;
if (isset($wp_filter['network_admin_menu'])) {
    echo '<p>network_admin_menu hook has ' . count($wp_filter['network_admin_menu']->callbacks) . ' callbacks:</p>';
    echo '<pre>';
    foreach ($wp_filter['network_admin_menu']->callbacks as $priority => $callbacks) {
        foreach ($callbacks as $callback) {
            if (is_array($callback['function'])) {
                $class = is_object($callback['function'][0]) ? get_class($callback['function'][0]) : $callback['function'][0];
                echo "Priority $priority: {$class}::{$callback['function'][1]}\n";
            } else {
                echo "Priority $priority: {$callback['function']}\n";
            }
        }
    }
    echo '</pre>';
} else {
    echo '<p class="error">network_admin_menu hook not found ✗</p>';
}

echo '<h2>4. Menu Check</h2>';
global $submenu;
if (isset($submenu['settings.php'])) {
    echo '<p>Settings submenu items:</p><pre>';
    print_r($submenu['settings.php']);
    echo '</pre>';
} else {
    echo '<p class="info">Run this from Network Admin context</p>';
}

echo '<h2>5. Plugin Activation</h2>';
$active_plugins = get_site_option('active_sitewide_plugins', []);
$plugin_file = 'WooCommerce SEO Filter claude/woo-seo-filter.php';
echo '<p>Network activated: <span class="' . (isset($active_plugins[$plugin_file]) ? 'ok">TRUE ✓' : 'error">FALSE ✗') . '</span></p>';
echo '<p>Active sitewide plugins:</p><pre>';
print_r(array_keys($active_plugins));
echo '</pre>';

echo '<h2>6. Constants</h2>';
echo '<pre>';
echo 'WSF_PATH: ' . (defined('WSF_PATH') ? WSF_PATH : 'NOT DEFINED') . "\n";
echo 'WSF_URL: ' . (defined('WSF_URL') ? WSF_URL : 'NOT DEFINED') . "\n";
echo 'WSF_VERSION: ' . (defined('WSF_VERSION') ? WSF_VERSION : 'NOT DEFINED') . "\n";
echo '</pre>';

echo '<h2>7. Current User</h2>';
$user = wp_get_current_user();
echo '<p>User ID: ' . $user->ID . '</p>';
echo '<p>User Login: ' . $user->user_login . '</p>';
echo '<p>Can manage_network: <span class="' . (current_user_can('manage_network') ? 'ok">TRUE ✓' : 'error">FALSE ✗') . '</span></p>';

echo '<h2>8. Log File Location</h2>';
if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
    $log_file = is_string(WP_DEBUG_LOG) ? WP_DEBUG_LOG : WP_CONTENT_DIR . '/debug.log';
    echo '<p>Log file: ' . $log_file . '</p>';
    echo '<p>Exists: <span class="' . (file_exists($log_file) ? 'ok">TRUE ✓' : 'error">FALSE ✗') . '</span></p>';
    if (file_exists($log_file)) {
        echo '<p>Last 20 lines with "WSF":</p>';
        echo '<pre>';
        $lines = file($log_file);
        $filtered = array_filter($lines, function($line) {
            return stripos($line, 'WSF') !== false;
        });
        echo htmlspecialchars(implode('', array_slice($filtered, -20)));
        echo '</pre>';
    }
} else {
    echo '<p class="error">WP_DEBUG_LOG is not enabled</p>';
    echo '<p>Add to wp-config.php:</p>';
    echo '<pre>define(\'WP_DEBUG\', true);
define(\'WP_DEBUG_LOG\', true);
define(\'WP_DEBUG_DISPLAY\', false);</pre>';
}

echo '<hr>';
echo '<p><a href="' . network_admin_url('settings.php?page=wsf-multisite-sync') . '">Try to open SEO Filter Sync page</a></p>';
?>
