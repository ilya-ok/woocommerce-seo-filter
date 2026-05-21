<?php

if (!defined('ABSPATH')) {
    exit;
}

class WSF_Shortcodes {
    
    private static $instance = null;
    
    private function __construct() {
        add_shortcode('wsf_filters', [$this, 'render_filters']);
        add_shortcode('wsf_active_filters', [$this, 'render_active_filters']);
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function render_filters($atts) {
        $atts = shortcode_atts([
            'attribute' => '',
            'title' => '',
            'type' => 'list'
        ], $atts);
        
        if (empty($atts['attribute'])) {
            return '';
        }
        
        if (!is_shop() && !is_product_category()) {
            return '';
        }
        
        ob_start();
        
        $current_category_slug = '';
        $current_params = $_GET;
        
        if (is_product_category()) {
            $current_term = get_queried_object();
            $current_category_slug = $current_term->slug;
        }
        
        $attribute_values = $this->get_attribute_values($atts['attribute'], $current_category_slug);
        
        if (empty($attribute_values)) {
            return '';
        }
        
        if (!empty($atts['title'])) {
            echo '<h3 class="wsf-shortcode-title">' . esc_html($atts['title']) . '</h3>';
        }
        
        $this->render_filter_list($atts['attribute'], $attribute_values, $current_category_slug, $current_params, $atts['type']);
        
        return ob_get_clean();
    }
    
    private function get_attribute_values($attribute, $current_category_slug) {
        $binding_manager = WSF_Binding_Manager::get_instance();
        $values = [];
        
        if ($current_category_slug) {
            $children = $binding_manager->get_child_bindings($current_category_slug);
            
            foreach ($children as $child) {
                foreach ($child['attributes'] as $attr) {
                    if ($attr['attribute'] === $attribute) {
                        $term = get_term_by('slug', $attr['value'], 'pa_' . $attribute);
                        if ($term) {
                            $values[] = [
                                'slug' => $attr['value'],
                                'name' => $term->name,
                                'category_slug' => $child['category_slug']
                            ];
                        }
                    }
                }
            }
        }
        
        $terms = get_terms([
            'taxonomy' => 'pa_' . $attribute,
            'hide_empty' => false,
        ]);
        
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $exists = false;
                foreach ($values as $val) {
                    if ($val['slug'] === $term->slug) {
                        $exists = true;
                        break;
                    }
                }

                if (!$exists) {
                    $values[] = [
                        'slug' => $term->slug,
                        'name' => $term->name,
                        'category_slug' => null
                    ];
                }
            }

            // Сортируем по порядку значений атрибута, установленному в WooCommerce (edit-tags.php)
            if (!empty($values)) {
                $order_map = [];
                foreach ($terms as $term) {
                    $order_map[$term->slug] = (int) get_term_meta($term->term_id, 'order', true);
                }
                usort($values, function ($a, $b) use ($order_map) {
                    $oa = isset($order_map[$a['slug']]) ? $order_map[$a['slug']] : PHP_INT_MAX;
                    $ob = isset($order_map[$b['slug']]) ? $order_map[$b['slug']] : PHP_INT_MAX;
                    return $oa - $ob;
                });
            }
        }

