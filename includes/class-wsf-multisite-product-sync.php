<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Синхронизация категорий и атрибутов товаров между сайтами мультисайта по SKU.
 *
 * Текущий сайт — источник. Целевые сайты выбираются вручную.
 * Для каждого товара источника (с SKU) находим товар с тем же SKU на целевых сайтах
 * и копируем категории и/или атрибуты (режим: добавить, не перезаписывать).
 */
class WSF_Multisite_Product_Sync {

    private static $instance = null;

    private function __construct() {
        if (!is_multisite()) {
            return;
        }

        add_action('admin_menu', [$this, 'add_submenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_wsf_mps_load_category', [$this, 'ajax_load_category']);
        add_action('wp_ajax_wsf_mps_sync', [$this, 'ajax_sync']);
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function add_submenu() {
        add_submenu_page(
            'wsf-settings',
            'Синхронизация по SKU',
            'Синхронизация по SKU',
            'manage_options',
            'wsf-mps',
            [$this, 'render_page']
        );
    }

    public function enqueue_scripts($hook) {
        if ('seo-filter_page_wsf-mps' !== $hook) {
            return;
        }

        wp_enqueue_style('wsf-admin', WSF_URL . 'assets/css/admin.css', [], WSF_VERSION);
        wp_enqueue_style('wsf-diagnose', WSF_URL . 'assets/css/diagnose.css', ['wsf-admin'], WSF_VERSION);
        wp_enqueue_style('wsf-mps', WSF_URL . 'assets/css/multisite-product-sync.css', ['wsf-diagnose'], WSF_VERSION);
        wp_enqueue_script('wsf-mps', WSF_URL . 'assets/js/multisite-product-sync.js', ['jquery'], WSF_VERSION, true);

        wp_localize_script('wsf-mps', 'wsfMps', [
            'ajax_url'    => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('wsf_admin_nonce'),
            'current_site' => get_current_blog_id(),
        ]);
    }

    public function render_page() {
        include WSF_PATH . 'templates/admin/multisite-product-sync.php';
    }

    /**
     * AJAX: загрузить товары из категории (или все) с их SKU, категориями, атрибутами.
     */
    public function ajax_load_category() {
        check_ajax_referer('wsf_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $category_slug = isset($_POST['category_slug']) ? sanitize_text_field($_POST['category_slug']) : '';

        $products = $this->get_source_products($category_slug);

        // Ссылка и название выбранной категории
        $category_name = '';
        $category_url  = '';
        if (!empty($category_slug)) {
            $cat_term = get_term_by('slug', $category_slug, 'product_cat');
            if ($cat_term) {
                $category_name = $cat_term->name;
                $link          = get_term_link($cat_term);
                $category_url  = is_wp_error($link) ? '' : $link;
            }
        }

        wp_send_json_success([
            'category_slug' => $category_slug,
            'category_name' => $category_name,
            'category_url'  => $category_url,
            'products'      => $products,
            'total'         => count($products),
            'no_sku'        => count(array_filter($products, fn($p) => empty($p['sku']))),
        ]);
    }

    /**
     * AJAX: выполнить синхронизацию.
     *
     * POST params:
     *   category_slug   — slug категории или '' (все товары)
     *   target_sites    — array of site IDs
     *   sync_categories — '1' / '0'
     *   sync_attributes — '1' / '0'
     */
    public function ajax_sync() {
        check_ajax_referer('wsf_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $category_slug    = isset($_POST['category_slug']) ? sanitize_text_field($_POST['category_slug']) : '';
        $raw_targets      = isset($_POST['target_sites']) ? (array) $_POST['target_sites'] : [];
        $sync_categories  = !empty($_POST['sync_categories']);
        $sync_attributes  = !empty($_POST['sync_attributes']);

        if (!$sync_categories && !$sync_attributes) {
            wp_send_json_error(['message' => 'Выберите хотя бы одно: категории или атрибуты']);
        }

        $target_site_ids = array_map('intval', $raw_targets);
        $target_site_ids = array_filter($target_site_ids, fn($id) => $id > 0 && $id !== get_current_blog_id());

        if (empty($target_site_ids)) {
            wp_send_json_error(['message' => 'Выберите хотя бы один целевой сайт']);
        }

        // Загружаем товары источника
        $source_products = $this->get_source_products($category_slug);
        $skipped_no_sku  = 0;
        $results_by_site = [];

        foreach ($target_site_ids as $site_id) {
            $site_info = get_blog_details($site_id);
            $site_name = $site_info ? $site_info->blogname : 'Site ' . $site_id;

            $site_result = [
                'site_id'   => $site_id,
                'site_name' => $site_name,
                'synced'    => 0,
                'skipped'   => 0,
                'not_found' => 0,
                'errors'    => 0,
                'details'   => [],
            ];

            switch_to_blog($site_id);

            foreach ($source_products as $product) {
                if (empty($product['sku'])) {
                    $skipped_no_sku++;
                    continue;
                }

                $target_id = wc_get_product_id_by_sku($product['sku']);

                if (!$target_id) {
                    $site_result['not_found']++;
                    $site_result['details'][] = [
                        'sku'    => $product['sku'],
                        'title'  => $product['title'],
                        'status' => 'not_found',
                        'msg'    => 'SKU не найден',
                    ];
                    continue;
                }

                $ops = [];

                if ($sync_categories && !empty($product['categories'])) {
                    $cat_slugs  = array_column($product['categories'], 'slug');
                    $cats_added = $this->sync_categories($target_id, $cat_slugs);
                    if ($cats_added > 0) {
                        $ops[] = 'категории +' . $cats_added;
                    }
                }

                if ($sync_attributes && !empty($product['attributes'])) {
                    $attrs_added = $this->sync_attributes($target_id, $product['attributes']);
                    if ($attrs_added > 0) {
                        $ops[] = 'атрибуты +' . $attrs_added;
                    }
                }

                if (!empty($ops)) {
                    $site_result['synced']++;
                    $site_result['details'][] = [
                        'sku'    => $product['sku'],
                        'title'  => $product['title'],
                        'status' => 'synced',
                        'msg'    => implode(', ', $ops),
                    ];
                } else {
                    $site_result['skipped']++;
                    $site_result['details'][] = [
                        'sku'    => $product['sku'],
                        'title'  => $product['title'],
                        'status' => 'skipped',
                        'msg'    => 'Уже актуален',
                    ];
                }
            }

            restore_current_blog();

            $results_by_site[] = $site_result;
        }

        wp_send_json_success([
            'total_source'  => count($source_products),
            'skipped_no_sku' => $skipped_no_sku,
            'results'       => $results_by_site,
        ]);
    }

    /**
     * Возвращает товары источника с SKU, категориями и атрибутами.
     *
     * @param string $category_slug  Slug категории или '' для всех.
     * @return array[]
     */
    private function get_source_products($category_slug = '') {
        $args = [
            'post_type'              => 'product',
            'posts_per_page'         => -1,
            'post_status'            => 'publish',
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];

        if (!empty($category_slug)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => $category_slug,
                ],
            ];
        }

        $query = new WP_Query($args);
        $result = [];

        foreach ($query->posts as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) {
                continue;
            }

            $sku = $product->get_sku();

            // Категории — возвращаем slug, name и URL для каждой
            $raw_cats = wp_get_object_terms($product_id, 'product_cat', ['fields' => 'all']);
            $cats = [];
            if (!is_wp_error($raw_cats)) {
                foreach ($raw_cats as $cat_term) {
                    $link   = get_term_link($cat_term);
                    $cats[] = [
                        'slug' => $cat_term->slug,
                        'name' => $cat_term->name,
                        'url'  => is_wp_error($link) ? '' : $link,
                    ];
                }
            }

            // Атрибуты-таксономии (slug => [values])
            $attrs = [];
            foreach ($product->get_attributes() as $attr_key => $attr_obj) {
                if (!$attr_obj->is_taxonomy()) {
                    continue;
                }
                $terms = wp_get_object_terms($product_id, $attr_key, ['fields' => 'slugs']);
                if (is_wp_error($terms)) {
                    continue;
                }
                $attr_name = str_replace('pa_', '', $attr_key);
                foreach ($terms as $term_slug) {
                    $attrs[] = [
                        'attribute' => $attr_name,
                        'value'     => $term_slug,
                    ];
                }
            }

            $result[] = [
                'id'         => $product_id,
                'sku'        => $sku,
                'title'      => $product->get_name(),
                'categories' => $cats,
                'attributes' => $attrs,
            ];
        }

        return $result;
    }

    /**
     * Добавляет категории товару на целевом сайте (append, только существующие slugs).
     *
     * @return int Количество добавленных категорий.
     */
    private function sync_categories($product_id, $source_slugs) {
        $current_slugs = wp_get_object_terms($product_id, 'product_cat', ['fields' => 'slugs']);
        $current_slugs = is_wp_error($current_slugs) ? [] : $current_slugs;

        $term_ids_to_add = [];

        foreach ($source_slugs as $slug) {
            if (in_array($slug, $current_slugs, true)) {
                continue; // Уже есть
            }
            $term = get_term_by('slug', $slug, 'product_cat');
            if ($term && !is_wp_error($term)) {
                $term_ids_to_add[] = $term->term_id;
            }
        }

        if (!empty($term_ids_to_add)) {
            wp_set_object_terms($product_id, $term_ids_to_add, 'product_cat', true);
        }

        return count($term_ids_to_add);
    }

    /**
     * Добавляет атрибуты товару на целевом сайте (append, только существующие термины).
     *
     * @return int Количество добавленных значений атрибутов.
     */
    private function sync_attributes($product_id, $source_attrs) {
        $product    = wc_get_product($product_id);
        $attributes = $product->get_attributes();
        $changed    = false;
        $added      = 0;

        foreach ($source_attrs as $attr) {
            $taxonomy = 'pa_' . $attr['attribute'];
            $attr_key = sanitize_title($taxonomy);
            $term     = get_term_by('slug', $attr['value'], $taxonomy);

            if (!$term || is_wp_error($term)) {
                continue; // Термин не существует на целевом сайте
            }

            if (isset($attributes[$attr_key])) {
                $attr_obj = $attributes[$attr_key];
                $options  = $attr_obj->get_options();
                if (in_array($term->term_id, $options, true)) {
                    continue; // Уже есть
                }
                $options[] = $term->term_id;
                $attr_obj->set_options($options);
                $attributes[$attr_key] = $attr_obj;
            } else {
                $wc_attr = new WC_Product_Attribute();
                $wc_attr->set_id(wc_attribute_taxonomy_id_by_name($attr['attribute']));
                $wc_attr->set_name($taxonomy);
                $wc_attr->set_options([$term->term_id]);
                $wc_attr->set_position(count($attributes));
                $wc_attr->set_visible(1);
                $wc_attr->set_variation(0);
                $attributes[$attr_key] = $wc_attr;
            }

            wp_set_object_terms($product_id, $term->term_id, $taxonomy, true);
            $changed = true;
            $added++;
        }

        if ($changed) {
            $product->set_attributes($attributes);
            $product->save();
        }

        return $added;
    }
}
