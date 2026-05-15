<?php
if (!defined('ABSPATH')) {
    exit;
}

$category_attributes = get_option('wsf_category_attributes', []);
$category_display = get_option('wsf_category_display_settings', []);
$category_available_attrs = get_option('wsf_category_available_attributes', []);

$all_wc_categories = get_terms([
    'taxonomy' => 'product_cat',
    'hide_empty' => false,
]);

$all_attributes = wc_get_attribute_taxonomies();

// Функция для построения иерархического списка категорий
function wsf_attributes_build_category_options($categories, $selected = '', $parent = 0, $level = 0) {
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
            
            $output .= wsf_attributes_build_category_options($categories, $selected, $category->term_id, $level + 1);
        }
    }
    
    return $output;
}

// Подготавливаем данные с порядком категорий
$configured_data = [];

// Настройки для главной страницы магазина
$shop_page_attrs = isset($category_available_attrs['shop']) ? $category_available_attrs['shop'] : [];
$shop_display_mode = isset($category_display['shop']) ? $category_display['shop'] : 'filters';

// Остальные категории (без shop)
foreach ($category_available_attrs as $slug => $attrs) {
    if ($slug === 'shop') continue;
    
    $category = get_term_by('slug', $slug, 'product_cat');
    if (!$category) continue;
    
    $configured_data[] = [
        'slug' => $slug,
        'name' => $category->name,
        'attributes' => $attrs,
        'display_mode' => isset($category_display[$slug]) ? $category_display[$slug] : 'filters',
        'order' => isset($category_attributes[$slug . '_order']) ? $category_attributes[$slug . '_order'] : 999
    ];
}

// Сортируем по порядку
usort($configured_data, function($a, $b) {
    return $a['order'] - $b['order'];
});
?>

<div class="wrap wsf-attributes-settings-wrap">
    <h1>Filter Attributes Settings</h1>
    
    <form method="post" id="wsf-attributes-form">
        <?php wp_nonce_field('wsf_save_attributes', 'wsf_attributes_nonce'); ?>
        
        <!-- Главная страница магазина - всегда первая -->
        <div class="wsf-shop-page-settings">
            <h2>Настройки для главной страницы магазина</h2>
            <?php
            $data = [
                'slug' => 'shop',
                'name' => 'Главная страница магазина',
                'attributes' => $shop_page_attrs,
                'display_mode' => $shop_display_mode,
                'order' => -1
            ];
            include WSF_PATH . 'templates/admin/attribute-category-item.php';
            ?>
        </div>
        
        <h2 style="margin-top: 30px;">Настройки категорий</h2>
        
        <div id="wsf-categories-list" class="wsf-categories-list">
            <?php foreach ($configured_data as $index => $data) : ?>
                <?php include WSF_PATH . 'templates/admin/attribute-category-item.php'; ?>
            <?php endforeach; ?>
        </div>
        
        <div class="wsf-add-category-section" style="margin-top: 20px;">
            <h3>Добавить категорию</h3>
            <div style="display: flex; gap: 10px; align-items: center;">
                <select id="wsf-new-category-select" style="min-width: 300px;">
                    <option value="">Выберите категорию</option>
                    <?php echo wsf_attributes_build_category_options($all_wc_categories); ?>
                </select>
                <button type="button" class="button button-primary" id="wsf-add-category-btn">
                    Добавить категорию
                </button>
            </div>
        </div>
        
        <div class="wsf-save-container">
            <button type="button" class="button button-primary button-large" id="wsf-save-attributes">
                Сохранить все настройки
            </button>
            <span class="wsf-save-status"></span>
        </div>
    </form>
</div>

<script type="text/template" id="wsf-category-item-template">
    <?php
    $index = '{{INDEX}}';
    $data = [
        'slug' => '{{SLUG}}',
        'name' => '{{NAME}}',
        'attributes' => [],
        'display_mode' => 'filters',
        'order' => 999
    ];
    include WSF_PATH . 'templates/admin/attribute-category-item.php';
    ?>
</script>

