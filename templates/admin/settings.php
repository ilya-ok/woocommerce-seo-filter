<?php
if (!defined('ABSPATH')) {
    exit;
}

$bindings = get_option('wsf_bindings', []);

$categories = get_terms([
    'taxonomy' => 'product_cat',
    'hide_empty' => false,
]);

$attributes = wc_get_attribute_taxonomies();

function wsf_build_category_options($categories, $selected = '', $parent = 0, $level = 0) {
    $output = '';
    
    foreach ($categories as $category) {
        if ($category->parent == $parent) {
            $indent = str_repeat('—', $level);
            $output .= sprintf(
                '<option value="%s" %s>%s %s</option>',
                esc_attr($category->slug),
                selected($selected, $category->slug, false),
                $indent,
                esc_html($category->name)
            );
            
            $output .= wsf_build_category_options($categories, $selected, $category->term_id, $level + 1);
        }
    }
    
    return $output;
}
?>

<div class="wrap wsf-settings-wrap">
    <h1>SEO Filter Bindings</h1>
    
    <div class="wsf-bindings-container">
        <div id="wsf-bindings-list" class="wsf-bindings-list">
            <?php if (!empty($bindings)) : ?>
                <?php foreach ($bindings as $index => $binding) : ?>
                    <?php include WSF_PATH . 'templates/admin/binding-item.php'; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <button type="button" class="button button-secondary wsf-add-binding" id="wsf-add-binding-btn">
            Добавить связку
        </button>
    </div>
    
    <div class="wsf-save-container">
        <button type="button" class="button button-primary wsf-save-bindings" id="wsf-save-bindings">
            Сохранить связки
        </button>
        <span class="wsf-save-status"></span>
    </div>
</div>

<script type="text/template" id="wsf-binding-template">
    <?php 
    $index = '{{INDEX}}';
    $binding = [
        'category_slug' => '',
        'parent_slug' => '',
        'attributes' => []
    ];
    include WSF_PATH . 'templates/admin/binding-item.php';
    ?>
</script>

<script type="text/template" id="wsf-attribute-template">
    <div class="wsf-attribute-row" data-attr-index="{{ATTR_INDEX}}">
        <select class="wsf-attribute-select" name="bindings[{{INDEX}}][attributes][{{ATTR_INDEX}}][attribute]">
            <option value="">Выберите атрибут</option>
        </select>
        
        <select class="wsf-attribute-value-select" name="bindings[{{INDEX}}][attributes][{{ATTR_INDEX}}][value]" disabled>
            <option value="">Выберите значение</option>
        </select>
        
        <button type="button" class="button button-small wsf-remove-attribute">Удалить атрибут</button>
    </div>
</script>