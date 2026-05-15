<?php

if (!defined('ABSPATH')) {
    exit;
}

class WSF_Admin {
    
    private static $instance = null;
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('wp_ajax_wsf_save_bindings', [$this, 'save_bindings']);
        add_action('wp_ajax_wsf_get_category_data', [$this, 'get_category_data']);
        add_action('wp_ajax_wsf_get_all_attributes', [$this, 'ajax_get_all_attributes']);
        add_action('wp_ajax_wsf_save_category_attributes', [$this, 'save_category_attributes']);
        add_action('wp_ajax_wsf_get_category_available_attributes', [$this, 'ajax_get_category_available_attributes']);
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function add_menu() {
        add_menu_page(
            'SEO Filter Settings',
            'SEO Filter',
            'manage_options',
            'wsf-settings',
            [$this, 'render_settings_page'],
            'dashicons-filter',
            56
        );
        
        add_submenu_page(
            'wsf-settings',
            'Filter Bindings',
            'Filter Bindings',
            'manage_options',
            'wsf-settings',
            [$this, 'render_settings_page']
        );
        
        add_submenu_page(
            'wsf-settings',
            'Filter Attributes',
            'Filter Attributes',
            'manage_options',
            'wsf-attributes',
            [$this, 'render_attributes_page']
        );
    }
    
    public function render_settings_page() {
        include WSF_PATH . 'templates/admin/settings.php';
    }
    
    public function render_attributes_page() {
        include WSF_PATH . 'templates/admin/attributes.php';
    }
    
    public function save_bindings() {
        check_ajax_referer('wsf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $bindings = isset($_POST['bindings']) ? $_POST['bindings'] : [];
        
        update_option('wsf_bindings', $bindings);
        
        wp_send_json_success(['message' => 'Bindings saved successfully']);
    }
    
    public function get_category_data() {
        check_ajax_referer('wsf_admin_nonce', 'nonce');
        
        $category_slug = isset($_POST['category_slug']) ? sanitize_text_field($_POST['category_slug']) : '';
        
        if (empty($category_slug)) {
            wp_send_json_error(['message' => 'Category slug required']);
        }
        
        $category = get_term_by('slug', $category_slug, 'product_cat');
        
        if (!$category) {
            wp_send_json_error(['message' => 'Category not found']);
        }
        
        $parent_binding = WSF_Binding_Manager::get_instance()->get_parent_binding($category_slug);
        $attributes = [];
        
        if ($parent_binding) {
            $attributes = $parent_binding['attributes'];
        }
        
        wp_send_json_success([
            'attributes' => $attributes,
            'category_id' => $category->term_id
        ]);
    }
    
    public function ajax_get_all_attributes() {
        check_ajax_referer('wsf_admin_nonce', 'nonce');
        
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        $attributes = [];
        $terms = [];
        
        foreach ($attribute_taxonomies as $tax) {
            $attribute_slug = wc_attribute_taxonomy_slug($tax->attribute_name);
            $attributes[$attribute_slug] = $tax->attribute_label;
            
            $attribute_terms = get_terms([
                'taxonomy' => 'pa_' . $attribute_slug,
                'hide_empty' => false,
            ]);
            
            $terms[$attribute_slug] = [];
            
            if (!is_wp_error($attribute_terms)) {
                foreach ($attribute_terms as $term) {
                    $terms[$attribute_slug][] = [
                        'slug' => $term->slug,
                        'name' => $term->name
                    ];
                }
            }
        }
        
        wp_send_json_success([
            'attributes' => $attributes,
            'terms' => $terms
        ]);
    }
    
    public function save_category_attributes() {
        check_ajax_referer('wsf_save_attributes', 'wsf_attributes_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $category_attributes = isset($_POST['category_attributes']) ? $_POST['category_attributes'] : [];
        $category_display = isset($_POST['category_display']) ? $_POST['category_display'] : [];
        $category_available_attrs = isset($_POST['category_available_attributes']) ? $_POST['category_available_attributes'] : [];
        $category_badge_attrs = isset($_POST['category_badge_attributes']) ? $_POST['category_badge_attributes'] : [];
        
        update_option('wsf_category_attributes', $category_attributes);
        update_option('wsf_category_display_settings', $category_display);
        update_option('wsf_category_available_attributes', $category_available_attrs);
        update_option('wsf_category_badge_attributes', $category_badge_attrs);
        
        wp_send_json_success(['message' => 'Settings saved']);
    }
    
    public function ajax_get_category_available_attributes() {
        check_ajax_referer('wsf_admin_nonce', 'nonce');
        
        $category_slug = isset($_POST['category_slug']) ? sanitize_text_field($_POST['category_slug']) : '';
        
        if (empty($category_slug)) {
            wp_send_json_error(['message' => 'Category slug required']);
        }
        
        $category_available_attrs = get_option('wsf_category_available_attributes', []);
        
        $available_attributes = isset($category_available_attrs[$category_slug]) 
            ? $category_available_attrs[$category_slug] 
            : [];
        
        // Если нет настроенных атрибутов, возвращаем все
        if (empty($available_attributes)) {
            $all_attrs = wc_get_attribute_taxonomies();
            foreach ($all_attrs as $attr) {
                $available_attributes[] = wc_attribute_taxonomy_slug($attr->attribute_name);
            }
        }
        
        wp_send_json_success([
            'attributes' => $available_attributes
        ]);
    }
}