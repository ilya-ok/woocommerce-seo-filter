<?php

if (!defined('ABSPATH')) {
    exit;
}

class WSF_Installer {
    
    public static function activate() {
        self::create_options();
        flush_rewrite_rules();
    }
    
    public static function deactivate() {
        flush_rewrite_rules();
    }
    
    private static function create_options() {
        $default_options = [
            'bindings' => [],
            'category_attributes' => [],
            'category_display_settings' => [],
            'category_available_attributes' => []
        ];
        
        add_option('wsf_bindings', $default_options['bindings']);
        add_option('wsf_category_attributes', $default_options['category_attributes']);
        add_option('wsf_category_display_settings', $default_options['category_display_settings']);
        add_option('wsf_category_available_attributes', $default_options['category_available_attributes']);
    }
}