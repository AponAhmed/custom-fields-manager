// assets/js/meta-box.js
jQuery(document).ready(function ($) {
    'use strict';

    class CFMMetaBox {
        constructor() {
            this.init();
        }

        init() {
            this.bindEvents();
            //this.initFileUploads();
            this.initRepeaterFields();
        }

        bindEvents() {
            // Enhanced file upload interactions
            $(document).on('change', '.cfm-file-upload-input', this.handleFileUpload.bind(this));

            // Modern select enhancements
            this.enhanceSelectElements();

            // Dynamic field interactions
            this.initConditionalFields();
        }

        handleFileUpload(e) {
            const input = e.target;
            const wrapper = $(input).closest('.cfm-file-upload-wrapper');
            const label = wrapper.find('.cfm-file-upload-label');
            const fileName = input.files[0]?.name || 'No file chosen';

            label.addClass('has-file');
            label.find('.cfm-file-name').text(fileName);
        }

        enhanceSelectElements() {
            $('.cfm-field-select:not([multiple])').each(function () {
                const $select = $(this);
                $select.wrap('<div class="cfm-select-wrapper"></div>');
            });
        }

        initConditionalFields() {
            // Add conditional field logic based on field options
            $('[data-condition]').each(function () {
                const $field = $(this);
                const condition = $field.data('condition');
                this.evaluateCondition(condition, $field);
            });
        }

        initRepeaterFields() {
            // Add repeater row
            $('.cfm-add-repeater-row').on('click', function (e) {
                e.preventDefault();
                this.addRepeaterRow($(this));
            }.bind(this));

            // Remove repeater row
            $('.cfm-remove-repeater-row').on('click', function (e) {
                e.preventDefault();
                this.removeRepeaterRow($(this));
            }.bind(this));
        }

        addRepeaterRow($button) {
            const $repeater = $button.closest('.cfm-repeater-field');
            const $items = $repeater.find('.cfm-repeater-items');
            const fieldName = $button.data('field-name');
            const template = this.getRepeaterTemplate(fieldName);

            $items.append(template);
            this.updateRepeaterIndexes($repeater);
        }

        removeRepeaterRow($button) {
            const $item = $button.closest('.cfm-repeater-item');
            $item.addClass('cfm-field-exit');

            setTimeout(() => {
                $item.remove();
                this.updateRepeaterIndexes($item.closest('.cfm-repeater-field'));
            }, 300);
        }

        updateRepeaterIndexes($repeater) {
            $repeater.find('.cfm-repeater-item').each(function (index) {
                const $item = $(this);
                $item.find('.cfm-repeater-item-title').text(`Row ${index + 1}`);

                // Update input names with new index
                $item.find('[name]').each(function () {
                    const name = $(this).attr('name').replace(/\[\d+\]/, `[${index}]`);
                    $(this).attr('name', name);
                });
            });
        }

        getRepeaterTemplate(fieldName) {
            // This would be generated server-side based on field configuration
            return `
                <div class="cfm-repeater-item cfm-field-enter">
                    <div class="cfm-repeater-item-header">
                        <span class="cfm-repeater-item-title">New Row</span>
                        <button type="button" class="cfm-btn-modern cfm-remove-repeater-row">
                            Remove
                        </button>
                    </div>
                    <div class="cfm-repeater-item-fields">
                        <!-- Sub fields would be generated here -->
                    </div>
                </div>
            `;
        }
    }

    // Initialize
    new CFMMetaBox();
});