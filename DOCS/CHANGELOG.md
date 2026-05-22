# Changelog WooCommerce SEO Filter

## 2026-05-22

### Синхронизация wsf_collapsed_attributes на все сайты мультисайта

**Проблема:** Настройка «скрыт по умолчанию» для фильтров (`wsf_collapsed_attributes`) не синхронизировалась на другие города через страницу SEO Filter Sync, т.к. отсутствовала в списке `$sync_options`.

**Решение:** Добавлена опция `wsf_collapsed_attributes` в массив `$sync_options`.

**Изменённый файл:** `includes/class-wsf-multisite-sync.php` — в массив `$sync_options` добавлена строка `'wsf_collapsed_attributes'`.

---

## 2026-05-21

### Автоснятие нулевых активных значений при отключении фильтра

**Проблема:** При снятии активного значения фильтра (например «Для декора») оставались другие активные значения того же атрибута с count=0 (например «Для гольфа»). Переход шёл на страницу без результатов вместо корректной страницы с оставшимися товарами.

**Решение:** JS-обработчик при клике на снятие активного фильтра проверяет другие активные значения той же группы. Если у них count=0 — они тоже убираются из URL назначения перед переходом.

**Изменённые файлы:**

- `assets/js/frontend.js`:
  - Добавлен обработчик `.wsf-filter-item.active > a` и `.wsf-badge.active`: собирает slugи других активных значений с `data-count="0"` в той же секции, вырезает их из URL назначения (манипуляция строкой query string без URLSearchParams, чтобы не перекодировать запятые)
  - Добавлен отдельный обработчик `.wsf-active-filter-item`: ищет count нулевых значений в сайдбарных виджетах (`.wsf-filter-collapsible` / `.wsf-badge-group`) с тем же `data-attribute`

- `includes/class-wsf-universal-filters-widget.php`, `includes/class-wsf-filter-widget.php`, `includes/class-wsf-shortcodes.php`:
  - В `render_list()`: к `<li>` добавлены атрибуты `data-slug` и `data-count`

- `includes/class-wsf-badges-widget.php`:
  - К `<a class="wsf-badge">` добавлены `data-slug` и `data-count`

- `includes/class-wsf-active-filters-widget.php`:
  - К `<a class="wsf-active-filter-item">` добавлены `data-attribute` и `data-slug`

---

### Сохранение query-фильтров при переходе на родительскую категорию

**Проблема:** При нахождении на SEO-странице с дополнительными query-фильтрами (например `/10-mm-gazon/?filter_naznachenie=dlya-golfa,dlya-sportivnyh-ploshchadok`) и выборе значения атрибута binding (например 50 мм высоты ворса) происходил переход на родительскую категорию, но `filter_naznachenie` из URL терялся.

**Причина:** В блоке перехода на родителя в `get_filter_url()` переменная `$query_params` инициализировалась только непарамными (`$non_filter_params`) значениями — все существующие `filter_*` из `$current_params` отбрасывались.

**Решение:** При формировании `$query_params` для перехода на родителя теперь включаются все `filter_*` / `query_type_*` из `$current_params`, кроме: кликнутого атрибута (добавляется с новым значением отдельно), атрибутов из parent binding (не дублируем), атрибутов из текущего binding (добавляются ниже с проверкой parent).

**Изменённый файл:** `includes/class-wsf-filter-manager.php`, метод `get_filter_url()`, блок `if ($binding_value_for_attr !== null)`.

---

### Multi-select для одного атрибута на SEO-страницах категорий

**Проблема:** На странице SEO-категории (например `/iskusstvennyj-gazon/10-mm-gazon/`) нельзя было добавить второе значение того же атрибута (например 12 мм). Клик на 12 мм уводил на `/10-mm-gazon/?filter_vysota-vorsa=12-mm` вместо `/iskusstvennyj-gazon/?filter_vysota-vorsa=10-mm,12-mm&query_type_vysota-vorsa=or`. Дополнительно: другие значения атрибута показывали count=0 и были некликабельны.

**Корневые причины:**

1. **URL-генерация** (`get_filter_url`): при добавлении значения атрибута, уже закодированного в binding текущей категории, срабатывал поиск частичного совпадения binding — находился, например, `12-mm-gazon`, и переход делался туда, оставляя `10-mm` как query param. В итоге на `12-mm-gazon` + `filter_vysota-vorsa=12-mm` товары не находились.

2. **Подсчёт товаров** (count=0): во всех трёх виджетах (`WSF_Universal_Filters_Widget`, `WSF_Filter_Widget`, `WSF_Badges_Widget`) функция `get_filter_product_count` добавляла в запрос binding-атрибуты текущей категории. Для `vysota-vorsa=12-mm` на странице `10-mm-gazon` это давало AND-условие `pa_vysota-vorsa=10-mm AND pa_vysota-vorsa=12-mm` → всегда 0.

