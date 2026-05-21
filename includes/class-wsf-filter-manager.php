<?php

if (!defined('ABSPATH')) {
    exit;
}

class WSF_Filter_Manager {
    
    private static $instance = null;
    
    private function __construct() {
        // Инициализация будет на следующих этапах
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function get_filter_url($attribute, $value, $current_category_slug = '', $current_params = []) {
        $binding_manager = WSF_Binding_Manager::get_instance();
        
        // Проверяем, активен ли этот фильтр
        $is_active = $this->is_filter_active($attribute, $value, $current_category_slug, $current_params);
        
        // Если фильтр активен, возвращаем URL для его снятия
        if ($is_active) {
            return $this->get_remove_filter_url($attribute, $value, $current_category_slug, $current_params);
        }
        
        $new_attributes = [];
        
        // Добавляем атрибуты из текущей категории
        if ($current_category_slug) {
            $current_binding = $binding_manager->get_binding_by_slug($current_category_slug);
            if ($current_binding && !empty($current_binding['attributes'])) {
                $new_attributes = $current_binding['attributes'];
            }
        }
        
        // Добавляем атрибуты из query параметров
        foreach ($current_params as $key => $val) {
            if (strpos($key, 'filter_') === 0) {
                $attr_name = str_replace('filter_', '', $key);
                $values = explode(',', $val);
                
                foreach ($values as $attr_value) {
                    $attr_value = trim($attr_value);
                    // Проверяем, нет ли уже такого атрибута
                    $exists = false;
                    foreach ($new_attributes as $existing_attr) {
                        if ($existing_attr['attribute'] === $attr_name && $existing_attr['value'] === $attr_value) {
                            $exists = true;
                            break;
                        }
                    }
                    
                    if (!$exists) {
                        $new_attributes[] = [
                            'attribute' => $attr_name,
                            'value' => $attr_value
                        ];
                    }
                }
            }
        }
        
        // Добавляем новый выбранный атрибут
        $new_attributes[] = ['attribute' => $attribute, 'value' => $value];

        // Если у этого атрибута уже есть значения в query params, не ищем binding —
        // просто добавляем к query params, чтобы не уходить на SEO-категорию с чужими значениями.
        $filter_key_check = 'filter_' . $attribute;
        if (isset($current_params[$filter_key_check])) {
            $new_params = $current_params;
            $existing_values = explode(',', $new_params[$filter_key_check]);
            if (!in_array($value, $existing_values)) {
                $existing_values[] = $value;
                $new_params[$filter_key_check] = implode(',', $existing_values);
            }
            $base_url = $current_category_slug
                ? $binding_manager->get_category_url($current_category_slug)
                : wc_get_page_permalink('shop');
            return add_query_arg($new_params, $base_url);
        }

        // Если добавляемый атрибут уже закодирован в текущей SEO-категории (binding),
        // уходим на родительскую категорию и добавляем ОБА значения как query params.
        if ($current_category_slug) {
            $current_binding = $binding_manager->get_binding_by_slug($current_category_slug);
            if ($current_binding && !empty($current_binding['attributes'])) {
                $binding_value_for_attr = null;
                foreach ($current_binding['attributes'] as $ba) {
                    if ($ba['attribute'] === $attribute) {
                        $binding_value_for_attr = $ba['value'];
                        break;
                    }
                }
                if ($binding_value_for_attr !== null) {
                    // Оба значения одного атрибута — идём на родителя с query params
                    $parent_slug = !empty($current_binding['parent_slug']) ? $current_binding['parent_slug'] : null;
                    $base_url = $parent_slug
                        ? $binding_manager->get_category_url($parent_slug)
                        : wc_get_page_permalink('shop');
                    // Определяем атрибуты родительской категории (чтобы не дублировать)
                    $parent_binding_attrs = [];
                    if ($parent_slug) {
                        $parent_binding = $binding_manager->get_binding_by_slug($parent_slug);
                        if ($parent_binding && !empty($parent_binding['attributes'])) {
                            foreach ($parent_binding['attributes'] as $pba) {
                                $parent_binding_attrs[$pba['attribute']] = $pba['value'];
                            }
                        }
                    }
                    // Собираем атрибуты текущего binding (кроме кликнутого)
                    $current_binding_attrs = [];
                    foreach ($current_binding['attributes'] as $ba) {
                        if ($ba['attribute'] !== $attribute) {
                            $current_binding_attrs[$ba['attribute']] = $ba['value'];
                        }
                    }

                    $query_params = [];
                    foreach ($current_params as $k => $v) {
                        if (strpos($k, 'filter_') === 0) {
                            $param_attr = substr($k, strlen('filter_'));
                            // Пропускаем атрибут кликнутого фильтра (добавим ниже с новым значением)
                            if ($param_attr === $attribute) continue;
                            // Пропускаем атрибуты, которые parent binding уже кодирует
                            if (isset($parent_binding_attrs[$param_attr])) continue;
                            // Пропускаем атрибуты из текущего binding — добавим их ниже
                            if (isset($current_binding_attrs[$param_attr])) continue;
                            $query_params[$k] = $v;
                        } elseif (strpos($k, 'query_type_') === 0) {
                            $param_attr = substr($k, strlen('query_type_'));
                            if ($param_attr === $attribute) continue;
                            if (isset($parent_binding_attrs[$param_attr])) continue;
                            if (isset($current_binding_attrs[$param_attr])) continue;
                            $query_params[$k] = $v;
                        } else {
                            $query_params[$k] = $v;
                        }
                    }
                    // Кликнутый атрибут: binding-значение + новое
                    $query_params[$filter_key_check] = $binding_value_for_attr . ',' . $value;
                    $query_params['query_type_' . $attribute] = 'or';
                    // Остальные атрибуты из binding — только если parent не кодирует
                    foreach ($current_binding_attrs as $ba_attr => $ba_val) {
                        if (isset($parent_binding_attrs[$ba_attr]) && $parent_binding_attrs[$ba_attr] === $ba_val) continue;
                        $fk = 'filter_' . $ba_attr;
                        $qtk = 'query_type_' . $ba_attr;
                        if (!isset($query_params[$fk])) {
                            $query_params[$fk] = $ba_val;
                            $query_params[$qtk] = 'or';
                        }
                    }
                    return add_query_arg($query_params, $base_url);
                }
            }
        }

        // Ищем точное совпадение
        $matching_binding = $binding_manager->get_binding_by_attributes($new_attributes);
        
        $filtered_params = [];
        foreach ($current_params as $key => $val) {
            if (strpos($key, 'filter_') !== 0 && strpos($key, 'query_type_') !== 0) {
                $filtered_params[$key] = $val;
            }
        }
        
        if ($matching_binding) {
            return $binding_manager->get_category_url($matching_binding['category_slug'], $filtered_params);
        }
        
        // Если точного совпадения нет, ищем частичное —
        // но только если нет атрибута с несколькими значениями (иначе binding не может их кодировать)
        $attr_value_counts = [];
        foreach ($new_attributes as $na) {
            $attr_value_counts[$na['attribute']] = ($attr_value_counts[$na['attribute']] ?? 0) + 1;
        }
        $has_multi_value_attr = max($attr_value_counts) > 1;

        $partial_match = $has_multi_value_attr ? null : $this->find_partial_match_category($new_attributes);
        
        if ($partial_match) {
            // Нашли категорию с частью атрибутов
            // Остальные атрибуты добавляем как query параметры
            $query_params = $filtered_params;
            
            foreach ($new_attributes as $attr) {
                $found_in_match = false;
                foreach ($partial_match['attributes'] as $match_attr) {
                    if ($match_attr['attribute'] === $attr['attribute'] && $match_attr['value'] === $attr['value']) {
                        $found_in_match = true;
                        break;
                    }
                }
                
                if (!$found_in_match) {
                    $filter_key = 'filter_' . $attr['attribute'];
                    $query_type_key = 'query_type_' . $attr['attribute'];
                    
                    if (isset($query_params[$filter_key])) {
                        $existing_values = explode(',', $query_params[$filter_key]);
                        if (!in_array($attr['value'], $existing_values)) {
                            $existing_values[] = $attr['value'];
                            $query_params[$filter_key] = implode(',', $existing_values);
                        }
                    } else {
                        $query_params[$filter_key] = $attr['value'];
                        $query_params[$query_type_key] = 'or';
                    }
                }
            }
            
            return $binding_manager->get_category_url($partial_match['category_slug'], $query_params);
        }
        
        // Если вообще не нашли, добавляем как query параметр
        $new_params = $current_params;
        $filter_key = 'filter_' . $attribute;
        $query_type_key = 'query_type_' . $attribute;
        
        if (isset($new_params[$filter_key])) {
            $existing_values = explode(',', $new_params[$filter_key]);
            if (!in_array($value, $existing_values)) {
                $existing_values[] = $value;
                $new_params[$filter_key] = implode(',', $existing_values);
            }
        } else {
            $new_params[$filter_key] = $value;
            $new_params[$query_type_key] = 'or';
        }
        
        $base_url = $current_category_slug 
            ? $binding_manager->get_category_url($current_category_slug)
            : wc_get_page_permalink('shop');
        
        return add_query_arg($new_params, $base_url);
    }
    
    private function is_filter_active($attribute, $value, $current_category_slug, $current_params) {
        $binding_manager = WSF_Binding_Manager::get_instance();
        
        // Проверяем в текущей категории
        if ($current_category_slug) {
            $binding = $binding_manager->get_binding_by_slug($current_category_slug);
            if ($binding && !empty($binding['attributes'])) {
                foreach ($binding['attributes'] as $attr) {
                    if ($attr['attribute'] === $attribute && $attr['value'] === $value) {
                        return true;
                    }
                }
            }
        }
        
        // Проверяем в query параметрах
        $filter_key = 'filter_' . $attribute;
        if (isset($current_params[$filter_key])) {
            $values = explode(',', $current_params[$filter_key]);
            // Очищаем значения от пробелов
            $values = array_map('trim', $values);
            if (in_array($value, $values)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function get_remove_filter_url($attribute, $value, $current_category_slug, $current_params) {
        $binding_manager = WSF_Binding_Manager::get_instance();
        
        // Сначала проверяем, есть ли фильтр в query параметрах
        $filter_key = 'filter_' . $attribute;
        $is_in_query = isset($current_params[$filter_key]);
        
        if ($is_in_query) {
            // Фильтр в query параметрах - просто удаляем его
            $new_params = $current_params;
            
            $values = explode(',', $new_params[$filter_key]);
            $values = array_map('trim', $values);
            $values = array_filter($values, function($v) use ($value) {
                return $v !== $value;
            });
            
            if (empty($values)) {
                unset($new_params[$filter_key]);
                unset($new_params['query_type_' . $attribute]);
            } else {
                $new_params[$filter_key] = implode(',', $values);
            }
            
            $base_url = $current_category_slug 
                ? $binding_manager->get_category_url($current_category_slug)
                : wc_get_page_permalink('shop');
            
            if (empty($new_params)) {
                return $base_url;
            }
            
            return add_query_arg($new_params, $base_url);
        }
        
        // Если фильтр из SEO-категории
        if ($current_category_slug) {
            $current_binding = $binding_manager->get_binding_by_slug($current_category_slug);
            
            if ($current_binding && !empty($current_binding['attributes'])) {
                // Собираем атрибуты без удаляемого
                $remaining_attrs = [];
                foreach ($current_binding['attributes'] as $attr) {
                    if ($attr['attribute'] !== $attribute || $attr['value'] !== $value) {
                        $remaining_attrs[] = $attr;
                    }
                }
                
                // Если остались атрибуты, ищем подходящую категорию во ВСЕХ связках
                if (!empty($remaining_attrs)) {
                    $matching_binding = $binding_manager->get_binding_by_attributes($remaining_attrs);
                    
                    if ($matching_binding) {
                        // Нашли точное совпадение
                        return $binding_manager->get_category_url($matching_binding['category_slug'], $current_params);
                    }
                    
                    // Если точного совпадения нет, ищем любую категорию с частичным совпадением
                    $partial_match = $this->find_partial_match_category($remaining_attrs);
                    
                    if ($partial_match) {
                        // Нашли категорию с частью атрибутов
                        // Остальные атрибуты добавляем как query параметры
                        $query_params = $current_params;
                        
                        foreach ($remaining_attrs as $attr) {
                            $found_in_match = false;
                            foreach ($partial_match['attributes'] as $match_attr) {
                                if ($match_attr['attribute'] === $attr['attribute'] && $match_attr['value'] === $attr['value']) {
                                    $found_in_match = true;
                                    break;
                                }
                            }
                            
                            if (!$found_in_match) {
                                $filter_key = 'filter_' . $attr['attribute'];
                                $query_type_key = 'query_type_' . $attr['attribute'];
                                $query_params[$filter_key] = $attr['value'];
                                $query_params[$query_type_key] = 'or';
                            }
                        }
                        
                        return $binding_manager->get_category_url($partial_match['category_slug'], $query_params);
                    }
                    
                    // Если вообще не нашли подходящих категорий, идём на главную магазина с фильтрами
                    $query_params = $current_params;
                    foreach ($remaining_attrs as $attr) {
                        $filter_key = 'filter_' . $attr['attribute'];
                        $query_type_key = 'query_type_' . $attr['attribute'];
                        $query_params[$filter_key] = $attr['value'];
                        $query_params[$query_type_key] = 'or';
                    }
                    
                    return add_query_arg($query_params, wc_get_page_permalink('shop'));
                } else {
                    // Все атрибуты удалены, переходим на главную магазина
                    return add_query_arg($current_params, wc_get_page_permalink('shop'));
                }
            }
        }
        
        // Удаляем из query параметров (если дошли сюда)
        $new_params = $current_params;
        
        if (isset($new_params[$filter_key])) {
            $values = explode(',', $new_params[$filter_key]);
            $values = array_map('trim', $values);
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
        
        $base_url = $current_category_slug 
            ? $binding_manager->get_category_url($current_category_slug)
            : wc_get_page_permalink('shop');
        
        if (empty($new_params)) {
            return $base_url;
        }
        
        return add_query_arg($new_params, $base_url);
    }
    
    private function find_partial_match_category($remaining_attrs) {
        $binding_manager = WSF_Binding_Manager::get_instance();
        $all_bindings = $binding_manager->get_bindings();
        
        $best_match = null;
        $best_match_count = 0;
        
        foreach ($all_bindings as $binding) {
            if (empty($binding['attributes'])) {
                continue;
            }
            
            $match_count = 0;
            
            // Считаем сколько атрибутов совпадает
            foreach ($remaining_attrs as $remain_attr) {
                foreach ($binding['attributes'] as $bind_attr) {
                    if ($bind_attr['attribute'] === $remain_attr['attribute'] && 
                        $bind_attr['value'] === $remain_attr['value']) {
                        $match_count++;
                        break;
                    }
                }
            }
            
            // Ищем максимальное совпадение, но не полное (полное уже проверили выше)
            if ($match_count > 0 && $match_count < count($remaining_attrs) && $match_count > $best_match_count) {
                $best_match = $binding;
                $best_match_count = $match_count;
            }
        }
        
        return $best_match;
    }
}