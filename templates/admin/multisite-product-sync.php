<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!is_multisite()) {
    echo '<div class="wrap"><p>' . esc_html__('Эта страница доступна только в мультисайте.') . '</p></div>';
    return;
}

$current_blog_id = get_current_blog_id();
$current_blog    = get_blog_details($current_blog_id);

// Все сайты сети, кроме текущего
$sites = get_sites([
    'number'  => 200,
    'orderby' => 'blogname',
]);

$other_sites = array_filter($sites, fn($s) => (int) $s->blog_id !== $current_blog_id);

$all_categories  = get_terms([
    'taxonomy'   => 'product_cat',
    'hide_empty' => false,
    'orderby'    => 'name',
    'order'      => 'ASC',
]);

$binding_manager = WSF_Binding_Manager::get_instance();
?>
<div class="wrap">
    <h1>SEO Filter — Синхронизация товаров между сайтами по SKU</h1>

    <p class="description">
        Источник: <strong><?php echo esc_html($current_blog ? $current_blog->blogname : 'Текущий сайт'); ?></strong>
        (ID <?php echo $current_blog_id; ?>).
        Выберите категорию, целевые сайты и что синхронизировать.
        Для каждого товара на целевых сайтах ищется товар с тем же SKU — и к нему добавляются
        недостающие категории и/или атрибуты.
    </p>

    <?php if (empty($other_sites)) : ?>
        <div class="notice notice-warning">
            <p>В сети нет других сайтов для синхронизации.</p>
        </div>
    <?php else : ?>

    <div class="wsf-diag-layout">

        <!-- Sidebar: дерево категорий -->
        <div class="wsf-diag-sidebar">
            <h2>Категории</h2>

            <ul class="wsf-diag-tree">
                <li class="wsf-diag-cat" data-slug="">
                    <div class="wsf-diag-cat-row">
                        <span class="wsf-diag-indent"></span>
                        <a href="#" class="wsf-diag-cat-link wsf-mps-cat-link" data-slug="">
                            Все товары
                        </a>
                    </div>
                </li>
            </ul>

            <?php if (!is_wp_error($all_categories) && !empty($all_categories)) : ?>
                <?php WSF_Diagnose::render_category_tree($all_categories, $binding_manager); ?>
            <?php endif; ?>
        </div>

        <!-- Main panel -->
        <div class="wsf-diag-main">

            <!-- Настройки синхронизации (всегда видны) -->
            <div class="wsf-mps-settings">

                <div class="wsf-mps-settings-row">
                    <strong>Целевые сайты:</strong>
                    <div class="wsf-mps-sites">
                        <?php foreach ($other_sites as $site) :
                            $details = get_blog_details($site->blog_id);
                            $label   = $details ? $details->blogname : 'Site ' . $site->blog_id;
                        ?>
                            <label class="wsf-mps-site-label">
                                <input type="checkbox"
                                       class="wsf-mps-site-checkbox"
                                       name="target_sites[]"
                                       value="<?php echo (int) $site->blog_id; ?>"
                                       checked>
                                <?php echo esc_html($label); ?>
                                <span class="wsf-mps-site-url"><?php echo esc_html($site->domain . $site->path); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="wsf-mps-settings-row">
                    <strong>Синхронизировать:</strong>
                    <div class="wsf-mps-what">
                        <label>
                            <input type="checkbox" id="wsf-mps-sync-cats" checked>
                            Категории
                        </label>
                        <label>
                            <input type="checkbox" id="wsf-mps-sync-attrs" checked>
                            Атрибуты
                        </label>
                    </div>
                </div>

            </div><!-- .wsf-mps-settings -->

            <div id="wsf-mps-result" class="wsf-diag-placeholder">
                <p>← Выберите категорию, настройте параметры и нажмите «Синхронизировать»</p>
            </div>

        </div><!-- .wsf-diag-main -->

    </div><!-- .wsf-diag-layout -->

    <?php endif; ?>
</div>