        return $values;
    }

    private function render_filter_list($attribute, $values, $current_category_slug, $current_params, $type) {
        $active_filters = $this->get_active_filter_values($attribute, $current_category_slug, $current_params);
        
        echo '<div class="wsf-shortcode-filter" data-attribute="' . esc_attr($attribute) . '">';
        
        if ($type === 'dropdown') {
            echo '<select class="wsf-filter-dropdown" data-attribute="' . esc_attr($attribute) . '">';
            echo '<option value="">Выберите...</option>';
            
            foreach ($values as $value) {
                $is_active = in_array($value['slug'], $active_filters);
                $filter_manager = WSF_Filter_Manager::get_instance();
                
                $url = $filter_manager->get_filter_url(
                    $attribute,
                    $value['slug'],
                    $current_category_slug,
                    $current_params
                );
                
                echo '<option value="' . esc_url($url) . '" ' . selected($is_active, true, false) . '>';
                echo esc_html($value['name']);
                echo '</option>';
            }
            
            echo '</select>';
        } else {
            echo '<ul class="wsf-filter-list">';
            
            foreach ($values as $value) {
                $is_active = in_array($value['slug'], $active_filters);
                $filter_manager = WSF_Filter_Manager::get_instance();
                
                $url = $filter_manager->get_filter_url(
                    $attribute,
                    $value['slug'],
                    $current_category_slug,
                    $current_params
                );
                
                $class = $is_active ? 'wsf-filter-item active' : 'wsf-filter-item';
                
                echo '<li class="' . esc_attr($class) . '" data-slug="' . esc_attr($value['slug']) . '" data-count="' . intval($value['count']) . '">';
                echo '<a href="' . esc_url($url) . '">';
                
                if ($is_active) {
                    echo '<span class="wsf-checkbox checked">✓</span>';
                } else {
                    echo '<span class="wsf-checkbox"></span>';
                }
                
                echo esc_html($value['name']);
                echo '</a>';
                echo '</li>';
            }
            
            echo '</ul>';
        }
        
        echo '</div>';
    }
    
    private function get_active_filter_values($attribute, $current_category_slug, $current_params) {
        $active = [];
        
        if ($current_category_slug) {
            $binding_manager = WSF_Binding_Manager::get_instance();
            $binding = $binding_manager->get_binding_by_slug($current_category_slug);
            
            if ($binding && !empty($binding['attributes'])) {
                foreach ($binding['attributes'] as $attr) {
                    if ($attr['attribute'] === $attribute) {
                        $active[] = $attr['value'];
                    }
                }
            }
        }
        
        $filter_key = 'filter_' . $attribute;
        if (isset($current_params[$filter_key])) {
            $values = explode(',', $current_params[$filter_key]);
            $active = array_merge($active, $values);
        }
        
        return array_unique($active);
    }
    
    public function render_active_filters($atts) {
        $atts = shortcode_atts([
            'title' => 'Активные фильтры'
        ], $atts);
        
        if (!is_shop() && !is_product_category()) {
            return '';
        }
        
        $current_category_slug = '';
        $current_params = $_GET;
        
        if (is_product_category()) {
            $current_term = get_queried_object();
            $current_category_slug = $current_term->slug;
        }
        
        $active_filters = $this->get_all_active_filters($current_category_slug, $current_params);
        
        if (empty($active_filters)) {
            return '';
        }
        
        ob_start();
        
        echo '<div class="wsf-active-filters wsf-shortcode-active-filters">';
        
        if (!empty($atts['title'])) {
            echo '<h3 class="wsf-active-filters-title">' . esc_html($atts['title']) . '</h3>';
        }
        
        echo '<ul class="wsf-active-filters-list">';
        
        foreach ($active_filters as $filter) {
            echo '<li class="wsf-active-filter-item">';
            echo '<span class="wsf-filter-label">' . esc_html($filter['attribute_label']) . ':</span> ';
            echo '<span class="wsf-filter-value">' . esc_html($filter['value_name']) . '</span>';
            echo '<a href="' . esc_url($filter['remove_url']) . '" class="wsf-remove-filter" title="Удалить фильтр">';
            echo '<span class="wsf-remove-icon">×</span>';
            echo '</a>';
            echo '</li>';
        }
        
        echo '</ul>';
        
        $reset_url = $this->get_reset_url($current_category_slug);
        echo '<a href="' . esc_url($reset_url) . '" class="wsf-reset-filters button">Сбросить все фильтры</a>';
        
        echo '</div>';
        
        return ob_get_clean();
    }
    
    private function get_all_active_filters($current_category_slug, $current_params) {
        $active_filters = [];
        $binding_manager = WSF_Binding_Manager::get_instance();
        
        $hierarchy = $this->build_category_hierarchy($current_category_slug);
        
        foreach ($hierarchy as $category_slug) {
            $binding = $binding_manager->get_binding_by_slug($category_slug);
            
            if ($binding && !empty($binding['attributes'])) {
                foreach ($binding['attributes'] as $attr) {
                    $term = get_term_by('slug', $attr['value'], 'pa_' . $attr['attribute']);
                    $attribute_label = $this->get_attribute_label($attr['attribute']);
                    
                    if ($term) {
                        $active_filters[] = [
                            'type' => 'category',
                            'attribute' => $attr['attribute'],
                            'attribute_label' => $attribute_label,
                            'value' => $attr['value'],
                            'value_name' => $term->name,
                            'category_slug' => $category_slug,
                            'remove_url' => $this->get_remove_url($category_slug, $attr, $current_params)
                        ];
                    }
                }
            }
        }
        
        foreach ($current_params as $key => $value) {
            if (strpos($key, 'filter_') === 0) {
                $attribute = str_replace('filter_', '', $key);
                $values = explode(',', $value);
                $attribute_label = $this->get_attribute_label($attribute);
                
                foreach ($values as $val) {
                    $term = get_term_by('slug', $val, 'pa_' . $attribute);
                    
                    if ($term) {
                        $active_filters[] = [
                            'type' => 'query',
                            'attribute' => $attribute,
                            'attribute_label' => $attribute_label,
                            'value' => $val,
                            'value_name' => $term->name,
                            'category_slug' => null,
                            'remove_url' => $this->get_remove_query_url($attribute, $val, $current_params, $current_category_slug)
                        ];
                    }
                }
            }
        }
        
        return $active_filters;
    }
    
    private function build_category_hierarchy($category_slug) {
        $hierarchy = [];
        
        if (empty($category_slug)) {
            return $hierarchy;
        }
        
        $binding_manager = WSF_Binding_Manager::get_instance();
        $current_slug = $category_slug;
        
        while ($current_slug) {
            $hierarchy[] = $current_slug;
            $binding = $binding_manager->get_binding_by_slug($current_slug);
            
            if (!$binding || empty($binding['parent_slug']) || $binding['parent_slug'] === 'woocommerce') {
                break;
            }
            
            $current_slug = $binding['parent_slug'];
        }
        
        return array_reverse($hierarchy);
    }
    
    private function get_attribute_label($attribute_slug) {
        $attributes = wc_get_attribute_taxonomies();
        
        foreach ($attributes as $attr) {
            if (wc_attribute_taxonomy_slug($attr->attribute_name) === $attribute_slug) {
                return $attr->attribute_label;
            }
        }
        
        return ucfirst(str_replace('-', ' ', $attribute_slug));
    }
    
    private function get_remove_url($current_category_slug, $attr_to_remove, $current_params) {
        return '#';
    }
    
    private function get_remove_query_url($attribute, $value, $current_params, $current_category_slug) {
        $new_params = $current_params;
        $filter_key = 'filter_' . $attribute;
        
        if (isset($new_params[$filter_key])) {
            $values = explode(',', $new_params[$filter_key]);
            $values = array_filter($values, function($v) use ($value) {
                return $v !== $value;
            });
            
            if (empty($values)) {
                unset($new_params[$filter_key]);
                unset($new_params['query_type_' . $attribute]);
            } else {
                $new_params[$filter_key] = implode(',', $values);
            }
        }
        
        $binding_manager = WSF_Binding_Manager::get_instance();
        $base_url = $current_category_slug 
            ? $binding_manager->get_category_url($current_category_slug)
            : wc_get_page_permalink('shop');
        
        if (empty($new_params)) {
            return $base_url;
        }
        
        return add_query_arg($new_params, $base_url);
    }
    
    private function get_reset_url($current_category_slug) {
        $binding_manager = WSF_Binding_Manager::get_instance();
        
        if ($current_category_slug) {
            $binding = $binding_manager->get_binding_by_slug($current_category_slug);
            
            if ($binding) {
                $parent_slug = $binding['parent_slug'] ?? '';
                
                while ($parent_slug && $parent_slug !== 'woocommerce') {
                    $parent_binding = $binding_manager->get_binding_by_slug($parent_slug);
                    
                    if (!$parent_binding) {
                        break;
                    }
                    
                    if (empty($parent_binding['attributes'])) {
                        return $binding_manager->get_category_url($parent_slug);
                    }
                    
                    $parent_slug = $parent_binding['parent_slug'] ?? '';
                }
            }
        }
        
        return wc_get_page_permalink('shop');
    }
}