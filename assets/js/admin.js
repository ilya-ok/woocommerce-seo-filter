jQuery(document).ready(function($) {
    
    let bindingIndex = $('.wsf-binding-item').length;
    let allAttributes = {};
    let allTerms = {};
    
    loadAllAttributesAndTerms();
    
    function loadAllAttributesAndTerms() {
        $.ajax({
            url: wsfAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'wsf_get_all_attributes',
                nonce: wsfAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    allAttributes = response.data.attributes;
                    allTerms = response.data.terms;
                    initializeExistingAttributes();
                }
            }
        });
    }
    
    function initializeExistingAttributes() {
        $('.wsf-attribute-select').each(function() {
            const $select = $(this);
            const bindingIndex = $select.data('binding-index');
            const currentValue = $select.val();
            
            if (!currentValue) {
                loadAttributesForSelect($select, bindingIndex);
            }
        });
    }
    
    function loadAttributesForSelect($select, bindingIndex, selectedAttr, selectedValue) {
        const $bindingItem = $('.wsf-binding-item[data-index="' + bindingIndex + '"]');
        const categorySlug = $bindingItem.find('.wsf-category-select').val();
        const currentAttr = selectedAttr || $select.val();
        
        let availableAttributes = Object.keys(allAttributes);
        
        // Получаем доступные атрибуты из настроек категории
        if (categorySlug) {
            $.ajax({
                url: wsfAdmin.ajax_url,
                type: 'POST',
                async: false,
                data: {
                    action: 'wsf_get_category_available_attributes',
                    nonce: wsfAdmin.nonce,
                    category_slug: categorySlug
                },
                success: function(response) {
                    if (response.success && response.data.attributes && response.data.attributes.length > 0) {
                        availableAttributes = response.data.attributes;
                    }
                }
            });
        }
        
        if (!currentAttr) {
            $select.empty().append('<option value="">Выберите атрибут</option>');
            
            availableAttributes.forEach(function(attrSlug) {
                if (allAttributes[attrSlug]) {
                    $select.append(
                        $('<option></option>')
                            .val(attrSlug)
                            .text(allAttributes[attrSlug])
                    );
                }
            });
        }
        
        if (selectedValue || currentAttr) {
            const $valueSelect = $select.closest('.wsf-attribute-row').find('.wsf-attribute-value-select');
            const attrToLoad = selectedAttr || currentAttr;
            if (attrToLoad && !$valueSelect.find('option[value!=""]').length) {
                loadAttributeValues($valueSelect, attrToLoad, selectedValue);
            }
        }
    }
    
    function loadAttributeValues($select, attribute, selectedValue) {
        $select.empty().append('<option value="">Выберите значение</option>');
        
        if (allTerms[attribute]) {
            allTerms[attribute].forEach(function(term) {
                $select.append(
                    $('<option></option>')
                        .val(term.slug)
                        .text(term.name)
                        .prop('selected', term.slug === selectedValue)
                );
            });
            $select.prop('disabled', false);
        }
    }
    
    function findBindingBySlug(slug) {
        let result = null;
        $('.wsf-binding-item').each(function() {
            const categorySlug = $(this).find('.wsf-category-select').val();
            if (categorySlug === slug) {
                result = {
                    attributes: []
                };
                $(this).find('.wsf-attribute-row').each(function() {
                    const attr = $(this).find('.wsf-attribute-select').val();
                    if (attr) {
                        result.attributes.push({ attribute: attr });
                    }
                });
                return false;
            }
        });
        return result;
    }
    
    $('#wsf-bindings-list').sortable({
        handle: '.wsf-binding-handle',
        placeholder: 'wsf-binding-placeholder',
        axis: 'y',
        opacity: 0.7
    });
    
    $(document).on('click', '.wsf-add-binding, .wsf-add-binding-after', function() {
        const afterIndex = $(this).data('after-index');
        const template = $('#wsf-binding-template').html();
        const newBinding = template.replace(/\{\{INDEX\}\}/g, bindingIndex);
        
        if (typeof afterIndex !== 'undefined') {
            $('.wsf-binding-item[data-index="' + afterIndex + '"]').after(newBinding);
        } else {
            $('#wsf-bindings-list').append(newBinding);
        }
        
        bindingIndex++;
    });
    
    $(document).on('click', '.wsf-remove-binding', function() {
        if (confirm('Удалить эту связку?')) {
            $(this).closest('.wsf-binding-item').remove();
        }
    });

    // Collapse/Expand binding
    $(document).on('click', '.wsf-collapse-toggle', function(e) {
        e.stopPropagation();
        toggleBindingCollapse($(this).closest('.wsf-binding-item'));
    });

    // Also allow clicking on the header bar (but not the handle or toggle button)
    $(document).on('click', '.wsf-binding-header-bar', function(e) {
        // Don't collapse if clicking on handle (for dragging) or collapse toggle itself
        if (!$(e.target).closest('.wsf-binding-handle').length &&
            !$(e.target).closest('.wsf-collapse-toggle').length) {
            toggleBindingCollapse($(this).closest('.wsf-binding-item'));
        }
    });

    function toggleBindingCollapse($bindingItem) {
        const $content = $bindingItem.find('.wsf-binding-content');
        const $icon = $bindingItem.find('.wsf-collapse-toggle .dashicons');
        const isCollapsed = $bindingItem.attr('data-collapsed') === 'true';

        if (isCollapsed) {
            $content.slideDown(200);
            $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
            $bindingItem.attr('data-collapsed', 'false');
        } else {
            $content.slideUp(200);
            $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
            $bindingItem.attr('data-collapsed', 'true');
        }
    }

    // Duplicate binding
    $(document).on('click', '.wsf-duplicate-binding', function() {
        const $sourceBinding = $(this).closest('.wsf-binding-item');
        const sourceIndex = $sourceBinding.data('index');

        // Собираем данные из исходной связки
        const categorySlug = $sourceBinding.find('.wsf-category-select').val();
        const parentSlug = $sourceBinding.find('.wsf-parent-select').val();
        const attributes = [];

        $sourceBinding.find('.wsf-attribute-row').each(function() {
            const attr = $(this).find('.wsf-attribute-select').val();
            const value = $(this).find('.wsf-attribute-value-select').val();
            if (attr && value) {
                attributes.push({ attribute: attr, value: value });
            }
        });

        // Создаем новую связку из шаблона
        const template = $('#wsf-binding-template').html();
        const newBinding = template.replace(/\{\{INDEX\}\}/g, bindingIndex);
        $sourceBinding.after(newBinding);

        const $newBinding = $('.wsf-binding-item[data-index="' + bindingIndex + '"]');

        // Заполняем категорию
        if (categorySlug) {
            $newBinding.find('.wsf-category-select').val(categorySlug).trigger('change');
        }

        // Заполняем родительскую категорию
        if (parentSlug) {
            $newBinding.find('.wsf-parent-select').val(parentSlug);
        }

        // Добавляем атрибуты
        setTimeout(function() {
            attributes.forEach(function(attr, index) {
                if (index > 0) {
                    $newBinding.find('.wsf-add-attribute').click();
                }

                setTimeout(function() {
                    const $attrRow = $newBinding.find('.wsf-attribute-row').eq(index);
                    const $attrSelect = $attrRow.find('.wsf-attribute-select');

                    // Загружаем атрибуты для select
                    loadAttributesForSelect($attrSelect, bindingIndex, attr.attribute, attr.value);

                    // Устанавливаем значение атрибута
                    setTimeout(function() {
                        $attrSelect.val(attr.attribute).trigger('change');

                        // Устанавливаем значение
                        setTimeout(function() {
                            $attrRow.find('.wsf-attribute-value-select').val(attr.value);
                        }, 100);
                    }, 100);
                }, 100 * index);
            });
        }, 300);

        bindingIndex++;

        // Показываем уведомление
        const $status = $('.wsf-save-status');
        $status.html('<span style="color: #00a32a;">✓ Связка дублирована</span>');
        setTimeout(function() {
            $status.html('');
        }, 2000);
    });
    
    $(document).on('change', '.wsf-category-select', function() {
        const $bindingItem = $(this).closest('.wsf-binding-item');
        const categorySlug = $(this).val();
        const index = $(this).data('index');
        
        if (!categorySlug) {
            $bindingItem.find('.wsf-category-name').text('Не выбрана');
            return;
        }
        
        const selectedOption = $(this).find('option:selected');
        const categoryName = selectedOption.text().replace(/^—+\s*/, '');
        
        $bindingItem.find('.wsf-category-name').text(categoryName);
        
        $.ajax({
            url: wsfAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'wsf_get_category_data',
                nonce: wsfAdmin.nonce,
                category_slug: categorySlug
            },
            success: function(response) {
                if (response.success) {
                    updateCategoryInfo($bindingItem, response.data);
                }
            }
        });
    });
    
    $(document).on('change', '.wsf-parent-select', function() {
        const bindingIndex = $(this).data('index');
        const $bindingItem = $(this).closest('.wsf-binding-item');
        
        // Перезагружаем атрибуты при смене родителя не нужно
        // так как атрибуты теперь берутся из настроек категории
    });
    
    function updateCategoryInfo($bindingItem, data) {
        // Обновление информации о категории будет добавлено позже
    }
    
    $(document).on('click', '.wsf-add-attribute', function() {
        const bindingIndex = $(this).data('index');
        const $attributesList = $(this).siblings('.wsf-attributes-list');
        const attrIndex = $attributesList.find('.wsf-attribute-row').length;
        
        const template = $('#wsf-attribute-template').html();
        const newAttribute = template
            .replace(/\{\{INDEX\}\}/g, bindingIndex)
            .replace(/\{\{ATTR_INDEX\}\}/g, attrIndex);
        
        $attributesList.append(newAttribute);
        
        const $newSelect = $attributesList.find('.wsf-attribute-row:last .wsf-attribute-select');
        loadAttributesForSelect($newSelect, bindingIndex);
    });
    
    $(document).on('click', '.wsf-remove-attribute', function() {
        $(this).closest('.wsf-attribute-row').remove();
    });
    
    $(document).on('change', '.wsf-attribute-select', function() {
        const attribute = $(this).val();
        const $valueSelect = $(this).closest('.wsf-attribute-row').find('.wsf-attribute-value-select');
        
        if (!attribute) {
            $valueSelect.empty().append('<option value="">Выберите значение</option>').prop('disabled', true);
            return;
        }
        
        loadAttributeValues($valueSelect, attribute);
    });
    
    $('#wsf-save-bindings').on('click', function() {
        const $button = $(this);
        const $status = $('.wsf-save-status');
        
        $button.prop('disabled', true);
        $status.html('<span class="spinner is-active"></span>');
        
        const bindings = [];
        
        $('.wsf-binding-item').each(function(index) {
            const $item = $(this);
            const categorySlug = $item.find('.wsf-category-select').val();
            const parentSlug = $item.find('.wsf-parent-select').val();
            
            if (!categorySlug) {
                return;
            }
            
            const attributes = [];
            $item.find('.wsf-attribute-row').each(function() {
                const attr = $(this).find('.wsf-attribute-select').val();
                const value = $(this).find('.wsf-attribute-value-select').val();
                
                if (attr && value) {
                    attributes.push({
                        attribute: attr,
                        value: value
                    });
                }
            });
            
            bindings.push({
                category_slug: categorySlug,
                parent_slug: parentSlug || '',
                attributes: attributes,
                order: index
            });
        });
        
        $.ajax({
            url: wsfAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'wsf_save_bindings',
                nonce: wsfAdmin.nonce,
                bindings: bindings
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
    
    function handleStickyButton() {
        const $container = $('.wsf-bindings-container');
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
    
    $(document).on('DOMSubtreeModified', '.wsf-bindings-list', function() {
        handleStickyButton();
    });
});