<?php
if (!defined('ABSPATH')) {
    exit;
}

$all_categories  = get_terms([
    'taxonomy'   => 'product_cat',
    'hide_empty' => false,
    'orderby'    => 'name',
    'order'      => 'ASC',
]);

$binding_manager = WSF_Binding_Manager::get_instance();
?>
<div class="wrap">
    <h1>SEO Filter — Синхронизация атрибутов</h1>

    <p class="description">
        Выберите категорию. Атрибуты из связки будут добавлены всем товарам категории,
        у которых они отсутствуют. Существующие атрибуты товара не затрагиваются.
    </p>

    <div class="wsf-diag-layout">

        <div class="wsf-diag-sidebar">
            <h2>Категории товаров</h2>
            <?php if (is_wp_error($all_categories) || empty($all_categories)) : ?>
                <p>Категории не найдены.</p>
            <?php else : ?>
                <?php WSF_Diagnose::render_category_tree($all_categories, $binding_manager); ?>
            <?php endif; ?>
        </div>

        <div class="wsf-diag-main">
            <div id="wsf-sync-result" class="wsf-diag-placeholder">
                <p>← Выберите категорию для синхронизации</p>
            </div>
        </div>

    </div>
</div>
