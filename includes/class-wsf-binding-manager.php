<?php

if (!defined('ABSPATH')) {
    exit;
}

class WSF_Binding_Manager {
    
    private static $instance = null;
    private $bindings = [];
    
    private function __construct() {
        // Не загружаем связки сразу
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function load_bindings() {
        if (!empty($this->bindings)) {
            return; // Уже загружены
        }
        
        $cache = WSF_Cache_Manager::get_instance();
        $cached_bindings = $cache->get('all_bindings');
        
        if (false !== $cached_bindings) {
            $this->bindings = $cached_bindings;
            return;
        }
        
        $this->bindings = get_option('wsf_bindings', []);
        $cache->set('all_bindings', $this->bindings);
    }
    
    public function get_bindings() {
        $this->load_bindings();
        return $this->bindings;
    }
    
    public function get_binding_by_slug($category_slug) {
        $this->load_bindings();
        foreach ($this->bindings as $binding) {
            if ($binding['category_slug'] === $category_slug) {
                return $binding;
            }
        }
        return null;
    }
    
    public function get_binding_by_attributes($attributes) {
        $this->load_bindings();
        foreach ($this->bindings as $binding) {
            if ($this->match_attributes($binding['attributes'], $attributes)) {
                return $binding;
            }
        }
        return null;
    }
    
    public function get_parent_binding($category_slug) {
        $this->load_bindings();
        $binding = $this->get_binding_by_slug($category_slug);
        
        if (!$binding || empty($binding['parent_slug'])) {
            return null;
        }
        
        return $this->get_binding_by_slug($binding['parent_slug']);
    }
    
    public function get_child_bindings($category_slug) {
        $this->load_bindings();
        $children = [];
        
        foreach ($this->bindings as $binding) {
            if (isset($binding['parent_slug']) && $binding['parent_slug'] === $category_slug) {
                $children[] = $binding;
            }
        }
        
        return $children;
    }
    
    public function get_category_url($category_slug, $query_params = []) {
        $category = get_term_by('slug', $category_slug, 'product_cat');
        
        if (!$category) {
            return home_url('/');
        }
        
        $url = get_term_link($category);
        
        if (!empty($query_params)) {
            $url = add_query_arg($query_params, $url);
        }
        
        return $url;
    }
    
    public function find_matching_category($current_slug, $attributes) {
        $current_binding = $this->get_binding_by_slug($current_slug);
        
        if (!$current_binding) {
            return null;
        }
        
        $parent_slug = $current_binding['parent_slug'] ?? '';
        $children = $this->get_child_bindings($parent_slug ?: $current_slug);
        
        foreach ($children as $child) {
            if ($this->match_attributes($child['attributes'], $attributes)) {
                return $child['category_slug'];
            }
        }
        
        return null;
    }
    
    private function match_attributes($binding_attrs, $query_attrs) {
        if (count($binding_attrs) !== count($query_attrs)) {
            return false;
        }
        
        foreach ($binding_attrs as $attr) {
            $found = false;
            foreach ($query_attrs as $query_attr) {
                if ($attr['attribute'] === $query_attr['attribute'] && 
                    $attr['value'] === $query_attr['value']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }
        
        return true;
    }
    
    public function get_active_filters($category_slug, $query_params) {
        $active = [];
        $binding = $this->get_binding_by_slug($category_slug);
        
        if ($binding && !empty($binding['attributes'])) {
            foreach ($binding['attributes'] as $attr) {
                $active[] = [
                    'attribute' => $attr['attribute'],
                    'value' => $attr['value'],
                    'from_category' => true
                ];
            }
        }
        
        foreach ($query_params as $key => $value) {
            if (strpos($key, 'filter_') === 0) {
                $attribute = str_replace('filter_', '', $key);
                $values = explode(',', $value);
                
                foreach ($values as $val) {
                    $active[] = [
                        'attribute' => $attribute,
                        'value' => $val,
                        'from_category' => false
                    ];
                }
            }
        }
        
        return $active;
    }
}