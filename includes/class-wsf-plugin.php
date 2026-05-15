<?php

if (!defined('ABSPATH')) {
    exit;
}

class WSF_Plugin {
    
    private static $instance = null;
    
    private function __construct() {
        $this->init_hooks();
        $this->init_components();
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function init_hooks() {
        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_scripts']);
    }
    
    private function init_components() {
        WSF_Cache_Manager::get_instance();
        WSF_Admin::get_instance();
        WSF_Sync::get_instance();
        WSF_Multisite_Product_Sync::get_instance();
        WSF_Filter_Manager::get_instance();
        WSF_Redirect_Handler::get_instance();
        WSF_Widget_Manager::get_instance();
        WSF_Category_Helper::get_instance();
        WSF_Query_Handler::get_instance();
        WSF_Shortcodes::get_instance();

    }
    
    public function admin_scripts($hook) {
        if ('toplevel_page_wsf-settings' === $hook) {
            wp_enqueue_style('wsf-admin', WSF_URL . 'assets/css/admin.css', [], WSF_VERSION);
            wp_enqueue_script('wsf-admin', WSF_URL . 'assets/js/admin.js', ['jquery', 'jquery-ui-sortable'], WSF_VERSION, true);
            
            wp_localize_script('wsf-admin', 'wsfAdmin', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wsf_admin_nonce')
            ]);
        }
        
        if ('seo-filter_page_wsf-attributes' === $hook) {
            wp_enqueue_style('wsf-admin', WSF_URL . 'assets/css/admin.css', [], WSF_VERSION);
            wp_enqueue_script('jquery-ui-sortable');
            
            wp_localize_script('jquery-ui-sortable', 'wsfAdmin', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wsf_admin_nonce')
            ]);
        }
    }
    
    public function frontend_scripts() {
        if (!is_shop() && !is_product_category()) {
            return;
        }
        
        wp_enqueue_style('wsf-frontend', WSF_URL . 'assets/css/frontend.css', [], WSF_VERSION);
        wp_enqueue_script('wsf-frontend', WSF_URL . 'assets/js/frontend.js', ['jquery'], WSF_VERSION, true);
    }
}