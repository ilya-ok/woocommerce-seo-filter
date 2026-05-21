<?php
if (!defined('ABSPATH')) {
    exit;
}

$all_attributes = wc_get_attribute_taxonomies();
$category_slug = $data['slug'];
$category_name = $data['name'];
$selected_attrs = $data['attributes'];
$display_mode = $data['display_mode'];
$is_shop = ($category_slug === 'shop');

// Получаем атрибуты для меток
$category_badge_attrs = get_option('wsf_category_badge_attributes', []);
$selected_badge_attrs = isset($category_badge_attrs[$category_slug]) ? $category_badge_attrs[$category_slug] : [];

// Глобальная настройка скрытых по умолчанию атрибутов
$collapsed_attributes = get_option('wsf_collapsed_attributes', []);
?>

<div class="wsf-category-item" data-category-slug="<?php echo esc_attr($category_slug); ?>">
    <?php if (!$is_shop) : ?>
    <div class="wsf-category-handle">
        <span class="dashicons dashicons-menu"></span>
    </div>
    <?php endif; ?>
    
    <div class="wsf-category-content">
        <div class="wsf-category-header">
            <h3><?php echo esc_html($category_name); ?></h3>
            <?php if (!$is_shop) : ?>
            <button type="button" class="button-link wsf-remove-category-btn">
                <span class="dashicons dashicons-trash"></span> Удалить
            </button>
            <?php endif; ?>
        </div>
        
        <div class="wsf-category-slug">
            Slug: <code><?php echo esc_html($category_slug); ?></code>
        </div>
        
        <div class="wsf-attributes-section">
            <h4>Атрибуты для фильтра:</h4>
            
            <div class="wsf-attributes-sortable" data-type="filter">
                <?php if (!empty($selected_attrs)) : ?>
                    <?php foreach ($selected_attrs as $attr_slug) : ?>
                        <?php
                        $attr_name = '';
                        foreach ($all_attributes as $attr) {
                            if (wc_attribute_taxonomy_slug($attr->attribute_name) === $attr_slug) {
                                $attr_name = $attr->attribute_label;
                                break;
                            }
                        }
                        if (empty($attr_name)) {
                            $attr_name = ucfirst(str_replace('-', ' ', $attr_slug));
                        }
                        ?>
                        <div class="wsf-attribute-item" data-attr-slug="<?php echo esc_attr($attr_slug); ?>">
                            <span class="wsf-attribute-handle dashicons dashicons-menu"></span>
                            <span class="wsf-attribute-name"><?php echo esc_html($attr_name); ?></span>
                            <label class="wsf-attr-collapsed-label">
                                <input type="checkbox" class="wsf-attr-collapsed-checkbox" value="<?php echo esc_attr($attr_slug); ?>" <?php checked(in_array($attr_slug, $collapsed_attributes, true)); ?>>
                                скрыт по умолчанию
                            </label>
                            <input type="hidden" name="category_available_attributes[<?php echo esc_attr($category_slug); ?>][]" value="<?php echo esc_attr($attr_slug); ?>">
                            <button type="button" class="button-link wsf-remove-attribute-btn">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="wsf-add-attribute-controls">
                <select class="wsf-attribute-select wsf-attribute-select-filter">
                    <option value="">Добавить атрибут...</option>
                    <?php foreach ($all_attributes as $attr) : ?>
                        <?php $attr_slug = wc_attribute_taxonomy_slug($attr->attribute_name); ?>
                        <option value="<?php echo esc_attr($attr_slug); ?>">
                            <?php echo esc_html($attr->attribute_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="wsf-attributes-section" style="margin-top: 20px;">
            <h4>Атрибуты для меток:</h4>
            
            <div class="wsf-attributes-sortable" data-type="badge">
                <?php if (!empty($selected_badge_attrs)) : ?>
                    <?php foreach ($selected_badge_attrs as $attr_slug) : ?>
                        <?php
                        $attr_name = '';
                        foreach ($all_attributes as $attr) {
                            if (wc_attribute_taxonomy_slug($attr->attribute_name) === $attr_slug) {
                                $attr_name = $attr->attribute_label;
                                break;
                            }
                        }
                        if (empty($attr_name)) {
                            $attr_name = ucfirst(str_replace('-', ' ', $attr_slug));
                        }
                        ?>
                        <div class="wsf-attribute-item" data-attr-slug="<?php echo esc_attr($attr_slug); ?>">
                            <span class="wsf-attribute-handle dashicons dashicons-menu"></span>
                            <span class="wsf-attribute-name"><?php echo esc_html($attr_name); ?></span>
                            <input type="hidden" name="category_badge_attributes[<?php echo esc_attr($category_slug); ?>][]" value="<?php echo esc_attr($attr_slug); ?>">
                            <button type="button" class="button-link wsf-remove-attribute-btn">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="wsf-add-attribute-controls">
                <select class="wsf-attribute-select wsf-attribute-select-badge">
                    <option value="">Добавить атрибут...</option>
                    <?php foreach ($all_attributes as $attr) : ?>
                        <?php $attr_slug = wc_attribute_taxonomy_slug($attr->attribute_name); ?>
                        <option value="<?php echo esc_attr($attr_slug); ?>">
                            <?php echo esc_html($attr->attribute_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="wsf-display-mode-section">
            <h4>Режим отображения:</h4>
            <label>
                <input type="radio" 
                       name="category_display[<?php echo esc_attr($category_slug); ?>]" 
                       value="filters" 
                       <?php checked($display_mode, 'filters'); ?>>
                Показывать только фильтры
            </label>
            <label>
                <input type="radio" 
                       name="category_display[<?php echo esc_attr($category_slug); ?>]" 
                       value="subcategories" 
                       <?php checked($display_mode, 'subcategories'); ?>>
                Показывать только подкатегории
            </label>
            <label>
                <input type="radio" 
                       name="category_display[<?php echo esc_attr($category_slug); ?>]" 
                       value="both" 
                       <?php checked($display_mode, 'both'); ?>>
                Показывать и фильтры, и подкатегории
            </label>
        </div>
    </div>
</div>