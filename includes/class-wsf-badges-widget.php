<?php

if (!defined('ABSPATH')) {
    exit;
}

class WSF_Badges_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'wsf_badges_widget',
            'SEO Filter Badges',
            ['description' => 'Виджет меток фильтров - компактное отображение активных атрибутов']
        );
    }
    
    public function widget($args, $instance) {
        if (!is_shop() && !is_product_category()) {
            return;
        }
        
        $title = !empty($instance['title']) ? $instance['title'] : '';
        $display_type = !empty($instance['display_type']) ? $instance['display_type'] : 'inline';
        
        $current_category_slug = '';
        $current_params = $_GET;
        
        if (is_product_category()) {
            $current_term = get_queried_object();
            $current_category_slug = $current_term->slug;
        }
        
        // Получаем атрибуты для меток
        $badge_attributes = $this->get_badge_attributes($current_category_slug);
        
        if (empty($badge_attributes)) {
            return;
        }
        
        echo $args['before_widget'];
        
        if ($title) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }
        
        $this->render_badges($badge_attributes, $current_category_slug, $current_params, $display_type);
        
        echo $args['after_widget'];
    }
    
    private function get_badge_attributes($current_category_slug) {
        $category_badge_attrs = get_option('wsf_category_badge_attributes', []);

        // Если это главная страница магазина
        if (empty($current_category_slug)) {
            $badge_attrs = isset($category_badge_attrs['shop']) ? $category_badge_attrs['shop'] : [];

            // Если пусто, используем атрибуты для фильтров
            if (empty($badge_attrs)) {
                $category_helper = WSF_Category_Helper::get_instance();
                $badge_attrs = $category_helper->get_available_attributes_for_category('');
            }

            return $badge_attrs;
        }

        // Проверяем атрибуты для текущей категории
        $badge_attrs = isset($category_badge_attrs[$current_category_slug])
            ? $category_badge_attrs[$current_category_slug]
            : [];

        // Если пусто, проверяем родительскую категорию
        if (empty($badge_attrs)) {
            $category_attributes = get_option('wsf_category_attributes', []);
            $attr_source = isset($category_attributes[$current_category_slug])
                ? $category_attributes[$current_category_slug]
                : 'parent';

            if ($attr_source === 'parent') {
                $category = get_term_by('slug', $current_category_slug, 'product_cat');
                if ($category && $category->parent) {
                    $parent_category = get_term($category->parent, 'product_cat');
                    if ($parent_category && !is_wp_error($parent_category)) {
                        // Рекурсивно получаем атрибуты родителя
                        $badge_attrs = $this->get_badge_attributes($parent_category->slug);
                    }
                }
            }
        }

        // Если всё ещё пусто, используем атрибуты для фильтров
        if (empty($badge_attrs)) {
            $category_helper = WSF_Category_Helper::get_instance();
            $badge_attrs = $category_helper->get_available_attributes_for_category($current_category_slug);
        }

        return array_unique($badge_attrs);
    }
    
    private function render_badges($badge_attributes, $current_category_slug, $current_params, $display_type) {
        $class = $display_type === 'vertical' ? 'wsf-badges-vertical' : 'wsf-badges-inline';
        
        echo '<div class="wsf-badges-container ' . esc_attr($class) . '">';
        
        foreach ($badge_attributes as $attribute) {
            $this->render_single_badge($attribute, $current_category_slug, $current_params);
        }
        
        echo '</div>';
    }
    
    private function render_single_badge($attribute, $current_category_slug, $current_params) {
        $attribute_values = $this->get_attribute_values($attribute, $current_category_slug);

        if (empty($attribute_values)) {
            return;
        }

        $attribute_label = $this->get_attribute_label($attribute);
        $active_values = $this->get_active_values($attribute, $current_category_slug, $current_params);

        // Сортируем: активные и с товарами вверху
        usort($attribute_values, function($a, $b) use ($active_values) {
            $a_is_active = in_array($a['slug'], $active_values);
            $b_is_active = in_array($b['slug'], $active_values);
            $a_has_products = $a['count'] > 0;
            $b_has_products = $b['count'] > 0;

            // Активные всегда выше
            if ($a_is_active && !$b_is_active) return -1;
            if (!$a_is_active && $b_is_active) return 1;

            // Среди неактивных: с товарами выше чем без товаров
            if (!$a_is_active && !$b_is_active) {
                if ($a_has_products && !$b_has_products) return -1;
                if (!$a_has_products && $b_has_products) return 1;
            }

            return 0;
        });

        echo '<div class="wsf-badge-group" data-attribute="' . esc_attr($attribute) . '">';
        echo '<span class="wsf-badge-label">' . esc_html($attribute_label) . ':</span>';
        echo '<div class="wsf-badge-items-wrapper">';
        echo '<div class="wsf-badge-items">';

        foreach ($attribute_values as $value) {
            $is_active = in_array($value['slug'], $active_values);
            $has_products = $value['count'] > 0;

            $filter_manager = WSF_Filter_Manager::get_instance();
            $url = $filter_manager->get_filter_url(
                $attribute,
                $value['slug'],
                $current_category_slug,
                $current_params
            );

            $badge_class = 'wsf-badge';
            if ($is_active) {
                $badge_class .= ' active';
            }
            if (!$has_products && !$is_active) {
                $badge_class .= ' disabled';
            }

            if ($has_products || $is_active) {
                echo '<a href="' . esc_url($url) . '" class="' . esc_attr($badge_class) . '">';
                echo esc_html($value['name']);
                echo ' <span class="wsf-badge-count">(' . $value['count'] . ')</span>';
                echo '</a>';
            } else {
                echo '<span class="' . esc_attr($badge_class) . '">';
                echo esc_html($value['name']);
                echo ' <span class="wsf-badge-count">(' . $value['count'] . ')</span>';
                echo '</span>';
            }
        }

        echo '</div>';
        echo '<button class="wsf-badge-toggle" data-action="expand" style="display: none;">Показать еще <i class="fas fa-chevron-down"></i></button>';
        echo '</div>';
        echo '</div>';
    }
    
    private function get_attribute_values($attribute, $current_category_slug) {
        $terms = get_terms([
            'taxonomy' => 'pa_' . $attribute,
            'hide_empty' => false,
        ]);
        
        $values = [];
        
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $values[] = [
                    'slug' => $term->slug,
                    'name' => $term->name,
                    'count' => $this->get_filter_product_count($attribute, $term->slug, $current_category_slug)
                ];
            }
        }
        
        return $values;
    }
    
    private function get_filter_product_count($attribute, $value, $current_category_slug = '') {
        $cache = WSF_Cache_Manager::get_instance();
        $current_params = $_GET;
        
        $cache_key = $cache->get_product_count_cache_key($attribute, $value, $current_category_slug, $current_params);
        
        $cached_count = $cache->get($cache_key);
        if (false !== $cached_count) {
            return $cached_count;
        }
        
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'tax_query' => [
                'relation' => 'AND',
            ],
        ];
        
        if ($current_category_slug) {
            $category = get_term_by('slug', $current_category_slug, 'product_cat');
            if ($category) {
                $args['tax_query'][] = [
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $category->term_id,
                ];
            }
            
            $binding_manager = WSF_Binding_Manager::get_instance();
            $binding = $binding_manager->get_binding_by_slug($current_category_slug);
            
            if ($binding && !empty($binding['attributes'])) {
                foreach ($binding['attributes'] as $attr) {
                    $args['tax_query'][] = [
                        'taxonomy' => 'pa_' . $attr['attribute'],
                        'field' => 'slug',
                        'terms' => $attr['value'],
                    ];
                }
            }
        }
        
        foreach ($current_params as $key => $val) {
            if (strpos($key, 'filter_') === 0) {
                $attr_name = str_replace('filter_', '', $key);
                if ($attr_name === $attribute) continue;
                
                $values = explode(',', $val);
                $args['tax_query'][] = [
                    'taxonomy' => 'pa_' . $attr_name,
                    'field' => 'slug',
                    'terms' => array_map('trim', $values),
                    'operator' => 'IN',
                ];
            }
        }
        
        $args['tax_query'][] = [
            'taxonomy' => 'pa_' . $attribute,
            'field' => 'slug',
            'terms' => $value,
        ];
        
        $query = new WP_Query($args);
        $count = $query->found_posts;
        
        $cache->set($cache_key, $count);
        
        return $count;
    }
    
    private function get_active_values($attribute, $current_category_slug, $current_params) {
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
            $active = array_merge($active, array_map('trim', $values));
        }
        
        return array_unique($active);
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
    
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : '';
        $display_type = !empty($instance['display_type']) ? $instance['display_type'] : 'inline';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">Заголовок:</label>
            <input class="widefat" 
                   id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                   type="text" 
                   value="<?php echo esc_attr($title); ?>">
        </p>
        
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('display_type')); ?>">Тип отображения:</label>
            <select class="widefat" 
                    id="<?php echo esc_attr($this->get_field_id('display_type')); ?>" 
                    name="<?php echo esc_attr($this->get_field_name('display_type')); ?>">
                <option value="inline" <?php selected($display_type, 'inline'); ?>>В линию</option>
                <option value="vertical" <?php selected($display_type, 'vertical'); ?>>Вертикально</option>
            </select>
        </p>
        
        <p class="description">
            Показывает компактные метки с атрибутами, настроенными в Filter Attributes Settings (раздел "Атрибуты для меток").
        </p>
        <?php
    }
    
    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['title'] = !empty($new_instance['title']) ? sanitize_text_field($new_instance['title']) : '';
        $instance['display_type'] = !empty($new_instance['display_type']) ? sanitize_text_field($new_instance['display_type']) : 'inline';
        
        return $instance;
    }
}