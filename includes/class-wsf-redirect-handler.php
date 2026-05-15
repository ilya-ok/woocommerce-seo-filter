<?php

if (!defined('ABSPATH')) {
    exit;
}

class WSF_Redirect_Handler {
    
    private static $instance = null;
    
    private function __construct() {
        add_action('template_redirect', [$this, 'handle_redirect'], 1);
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function handle_redirect() {
        if (!is_product_category() && !is_shop()) {
            return;
        }
        
        $query_params = $_GET;
        $attributes = $this->extract_attributes_from_query($query_params);
        
        if (empty($attributes)) {
            return;
        }
        
        $current_category_slug = '';
        if (is_product_category()) {
            $current_term = get_queried_object();
            $current_category_slug = $current_term->slug;
        }
        
        $binding_manager = WSF_Binding_Manager::get_instance();
        
        if ($current_category_slug) {
            $current_binding = $binding_manager->get_binding_by_slug($current_category_slug);
            if ($current_binding && !empty($current_binding['attributes'])) {
                $attributes = array_merge($current_binding['attributes'], $attributes);
            }
        }
        
        $matching_binding = $binding_manager->get_binding_by_attributes($attributes);
        
        if (!$matching_binding) {
            return;
        }
        
        $other_params = $this->get_non_filter_params($query_params);
        $redirect_url = $binding_manager->get_category_url($matching_binding['category_slug'], $other_params);
        
        if ($redirect_url !== $this->get_current_url()) {
            wp_redirect($redirect_url, 301);
            exit;
        }
    }
    
    private function extract_attributes_from_query($query_params) {
        $attributes = [];
        
        foreach ($query_params as $key => $value) {
            if (strpos($key, 'filter_') === 0) {
                $attribute = str_replace('filter_', '', $key);
                $values = explode(',', $value);
                
                foreach ($values as $val) {
                    $attributes[] = [
                        'attribute' => $attribute,
                        'value' => sanitize_text_field($val)
                    ];
                }
            }
        }
        
        return $attributes;
    }
    
    private function get_non_filter_params($query_params) {
        $filtered = [];
        
        foreach ($query_params as $key => $value) {
            if (strpos($key, 'filter_') !== 0 && strpos($key, 'query_type_') !== 0) {
                $filtered[$key] = $value;
            }
        }
        
        return $filtered;
    }
    
    private function get_current_url() {
        return (is_ssl() ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
}