3. **Кэш**: `WSF_Badges_Widget` рендерится первым и заполнял `static $runtime_cache` нулями до того как `WSF_Universal_Filters_Widget` начинал считать. Исправление в Badges-виджете устранило первопричину.

4. **Версия кэша**: добавлена константа `CACHE_VERSION = 2` для инвалидации устаревших нулевых записей при обновлении логики подсчёта.

**Изменённые файлы:**

- `includes/class-wsf-filter-manager.php`, метод `get_filter_url()`:
  - Если атрибут уже есть в query params — просто добавляем значение к query params, не ищем binding
  - Если атрибут закодирован в binding текущей SEO-категории — уходим на родительскую категорию с обоими значениями в query params (`10-mm,12-mm`); атрибуты, уже закодированные в родительской категории, в query params не дублируются
  - При наличии атрибута с несколькими значениями — `find_partial_match_category` не вызывается (binding не может кодировать несколько значений одного атрибута), переход сразу к query params

- `includes/class-wsf-universal-filters-widget.php`:
  - `get_filter_product_count()`: при `$binding_has_same_attr = true` не добавляем в запрос ни категорию, ни binding-атрибуты — считаем глобально по значению атрибута
  - `render_list()`: при `$attr_in_current_binding = true` все значения атрибута рендерятся как кликабельные ссылки (даже с count=0)

- `includes/class-wsf-filter-widget.php`: те же исправления в `get_filter_product_count()` и `render_list()`

- `includes/class-wsf-badges-widget.php`: то же исправление в `get_filter_product_count()` — было корневой причиной проблемы с кэшем

- `includes/class-wsf-cache-manager.php`: добавлена константа `CACHE_VERSION = 2`, включена в ключ кэша через `count_v2_...`

---

## 2026-05-20

### Сворачиваемые фильтры в сайдбаре

**Что добавлено:** Все секции фильтров в сайдбаре теперь сворачиваются/разворачиваются по клику на заголовок атрибута. Стрелка-шеврон анимированно показывает состояние. Настройка «скрыт по умолчанию» задаётся глобально для каждого атрибута на странице `/wp-admin/admin.php?page=wsf-attributes`.

**Хранение:** новая опция `wsf_collapsed_attributes` — массив slug атрибутов, которые должны быть свёрнуты при загрузке страницы.

**Изменённые файлы:**

- `includes/class-wsf-universal-filters-widget.php`:
  - `render_single_filter()`: `<h3 class="wsf-filter-title">` заменён на `<button class="wsf-filter-toggle">` внутри `<div class="wsf-filter-collapsible">`, тело списка обёрнуто в `<div class="wsf-filter-collapsible-body">`; читает `wsf_collapsed_attributes` и добавляет класс `wsf-filter-collapsed` если атрибут в списке

- `includes/class-wsf-filter-widget.php`:
  - `widget()`: при наличии заголовка всегда рендерит коллапсируемую обёртку (настройки `collapsible`/`collapsed_default` в виджете удалены); читает `wsf_collapsed_attributes`

- `includes/class-wsf-admin.php`:
  - `save_category_attributes()`: добавлено сохранение `wsf_collapsed_attributes` из POST-параметра `collapsed_attributes`

- `templates/admin/attribute-category-item.php`:
  - К каждому элементу атрибута в разделе «Атрибуты для фильтра» добавлен чекбокс `wsf-attr-collapsed-checkbox` «скрыт по умолчанию»; читает `wsf_collapsed_attributes` для проставления checked

- `templates/admin/attributes.php`:
  - JS-шаблон `#wsf-attribute-item-template`: добавлен плейсхолдер `{{COLLAPSED_CHECKBOX}}`
  - В обработчике выбора атрибута: для `attrType === 'filter'` генерируется чекбокс с подстановкой
  - В обработчике сохранения: собираются все отмеченные `.wsf-attr-collapsed-checkbox` и передаются как `collapsed_attributes` в AJAX

- `assets/css/frontend.css`:
  - Добавлены стили `.wsf-filter-collapsible`, `.wsf-filter-toggle`, `.wsf-filter-toggle-title`, `.wsf-filter-toggle-icon`, `.wsf-filter-collapsible-body`
  - Анимация `max-height` + `opacity` при сворачивании; стрелка-шеврон через CSS `border`
  - Padding `0 0 10px` на `.wsf-filter-toggle` только в раскрытом состоянии (`:not(.wsf-filter-collapsed)`)

- `assets/css/admin.css`:
  - Добавлены стили `.wsf-attr-collapsed-label` для чекбокса в списке атрибутов

- `assets/js/frontend.js`:
  - Обработчик клика `.wsf-filter-toggle`: `toggleClass('wsf-filter-collapsed')` + через 320ms пересчёт `window.StickySidebar.recalculate()` / `update()` (пересчёт нужен, т.к. высота сайдбара изменилась)

---

## 2026-03-27

- Добавлена сортировка значений атрибутов в `wsf-filter-list` по WooCommerce-порядку

