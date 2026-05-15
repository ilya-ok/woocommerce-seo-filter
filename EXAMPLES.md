# Примеры использования

## Пример 1: Простой фильтр по высоте ворса

### Настройка

**Категории:**
- Искусственный газон (`iskusstvennyj-gazon`) - родительская
- 10 мм газон (`10-mm-gazon`) - дочерняя
- 15 мм газон (`15-mm-gazon`) - дочерняя
- 20 мм газон (`20-mm-gazon`) - дочерняя

**Атрибут WooCommerce:**
- Название: Высота ворса
- Slug: `vysota-vorsa`
- Значения: `10-mm`, `15-mm`, `20-mm`

**Связки в плагине:**

```
Связка 1:
- Категория: 10-mm-gazon
- Родитель: iskusstvennyj-gazon
- Атрибуты: vysota-vorsa = 10-mm

Связка 2:
- Категория: 15-mm-gazon
- Родитель: iskusstvennyj-gazon
- Атрибуты: vysota-vorsa = 15-mm

Связка 3:
- Категория: 20-mm-gazon
- Родитель: iskusstvennyj-gazon
- Атрибуты: vysota-vorsa = 20-mm
```

### Результат

**URL структура:**
- `ural-floor.ru/iskusstvennyj-gazon/` - все товары
- `ural-floor.ru/iskusstvennyj-gazon/10-mm-gazon/` - товары с высотой 10 мм
- `ural-floor.ru/iskusstvennyj-gazon/15-mm-gazon/` - товары с высотой 15 мм

**Автоматический редирект:**
- `?filter_vysota-vorsa=10-mm` → `/10-mm-gazon/`

---

## Пример 2: Многоуровневая структура спортивных покрытий

### Настройка

**Категории:**
```
Спортивные покрытия (sportivnye-pokrytiya)
├── Резиновые покрытия (rezinovye-pokrytiya)
│   ├── Резиновая плитка (rezinovaya-plitka)
│   │   ├── Резиновая плитка для улицы (rezinovaya-plitka-ulitsa)
│   │   └── Резиновая плитка для зала (rezinovaya-plitka-zal)
│   └── Резиновые рулоны (rezinovye-rulony)
├── ПВХ покрытия (pvh-pokrytiya)
└── Искусственный газон (iskusstvennyj-gazon-sport)
```

**Атрибуты WooCommerce:**
- `material` (Материал): rezinovoe, pvh, gazon
- `tip` (Тип): plitka, rulon
- `naznachenie` (Назначение): dlya-ulitsy, dlya-zala

**Связки:**

```
Уровень 1:
Категория: rezinovye-pokrytiya
Родитель: sportivnye-pokrytiya
Атрибуты: material = rezinovoe

Уровень 2:
Категория: rezinovaya-plitka
Родитель: rezinovye-pokrytiya
Атрибуты: 
  - material = rezinovoe
  - tip = plitka

Уровень 3:
Категория: rezinovaya-plitka-ulitsa
Родитель: rezinovaya-plitka
Атрибуты:
  - material = rezinovoe
  - tip = plitka
  - naznachenie = dlya-ulitsy

Категория: rezinovaya-plitka-zal
Родитель: rezinovaya-plitka
Атрибуты:
  - material = rezinovoe
  - tip = plitka
  - naznachenie = dlya-zala
```

### Результат

**URL структура:**
```
/sportivnye-pokrytiya/
├── /rezinovye-pokrytiya/ (material=rezinovoe)
│   ├── /rezinovaya-plitka/ (material=rezinovoe, tip=plitka)
│   │   ├── /rezinovaya-plitka-ulitsa/ (+ naznachenie=dlya-ulitsy)
│   │   └── /rezinovaya-plitka-zal/ (+ naznachenie=dlya-zala)
```

**Активные фильтры на странице `/rezinovaya-plitka-ulitsa/`:**
- Материал: Резиновое покрытие (удалить → `/rezinovaya-plitka/`)
- Тип: Плитка (удалить → `/rezinovye-pokrytiya/?filter_naznachenie=dlya-ulitsy`)
- Назначение: Для улицы (удалить → `/rezinovaya-plitka/`)

---

## Пример 3: Комбинация SEO и обычных фильтров

### Сценарий

Пользователь находится на странице `/rezinovaya-plitka/` (материал=резиновое, тип=плитка).  
Он выбирает дополнительный фильтр "Цвет: Черный" (который не имеет SEO категории).

**Результат:**
```
/rezinovaya-plitka/?filter_cvet=chernyj&query_type_cvet=or
```

Теперь он выбирает "Назначение: Для улицы" (которое имеет SEO категорию):

**Результат:**
```
/rezinovaya-plitka-ulitsa/?filter_cvet=chernyj&query_type_cvet=or
```

