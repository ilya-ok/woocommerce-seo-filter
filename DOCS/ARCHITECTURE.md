# Архитектура плагина WooCommerce SEO Filter

## Основные принципы

1. **Без rewrite rules** — не используем WordPress rewrite, не подменяем основной запрос
2. **Стандартные категории WooCommerce** — не создаём новые таксономии
3. **ООП + Singleton** — каждый класс в отдельном файле
4. **Автозагрузка классов** — через SPL autoloader
5. **Использование slug вместо ID** — для синхронизации между сайтами мультисайта
6. **Многоуровневое кэширование** — Runtime cache + WordPress Object Cache
7. **Ajax без перезагрузки** — для админки

## Структура файлов

```
woo-seo-filter/
├── woo-seo-filter.php              # Главный файл плагина
├── includes/                        # Классы
│   ├── class-autoloader.php        # Автозагрузка классов
│   ├── class-wsf-plugin.php        # Главный класс
│   ├── class-wsf-installer.php     # Установка/деинсталляция
│   ├── class-wsf-admin.php         # Админка и Ajax
│   ├── class-wsf-binding-manager.php    # Управление связками
│   ├── class-wsf-filter-manager.php     # Генерация URL фильтров
│   ├── class-wsf-redirect-handler.php   # 301 редиректы
│   ├── class-wsf-query-handler.php      # Модификация WP_Query
│   ├── class-wsf-category-helper.php    # Вспомогательные функции
│   ├── class-wsf-cache-manager.php      # Кэширование
│   ├── class-wsf-widget-manager.php     # Регистрация виджетов
│   ├── class-wsf-filter-widget.php      # Виджет фильтра (одиночный)
│   ├── class-wsf-universal-filters-widget.php  # Универсальный виджет
│   ├── class-wsf-badges-widget.php      # Виджет меток (компактный)
│   ├── class-wsf-active-filters-widget.php     # Активные фильтры
│   ├── class-wsf-multisite-sync.php     # Синхронизация мультисайта
│   └── class-wsf-shortcodes.php    # Шорткоды
├── templates/admin/                # Шаблоны админки
│   ├── settings.php               # Страница связок
│   ├── binding-item.php           # Элемент связки
│   ├── attributes.php             # Страница настроек атрибутов
│   └── attribute-category-item.php # Элемент категории
├── assets/
│   ├── css/
│   │   ├── admin.css             # Стили админки
│   │   ├── frontend.css          # Стили фронтенда
│   │   └── multisite-sync.css    # Стили страницы синхронизации
│   └── js/
│       ├── admin.js              # JS админки
│       ├── frontend.js           # JS фронтенда
│       └── multisite-sync.js     # JS синхронизации мультисайта
└── DOCS/
    ├── ARCHITECTURE.md           # Этот файл
    ├── CONCEPTS.md               # Ключевые концепции
    ├── DECISIONS.md              # Принятые решения
    ├── STATUS.md                 # Статус разработки
    ├── BUGS.md                   # Исправленные баги
    ├── DEVNOTES.md               # Заметки для разработки
    └── CHANGELOG.md              # История изменений
```