**Проблема:** Значения атрибутов в фильтрах (`.wsf-filter-list`) выводились в произвольном порядке, не совпадающем с порядком, заданным на странице `wp-admin/edit-tags.php?taxonomy=pa_*` через drag & drop.

**Решение:** WooCommerce хранит порядок терминов атрибута в `wp_termmeta` с `meta_key = 'order'`. После построения массива `$values` в методе `get_attribute_values()` добавлена сортировка по этому значению через `get_term_meta($term->term_id, 'order', true)`.

**Изменённые файлы:**

- `includes/class-wsf-universal-filters-widget.php`:
  - В `get_attribute_values()`: после заполнения `$values` из bindings и `get_terms()` строится `$order_map[slug => order]` из уже загруженных `$terms` (без дополнительных запросов), затем `usort()` сортирует `$values` по WC-порядку
  - В `render_list()`: перед существующим `usort()` каждому элементу присваивается `_pos` (исходная позиция); `_pos` используется как tiebreaker — внутри каждой группы (активные / с товарами / пустые) сохраняется WC-порядок

- `includes/class-wsf-filter-widget.php`:
  - В `get_attribute_values()`: та же сортировка по WC term meta `order` после построения `$values`

- `includes/class-wsf-shortcodes.php`:
  - В `get_attribute_values()`: та же сортировка по WC term meta `order` после построения `$values`

**Важные детали:**
- Термины без явного `order` в meta получают `PHP_INT_MAX` и оказываются в конце своей группы
- После `get_terms()` WordPress автоматически прогревает кэш term meta, поэтому последующие `get_term_meta()` для этих же терминов работают из кэша — дополнительных SQL-запросов нет
- Логика «активные → с товарами → пустые» сохранена без изменений; WC-порядок применяется только внутри каждой группы

---

## 2026-02-12
- Добавлена функция сворачивания меток в виджете WSF_Badges_Widget
  - Метки автоматически сворачиваются в одну строку (max-height: 28px)
  - Кнопка "Показать еще" появляется только если метки не помещаются
  - При раскрытии кнопка меняется на "Свернуть"
  - JavaScript автоматически определяет переполнение (scrollHeight vs visibleHeight)
  - Адаптивное поведение при изменении размера окна
- Обновлена структура HTML: добавлен wrapper `.wsf-badge-items-wrapper`
  - Wrapper — flex-контейнер, содержит `.wsf-badge-items` (метки) и `.wsf-badge-toggle` (кнопка)
  - Кнопка всегда видна справа от контейнера меток (не скрывается overflow)
  - Метки слева (flex: 1), кнопка справа (flex-shrink: 0)
- Добавлены CSS стили для collapsed состояния и позиционирования
- Добавлен JavaScript в frontend.js для управления показом/скрытием меток
- Реализован умный sticky sidebar для фильтров в магазине
  - Добавлен объект StickySidebar в themes/sportshop/js/common.js
  - Поддержка коротких и высоких сайдбаров
  - Отслеживание направления скролла (вверх/вниз)
  - Плавное прилипание к верху/низу экрана
  - Учёт padding контейнера при позиционировании
  - Использование requestAnimationFrame для производительности

---

## 2026-02-10
- Добавлена функция сворачивания/разворачивания отдельных связок на странице Filter Bindings
- Добавлена кнопка дублирования связок для быстрого заполнения
- Обновлён UI страницы связок: компактная шапка с кнопками
- Улучшена эргономика: drag handle отдельно от области сворачивания
- Обновлены стили для визуального отображения свёрнутых связок

---

## 2026-02-09
- Создан класс WSF_Multisite_Sync для синхронизации настроек между сайтами
- Добавлена страница в Network Admin (Settings → SEO Filter Sync)
- Реализован экспорт настроек в JSON (позже упрощено до прямой синхронизации через options)
- Реализована синхронизация на выбранные/все сайты через прямое копирование WordPress options
- Созданы assets/css/multisite-sync.css и assets/js/multisite-sync.js
- Интегрирован класс в главный файл плагина

---

## 2026-01-23
- Исправлено дублирование фильтров (array_unique)
- Подтверждено: счетчик товаров в метках уже реализован
- Исправлено: виджет меток теперь работает на всех уровнях вложенности (наследование от родителя)

---

## 2026-01-22
- Добавлен виджет SEO Filter Badges
- Настройка атрибутов для меток на странице Filter Attributes
- Упрощено добавление атрибутов (убран двойной клик)
- Исправлена проблема с неработающими связками
- Добавлена оптимизация (кэширование)

---

## 2026-01-21
- Переработана страница Filter Attributes (Drag & Drop)
- Добавлена настройка для главной страницы магазина
- Создан универсальный виджет фильтров
- Исправлена логика снятия/добавления фильтров

---

## 2026-01-20
- Создана базовая структура плагина
- Реализована страница настроек связок
- Ajax сохранение и Drag & Drop