**Активные фильтры:**
- Материал: Резиновое
- Тип: Плитка
- Назначение: Для улицы
- Цвет: Черный (query параметр)

---

## Пример 4: Использование виджетов

### В sidebar.php темы:

```php
<?php
// Виджет фильтра по материалу
the_widget('WSF_Filter_Widget', [
    'title' => 'Материал',
    'attribute' => 'material',
    'display_type' => 'list'
]);

// Виджет фильтра по типу
the_widget('WSF_Filter_Widget', [
    'title' => 'Тип покрытия',
    'attribute' => 'tip',
    'display_type' => 'list'
]);

// Активные фильтры
the_widget('WSF_Active_Filters_Widget', [
    'title' => 'Выбранные фильтры'
]);
?>
```

### С помощью шорткодов:

```php
// В шаблоне
<?php echo do_shortcode('[wsf_filters attribute="material" title="Материал"]'); ?>
<?php echo do_shortcode('[wsf_filters attribute="tip" title="Тип покрытия"]'); ?>
<?php echo do_shortcode('[wsf_active_filters]'); ?>
```

### В редакторе Gutenberg:

```
Добавьте блок "Shortcode" и вставьте:

[wsf_filters attribute="material" title="Материал" type="list"]
[wsf_filters attribute="vysota-vorsa" title="Высота ворса" type="dropdown"]
[wsf_active_filters title="Активные фильтры"]
```

---

## Пример 5: Множественный выбор

### Сценарий

Пользователь хочет видеть товары с высотой ворса 10 мм ИЛИ 15 мм.

**Если обе категории существуют:**
Создайте отдельную категорию для комбинации или используйте query параметры:

```
/iskusstvennyj-gazon/?filter_vysota-vorsa=10-mm,15-mm&query_type_vysota-vorsa=or
```

**Если нужна SEO категория для комбинации:**

Создайте категорию:
- Slug: `gazon-10-15-mm`
- Связка: vysota-vorsa = 10-mm, vysota-vorsa = 15-mm

---

## Пример 6: Сброс фильтров

### Кнопка сброса всех фильтров:

На странице `/rezinovaya-plitka-ulitsa/?filter_cvet=chernyj`:

**Кнопка "Сбросить все фильтры"** ведет на:
- Если у родителя есть атрибуты → на родителя без атрибутов
- Если родитель - каталог → на главную страницу магазина

В данном случае: `/rezinovye-pokrytiya/` (первый родитель без дополнительных query параметров)

---

## Пример 7: Программное получение данных

### Получить активные фильтры:

```php
$binding_manager = WSF_Binding_Manager::get_instance();
$current_slug = 'rezinovaya-plitka';
$query_params = $_GET;

$active_filters = $binding_manager->get_active_filters($current_slug, $query_params);

foreach ($active_filters as $filter) {
    echo $filter['attribute'] . ': ' . $filter['value'];
}
```

### Получить URL фильтра:

```php
$filter_manager = WSF_Filter_Manager::get_instance();

$url = $filter_manager->get_filter_url(
    'vysota-vorsa',  // атрибут
    '10-mm',         // значение
    'iskusstvennyj-gazon',  // текущая категория
    $_GET            // текущие параметры
);

echo '<a href="' . $url . '">10 мм</a>';
```

### Проверить, нужно ли показывать фильтры:

```php
$category_helper = WSF_Category_Helper::get_instance();

if ($category_helper->should_display_filters('rezinovaya-plitka')) {
    // Показываем фильтры
}

if ($category_helper->should_display_subcategories('rezinovaya-plitka')) {
    // Показываем подкатегории
}
```

---

## Пример 8: Интеграция с кастомным шаблоном

### archive-product.php

```php
<?php
get_header('shop');

$current_term = get_queried_object();
$current_slug = $current_term->slug ?? '';
$category_helper = WSF_Category_Helper::get_instance();
?>

<div class="shop-container">
    <aside class="shop-sidebar">
        <?php if ($category_helper->should_display_filters($current_slug)) : ?>
            <div class="filters-section">
                <h3>Фильтры</h3>
                <?php
                echo do_shortcode('[wsf_filters attribute="material" title="Материал"]');
                echo do_shortcode('[wsf_filters attribute="tip" title="Тип"]');
                echo do_shortcode('[wsf_filters attribute="vysota-vorsa" title="Высота ворса"]');
                ?>
            </div>
            
            <?php echo do_shortcode('[wsf_active_filters]'); ?>
        <?php endif; ?>
    </aside>
    
    <main class="shop-content">
        <?php woocommerce_content(); ?>
    </main>
</div>

<?php get_footer('shop'); ?>
```

Все примеры готовы к использованию после настройки плагина согласно инструкции!