<script type="text/template" id="wsf-attribute-item-template">
    <div class="wsf-attribute-item" data-attr-slug="{{ATTR_SLUG}}">
        <span class="wsf-attribute-handle dashicons dashicons-menu"></span>
        <span class="wsf-attribute-name">{{ATTR_NAME}}</span>
        <input type="hidden" name="{{FIELD_NAME}}[{{CAT_SLUG}}][]" value="{{ATTR_SLUG}}">
        <button type="button" class="button-link wsf-remove-attribute-btn">
            <span class="dashicons dashicons-trash"></span>
        </button>
    </div>
</script>

<script>
jQuery(document).ready(function($) {
    
    // Drag & Drop для категорий (исключая shop)
    $('#wsf-categories-list').sortable({
        handle: '.wsf-category-handle',
        placeholder: 'wsf-category-placeholder',
        axis: 'y',
        opacity: 0.7,
        tolerance: 'pointer',
        items: '.wsf-category-item:not([data-category-slug="shop"])'
    });
    
    // Drag & Drop для атрибутов внутри категории
    function initAttributesSortable() {
        $('.wsf-attributes-sortable').sortable({
            handle: '.wsf-attribute-handle',
            placeholder: 'wsf-attribute-placeholder',
            axis: 'y',
            opacity: 0.7,
            tolerance: 'pointer'
        });
    }
    
    initAttributesSortable();
    
    // Инициализируем sortable для shop page при загрузке
    $('.wsf-shop-page-settings .wsf-attributes-sortable').sortable({
        handle: '.wsf-attribute-handle',
        placeholder: 'wsf-attribute-placeholder',
        axis: 'y',
        opacity: 0.7,
        tolerance: 'pointer'
    });
    
    // Добавление категории
    $('#wsf-add-category-btn').on('click', function() {
        const $select = $('#wsf-new-category-select');
        const categorySlug = $select.val();
        const categoryName = $select.find('option:selected').text().replace(/^—+\s*/, '');
        
        if (!categorySlug) {
            alert('Выберите категорию');
            return;
        }
        
        if ($('.wsf-category-item[data-category-slug="' + categorySlug + '"]').length) {
            alert('Эта категория уже добавлена');
            return;
        }
        
        const template = $('#wsf-category-item-template').html();
        const index = $('.wsf-category-item').length;
        const newItem = template
            .replace(/\{\{INDEX\}\}/g, index)
            .replace(/\{\{SLUG\}\}/g, categorySlug)
            .replace(/\{\{NAME\}\}/g, categoryName);
        
        $('#wsf-categories-list').append(newItem);
        $select.val('');
        
        initAttributesSortable();
    });
    
    // Удаление категории
    $(document).on('click', '.wsf-remove-category-btn', function() {
        if (confirm('Удалить настройки этой категории?')) {
            $(this).closest('.wsf-category-item').remove();
        }
    });
    
    // Добавление атрибута - убрали кнопку, работает только через select
    $(document).on('click', '.wsf-add-attribute-btn', function() {
        // Этот обработчик больше не нужен
    });
    
    // Выбор атрибута из списка
    $(document).on('change', '.wsf-attribute-select', function() {
        const attrSlug = $(this).val();
        const attrName = $(this).find('option:selected').text();
        const $categoryItem = $(this).closest('.wsf-category-item');
        const categorySlug = $categoryItem.data('category-slug');
        
        if (!attrSlug) return;
        
        // Определяем тип (filter или badge) по классу select
        const isFilter = $(this).hasClass('wsf-attribute-select-filter');
        const attrType = isFilter ? 'filter' : 'badge';
        
        const $list = $categoryItem.find('.wsf-attributes-sortable[data-type="' + attrType + '"]');
        
        // Проверяем, не добавлен ли уже
        if ($list.find('[data-attr-slug="' + attrSlug + '"]').length) {
            alert('Этот атрибут уже добавлен');
            $(this).val('');
            return;
        }
        
        const template = $('#wsf-attribute-item-template').html();
        const fieldName = attrType === 'filter' ? 'category_available_attributes' : 'category_badge_attributes';
        
        const newAttr = template
            .replace(/\{\{ATTR_SLUG\}\}/g, attrSlug)
            .replace(/\{\{ATTR_NAME\}\}/g, attrName)
            .replace(/\{\{CAT_SLUG\}\}/g, categorySlug)
            .replace(/\{\{FIELD_NAME\}\}/g, fieldName);
        
        $list.append(newAttr);
        
        // Сбрасываем select обратно на placeholder
        $(this).val('');
    });
    
    // Удаление атрибута
    $(document).on('click', '.wsf-remove-attribute-btn', function() {
        $(this).closest('.wsf-attribute-item').remove();
    });
    
    // Сохранение
    $('#wsf-save-attributes').on('click', function() {
        const $button = $(this);
        const $status = $('.wsf-save-status');
        
        $button.prop('disabled', true);
        $status.html('<span class="spinner is-active"></span>');
        
        const categories = {};
        const display = {};
        const attributes = {};
        const badgeAttributes = {};
        
        // Сохраняем настройки главной страницы магазина
        const $shopItem = $('.wsf-shop-page-settings .wsf-category-item');
        if ($shopItem.length) {
            const shopDisplayMode = $shopItem.find('input[name="category_display[shop]"]:checked').val();
            display['shop'] = shopDisplayMode;
            
            const shopAttrs = [];
            $shopItem.find('.wsf-attributes-sortable[data-type="filter"] .wsf-attribute-item').each(function() {
                shopAttrs.push($(this).data('attr-slug'));
            });
            attributes['shop'] = shopAttrs;
            
            const shopBadgeAttrs = [];
            $shopItem.find('.wsf-attributes-sortable[data-type="badge"] .wsf-attribute-item').each(function() {
                shopBadgeAttrs.push($(this).data('attr-slug'));
            });
            badgeAttributes['shop'] = shopBadgeAttrs;
        }
        
        // Сохраняем настройки остальных категорий
        $('#wsf-categories-list .wsf-category-item').each(function(order) {
            const $item = $(this);
            const slug = $item.data('category-slug');
            
            if (slug === 'shop') return; // Пропускаем shop, уже сохранен
            
            const displayMode = $item.find('input[name="category_display[' + slug + ']"]:checked').val();
            
            display[slug] = displayMode;
            
            const attrs = [];
            $item.find('.wsf-attributes-sortable[data-type="filter"] .wsf-attribute-item').each(function() {
                attrs.push($(this).data('attr-slug'));
            });
            
            attributes[slug] = attrs;
            
            const badgeAttrs = [];
            $item.find('.wsf-attributes-sortable[data-type="badge"] .wsf-attribute-item').each(function() {
                badgeAttrs.push($(this).data('attr-slug'));
            });
            
            badgeAttributes[slug] = badgeAttrs;
            categories[slug + '_order'] = order;
        });
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wsf_save_category_attributes',
                wsf_attributes_nonce: $('input[name="wsf_attributes_nonce"]').val(),
                category_attributes: categories,
                category_display: display,
                category_available_attributes: attributes,
                category_badge_attributes: badgeAttributes
            },
            success: function(response) {
                $button.prop('disabled', false);
                
                if (response.success) {
                    $status.html('<span style="color: green;">✓ Сохранено успешно</span>');
                } else {
                    $status.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                }
                
                setTimeout(function() {
                    $status.html('');
                }, 3000);
            },
            error: function() {
                $button.prop('disabled', false);
                $status.html('<span style="color: red;">✗ Ошибка сохранения</span>');
                
                setTimeout(function() {
                    $status.html('');
                }, 3000);
            }
        });
    });
    
    // Прилипающая кнопка сохранения
    function handleStickyButton() {
        const $container = $('#wsf-categories-list');
        const $saveContainer = $('.wsf-save-container');
        const containerHeight = $container.outerHeight();
        const windowHeight = $(window).height();
        
        if (containerHeight > windowHeight) {
            $saveContainer.addClass('wsf-sticky');
        } else {
            $saveContainer.removeClass('wsf-sticky');
        }
    }
    
    $(window).on('resize scroll', handleStickyButton);
    handleStickyButton();
});
</script>