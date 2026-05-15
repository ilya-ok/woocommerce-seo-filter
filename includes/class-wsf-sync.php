<?php

if (!defined('ABSPATH')) {
    exit;
}

class WSF_Sync {

    private static $instance = null;

    private function __construct() {
        add_action('admin_menu', [$this, 'add_submenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_wsf_sync_get_products', [$this, 'ajax_get_products']);
        add_action('wp_ajax_wsf_sync_category', [$this, 'ajax_sync_category']);
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
            'Синхронизация атрибутов',
            'Синхронизация',
            'manage_options',
            'wsf-sync',
            [$this, 'render_page']
        );
    }

    public function enqueue_scripts($hook) {
        if ('seo-filter_page_wsf-sync' !== $hook) {
            return;
        }

        wp_enqueue_style('wsf-admin', WSF_URL . 'assets/css/admin.css', [], WSF_VERSION);
        wp_enqueue_style('wsf-diagnose', WSF_URL . 'assets/css/diagnose.css', ['wsf-admin'], WSF_VERSION);
        wp_enqueue_style('wsf-sync', WSF_URL . 'assets/css/sync.css', ['wsf-diagnose'], WSF_VERSION);
        wp_enqueue_script('wsf-sync', WSF_URL . 'assets/js/sync.js', ['jquery'], WSF_VERSION, true);

        wp_localize_script('wsf-sync', 'wsfSync', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('wsf_admin_nonce'),
        ]);
    }

    public function render_page() {
        include WSF_PATH . 'templates/admin/sync.php';
    }

    /**
     * AJAX: получить список товаров категории и статус синхронизации.
     */
    public function ajax_get_products() {
        check_ajax_referer('wsf_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $slug = isset($_POST['category_slug']) ? sanitize_text_field($_POST['category_slug']) : '';

        if (empty($slug)) {
            wp_send_json_error(['message' => 'Category slug required']);
        }

        $cat = get_term_by('slug', $slug, 'product_cat');
        if (!$cat) {
            wp_send_json_error(['message' => 'Category not found']);
        }

        $binding = WSF_Binding_Manager::get_instance()->get_binding_by_slug($slug);

        // Получаем все товары категории
        $product_ids = $this->get_product_ids_by_category($slug);

        $needs_sync    = [];
        $already_synced = [];

        if ($binding && !empty($binding['attributes'])) {
            foreach ($product_ids as $id) {
                $missing = $this->get_missing_attributes($id, $binding['attributes']);
                if (!empty($missing)) {
                    $needs_sync[] = [
                        'id'       => $id,
                        'title'    => get_the_title($id),
                        'edit_url' => get_edit_post_link($id, 'raw'),
                        'missing'  => $missing,
                    ];
                } else {
                    $already_synced[] = [
                        'id'       => $id,
                        'title'    => get_the_title($id),
                        'edit_url' => get_edit_post_link($id, 'raw'),
                    ];
                }
            }
        } else {
            // Связки нет — просто отдаём список товаров
            foreach ($product_ids as $id) {
                $already_synced[] = [
                    'id'       => $id,
                    'title'    => get_the_title($id),
                    'edit_url' => get_edit_post_link($id, 'raw'),
                ];
            }
        }

        wp_send_json_success([
            'category'       => ['name' => $cat->name, 'slug' => $cat->slug],
            'binding'        => $binding,
            'needs_sync'     => $needs_sync,
            'already_synced' => $already_synced,
        ]);
    }

    /**
     * AJAX: синхронизировать атрибуты — добавить атрибуты из связки
     * всем товарам категории, у которых они отсутствуют.
     */
    public function ajax_sync_category() {
        check_ajax_referer('wsf_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $slug = isset($_POST['category_slug']) ? sanitize_text_field($_POST['category_slug']) : '';

        if (empty($slug)) {
            wp_send_json_error(['message' => 'Category slug required']);
        }

        $binding = WSF_Binding_Manager::get_instance()->get_binding_by_slug($slug);

        if (!$binding || empty($binding['attributes'])) {
            wp_send_json_error(['message' => 'No binding or attributes for this category']);
        }

        $product_ids = $this->get_product_ids_by_category($slug);
        $results     = [];
        $synced      = 0;
        $skipped     = 0;

        foreach ($product_ids as $id) {
            $missing = $this->get_missing_attributes($id, $binding['attributes']);

            if (empty($missing)) {
                $skipped++;
                $results[] = [
                    'id'      => $id,
                    'title'   => get_the_title($id),
                    'status'  => 'skipped',
                    'message' => 'Уже синхронизирован',
                ];
                continue;
            }

            $ok = $this->assign_attributes($id, $missing);

            if ($ok) {
                $synced++;
                $attr_labels = array_map(fn($a) => $a['attribute'] . '=' . $a['value'], $missing);
                $results[] = [
                    'id'      => $id,
                    'title'   => get_the_title($id),
                    'status'  => 'synced',
                    'message' => 'Добавлено: ' . implode(', ', $attr_labels),
                ];
            } else {
                $results[] = [
                    'id'      => $id,
                    'title'   => get_the_title($id),
                    'status'  => 'error',
                    'message' => 'Ошибка при сохранении',
                ];
            }
        }

        wp_send_json_success([
            'synced'  => $synced,
            'skipped' => $skipped,
            'total'   => count($product_ids),
            'results' => $results,
        ]);
    }

    /**
     * Получает ID всех товаров (publish) в заданной категории.
     *
     * @param string $slug Slug категории.
     * @return int[]
     */
    private function get_product_ids_by_category($slug) {
        $query = new WP_Query([
            'post_type'              => 'product',
            'posts_per_page'         => -1,
            'post_status'            => 'publish',
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'tax_query'              => [
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => $slug,
                ],
            ],
        ]);

        return $query->posts;
    }

    /**
     * Возвращает атрибуты из $required, которых нет у товара $product_id.
     *
     * @param int   $product_id  ID товара.
     * @param array $required    Массив [['attribute'=>'slug', 'value'=>'slug'], ...].
     * @return array             Только те, которые отсутствуют.
     */
    private function get_missing_attributes($product_id, $required) {
        $missing = [];

        foreach ($required as $attr) {
            $taxonomy = 'pa_' . $attr['attribute'];
            $terms    = wp_get_object_terms($product_id, $taxonomy, ['fields' => 'slugs']);

            if (is_wp_error($terms) || !in_array($attr['value'], $terms, true)) {
                $missing[] = $attr;
            }
        }

        return $missing;
    }

    /**
     * Добавляет атрибуты товару через WooCommerce Product API.
     *
     * @param int   $product_id  ID товара.
     * @param array $attrs       Массив [['attribute'=>'slug', 'value'=>'slug'], ...].
     * @return bool              true при успехе.
     */
    private function assign_attributes($product_id, $attrs) {
        $product    = wc_get_product($product_id);
        $attributes = $product->get_attributes();
        $changed    = false;

        foreach ($attrs as $attr) {
            $taxonomy = 'pa_' . $attr['attribute'];
            $attr_key = sanitize_title($taxonomy);
            $term     = get_term_by('slug', $attr['value'], $taxonomy);

            if (!$term) {
                continue; // Термин не найден — пропускаем
            }

            if (isset($attributes[$attr_key])) {
                // Атрибут уже есть у товара — добавляем значение
                $attr_obj = $attributes[$attr_key];
                $options  = $attr_obj->get_options(); // array of term IDs
                if (!in_array($term->term_id, $options, true)) {
                    $options[] = $term->term_id;
                    $attr_obj->set_options($options);
                    $attributes[$attr_key] = $attr_obj;
                    $changed = true;
                }
            } else {
                // Атрибута нет совсем — создаём
                $wc_attr = new WC_Product_Attribute();
                $wc_attr->set_id(wc_attribute_taxonomy_id_by_name($attr['attribute']));
                $wc_attr->set_name($taxonomy);
                $wc_attr->set_options([$term->term_id]);
                $wc_attr->set_position(count($attributes));
                $wc_attr->set_visible(1);
                $wc_attr->set_variation(0);
                $attributes[$attr_key] = $wc_attr;
                $changed = true;
            }

            // Явно устанавливаем связь термина с товаром
            wp_set_object_terms($product_id, $term->term_id, $taxonomy, true);
        }

        if ($changed) {
            $product->set_attributes($attributes);
            $product->save();
        }

        return true;
    }
}
