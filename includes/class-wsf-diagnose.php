<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Вспомогательный класс со статическим методом рендера дерева категорий.
 * Используется шаблонами страниц синхронизации.
 */
class WSF_Diagnose {

    /**
     * Рекурсивно выводит дерево категорий WooCommerce.
     * Используется на страницах синхронизации.
     *
     * @param array               $categories   Все категории (результат get_terms).
     * @param WSF_Binding_Manager $bm           Менеджер связок.
     * @param int                 $parent_id    ID родительской категории.
     */
    public static function render_category_tree($categories, $bm, $parent_id = 0) {
        $children = array_filter($categories, fn($c) => (int) $c->parent === $parent_id);

        if (empty($children)) {
            return;
        }
        ?>
        <ul class="wsf-diag-tree">
            <?php foreach ($children as $cat) :
                $has_binding  = (bool) $bm->get_binding_by_slug($cat->slug);
                $has_children = !empty(array_filter($categories, fn($c) => (int) $c->parent === $cat->term_id));
            ?>
                <li class="wsf-diag-cat<?php echo $has_children ? ' has-children' : ''; ?>"
                    data-slug="<?php echo esc_attr($cat->slug); ?>">

                    <div class="wsf-diag-cat-row">
                        <?php if ($has_children) : ?>
                            <button class="wsf-diag-toggle" type="button" aria-label="Развернуть">▶</button>
                        <?php else : ?>
                            <span class="wsf-diag-indent"></span>
                        <?php endif; ?>

                        <a href="#" class="wsf-diag-cat-link"
                           data-slug="<?php echo esc_attr($cat->slug); ?>">
                            <?php echo esc_html($cat->name); ?>
                        </a>

                        <span class="wsf-diag-count"><?php echo (int) $cat->count; ?></span>

                        <?php if ($has_binding) : ?>
                            <span class="wsf-diag-binding-badge" title="Есть связка">🔗</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($has_children) : ?>
                        <div class="wsf-diag-subtree collapsed">
                            <?php self::render_category_tree($categories, $bm, $cat->term_id); ?>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }
}
