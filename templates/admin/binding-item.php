<?php
if (!defined('ABSPATH')) {
    exit;
}

$category_slug = $binding['category_slug'] ?? '';
$parent_slug = $binding['parent_slug'] ?? '';
$binding_attributes = $binding['attributes'] ?? [];

$category = $category_slug ? get_term_by('slug', $category_slug, 'product_cat') : null;
$parent_category = $parent_slug ? get_term_by('slug', $parent_slug, 'product_cat') : null;
?>

<div class="wsf-binding-item" data-index="<?php echo esc_attr($index); ?>" data-collapsed="false">
    <div class="wsf-binding-header-bar">
        <div class="wsf-binding-handle">
            <span class="dashicons dashicons-menu"></span>
        </div>

        <button type="button" class="wsf-collapse-toggle" title="Свернуть/Развернуть">
            <span class="dashicons dashicons-arrow-up-alt2"></span>
        </button>

        <div class="wsf-binding-title">
            <strong>Категория:</strong>
            <span class="wsf-category-name">
                <?php echo $category ? esc_html($category->name) : 'Не выбрана'; ?>
            </span>
        </div>
    </div>

    <div class="wsf-binding-content">
        <div class="wsf-binding-row">
            <strong>Название категории:</strong>
            <span class="wsf-category-name">
                <?php echo $category ? esc_html($category->name) : 'Не выбрана'; ?>
            </span>
        </div>
        
        <?php if ($parent_category) : ?>
        <div class="wsf-binding-row">
            <strong>Родительская категория:</strong>
            <a href="<?php echo esc_url(get_term_link($parent_category)); ?>" target="_blank">
                <?php echo esc_html($parent_category->name); ?>
            </a>
            (ID: <?php echo esc_html($parent_category->term_id); ?>)
            <a href="<?php echo esc_url(admin_url('term.php?taxonomy=product_cat&tag_ID=' . $parent_category->term_id)); ?>" target="_blank">
                Редактировать
            </a>
        </div>
        <?php elseif ($parent_slug === 'woocommerce') : ?>
        <div class="wsf-binding-row">
            <strong>Родительская категория:</strong>
            Каталог WooCommerce
        </div>
        <?php endif; ?>
        
        <?php if ($category) : ?>
        <div class="wsf-binding-row">
            <strong>Категория связки:</strong>
            <a href="<?php echo esc_url(get_term_link($category)); ?>" target="_blank">
                <?php echo esc_html($category->name); ?>
            </a>
            (ID: <?php echo esc_html($category->term_id); ?>)
            <a href="<?php echo esc_url(admin_url('term.php?taxonomy=product_cat&tag_ID=' . $category->term_id)); ?>" target="_blank">
                Редактировать
            </a>
        </div>
        <?php endif; ?>
        
        <div class="wsf-binding-row">
            <label>
                <strong>Выберите категорию:</strong>
            </label>
            <select class="wsf-category-select" name="bindings[<?php echo esc_attr($index); ?>][category_slug]" data-index="<?php echo esc_attr($index); ?>">
                <option value="">Выберите категорию</option>
                <?php 
                if (!empty($categories)) {
                    echo wsf_build_category_options($categories, $category_slug);
                }
                ?>
            </select>
        </div>
        
        <div class="wsf-binding-row">
            <label>
                <strong>Родительская категория по фильтру:</strong>
            </label>
            <select class="wsf-parent-select" name="bindings[<?php echo esc_attr($index); ?>][parent_slug]" data-index="<?php echo esc_attr($index); ?>">
                <option value="">Нет родителя</option>
                <option value="woocommerce" <?php selected($parent_slug, 'woocommerce'); ?>>Каталог WooCommerce</option>
                <?php 
                if (!empty($categories)) {
                    echo wsf_build_category_options($categories, $parent_slug);
                }
                ?>
            </select>
        </div>
        
        <div class="wsf-attributes-container">
            <strong>Атрибуты:</strong>
            <div class="wsf-attributes-list" data-binding-index="<?php echo esc_attr($index); ?>">
                <?php if (!empty($binding_attributes)) : ?>
                    <?php foreach ($binding_attributes as $attr_index => $attr) : ?>
                        <?php
                        $attr_taxonomy = wc_get_attribute_taxonomies();
                        $attr_label = '';
                        $attr_slug = $attr['attribute'];
                        
                        foreach ($attr_taxonomy as $tax) {
                            if (wc_attribute_taxonomy_slug($tax->attribute_name) === $attr_slug) {
                                $attr_label = $tax->attribute_label;
                                break;
                            }
                        }
                        
                        $attr_terms = get_terms([
                            'taxonomy' => 'pa_' . $attr_slug,
                            'hide_empty' => false,
                        ]);
                        ?>
                        <div class="wsf-attribute-row" data-attr-index="<?php echo esc_attr($attr_index); ?>">
                            <select class="wsf-attribute-select" name="bindings[<?php echo esc_attr($index); ?>][attributes][<?php echo esc_attr($attr_index); ?>][attribute]" data-binding-index="<?php echo esc_attr($index); ?>" data-attr-index="<?php echo esc_attr($attr_index); ?>">
                                <option value="">Выберите атрибут</option>
                                <option value="<?php echo esc_attr($attr_slug); ?>" selected><?php echo esc_html($attr_label); ?></option>
                            </select>
                            
                            <select class="wsf-attribute-value-select" name="bindings[<?php echo esc_attr($index); ?>][attributes][<?php echo esc_attr($attr_index); ?>][value]">
                                <option value="">Выберите значение</option>
                                <?php if (!is_wp_error($attr_terms) && !empty($attr_terms)) : ?>
                                    <?php foreach ($attr_terms as $term) : ?>
                                        <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($attr['value'], $term->slug); ?>>
                                            <?php echo esc_html($term->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            
                            <button type="button" class="button button-small wsf-remove-attribute">Удалить атрибут</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <button type="button" class="button button-secondary wsf-add-attribute" data-index="<?php echo esc_attr($index); ?>">
                Добавить атрибут
            </button>
        </div>
        
        <div class="wsf-binding-actions">
            <button type="button" class="button button-secondary wsf-duplicate-binding">
                <span class="dashicons dashicons-admin-page"></span> Дублировать
            </button>
            <button type="button" class="button button-link-delete wsf-remove-binding">
                Удалить связку
            </button>
        </div>
    </div>
    
    <button type="button" class="button button-secondary wsf-add-binding-after" data-after-index="<?php echo esc_attr($index); ?>">
        Добавить связку
    </button>
</div>