<?php

if (!defined('ABSPATH')) {
    exit;
}

class WSF_Cache_Manager {
    
    private static $instance = null;
    private static $runtime_cache = [];
    const CACHE_GROUP = 'wsf_filters';
    const CACHE_EXPIRATION = 3600; // 1 час
    
    private function __construct() {
        add_action('save_post', [$this, 'clear_product_cache']);
        add_action('edited_term', [$this, 'clear_term_cache'], 10, 3);
        add_action('create_term', [$this, 'clear_term_cache'], 10, 3);
        add_action('delete_term', [$this, 'clear_term_cache'], 10, 3);
        add_action('updated_option', [$this, 'clear_option_cache'], 10, 3);
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function get($key, $group = self::CACHE_GROUP) {
        // Проверяем runtime кэш
        $runtime_key = $group . '_' . $key;
        if (isset(self::$runtime_cache[$runtime_key])) {
            return self::$runtime_cache[$runtime_key];
        }
        
        // Проверяем object cache
        $cached = wp_cache_get($key, $group);
        
        if (false !== $cached) {
            self::$runtime_cache[$runtime_key] = $cached;
            return $cached;
        }
        
        return false;
    }
    
    public function set($key, $data, $group = self::CACHE_GROUP, $expiration = self::CACHE_EXPIRATION) {
        $runtime_key = $group . '_' . $key;
        self::$runtime_cache[$runtime_key] = $data;
        
        return wp_cache_set($key, $data, $group, $expiration);
    }
    
    public function delete($key, $group = self::CACHE_GROUP) {
        $runtime_key = $group . '_' . $key;
        unset(self::$runtime_cache[$runtime_key]);
        
        return wp_cache_delete($key, $group);
    }
    
    public function flush($group = self::CACHE_GROUP) {
        // Очищаем runtime кэш
        foreach (self::$runtime_cache as $key => $value) {
            if (strpos($key, $group . '_') === 0) {
                unset(self::$runtime_cache[$key]);
            }
        }
        
        // Очищаем object cache для группы
        wp_cache_flush();
    }
    
    public function clear_product_cache($post_id) {
        if (get_post_type($post_id) === 'product') {
            $this->flush();
        }
    }
    
    public function clear_term_cache($term_id, $tt_id, $taxonomy) {
        if (strpos($taxonomy, 'pa_') === 0 || $taxonomy === 'product_cat') {
            $this->flush();
        }
    }
    
    public function clear_option_cache($option, $old_value, $value) {
        if (strpos($option, 'wsf_') === 0) {
            $this->flush();
        }
    }
    
    public function get_product_count_cache_key($attribute, $value, $category_slug, $query_params) {
        return 'count_' . md5(serialize([
            'attr' => $attribute,
            'val' => $value,
            'cat' => $category_slug,
            'query' => $query_params
        ]));
    }
}