<?php

if (!defined('ABSPATH')) {
    exit;
}

class WSF_Universal_Filters_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'wsf_universal_filters_widget',
            'SEO Universal Filters',
            ['description' => 'Универсальный виджет фильтров - автоматически показывает все фильтры для текущей категории']
        );
    }
    
    public function widget($args, $instance) {
        if (!is_shop() && !is_product_category()) {
            return;
        }
        
        $title = !empty($instance['title']) ? $instance['title'] : '';
        $display_type = !empty($instance['display_type']) ? $instance['display_type'] : 'list';
        
        $current_category_slug = '';
        $current_params = $_GET;
        
        if (is_product_category()) {
            $current_term = get_queried_object();
            $current_category_slug = $current_term->slug;
        }
        
        // Получаем доступные атрибуты для текущей категории
        $category_helper = WSF_Category_Helper::get_instance();
        $available_attributes = $category_helper->get_available_attributes_for_category($current_category_slug);
        
        if (empty($available_attributes)) {
            return;
        }
        
        // Проверяем, нужно ли показывать фильтры
        if (!$category_helper->should_display_filters($current_category_slug)) {
            return;
        }
        
        echo $args['before_widget'];
        
        // Выводим фильтр для каждого атрибута
        foreach ($available_attributes as $attribute_slug) {
            $this->render_single_filter($attribute_slug, $current_category_slug, $current_params, $display_type);
        }
        
        echo $args['after_widget'];
    }
    
    private function render_single_filter($attribute, $current_category_slug, $current_params, $display_type) {
        $attribute_values = $this->get_attribute_values($attribute, $current_category_slug);
        
        if (empty($attribute_values)) {
            return;
        }
        
        // Получаем название атрибута
        $attribute_label = $this->get_attribute_label($attribute);
        
        echo '<div class="wsf-filter-section" data-attribute="' . esc_attr($attribute) . '">';
        echo '<h3 class="wsf-filter-title">' . esc_html($attribute_label) . '</h3>';
        
        $this->render_filter($attribute, $attribute_values, $current_category_slug, $current_params, $display_type);
        
        echo '</div>';
    }
    
    private function get_attribute_values($attribute, $current_category_slug) {
        $binding_manager = WSF_Binding_Manager::get_instance();
        $values = [];
        $seen_slugs = []; // Для отслеживания уже добавленных значений
        
        // Получаем значения из дочерних SEO-категорий
        if ($current_category_slug) {
            $children = $binding_manager->get_child_bindings($current_category_slug);
            
            foreach ($children as $child) {
                foreach ($child['attributes'] as $attr) {
                    if ($attr['attribute'] === $attribute) {
                        // Проверяем, не добавляли ли уже это значение
                        if (!in_array($attr['value'], $seen_slugs)) {
                            $term = get_term_by('slug', $attr['value'], 'pa_' . $attribute);
                            if ($term) {
                                $values[] = [
                                    'slug' => $attr['value'],
                                    'name' => $term->name,
                                    'category_slug' => $child['category_slug'],
                                    'count' => $this->get_filter_product_count($attribute, $attr['value'], $current_category_slug)
                                ];
                                $seen_slugs[] = $attr['value'];
                            }
                        }
                    }
                }
            }
        }
        
        // Добавляем все остальные значения атрибута
        $terms = get_terms([
            'taxonomy' => 'pa_' . $attribute,
            'hide_empty' => false,
        ]);
        
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                // Проверяем, не добавляли ли уже это значение
                if (!in_array($term->slug, $seen_slugs)) {
                    $values[] = [
                        'slug' => $term->slug,
                        'name' => $term->name,
                        'category_slug' => null,
                        'count' => $this->get_filter_product_count($attribute, $term->slug, $current_category_slug)
                    ];
                    $seen_slugs[] = $term->slug;
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
    
    private function get_filter_product_count($attribute, $value, $current_category_slug = '') {
        $cache = WSF_Cache_Manager::get_instance();
        $current_params = $_GET;
        
        // Создаём ключ кэша
        $cache_key = $cache->get_product_count_cache_key($attribute, $value, $current_category_slug, $current_params);
        
        // Проверяем кэш
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
        
        // Добавляем текущую категорию если есть
        if ($current_category_slug) {
            $category = get_term_by('slug', $current_category_slug, 'product_cat');
            if ($category) {
                $args['tax_query'][] = [
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $category->term_id,
                ];
            }
            
            // Добавляем атрибуты текущей SEO-категории
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
        
        // Добавляем активные фильтры из query параметров
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
        
        // Добавляем проверяемый атрибут
        $args['tax_query'][] = [
            'taxonomy' => 'pa_' . $attribute,
            'field' => 'slug',
            'terms' => $value,
        ];
        
        $query = new WP_Query($args);
        $count = $query->found_posts;
        
        // Сохраняем в кэш
        $cache->set($cache_key, $count);
        
        return $count;
    }
    
    private function render_filter($attribute, $values, $current_category_slug, $current_params, $display_type) {
        $active_filters = $this->get_active_filter_values($attribute, $current_category_slug, $current_params);
        
        if ($display_type === 'dropdown') {
            $this->render_dropdown($attribute, $values, $current_category_slug, $current_params, $active_filters);
        } else {
            $this->render_list($attribute, $values, $current_category_slug, $current_params, $active_filters);
        }
    }
    
    private function render_list($attribute, $values, $current_category_slug, $current_params, $active_filters) {
        // Добавляем индекс для сохранения WC-порядка внутри групп
        foreach ($values as $i => &$v) {
            $v['_pos'] = $i;
        }
        unset($v);

        // Сортируем: активные и с товарами вверху; внутри группы — по WC-порядку
        usort($values, function($a, $b) use ($active_filters) {
            $a_is_active = in_array($a['slug'], $active_filters);
            $b_is_active = in_array($b['slug'], $active_filters);
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

            // Внутри одной группы сохраняем WC-порядок
            return $a['_pos'] - $b['_pos'];
        });

        echo '<ul class="wsf-filter-list">';

        foreach ($values as $value) {
            $is_active = in_array($value['slug'], $active_filters);
            $has_products = $value['count'] > 0;
            $filter_manager = WSF_Filter_Manager::get_instance();
            
            $url = $filter_manager->get_filter_url(
                $attribute,
                $value['slug'],
                $current_category_slug,
                $current_params
            );
            
            $class = $is_active ? 'wsf-filter-item active' : 'wsf-filter-item';
            if (!$has_products && !$is_active) {
                $class .= ' disabled';
            }
            
            echo '<li class="' . esc_attr($class) . '">';
            
            if ($has_products || $is_active) {
                echo '<a href="' . esc_url($url) . '">';
            } else {
                echo '<span class="wsf-filter-link-disabled">';
            }
            
            if ($is_active) {
                echo '<span class="wsf-checkbox checked">✓</span>';
            } else {
                echo '<span class="wsf-checkbox"></span>';
            }
            
            echo esc_html($value['name']);
            echo ' <span class="wsf-filter-count">(' . $value['count'] . ')</span>';
            
            if ($has_products || $is_active) {
                echo '</a>';
            } else {
                echo '</span>';
            }
            
            echo '</li>';
        }
        
        echo '</ul>';
    }
    
    private function render_dropdown($attribute, $values, $current_category_slug, $current_params, $active_filters) {
        echo '<select class="wsf-filter-dropdown" data-attribute="' . esc_attr($attribute) . '">';
        echo '<option value="">Выберите...</option>';
        
        foreach ($values as $value) {
            $is_active = in_array($value['slug'], $active_filters);
            $has_products = $value['count'] > 0;
            $filter_manager = WSF_Filter_Manager::get_instance();
            
            $url = $filter_manager->get_filter_url(
                $attribute,
                $value['slug'],
                $current_category_slug,
                $current_params
            );
            
            $disabled = (!$has_products && !$is_active) ? 'disabled' : '';
            
            echo '<option value="' . esc_url($url) . '" ' . selected($is_active, true, false) . ' ' . $disabled . '>';
            echo esc_html($value['name']) . ' (' . $value['count'] . ')';
            echo '</option>';
        }
        
        echo '</select>';
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
        $title = !empty($instance['title']) ? $instance['title'] : 'Фильтры';
        $display_type = !empty($instance['display_type']) ? $instance['display_type'] : 'list';
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
                <option value="list" <?php selected($display_type, 'list'); ?>>Список</option>
                <option value="dropdown" <?php selected($display_type, 'dropdown'); ?>>Выпадающий список</option>
            </select>
        </p>
        
        <p class="description">
            Этот виджет автоматически показывает все фильтры, настроенные для текущей категории в Filter Attributes Settings.
        </p>
        <?php
    }
    
    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['title'] = !empty($new_instance['title']) ? sanitize_text_field($new_instance['title']) : 'Фильтры';
        $instance['display_type'] = !empty($new_instance['display_type']) ? sanitize_text_field($new_instance['display_type']) : 'list';
        
        return $instance;
    }
}