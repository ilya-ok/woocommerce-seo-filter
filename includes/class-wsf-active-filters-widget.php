<?php

if (!defined('ABSPATH')) {
    exit;
}

class WSF_Active_Filters_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'wsf_active_filters_widget',
            'SEO Active Filters',
            ['description' => 'Виджет активных SEO фильтров']
        );
    }
    
    public function widget($args, $instance) {
        if (!is_shop() && !is_product_category()) {
            return;
        }
        
        $title = !empty($instance['title']) ? $instance['title'] : 'Активные фильтры';
        
        $current_category_slug = '';
        $current_params = $_GET;
        
        if (is_product_category()) {
            $current_term = get_queried_object();
            $current_category_slug = $current_term->slug;
        }
        
        $active_filters = $this->get_all_active_filters($current_category_slug, $current_params);
        
        if (empty($active_filters)) {
            return;
        }
        
        echo $args['before_widget'];
        
        if ($title) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }
        
        $this->render_active_filters($active_filters, $current_category_slug, $current_params);
        
        echo $args['after_widget'];
    }
    
    private function get_all_active_filters($current_category_slug, $current_params) {
        $active_filters = [];
        $binding_manager = WSF_Binding_Manager::get_instance();
        $seen_filters = []; // Для отслеживания уже добавленных фильтров
        
        // Получаем атрибуты ТОЛЬКО из текущей категории (не из иерархии)
        if ($current_category_slug) {
            $binding = $binding_manager->get_binding_by_slug($current_category_slug);
            
            if ($binding && !empty($binding['attributes'])) {
                foreach ($binding['attributes'] as $attr) {
                    $term = get_term_by('slug', $attr['value'], 'pa_' . $attr['attribute']);
                    $attribute_label = $this->get_attribute_label($attr['attribute']);
                    
                    if ($term) {
                        $filter_key = $attr['attribute'] . ':' . $attr['value'];
                        
                        // Проверяем, не добавляли ли уже этот фильтр
                        if (!isset($seen_filters[$filter_key])) {
                            $active_filters[] = [
                                'type' => 'category',
                                'attribute' => $attr['attribute'],
                                'attribute_label' => $attribute_label,
                                'value' => $attr['value'],
                                'value_name' => $term->name,
                                'category_slug' => $current_category_slug,
                                'remove_url' => $this->get_remove_url_single($current_category_slug, $attr, $current_params)
                            ];
                            
                            $seen_filters[$filter_key] = true;
                        }
                    }
                }
            }
        }
        
        // Добавляем фильтры из query параметров
        foreach ($current_params as $key => $value) {
            if (strpos($key, 'filter_') === 0) {
                $attribute = str_replace('filter_', '', $key);
                $values = explode(',', $value);
                $attribute_label = $this->get_attribute_label($attribute);
                
                foreach ($values as $val) {
                    $val = trim($val);
                    $term = get_term_by('slug', $val, 'pa_' . $attribute);
                    
                    if ($term) {
                        $filter_key = $attribute . ':' . $val;
                        
                        // Проверяем, не добавляли ли уже этот фильтр
                        if (!isset($seen_filters[$filter_key])) {
                            $active_filters[] = [
                                'type' => 'query',
                                'attribute' => $attribute,
                                'attribute_label' => $attribute_label,
                                'value' => $val,
                                'value_name' => $term->name,
                                'category_slug' => null,
                                'remove_url' => $this->get_remove_query_url($attribute, $val, $current_params, $current_category_slug)
                            ];
                            
                            $seen_filters[$filter_key] = true;
                        }
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
    
    private function get_remove_url_single($current_category_slug, $attr_to_remove, $current_params) {
        $binding_manager = WSF_Binding_Manager::get_instance();
        $current_binding = $binding_manager->get_binding_by_slug($current_category_slug);
        
        if (!$current_binding) {
            return wc_get_page_permalink('shop');
        }
        
        // Собираем все атрибуты из текущей категории
        $all_current_attrs = [];
        if (!empty($current_binding['attributes'])) {
            $all_current_attrs = $current_binding['attributes'];
        }
        
        // Добавляем атрибуты из query параметров
        foreach ($current_params as $key => $val) {
            if (strpos($key, 'filter_') === 0) {
                $attr_name = str_replace('filter_', '', $key);
                $values = explode(',', $val);
                
                foreach ($values as $attr_value) {
                    $attr_value = trim($attr_value);
                    $exists = false;
                    
                    foreach ($all_current_attrs as $existing) {
                        if ($existing['attribute'] === $attr_name && $existing['value'] === $attr_value) {
                            $exists = true;
                            break;
                        }
                    }
                    
                    if (!$exists) {
                        $all_current_attrs[] = [
                            'attribute' => $attr_name,
                            'value' => $attr_value
                        ];
                    }
                }
            }
        }
        
        // Убираем удаляемый атрибут
        $remaining_attrs = [];
        foreach ($all_current_attrs as $attr) {
            if ($attr['attribute'] !== $attr_to_remove['attribute'] || 
                $attr['value'] !== $attr_to_remove['value']) {
                $remaining_attrs[] = $attr;
            }
        }
        
        // Если не осталось атрибутов
        if (empty($remaining_attrs)) {
            $non_filter_params = [];
            foreach ($current_params as $key => $val) {
                if (strpos($key, 'filter_') !== 0 && strpos($key, 'query_type_') !== 0) {
                    $non_filter_params[$key] = $val;
                }
            }
            return add_query_arg($non_filter_params, wc_get_page_permalink('shop'));
        }
        
        // Ищем точное совпадение
        $matching_binding = $binding_manager->get_binding_by_attributes($remaining_attrs);
        
        $non_filter_params = [];
        foreach ($current_params as $key => $val) {
            if (strpos($key, 'filter_') !== 0 && strpos($key, 'query_type_') !== 0) {
                $non_filter_params[$key] = $val;
            }
        }
        
        if ($matching_binding) {
            return $binding_manager->get_category_url($matching_binding['category_slug'], $non_filter_params);
        }
        
        // Ищем частичное совпадение
        $filter_manager = WSF_Filter_Manager::get_instance();
        $partial_match = $this->find_best_match($remaining_attrs);
        
        if ($partial_match) {
            $query_params = $non_filter_params;
            
            foreach ($remaining_attrs as $attr) {
                $found = false;
                foreach ($partial_match['attributes'] as $match_attr) {
                    if ($match_attr['attribute'] === $attr['attribute'] && 
                        $match_attr['value'] === $attr['value']) {
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    $filter_key = 'filter_' . $attr['attribute'];
                    $query_type_key = 'query_type_' . $attr['attribute'];
                    
                    if (isset($query_params[$filter_key])) {
                        $existing = explode(',', $query_params[$filter_key]);
                        if (!in_array($attr['value'], $existing)) {
                            $existing[] = $attr['value'];
                            $query_params[$filter_key] = implode(',', $existing);
                        }
                    } else {
                        $query_params[$filter_key] = $attr['value'];
                        $query_params[$query_type_key] = 'or';
                    }
                }
            }
            
            return $binding_manager->get_category_url($partial_match['category_slug'], $query_params);
        }
        
        // Если ничего не найдено, идём на главную с фильтрами
        $query_params = $non_filter_params;
        foreach ($remaining_attrs as $attr) {
            $filter_key = 'filter_' . $attr['attribute'];
            $query_type_key = 'query_type_' . $attr['attribute'];
            $query_params[$filter_key] = $attr['value'];
            $query_params[$query_type_key] = 'or';
        }
        
        return add_query_arg($query_params, wc_get_page_permalink('shop'));
    }
    
    private function find_best_match($remaining_attrs) {
        $binding_manager = WSF_Binding_Manager::get_instance();
        $all_bindings = $binding_manager->get_bindings();
        
        $best_match = null;
        $best_count = 0;
        
        foreach ($all_bindings as $binding) {
            if (empty($binding['attributes'])) {
                continue;
            }
            
            $match_count = 0;
            
            foreach ($remaining_attrs as $remain_attr) {
                foreach ($binding['attributes'] as $bind_attr) {
                    if ($bind_attr['attribute'] === $remain_attr['attribute'] && 
                        $bind_attr['value'] === $remain_attr['value']) {
                        $match_count++;
                        break;
                    }
                }
            }
            
            if ($match_count > 0 && $match_count > $best_count && $match_count < count($remaining_attrs)) {
                $best_match = $binding;
                $best_count = $match_count;
            }
        }
        
        return $best_match;
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
    
    private function get_attribute_label($attribute_slug) {
        $attributes = wc_get_attribute_taxonomies();
        
        foreach ($attributes as $attr) {
            if (wc_attribute_taxonomy_slug($attr->attribute_name) === $attribute_slug) {
                return $attr->attribute_label;
            }
        }
        
        return ucfirst(str_replace('-', ' ', $attribute_slug));
    }
    
    private function render_active_filters($active_filters, $current_category_slug, $current_params) {
        echo '<div class="wsf-active-filters">';
        echo '<div class="wsf-active-filters-list">';

        foreach ($active_filters as $filter) {
            echo '<a href="' . esc_url($filter['remove_url']) . '" class="wsf-active-filter-item" title="Удалить фильтр">';
            echo '<span class="wsf-filter-label">' . esc_html($filter['attribute_label']) . ':</span> ';
            echo '<span class="wsf-filter-value">' . esc_html($filter['value_name']) . '</span>';
            echo ' <span class="wsf-remove-icon">×</span>';
            echo '</a>';
        }

        echo '</div>';

        $reset_url = $this->get_reset_url($current_category_slug);
        echo '<a href="' . esc_url($reset_url) . '" class="wsf-reset-filters button">Сбросить все фильтры</a>';

        echo '</div>';
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
    
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : 'Активные фильтры';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">Заголовок:</label>
            <input class="widefat" 
                   id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                   type="text" 
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <?php
    }
    
    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['title'] = !empty($new_instance['title']) ? sanitize_text_field($new_instance['title']) : 'Активные фильтры';
        
        return $instance;
    }
}