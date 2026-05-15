<?php

if (!defined('ABSPATH')) {
    exit;
}

class WSF_Category_Helper {
    
    private static $instance = null;
    
    private function __construct() {
        add_filter('woocommerce_layered_nav_link', [$this, 'modify_layered_nav_link'], 10, 3);
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function modify_layered_nav_link($link, $term, $taxonomy) {
        $attribute = str_replace('pa_', '', $taxonomy);
        
        $current_category_slug = '';
        $current_params = $_GET;
        
        if (is_product_category()) {
            $current_term = get_queried_object();
            $current_category_slug = $current_term->slug;
        }
        
        $filter_manager = WSF_Filter_Manager::get_instance();
        $new_link = $filter_manager->get_filter_url(
            $attribute,
            $term->slug,
            $current_category_slug,
            $current_params
        );
        
        return $new_link;
    }
    
    public function should_display_filters($category_slug) {
        $display_settings = get_option('wsf_category_display_settings', []);
        
        // Если это главная страница магазина
        if (empty($category_slug)) {
            $mode = isset($display_settings['shop']) ? $display_settings['shop'] : 'filters';
            return in_array($mode, ['filters', 'both']);
        }
        
        if (!isset($display_settings[$category_slug])) {
            return true;
        }
        
        $mode = $display_settings[$category_slug];
        return in_array($mode, ['filters', 'both']);
    }
    
    public function should_display_subcategories($category_slug) {
        $display_settings = get_option('wsf_category_display_settings', []);
        
        // Если это главная страница магазина
        if (empty($category_slug)) {
            $mode = isset($display_settings['shop']) ? $display_settings['shop'] : 'filters';
            return in_array($mode, ['subcategories', 'both']);
        }
        
        if (!isset($display_settings[$category_slug])) {
            return true;
        }
        
        $mode = $display_settings[$category_slug];
        return in_array($mode, ['subcategories', 'both']);
    }
    
    public function get_category_attributes($category_slug) {
        $category_attributes = get_option('wsf_category_attributes', []);
        $binding_manager = WSF_Binding_Manager::get_instance();
        
        $attr_source = isset($category_attributes[$category_slug]) 
            ? $category_attributes[$category_slug] 
            : 'parent';
        
        if ($attr_source === 'own') {
            $binding = $binding_manager->get_binding_by_slug($category_slug);
            return $binding ? ($binding['attributes'] ?? []) : [];
        }
        
        $parent_binding = $binding_manager->get_parent_binding($category_slug);
        return $parent_binding ? ($parent_binding['attributes'] ?? []) : [];
    }
    
    public function get_available_attributes_for_category($category_slug) {
        $cache = WSF_Cache_Manager::get_instance();
        $cache_key = 'avail_attrs_' . $category_slug;
        
        $cached = $cache->get($cache_key);
        if (false !== $cached) {
            return $cached;
        }
        
        $category_available_attrs = get_option('wsf_category_available_attributes', []);
        
        // Если это главная страница магазина
        if (empty($category_slug)) {
            $available = isset($category_available_attrs['shop']) 
                ? $category_available_attrs['shop'] 
                : [];
        } else {
            $available = isset($category_available_attrs[$category_slug]) 
                ? $category_available_attrs[$category_slug] 
                : [];
            
            // Если нет настроенных атрибутов для этой категории, проверяем родителя
            if (empty($available)) {
                $category_attributes = get_option('wsf_category_attributes', []);
                $attr_source = isset($category_attributes[$category_slug]) 
                    ? $category_attributes[$category_slug] 
                    : 'parent';
                
                if ($attr_source === 'parent') {
                    $category = get_term_by('slug', $category_slug, 'product_cat');
                    if ($category && $category->parent) {
                        $parent_category = get_term($category->parent, 'product_cat');
                        if ($parent_category && !is_wp_error($parent_category)) {
                            $available = isset($category_available_attrs[$parent_category->slug]) 
                                ? $category_available_attrs[$parent_category->slug] 
                                : [];
                        }
                    }
                }
            }
        }
        
        // Если всё ещё пусто, возвращаем все атрибуты
        if (empty($available)) {
            $all_attrs = wc_get_attribute_taxonomies();
            foreach ($all_attrs as $attr) {
                $available[] = wc_attribute_taxonomy_slug($attr->attribute_name);
            }
        }

        // Убираем дубликаты
        $available = array_unique($available);

        $cache->set($cache_key, $available);

        return $available;
    }
}