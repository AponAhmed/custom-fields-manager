// assets/js/meta-box.js
jQuery(document).ready(function ($) {
    'use strict';

    class CFMMetaBox {
        constructor() {
            this.mediaFrame = null;
            this.currentMediaField = null;
            this.init();
        }

        init() {
            this.bindEvents();
            this.initRepeaterFields();
            this.initMediaUpload();
            this.initNumberControls();
            this.initSortable();
        }

        bindEvents() {
            // Use event delegation for dynamic elements
            $(document).on('click', '.cfm-add-repeater-row', this.addRepeaterRow.bind(this));
            $(document).on('click', '.cfm-remove-repeater-row', this.removeRepeaterRow.bind(this));
            $(document).on('click', '.cfm-move-repeater-row', this.moveRepeaterRow.bind(this));
            
            // Table repeater events
            $(document).on('click', '.cfm-table-add-row', this.addTableRepeaterRow.bind(this));
            $(document).on('click', '.cfm-table-remove-row', this.removeTableRepeaterRow.bind(this));
            
            // Media events
            $(document).on('click', '.cfm-media-upload-btn', this.openMediaFrame.bind(this));
            $(document).on('click', '.cfm-media-remove-btn', this.removeMediaItem.bind(this));
            $(document).on('click', '.cfm-media-remove-all-btn', this.removeAllMedia.bind(this));
            
            // Number controls
            $(document).on('click', '.cfm-number-up', this.incrementNumber.bind(this));
            $(document).on('click', '.cfm-number-down', this.decrementNumber.bind(this));
            
            // Initialize existing repeater items
            this.initExistingRepeaterItems();
        }

        initExistingRepeaterItems() {
            $('.cfm-repeater-field').each((index, repeater) => {
                this.updateRepeaterIndexes($(repeater));
            });
        }

        // Original repeater methods (for non-table layout)
        addRepeaterRow(e) {
            e.preventDefault();
            const $button = $(e.target).closest('.cfm-add-repeater-row');
            const $repeater = $button.closest('.cfm-repeater-field');
            const $items = $repeater.find('.cfm-repeater-items');
            const fieldName = $button.data('field-name');
            
            const templateId = `cfm-repeater-template-${fieldName}`;
            const template = document.getElementById(templateId);
            
            if (!template) {
                console.error(`Template not found: ${templateId}`);
                return;
            }
            
            const templateContent = template.content.cloneNode(true);
            const $newItem = $(templateContent).find('.cfm-repeater-item').first();
            
            if ($newItem.length) {
                $items.append($newItem);
                this.updateRepeaterIndexes($repeater);
                
                $newItem.addClass('cfm-field-enter');
                setTimeout(() => {
                    $newItem.removeClass('cfm-field-enter');
                }, 300);
            }
        }

        removeRepeaterRow(e) {
            e.preventDefault();
            const $button = $(e.target).closest('.cfm-remove-repeater-row');
            const $item = $button.closest('.cfm-repeater-item');
            const $repeater = $item.closest('.cfm-repeater-field');
            
            $item.addClass('cfm-field-exit');
            
            setTimeout(() => {
                $item.remove();
                this.updateRepeaterIndexes($repeater);
            }, 300);
        }

        // Table repeater methods
        addTableRepeaterRow(e) {
            e.preventDefault();
            const $button = $(e.target).closest('.cfm-table-add-row');
            const $repeater = $button.closest('.cfm-repeater-field');
            const $tbody = $repeater.find('.cfm-repeater-table tbody');
            const fieldName = $button.data('field-name');
            
            const templateId = `cfm-repeater-template-${fieldName}`;
            const template = document.getElementById(templateId);
            
            if (!template) {
                console.error(`Template not found: ${templateId}`);
                return;
            }
            
            const templateContent = template.content.cloneNode(true);
            const $newRow = $(templateContent).find('.cfm-repeater-item').first();
            
            if ($newRow.length) {
                $tbody.append($newRow);
                this.updateTableRepeaterIndexes($repeater);
                
                $newRow.addClass('cfm-field-enter');
                setTimeout(() => {
                    $newRow.removeClass('cfm-field-enter');
                }, 300);
            }
        }

        removeTableRepeaterRow(e) {
            e.preventDefault();
            const $button = $(e.target).closest('.cfm-table-remove-row');
            const $row = $button.closest('.cfm-repeater-item');
            const $repeater = $row.closest('.cfm-repeater-field');
            
            $row.addClass('cfm-field-exit');
            
            setTimeout(() => {
                $row.remove();
                this.updateTableRepeaterIndexes($repeater);
            }, 300);
        }

        updateRepeaterIndexes($repeater) {
            $repeater.find('.cfm-repeater-item').each(function (index) {
                const $item = $(this);
                $item.find('.cfm-repeater-item-title').text(cfmMetaBox.i18n.row + ' ' + (index + 1));
                
                $item.find('[name]').each(function () {
                    const $input = $(this);
                    const currentName = $input.attr('name');
                    
                    const newName = currentName.replace(/\[(\d+)\]/g, `[${index}]`);
                    $input.attr('name', newName);
                });
            });
        }

        updateTableRepeaterIndexes($repeater) {
            $repeater.find('.cfm-repeater-item').each(function (index) {
                const $row = $(this);
                
                $row.find('[name]').each(function () {
                    const $input = $(this);
                    const currentName = $input.attr('name');
                    
                    const newName = currentName.replace(/\[(\d+)\]/g, `[${index}]`);
                    $input.attr('name', newName);
                });
            });
        }

        moveRepeaterRow(e) {
            e.preventDefault();
            const $button = $(e.target).closest('.cfm-move-repeater-row');
            const $item = $button.closest('.cfm-repeater-item');
            const $repeater = $item.closest('.cfm-repeater-field');
            
            if ($button.hasClass('move-up')) {
                $item.prev('.cfm-repeater-item').before($item);
            } else if ($button.hasClass('move-down')) {
                $item.next('.cfm-repeater-item').after($item);
            }
            
            this.updateRepeaterIndexes($repeater);
        }

        initSortable() {
            // Original repeater sortable
            $('.cfm-repeater-items').sortable({
                handle: '.cfm-move-repeater-row',
                placeholder: 'cfm-repeater-placeholder',
                forcePlaceholderSize: true,
                update: (event, ui) => {
                    const $repeater = ui.item.closest('.cfm-repeater-field');
                    this.updateRepeaterIndexes($repeater);
                }
            });

            // Table repeater sortable
            $('.cfm-repeater-table tbody').sortable({
                handle: '.cfm-drag-handle',
                axis: 'y',
                placeholder: 'cfm-repeater-table-placeholder',
                forcePlaceholderSize: true,
                update: (event, ui) => {
                    const $repeater = ui.item.closest('.cfm-repeater-field');
                    this.updateTableRepeaterIndexes($repeater);
                }
            });
        }

        initMediaUpload() {
            // Media frame will be created when needed
        }

        openMediaFrame(e) {
            e.preventDefault();
            const $button = $(e.target).closest('.cfm-media-upload-btn');
            this.currentMediaField = $button.data('field');
            const $wrapper = $button.closest('.cfm-media-upload-wrapper');
            const isMultiple = $wrapper.data('multiple') === 'multiple';

            if (!this.mediaFrame) {
                this.mediaFrame = wp.media({
                    title: cfmMetaBox.media_frame_title,
                    button: {
                        text: cfmMetaBox.media_frame_button
                    },
                    multiple: isMultiple
                });

                this.mediaFrame.on('select', this.handleMediaSelect.bind(this));
            } else {
                this.mediaFrame.setState('library');
                this.mediaFrame.options.multiple = isMultiple;
            }

            this.mediaFrame.open();
        }

        handleMediaSelect() {
            if (!this.currentMediaField) return;

            const selection = this.mediaFrame.state().get('selection');
            const $wrapper = $(`[data-field-id="${this.currentMediaField}"]`);
            const $hiddenInput = $wrapper.find('.cfm-media-hidden-input');
            const isMultiple = $wrapper.data('multiple') === 'multiple';
            const $preview = $wrapper.find('.cfm-media-preview');

            if (!isMultiple) {
                const attachment = selection.first();
                const url = attachment.get('url');
                $hiddenInput.val(url);
                this.renderMediaPreview(url, this.currentMediaField, $preview);
            } else {
                const currentValues = $hiddenInput.val() ? $hiddenInput.val().split(',') : [];
                const newValues = [];

                selection.each((attachment) => {
                    const url = attachment.get('url');
                    if (!currentValues.includes(url)) {
                        newValues.push(url);
                        this.renderMediaPreview(url, this.currentMediaField, $preview);
                    }
                });

                $hiddenInput.val([...currentValues, ...newValues].join(','));
            }

            this.currentMediaField = null;
        }

        renderMediaPreview(url, fieldId, $previewContainer) {
            if (!$previewContainer.length) {
                $previewContainer = $(`<div class="cfm-media-preview" id="${fieldId}-preview"></div>`);
                $previewContainer.insertAfter($(`[data-field-id="${fieldId}"]`).find('.cfm-media-upload-controls'));
            }

            if ($previewContainer.find(`[data-url="${url}"]`).length) {
                return;
            }

            const previewItem = `
                <div class="cfm-media-preview-item" data-url="${url}">
                    <div class="cfm-media-loading">Loading...</div>
                </div>
            `;
            $previewContainer.append(previewItem);

            $.ajax({
                url: cfmMetaBox.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cfm_get_attachment_url',
                    attachment_id: this.extractAttachmentId(url),
                    nonce: cfmMetaBox.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.updateMediaPreview(url, response.data.url, fieldId);
                    } else {
                        this.updateMediaPreview(url, url, fieldId);
                    }
                },
                error: () => {
                    this.updateMediaPreview(url, url, fieldId);
                }
            });
        }

        updateMediaPreview(url, previewUrl, fieldId) {
            const $previewItem = $(`[data-field-id="${fieldId}"] .cfm-media-preview-item[data-url="${url}"]`);
            const isImage = /\.(jpg|jpeg|png|gif|webp)$/i.test(previewUrl);

            let previewHtml = '';
            if (isImage) {
                previewHtml = `
                    <img src="${previewUrl}" alt="" class="cfm-media-thumbnail">
                    <div class="cfm-media-actions">
                        <a href="${previewUrl}" target="_blank" class="cfm-btn-modern cfm-media-view">${cfmMetaBox.i18n.view}</a>
                        <button type="button" class="cfm-btn-modern cfm-btn-danger cfm-media-remove-btn" data-field="${fieldId}" data-url="${url}">${cfmMetaBox.i18n.remove}</button>
                    </div>
                `;
            } else {
                previewHtml = `
                    <div class="cfm-media-file-icon">
                        <span class="dashicons dashicons-media-document"></span>
                        <span class="cfm-media-filename">${this.getFilenameFromUrl(url)}</span>
                    </div>
                    <div class="cfm-media-actions">
                        <a href="${previewUrl}" target="_blank" class="cfm-btn-modern cfm-media-view">${cfmMetaBox.i18n.view}</a>
                        <button type="button" class="cfm-btn-modern cfm-btn-danger cfm-media-remove-btn" data-field="${fieldId}" data-url="${url}">${cfmMetaBox.i18n.remove}</button>
                    </div>
                `;
            }

            $previewItem.html(previewHtml);
        }

        removeMediaItem(e) {
            e.preventDefault();
            const $button = $(e.target).closest('.cfm-media-remove-btn');
            const fieldId = $button.data('field');
            const urlToRemove = $button.data('url');
            const $wrapper = $(`[data-field-id="${fieldId}"]`);
            const $hiddenInput = $wrapper.find('.cfm-media-hidden-input');
            const isMultiple = $wrapper.data('multiple') === 'multiple';

            if (isMultiple) {
                const currentValues = $hiddenInput.val() ? $hiddenInput.val().split(',') : [];
                const newValues = currentValues.filter(val => val !== urlToRemove);
                $hiddenInput.val(newValues.join(','));
            } else {
                $hiddenInput.val('');
            }

            $(`[data-field-id="${fieldId}"] .cfm-media-preview-item[data-url="${urlToRemove}"]`).remove();

            const $preview = $(`[data-field-id="${fieldId}"] .cfm-media-preview`);
            if ($preview.children().length === 0) {
                $preview.remove();
            }
        }

        removeAllMedia(e) {
            e.preventDefault();
            const $button = $(e.target).closest('.cfm-media-remove-all-btn');
            const fieldId = $button.data('field');
            const $wrapper = $(`[data-field-id="${fieldId}"]`);
            
            $wrapper.find('.cfm-media-hidden-input').val('');
            $wrapper.find('.cfm-media-preview').remove();
        }

        initNumberControls() {
            // Number controls are handled by event delegation
        }

        incrementNumber(e) {
            e.preventDefault();
            const $button = $(e.target).closest('.cfm-number-up');
            const $wrapper = $button.closest('.cfm-number-input-wrapper');
            const $input = $wrapper.find('input[type="number"]');
            const step = parseFloat($input.attr('step')) || 1;
            const max = $input.attr('max') ? parseFloat($input.attr('max')) : Infinity;
            const currentValue = parseFloat($input.val()) || 0;
            const newValue = currentValue + step;

            if (newValue <= max) {
                $input.val(newValue).trigger('change');
            }
        }

        decrementNumber(e) {
            e.preventDefault();
            const $button = $(e.target).closest('.cfm-number-down');
            const $wrapper = $button.closest('.cfm-number-input-wrapper');
            const $input = $wrapper.find('input[type="number"]');
            const step = parseFloat($input.attr('step')) || 1;
            const min = $input.attr('min') ? parseFloat($input.attr('min')) : -Infinity;
            const currentValue = parseFloat($input.val()) || 0;
            const newValue = currentValue - step;

            if (newValue >= min) {
                $input.val(newValue).trigger('change');
            }
        }

        extractAttachmentId(url) {
            const match = url.match(/wp-content\/uploads\/(\d{4}\/\d{2}\/)?([^\/]+)$/);
            if (match) {
                return null;
            }
            return null;
        }

        getFilenameFromUrl(url) {
            return url.split('/').pop() || 'file';
        }

        initRepeaterFields() {
            // Already handled by bindEvents
        }
    }

    // Initialize
    new CFMMetaBox();
});