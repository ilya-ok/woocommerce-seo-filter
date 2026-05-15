<?php

if (!defined('ABSPATH')) {
    exit;
}

class WSF_Query_Handler {
    
    private static $instance = null;
    
    private function __construct() {
        add_action('pre_get_posts', [$this, 'modify_product_query'], 20);
        add_filter('woocommerce_product_query_tax_query', [$this, 'add_tax_query'], 10, 2);
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function modify_product_query($query) {
        if (!$query->is_main_query() || is_admin()) {
            return;
        }
        
        if (!is_shop() && !is_product_category()) {
            return;
        }
        
        $current_category_slug = '';
        
        if (is_product_category()) {
            $current_term = get_queried_object();
            $current_category_slug = $current_term->slug;
        }
        
        if ($current_category_slug) {
            $binding_manager = WSF_Binding_Manager::get_instance();
            $binding = $binding_manager->get_binding_by_slug($current_category_slug);
            
            if ($binding && !empty($binding['attributes'])) {
                $this->add_binding_attributes_to_query($query, $binding['attributes']);
            }
        }
    }
    
    private function add_binding_attributes_to_query($query, $attributes) {
        $tax_query = $query->get('tax_query') ?: [];
        
        if (!isset($tax_query['relation'])) {
            $tax_query['relation'] = 'AND';
        }
        
        foreach ($attributes as $attr) {
            $taxonomy = 'pa_' . $attr['attribute'];
            
            $tax_query[] = [
                'taxonomy' => $taxonomy,
                'field' => 'slug',
                'terms' => [$attr['value']],
                'operator' => 'IN'
            ];
        }
        
        $query->set('tax_query', $tax_query);
    }
    
    public function add_tax_query($tax_query, $product_query) {
        if (is_admin()) {
            return $tax_query;
        }
        
        $query_params = $_GET;
        
        foreach ($query_params as $key => $value) {
            if (strpos($key, 'filter_') === 0) {
                $attribute = str_replace('filter_', '', $key);
                $query_type_key = 'query_type_' . $attribute;
                $query_type = isset($query_params[$query_type_key]) ? $query_params[$query_type_key] : 'or';
                
                $values = explode(',', $value);
                $taxonomy = 'pa_' . $attribute;
                
                $tax_query[] = [
                    'taxonomy' => $taxonomy,
                    'field' => 'slug',
                    'terms' => $values,
                    'operator' => strtoupper($query_type) === 'AND' ? 'AND' : 'IN'
                ];
            }
        }
        
        return $tax_query;
    }
}