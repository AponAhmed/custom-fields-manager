// admin.js
class CFMAdmin {
    constructor() {
        this.components = {};
        this.init();
    }

    init() {
        if (document.getElementById('cfm-field-group-edit')) {
            this.initEditPage();
        }
        this.bindGlobalEvents();
    }

    initEditPage() {
        this.components.fieldManager = new CFMFieldManager();
        this.components.locationRules = new CFMLocationRules();
        this.components.formHandler = new CFMFormHandler();
    }

    bindGlobalEvents() {
        // Global confirmations
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-cfm-confirm]')) {
                const message = e.target.getAttribute('data-cfm-confirm');
                if (!confirm(message)) {
                    e.preventDefault();
                }
            }
        });

        // Fix for required field validation on hidden elements
        document.addEventListener('submit', (e) => {
            if (e.target.matches('#cfm-field-group-form')) {
                this.handleFormSubmit(e);
            }
        });
    }

    handleFormSubmit(e) {
        // Remove required attribute from hidden sub-fields before form submission
        const hiddenSubFields = document.querySelectorAll('.cfm-sub-field-label-input[required], .cfm-sub-field-name-input[required]');
        hiddenSubFields.forEach(field => {
            const subFieldContainer = field.closest('.cfm-sub-fields-setting');
            if (subFieldContainer && subFieldContainer.style.display === 'none') {
                field.removeAttribute('required');
            }
        });
    }
}

class CFMFieldManager {
    constructor() {
        this.container = document.getElementById('cfm-fields-container');
        this.fieldIndex = window.cfmFieldGroup?.fields?.length || 0;
        this.init();
    }

    init() {
        this.bindEvents();
        this.initSortable();
        this.renderExistingFields();
    }

    bindEvents() {
        // Add field
        document.getElementById('cfm-add-field')?.addEventListener('click', (e) => {
            e.preventDefault();
            this.addField();
        });

        // Delegated events for fields
        this.container.addEventListener('click', (e) => {
            const target = e.target;
            const fieldElement = target.closest('.cfm-field');
            const subFieldElement = target.closest('.cfm-sub-field');

            if (target.matches('.cfm-remove-field') || target.closest('.cfm-remove-field')) {
                e.preventDefault();
                e.stopPropagation();
                this.removeField(fieldElement);
            }
            else if (target.matches('.cfm-toggle-options') || target.closest('.cfm-toggle-options')) {
                e.preventDefault();
                e.stopPropagation();
                this.toggleOptions(fieldElement);
            }
            else if (target.matches('.cfm-field-header') && !target.matches('.cfm-remove-field') && !target.closest('.cfm-remove-field')) {
                e.preventDefault();
                this.toggleField(fieldElement);
            }
            else if (target.matches('.cfm-add-sub-field') || target.closest('.cfm-add-sub-field')) {
                e.preventDefault();
                e.stopPropagation();
                this.addSubField(fieldElement);
            }
            else if (target.matches('.cfm-remove-sub-field') || target.closest('.cfm-remove-sub-field')) {
                e.preventDefault();
                e.stopPropagation();
                this.removeSubField(subFieldElement);
            }
            else if (target.matches('.cfm-sub-toggle-options') || target.closest('.cfm-sub-toggle-options')) {
                e.preventDefault();
                e.stopPropagation();
                this.toggleSubFieldOptions(subFieldElement);
            }
        });

        // Field type changes
        this.container.addEventListener('change', (e) => {
            if (e.target.matches('.cfm-field-type-select')) {
                this.updateFieldType(e.target);
            }
            else if (e.target.matches('.cfm-sub-field-type-select')) {
                this.updateSubFieldType(e.target);
            }
        });

        // Auto-generate field names
        this.container.addEventListener('blur', (e) => {
            if (e.target.matches('.cfm-field-label-input')) {
                this.updateFieldName(e.target);
            }
            else if (e.target.matches('.cfm-sub-field-label-input')) {
                this.updateSubFieldName(e.target);
            }
        });
    }

    initSortable() {
        if (typeof Sortable !== 'undefined') {
            this.sortable = Sortable.create(this.container, {
                handle: '.cfm-field-grip',
                ghostClass: 'cfm-field-ghost',
                chosenClass: 'cfm-field-chosen',
                dragClass: 'cfm-field-drag',
                animation: 150,
                onStart: (evt) => {
                    evt.item.classList.add('cfm-field-dragging');
                },
                onEnd: (evt) => {
                    evt.item.classList.remove('cfm-field-dragging');
                    this.updateFieldOrder();
                }
            });
        } else {
            console.warn('Sortable.js not loaded');
        }
    }

    renderExistingFields() {
        if (!window.cfmFieldGroup?.fields) {
            // Add one default field
            this.addField();
            return;
        }

        window.cfmFieldGroup.fields.forEach((fieldData, index) => {
            const fieldElement = this.addField(fieldData, index);
            
            // Initialize sub-fields if they exist
            if (fieldData.sub_fields && fieldData.sub_fields.length > 0) {
                fieldData.sub_fields.forEach((subFieldData, subIndex) => {
                    if (subIndex > 0) { // First sub-field is already rendered
                        this.addSubField(fieldElement, subFieldData, subIndex);
                    } else {
                        // Update the first sub-field with data
                        const firstSubField = fieldElement.querySelector('.cfm-sub-field');
                        if (firstSubField) {
                            this.updateSubFieldValues(firstSubField, subFieldData);
                            this.updateSubFieldOptions(firstSubField, subFieldData.type || 'text', subFieldData.options || {});
                        }
                    }
                });
            }
        });
    }

    addField(data = {}, index = null) {
        const fieldIndex = index !== null ? index : this.fieldIndex;
        const fieldData = {
            index: fieldIndex,
            key: data.key || `field_${Date.now()}`,
            label: data.label || 'New Field',
            name: data.name || '',
            type: data.type || 'text',
            instructions: data.instructions || '',
            required: data.required || false,
            options: data.options || {},
            sub_fields: data.sub_fields || []
        };

        const fieldElement = this.createFieldElement(fieldData);

        if (index !== null && index < this.container.children.length) {
            this.container.insertBefore(fieldElement, this.container.children[index]);
        } else {
            this.container.appendChild(fieldElement);
        }

        // Initialize field with proper state
        this.updateFieldOptions(fieldElement, fieldData.type, fieldData.options);

        // Show field body for new fields
        if (index === null) {
            this.showFieldBody(fieldElement);
        }

        if (index === null) {
            this.fieldIndex++;
        }

        return fieldElement;
    }

    createFieldElement(data) {
        const div = document.createElement('div');
        div.className = 'cfm-field';
        div.dataset.index = data.index;
        div.dataset.key = data.key;
        div.innerHTML = this.getFieldHTML(data);
        return div;
    }

    getFieldHTML(data) {
        return `
            <div class="cfm-field-header">
                <span class="cfm-field-grip dashicons dashicons-menu"></span>
                <span class="cfm-field-label">${this.escapeHtml(data.label)}</span>
                <span class="cfm-field-type">${data.type}</span>
                <div class="cfm-field-actions">
                    <button type="button" class="cfm-btn cfm-btn-icon cfm-remove-field" title="Remove Field">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                </div>
            </div>
            <div class="cfm-field-body">
                ${this.getFieldSettingsHTML(data)}
                <div class="cfm-field-options-toggle">
                    <button type="button" class="cfm-btn cfm-btn-outline cfm-toggle-options">
                        <span class="dashicons dashicons-admin-generic"></span>
                        Field Options
                    </button>
                </div>
                <div class="cfm-field-options-section">
                    ${this.getFieldOptionsHTML(data)}
                </div>
                ${this.getSubFieldsHTML(data)}
                <input type="hidden" name="fields[${data.index}][key]" value="${data.key}">
            </div>
        `;
    }

    getFieldSettingsHTML(data) {
        return `
            <div class="cfm-field-settings">
                <div class="cfm-form-row">
                    <div class="cfm-form-col">
                        <label class="cfm-form-label">Field Label *</label>
                        <input type="text" name="fields[${data.index}][label]" value="${this.escapeHtml(data.label)}" class="cfm-form-input cfm-field-label-input" required>
                    </div>
                    <div class="cfm-form-col">
                        <label class="cfm-form-label">Field Name *</label>
                        <input type="text" name="fields[${data.index}][name]" value="${this.escapeHtml(data.name)}" class="cfm-form-input cfm-field-name-input" required>
                    </div>
                </div>
                <div class="cfm-form-row">
                    <div class="cfm-form-col">
                        <label class="cfm-form-label">Field Type *</label>
                        <select name="fields[${data.index}][type]" class="cfm-form-select cfm-field-type-select" required>
                            ${this.getFieldTypeOptions(data.type)}
                        </select>
                    </div>
                    <div class="cfm-form-col">
                        <label class="cfm-form-label">Instructions</label>
                        <textarea name="fields[${data.index}][instructions]" class="cfm-form-textarea" rows="2" placeholder="Help text for this field">${this.escapeHtml(data.instructions)}</textarea>
                    </div>
                </div>
                <div class="cfm-form-row">
                    <div class="cfm-form-col">
                        <label class="cfm-checkbox-label">
                            <input type="checkbox" name="fields[${data.index}][required]" value="1" ${data.required ? 'checked' : ''}>
                            <span class="cfm-checkbox-text">Required Field</span>
                        </label>
                    </div>
                </div>
            </div>
        `;
    }

    getFieldTypeOptions(selectedType) {
        const types = [
            { value: 'text', label: 'Text' },
            { value: 'textarea', label: 'Text Area' },
            { value: 'number', label: 'Number' },
            { value: 'email', label: 'Email' },
            { value: 'url', label: 'URL' },
            { value: 'select', label: 'Select' },
            { value: 'checkbox', label: 'Checkbox' },
            { value: 'radio', label: 'Radio Button' },
            { value: 'date', label: 'Date' },
            { value: 'media', label: 'Media' },
            { value: 'wysiwyg', label: 'WYSIWYG Editor' },
            { value: 'repeater', label: 'Repeater' }
        ];

        return types.map(type =>
            `<option value="${type.value}" ${type.value === selectedType ? 'selected' : ''}>${type.label}</option>`
        ).join('');
    }

    getFieldOptionsHTML(data) {
        return `
            <div class="cfm-field-options-content">
                <div class="cfm-options-tabs">
                    <button type="button" class="cfm-options-tab active" data-tab="style">
                        <span class="dashicons dashicons-art"></span>
                        Style Options
                    </button>
                    <button type="button" class="cfm-options-tab" data-tab="advanced">
                        <span class="dashicons dashicons-admin-generic"></span>
                        Advanced Options
                    </button>
                </div>
                <div class="cfm-options-panels">
                    <div class="cfm-options-panel active" data-panel="style">
                        <div class="cfm-options-panel-header">
                            <h4>Style & Layout Options</h4>
                            <p class="cfm-options-description">Control the appearance and layout of this field</p>
                        </div>
                        <div class="cfm-options-panel-content" data-field-type="${data.type}">
                            <!-- Style options will be populated here -->
                        </div>
                    </div>
                    <div class="cfm-options-panel" data-panel="advanced">
                        <div class="cfm-options-panel-header">
                            <h4>Advanced Field Options</h4>
                            <p class="cfm-options-description">Configure field behavior and validation</p>
                        </div>
                        <div class="cfm-options-panel-content" data-field-type="${data.type}">
                            <!-- Advanced options will be populated here -->
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    getSubFieldsHTML(data) {
        const isRepeater = data.type === 'repeater';
        const firstSubField = data.sub_fields && data.sub_fields.length > 0 ? data.sub_fields[0] : {
            key: `sub_field_${Date.now()}`,
            label: '',
            name: '',
            type: 'text',
            required: false,
            options: {}
        };

        return `
            <div class="cfm-sub-fields-setting" style="display: ${isRepeater ? 'block' : 'none'}">
                <div class="cfm-sub-fields-header">
                    <h4>Sub Fields</h4>
                    <p class="cfm-field-description">Add fields that can be repeated multiple times</p>
                    <button type="button" class="cfm-btn cfm-btn-outline cfm-btn-sm cfm-add-sub-field">
                        <span class="dashicons dashicons-plus"></span>
                        Add Sub Field
                    </button>
                </div>
                <div class="cfm-sub-fields-container">
                    ${this.getSubFieldHTML(data.index, 0, firstSubField, isRepeater)}
                </div>
            </div>
        `;
    }

    getSubFieldHTML(parentIndex, subIndex, data, isRepeaterParent = false) {
        // Only make sub-fields required if parent field is a repeater
        const requiredAttr = isRepeaterParent ? 'required' : '';
        
        return `
            <div class="cfm-sub-field" data-index="${subIndex}">
                <div class="cfm-sub-field-header">
                    <span class="cfm-sub-field-label">${data.label || 'Sub Field ' + (subIndex + 1)}</span>
                    <div class="cfm-sub-field-actions">
                        <button type="button" class="cfm-btn cfm-btn-icon cfm-sub-toggle-options" title="Sub Field Options">
                            <span class="dashicons dashicons-admin-generic"></span>
                        </button>
                        <button type="button" class="cfm-btn cfm-btn-icon cfm-remove-sub-field" title="Remove Sub Field">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>
                </div>
                <div class="cfm-sub-field-body">
                    <div class="cfm-form-row">
                        <div class="cfm-form-col">
                            <label class="cfm-form-label">Field Label *</label>
                            <input type="text" name="fields[${parentIndex}][sub_fields][${subIndex}][label]" value="${this.escapeHtml(data.label)}" class="cfm-form-input cfm-sub-field-label-input" ${requiredAttr}>
                        </div>
                        <div class="cfm-form-col">
                            <label class="cfm-form-label">Field Name *</label>
                            <input type="text" name="fields[${parentIndex}][sub_fields][${subIndex}][name]" value="${this.escapeHtml(data.name)}" class="cfm-form-input cfm-sub-field-name-input" ${requiredAttr}>
                        </div>
                    </div>
                    <div class="cfm-form-row">
                        <div class="cfm-form-col">
                            <label class="cfm-form-label">Field Type *</label>
                            <select name="fields[${parentIndex}][sub_fields][${subIndex}][type]" class="cfm-form-select cfm-sub-field-type-select">
                                ${this.getSubFieldTypeOptions(data.type)}
                            </select>
                        </div>
                        <div class="cfm-form-col">
                            <label class="cfm-checkbox-label">
                                <input type="checkbox" name="fields[${parentIndex}][sub_fields][${subIndex}][required]" value="1" ${data.required ? 'checked' : ''}>
                                <span class="cfm-checkbox-text">Required Field</span>
                            </label>
                        </div>
                    </div>
                    <div class="cfm-sub-field-options-section">
                        <div class="cfm-sub-field-options-toggle">
                            <button type="button" class="cfm-btn cfm-btn-outline cfm-btn-sm cfm-sub-toggle-options">
                                <span class="dashicons dashicons-admin-generic"></span>
                                Sub Field Options
                            </button>
                        </div>
                        <div class="cfm-sub-field-options-content">
                            <div class="cfm-options-panel active">
                                <div class="cfm-options-panel-header">
                                    <h4>Sub Field Options</h4>
                                    <p class="cfm-options-description">Configure sub field behavior and appearance</p>
                                </div>
                                <div class="cfm-options-panel-content" data-sub-field-type="${data.type || 'text'}">
                                    <!-- Sub field options will be populated here -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="fields[${parentIndex}][sub_fields][${subIndex}][key]" value="${data.key || `sub_field_${Date.now()}`}">
                </div>
            </div>
        `;
    }

    getSubFieldTypeOptions(selectedType) {
        const types = [
            { value: 'text', label: 'Text' },
            { value: 'textarea', label: 'Text Area' },
            { value: 'number', label: 'Number' },
            { value: 'email', label: 'Email' },
            { value: 'url', label: 'URL' },
            { value: 'select', label: 'Select' },
            { value: 'checkbox', label: 'Checkbox' },
            { value: 'radio', label: 'Radio Button' },
            { value: 'date', label: 'Date' },
            { value: 'media', label: 'Media' }
        ];

        return types.map(type =>
            `<option value="${type.value}" ${type.value === selectedType ? 'selected' : ''}>${type.label}</option>`
        ).join('');
    }

    updateSubFieldValues(subFieldElement, data) {
        const labelInput = subFieldElement.querySelector('.cfm-sub-field-label-input');
        const nameInput = subFieldElement.querySelector('.cfm-sub-field-name-input');
        const typeSelect = subFieldElement.querySelector('.cfm-sub-field-type-select');
        const requiredCheckbox = subFieldElement.querySelector('input[type="checkbox"][name*="required"]');
        const keyInput = subFieldElement.querySelector('input[type="hidden"][name*="key"]');

        if (labelInput && data.label) labelInput.value = data.label;
        if (nameInput && data.name) nameInput.value = data.name;
        if (typeSelect && data.type) typeSelect.value = data.type;
        if (requiredCheckbox) requiredCheckbox.checked = !!data.required;
        if (keyInput && data.key) keyInput.value = data.key;

        // Update label in header
        const labelSpan = subFieldElement.querySelector('.cfm-sub-field-label');
        if (labelSpan) {
            labelSpan.textContent = data.label || 'Sub Field';
        }

        // Update required attributes based on parent field type
        this.updateSubFieldRequiredAttributes(subFieldElement);
    }

    updateSubFieldRequiredAttributes(subFieldElement) {
        const parentField = subFieldElement.closest('.cfm-field');
        const parentType = parentField?.querySelector('.cfm-field-type-select')?.value;
        const isRepeaterParent = parentType === 'repeater';
        
        const labelInput = subFieldElement.querySelector('.cfm-sub-field-label-input');
        const nameInput = subFieldElement.querySelector('.cfm-sub-field-name-input');

        if (labelInput && nameInput) {
            if (isRepeaterParent) {
                labelInput.setAttribute('required', 'required');
                nameInput.setAttribute('required', 'required');
            } else {
                labelInput.removeAttribute('required');
                nameInput.removeAttribute('required');
            }
        }
    }

    getStyleOptions(fieldType) {
        return [
            {
                name: 'width',
                label: 'Field Width',
                type: 'select',
                default: '100',
                options: [
                    { value: '25', label: '25%' },
                    { value: '33', label: '33%' },
                    { value: '50', label: '50%' },
                    { value: '66', label: '66%' },
                    { value: '75', label: '75%' },
                    { value: '100', label: '100%' }
                ]
            },
            {
                name: 'grid_template',
                label: 'Grid Template',
                type: 'select',
                default: 'auto',
                options: [
                    { value: 'auto', label: 'Auto' },
                    { value: '1fr', label: 'Single Column' },
                    { value: '1fr 1fr', label: 'Two Columns (Equal)' },
                    { value: '1fr 2fr', label: 'Two Columns (1:2)' },
                    { value: '2fr 1fr', label: 'Two Columns (2:1)' },
                    { value: '1fr 1fr 1fr', label: 'Three Columns' },
                    { value: 'repeat(auto-fit, minmax(200px, 1fr))', label: 'Responsive Grid' }
                ]
            }
        ];
    }

    getAdvancedOptions(fieldType) {
        const commonOptions = [
            {
                name: 'placeholder',
                label: 'Placeholder Text',
                type: 'text',
                default: '',
                placeholder: 'Enter text here...'
            },
            {
                name: 'default_value',
                label: 'Default Value',
                type: 'text',
                default: '',
                placeholder: 'Default value...'
            }
        ];

        const typeSpecific = {
            text: [
                {
                    name: 'maxlength',
                    label: 'Max Length',
                    type: 'number',
                    default: '',
                    min: 0,
                    placeholder: '255'
                }
            ],
            textarea: [
                {
                    name: 'rows',
                    label: 'Number of Rows',
                    type: 'number',
                    default: 4,
                    min: 2,
                    max: 20
                }
            ],
            number: [
                {
                    name: 'min',
                    label: 'Minimum Value',
                    type: 'number',
                    default: ''
                },
                {
                    name: 'max',
                    label: 'Maximum Value',
                    type: 'number',
                    default: ''
                }
            ],
            select: [
                {
                    name: 'choices',
                    label: 'Choices',
                    type: 'choices-editor',
                    default: '',
                    placeholder: 'choice1 : Label 1\nchoice2 : Label 2'
                },
                {
                    name: 'multiple',
                    label: 'Allow Multiple Selections',
                    type: 'checkbox',
                    default: false
                }
            ],
            checkbox: [
                {
                    name: 'choices',
                    label: 'Choices',
                    type: 'choices-editor',
                    default: '',
                    placeholder: 'choice1 : Label 1\nchoice2 : Label 2'
                }
            ],
            radio: [
                {
                    name: 'choices',
                    label: 'Choices',
                    type: 'choices-editor',
                    default: '',
                    placeholder: 'choice1 : Label 1\nchoice2 : Label 2'
                }
            ],
            media: [
                {
                    name: 'allowed_types',
                    label: 'Allowed File Types',
                    type: 'text',
                    default: 'jpg,jpeg,png,gif,pdf,doc,docx',
                    placeholder: 'jpg,png,pdf,doc'
                },
                {
                    name: 'max_size',
                    label: 'Max File Size (MB)',
                    type: 'number',
                    default: 2,
                    min: 0
                }
            ]
        };

        return [...commonOptions, ...(typeSpecific[fieldType] || [])];
    }

    getSubFieldOptions(fieldType) {
        const commonOptions = [
            {
                name: 'placeholder',
                label: 'Placeholder Text',
                type: 'text',
                default: '',
                placeholder: 'Enter text here...'
            },
            {
                name: 'default_value',
                label: 'Default Value',
                type: 'text',
                default: '',
                placeholder: 'Default value...'
            }
        ];

        const typeSpecific = {
            text: [
                {
                    name: 'maxlength',
                    label: 'Max Length',
                    type: 'number',
                    default: '',
                    min: 0,
                    placeholder: '255'
                }
            ],
            textarea: [
                {
                    name: 'rows',
                    label: 'Number of Rows',
                    type: 'number',
                    default: 4,
                    min: 2,
                    max: 20
                }
            ],
            number: [
                {
                    name: 'min',
                    label: 'Minimum Value',
                    type: 'number',
                    default: ''
                },
                {
                    name: 'max',
                    label: 'Maximum Value',
                    type: 'number',
                    default: ''
                }
            ],
            select: [
                {
                    name: 'choices',
                    label: 'Choices',
                    type: 'choices-editor',
                    default: '',
                    placeholder: 'choice1 : Label 1\nchoice2 : Label 2'
                }
            ],
            media: [
                {
                    name: 'allowed_types',
                    label: 'Allowed File Types',
                    type: 'text',
                    default: 'jpg,jpeg,png,gif,pdf',
                    placeholder: 'jpg,png,pdf'
                }
            ]
        };

        return [...commonOptions, ...(typeSpecific[fieldType] || [])];
    }

    updateFieldOptions(fieldElement, fieldType, currentValues = {}) {
        const stylePanel = fieldElement.querySelector('.cfm-options-panel[data-panel="style"] .cfm-options-panel-content');
        const advancedPanel = fieldElement.querySelector('.cfm-options-panel[data-panel="advanced"] .cfm-options-panel-content');

        if (stylePanel) {
            const styleOptions = this.getStyleOptions(fieldType);
            stylePanel.innerHTML = styleOptions.map(option =>
                this.renderFieldOption(option, fieldElement.dataset.index, currentValues[option.name])
            ).join('');
        }

        if (advancedPanel) {
            const advancedOptions = this.getAdvancedOptions(fieldType);
            advancedPanel.innerHTML = advancedOptions.map(option =>
                this.renderFieldOption(option, fieldElement.dataset.index, currentValues[option.name])
            ).join('');
        }

        this.initConditionalFields(fieldElement);
        this.initOptionsTabs(fieldElement);
        this.initChoicesEditors(fieldElement);
    }

    updateSubFieldOptions(subFieldElement, fieldType, currentValues = {}) {
        const optionsPanel = subFieldElement.querySelector('.cfm-options-panel-content');
        if (!optionsPanel) {
            console.warn('Sub-field options panel not found');
            return;
        }

        const subFieldOptions = this.getSubFieldOptions(fieldType);
        optionsPanel.innerHTML = subFieldOptions.map(option =>
            this.renderSubFieldOption(option, subFieldElement, currentValues[option.name])
        ).join('');

        this.initConditionalSubFields(subFieldElement);
        this.initChoicesEditors(subFieldElement);
    }

    renderFieldOption(option, fieldIndex, value, isSubField = false) {
        const currentValue = value !== undefined ? value : option.default;
        const namePrefix = `fields[${fieldIndex}][options]`;
        const name = `${namePrefix}[${option.name}]`;
        const showIf = option.showIf ? `data-show-if="${JSON.stringify(option.showIf).replace(/"/g, '&quot;')}"` : '';

        let inputHTML = '';

        switch (option.type) {
            case 'text':
                inputHTML = `<input type="text" name="${name}" value="${currentValue}" class="cfm-form-input" placeholder="${option.placeholder || ''}">`;
                break;
            case 'number':
                inputHTML = `<input type="number" name="${name}" value="${currentValue}" min="${option.min || ''}" max="${option.max || ''}" step="${option.step || 1}" class="cfm-form-input">`;
                break;
            case 'checkbox':
                inputHTML = `
                    <label class="cfm-checkbox-label">
                        <input type="checkbox" name="${name}" value="1" ${currentValue ? 'checked' : ''}>
                        <span class="cfm-checkbox-text">${option.label}</span>
                    </label>
                `;
                break;
            case 'select':
                inputHTML = `<select name="${name}" class="cfm-form-select">`;
                option.options.forEach(opt => {
                    const optionValue = typeof opt === 'object' ? opt.value : opt;
                    const optionLabel = typeof opt === 'object' ? opt.label : this.formatOptionLabel(opt);
                    inputHTML += `<option value="${optionValue}" ${optionValue === currentValue ? 'selected' : ''}>${optionLabel}</option>`;
                });
                inputHTML += `</select>`;
                break;
            case 'textarea':
                inputHTML = `<textarea name="${name}" class="cfm-form-textarea" rows="3" placeholder="${option.placeholder || ''}">${currentValue}</textarea>`;
                break;
            case 'choices-editor':
                inputHTML = this.getChoicesEditorHTML(name, currentValue, option.placeholder);
                break;
        }

        if (option.showIf) {
            return `
                <div class="cfm-option-field cfm-conditional-field" ${showIf}>
                    <label class="cfm-option-label">${option.label}</label>
                    ${inputHTML}
                </div>
            `;
        }

        if (option.type === 'checkbox') {
            return `<div class="cfm-option-field cfm-option-checkbox">${inputHTML}</div>`;
        }

        return `
            <div class="cfm-option-field">
                <label class="cfm-option-label">${option.label}</label>
                ${inputHTML}
            </div>
        `;
    }

    renderSubFieldOption(option, subFieldElement, value) {
        const parentIndex = subFieldElement.closest('.cfm-field').dataset.index;
        const subIndex = subFieldElement.dataset.index;
        const currentValue = value !== undefined ? value : option.default;
        const name = `fields[${parentIndex}][sub_fields][${subIndex}][options][${option.name}]`;
        const showIf = option.showIf ? `data-show-if="${JSON.stringify(option.showIf).replace(/"/g, '&quot;')}"` : '';

        let inputHTML = '';

        switch (option.type) {
            case 'text':
                inputHTML = `<input type="text" name="${name}" value="${currentValue}" class="cfm-form-input" placeholder="${option.placeholder || ''}">`;
                break;
            case 'number':
                inputHTML = `<input type="number" name="${name}" value="${currentValue}" min="${option.min || ''}" max="${option.max || ''}" class="cfm-form-input">`;
                break;
            case 'checkbox':
                inputHTML = `
                    <label class="cfm-checkbox-label">
                        <input type="checkbox" name="${name}" value="1" ${currentValue ? 'checked' : ''}>
                        <span class="cfm-checkbox-text">${option.label}</span>
                    </label>
                `;
                break;
            case 'select':
                inputHTML = `<select name="${name}" class="cfm-form-select">`;
                if (option.options) {
                    option.options.forEach(opt => {
                        const optionValue = typeof opt === 'object' ? opt.value : opt;
                        const optionLabel = typeof opt === 'object' ? opt.label : this.formatOptionLabel(opt);
                        inputHTML += `<option value="${optionValue}" ${optionValue === currentValue ? 'selected' : ''}>${optionLabel}</option>`;
                    });
                }
                inputHTML += `</select>`;
                break;
            case 'textarea':
                inputHTML = `<textarea name="${name}" class="cfm-form-textarea" rows="3" placeholder="${option.placeholder || ''}">${currentValue}</textarea>`;
                break;
            case 'choices-editor':
                inputHTML = this.getChoicesEditorHTML(name, currentValue, option.placeholder);
                break;
        }

        if (option.showIf) {
            return `
                <div class="cfm-option-field cfm-conditional-field" ${showIf}>
                    <label class="cfm-option-label">${option.label}</label>
                    ${inputHTML}
                </div>
            `;
        }

        if (option.type === 'checkbox') {
            return `<div class="cfm-option-field cfm-option-checkbox">${inputHTML}</div>`;
        }

        return `
            <div class="cfm-option-field">
                <label class="cfm-option-label">${option.label}</label>
                ${inputHTML}
            </div>
        `;
    }

    getChoicesEditorHTML(name, value, placeholder) {
        return `
            <div class="cfm-choices-editor">
                <textarea name="${name}" class="cfm-form-textarea cfm-choices-textarea" rows="4" placeholder="${placeholder || 'choice1 : Label 1\nchoice2 : Label 2'}">${value}</textarea>
                <div class="cfm-choices-preview">
                    <div class="cfm-choices-preview-header">
                        <span>Preview:</span>
                        <small>Format: value : Label</small>
                    </div>
                    <div class="cfm-choices-list"></div>
                </div>
            </div>
        `;
    }

    initChoicesEditors(container) {
        const editors = container.querySelectorAll('.cfm-choices-editor');
        editors.forEach(editor => {
            const textarea = editor.querySelector('.cfm-choices-textarea');
            const preview = editor.querySelector('.cfm-choices-list');
            
            const updatePreview = () => {
                const value = textarea.value;
                const choices = this.parseChoices(value);
                preview.innerHTML = choices.map(choice => 
                    `<div class="cfm-choice-item"><code>${choice.value}</code>: ${choice.label}</div>`
                ).join('');
            };

            textarea.addEventListener('input', updatePreview);
            updatePreview();
        });
    }

    parseChoices(text) {
        if (!text) return [];
        return text.split('\n')
            .map(line => line.trim())
            .filter(line => line)
            .map(line => {
                const parts = line.split(':').map(part => part.trim());
                return {
                    value: parts[0] || '',
                    label: parts[1] || parts[0] || ''
                };
            });
    }

    initOptionsTabs(fieldElement) {
        const tabsContainer = fieldElement.querySelector('.cfm-options-tabs');
        if (!tabsContainer) return;

        const tabs = fieldElement.querySelectorAll('.cfm-options-tab');
        const panels = fieldElement.querySelectorAll('.cfm-options-panel');

        tabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const targetPanel = tab.dataset.tab;

                // Update tabs
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                // Update panels
                panels.forEach(panel => {
                    panel.classList.remove('active');
                    if (panel.dataset.panel === targetPanel) {
                        panel.classList.add('active');
                    }
                });
            });
        });
    }

    initConditionalFields(fieldElement) {
        const conditionalFields = fieldElement.querySelectorAll('.cfm-conditional-field');

        conditionalFields.forEach(field => {
            const showIf = JSON.parse(field.getAttribute('data-show-if'));
            const [conditionField, conditionValue] = Object.entries(showIf)[0];

            const conditionElement = fieldElement.querySelector(`[name="fields[${fieldElement.dataset.index}][options][${conditionField}]"]`);
            if (conditionElement) {
                const checkCondition = () => {
                    const currentValue = conditionElement.type === 'checkbox' ? conditionElement.checked : conditionElement.value;
                    field.style.display = currentValue == conditionValue ? 'block' : 'none';
                };

                conditionElement.addEventListener('change', checkCondition);
                checkCondition();
            }
        });
    }

    initConditionalSubFields(subFieldElement) {
        const conditionalFields = subFieldElement.querySelectorAll('.cfm-conditional-field');

        conditionalFields.forEach(field => {
            const showIf = JSON.parse(field.getAttribute('data-show-if'));
            const [conditionField, conditionValue] = Object.entries(showIf)[0];
            const parentIndex = subFieldElement.closest('.cfm-field').dataset.index;
            const subIndex = subFieldElement.dataset.index;

            const conditionElement = subFieldElement.querySelector(`[name="fields[${parentIndex}][sub_fields][${subIndex}][options][${conditionField}]"]`);
            if (conditionElement) {
                const checkCondition = () => {
                    const currentValue = conditionElement.type === 'checkbox' ? conditionElement.checked : conditionElement.value;
                    field.style.display = currentValue == conditionValue ? 'block' : 'none';
                };

                conditionElement.addEventListener('change', checkCondition);
                checkCondition();
            }
        });
    }

    formatOptionLabel(option) {
        if (option === 'auto') return 'Auto';
        if (option === '1fr') return 'Single Column';
        if (option === '1fr 1fr') return 'Two Columns (Equal)';
        if (option === '1fr 2fr') return 'Two Columns (1:2)';
        if (option === '2fr 1fr') return 'Two Columns (2:1)';
        if (option === '1fr 1fr 1fr') return 'Three Columns';
        if (option.includes('repeat')) return 'Responsive Grid';
        return option.charAt(0).toUpperCase() + option.slice(1).replace(/_/g, ' ');
    }

    removeField(fieldElement) {
        if (!fieldElement) return;

        if (!confirm(cfmData?.i18n?.confirmDelete || 'Are you sure you want to delete this field?')) {
            return;
        }

        fieldElement.style.transition = 'all 0.3s ease';
        fieldElement.style.height = fieldElement.offsetHeight + 'px';

        requestAnimationFrame(() => {
            fieldElement.style.height = '0';
            fieldElement.style.opacity = '0';
            fieldElement.style.marginBottom = '0';
            fieldElement.style.paddingTop = '0';
            fieldElement.style.paddingBottom = '0';
            fieldElement.style.overflow = 'hidden';

            setTimeout(() => {
                fieldElement.remove();
                this.updateFieldOrder();
            }, 300);
        });
    }

    showFieldBody(fieldElement) {
        const body = fieldElement.querySelector('.cfm-field-body');
        if (body) {
            body.style.display = 'block';
            body.classList.add('open');
        }
    }

    toggleField(fieldElement) {
        if (!fieldElement) return;

        const body = fieldElement.querySelector('.cfm-field-body');
        if (body) {
            if (body.style.display === 'block') {
                body.style.display = 'none';
                body.classList.remove('open');
            } else {
                body.style.display = 'block';
                body.classList.add('open');
            }
        }
    }

    toggleOptions(fieldElement) {
        if (!fieldElement) return;

        const optionsContent = fieldElement.querySelector('.cfm-field-options-content');
        const toggleBtn = fieldElement.querySelector('.cfm-toggle-options');

        if (optionsContent && toggleBtn) {
            const isVisible = optionsContent.style.display === 'block';
            
            if (isVisible) {
                optionsContent.style.display = 'none';
                toggleBtn.classList.remove('active');
            } else {
                optionsContent.style.display = 'block';
                toggleBtn.classList.add('active');
            }
        }
    }

    toggleSubFieldOptions(subFieldElement) {
        if (!subFieldElement) return;

        const optionsContent = subFieldElement.querySelector('.cfm-sub-field-options-content');
        const toggleBtn = subFieldElement.querySelector('.cfm-sub-toggle-options');

        if (optionsContent && toggleBtn) {
            const isVisible = optionsContent.style.display === 'block';
            
            if (isVisible) {
                optionsContent.style.display = 'none';
                toggleBtn.classList.remove('active');
            } else {
                optionsContent.style.display = 'block';
                toggleBtn.classList.add('active');
            }
        }
    }

    updateFieldType(selectElement) {
        const fieldElement = selectElement.closest('.cfm-field');
        if (!fieldElement) return;

        const fieldType = selectElement.value;

        // Update type badge
        const typeBadge = fieldElement.querySelector('.cfm-field-type');
        if (typeBadge) {
            typeBadge.textContent = fieldType;
        }

        // Update options
        this.updateFieldOptions(fieldElement, fieldType);

        // Toggle sub-fields for repeater
        const subFieldsSection = fieldElement.querySelector('.cfm-sub-fields-setting');
        if (subFieldsSection) {
            const isRepeater = fieldType === 'repeater';
            subFieldsSection.style.display = isRepeater ? 'block' : 'none';
            
            // Update required attributes for all sub-fields
            const subFields = fieldElement.querySelectorAll('.cfm-sub-field');
            subFields.forEach(subField => {
                this.updateSubFieldRequiredAttributes(subField);
            });
        }
    }

    updateSubFieldType(selectElement) {
        const subFieldElement = selectElement.closest('.cfm-sub-field');
        if (!subFieldElement) return;

        const fieldType = selectElement.value;
        this.updateSubFieldOptions(subFieldElement, fieldType);
    }

    updateFieldName(labelInput) {
        const fieldElement = labelInput.closest('.cfm-field');
        if (!fieldElement) return;

        const nameInput = fieldElement.querySelector('.cfm-field-name-input');

        if (nameInput && (!nameInput.value || nameInput.value === 'new_field')) {
            const label = labelInput.value;
            const name = this.sanitizeFieldName(label);
            nameInput.value = name;
        }

        // Update label in header
        const labelSpan = fieldElement.querySelector('.cfm-field-label');
        if (labelSpan) {
            labelSpan.textContent = labelInput.value || 'New Field';
        }
    }

    updateSubFieldName(labelInput) {
        const subFieldElement = labelInput.closest('.cfm-sub-field');
        if (!subFieldElement) return;

        const nameInput = subFieldElement.querySelector('.cfm-sub-field-name-input');

        if (nameInput && (!nameInput.value || nameInput.value === 'new_field')) {
            const label = labelInput.value;
            const name = this.sanitizeFieldName(label);
            nameInput.value = name;
        }

        // Update label in header
        const labelSpan = subFieldElement.querySelector('.cfm-sub-field-label');
        if (labelSpan) {
            labelSpan.textContent = labelInput.value || 'Sub Field';
        }
    }

    addSubField(fieldElement, data = {}, subIndex = null) {
        if (!fieldElement) return;

        const container = fieldElement.querySelector('.cfm-sub-fields-container');
        if (!container) return;

        const actualSubIndex = subIndex !== null ? subIndex : container.children.length;
        const parentIndex = fieldElement.dataset.index;
        const parentType = fieldElement.querySelector('.cfm-field-type-select')?.value;
        const isRepeaterParent = parentType === 'repeater';

        const subFieldData = {
            key: data.key || `sub_field_${Date.now()}`,
            label: data.label || '',
            name: data.name || '',
            type: data.type || 'text',
            required: data.required || false,
            options: data.options || {}
        };

        const subFieldHTML = this.getSubFieldHTML(parentIndex, actualSubIndex, subFieldData, isRepeaterParent);
        
        if (subIndex !== null && subIndex < container.children.length) {
            const existingSubField = container.children[subIndex];
            existingSubField.insertAdjacentHTML('beforebegin', subFieldHTML);
            existingSubField.remove();
        } else {
            container.insertAdjacentHTML('beforeend', subFieldHTML);
        }

        // Initialize the new sub-field options
        const newSubField = container.querySelector(`[data-index="${actualSubIndex}"]`);
        if (newSubField) {
            this.updateSubFieldOptions(newSubField, subFieldData.type, subFieldData.options);
            this.updateSubFieldRequiredAttributes(newSubField);
        }

        return newSubField;
    }

    removeSubField(subFieldElement) {
        if (!subFieldElement) return;

        if (!confirm(cfmData?.i18n?.confirmDelete || 'Are you sure you want to delete this sub-field?')) {
            return;
        }

        subFieldElement.style.transition = 'all 0.3s ease';
        subFieldElement.style.height = subFieldElement.offsetHeight + 'px';

        requestAnimationFrame(() => {
            subFieldElement.style.height = '0';
            subFieldElement.style.opacity = '0';
            subFieldElement.style.marginBottom = '0';
            subFieldElement.style.overflow = 'hidden';

            setTimeout(() => {
                subFieldElement.remove();
            }, 300);
        });
    }

    updateFieldOrder() {
        const fields = this.container.querySelectorAll('.cfm-field');
        fields.forEach((fieldElement, newIndex) => {
            const oldIndex = fieldElement.dataset.index;
            fieldElement.dataset.index = newIndex;

            // Update all input names
            fieldElement.querySelectorAll('[name]').forEach(input => {
                const name = input.getAttribute('name');
                const newName = name.replace(/fields\[(\d+)\]/g, `fields[${newIndex}]`);
                input.setAttribute('name', newName);
            });
        });

        // Update field index counter
        this.fieldIndex = fields.length;
    }

    // Utility methods
    escapeHtml(unsafe) {
        if (unsafe === null || unsafe === undefined) return '';
        return unsafe.toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    sanitizeFieldName(str) {
        return str
            .toLowerCase()
            .replace(/[^a-z0-9_\s]/g, '')
            .replace(/\s+/g, '_')
            .substring(0, 50);
    }
}

class CFMLocationRules {
    constructor() {
        this.container = document.getElementById('cfm-location-rules');
        this.groupIndex = window.cfmFieldGroup?.location?.length || 0;
        this.init();
    }

    init() {
        this.bindEvents();
        this.renderExistingRules();
    }

    bindEvents() {
        document.getElementById('cfm-add-rule-group')?.addEventListener('click', (e) => {
            e.preventDefault();
            this.addRuleGroup();
        });

        this.container.addEventListener('click', (e) => {
            if (e.target.matches('.cfm-add-rule') || e.target.closest('.cfm-add-rule')) {
                e.preventDefault();
                const group = e.target.closest('.cfm-rule-group');
                this.addRule(group.dataset.index);
            } else if (e.target.matches('.cfm-remove-rule') || e.target.closest('.cfm-remove-rule')) {
                e.preventDefault();
                this.removeRule(e.target.closest('.cfm-rule'));
            }
        });
    }

    renderExistingRules() {
        if (!window.cfmFieldGroup?.location || window.cfmFieldGroup.location.length === 0) {
            this.addRuleGroup();
            return;
        }

        window.cfmFieldGroup.location.forEach((ruleGroup, groupIndex) => {
            this.addRuleGroup(groupIndex);
            ruleGroup.forEach((rule, ruleIndex) => {
                if (ruleIndex > 0) {
                    this.addRule(groupIndex, rule, ruleIndex);
                } else {
                    // Update the first rule with existing data
                    const group = this.container.querySelector(`[data-index="${groupIndex}"]`);
                    if (group) {
                        const firstRule = group.querySelector('.cfm-rule');
                        this.updateRuleValues(firstRule, rule);
                    }
                }
            });
        });
    }

    addRuleGroup(groupIndex = null) {
        const actualIndex = groupIndex !== null ? groupIndex : this.groupIndex;
        const showOr = this.container.children.length > 0;

        const groupHTML = `
            ${showOr ? '<div class="cfm-rule-connector cfm-rule-or">OR</div>' : ''}
            <div class="cfm-rule-group" data-index="${actualIndex}">
                ${this.getRuleHTML(actualIndex, 0)}
                <button type="button" class="cfm-btn cfm-btn-outline cfm-add-rule">
                    <span class="dashicons dashicons-plus"></span>
                    Add Rule
                </button>
            </div>
        `;

        this.container.insertAdjacentHTML('beforeend', groupHTML);

        if (groupIndex === null) {
            this.groupIndex++;
        }
    }

    addRule(groupIndex, data = {}, ruleIndex = null) {
        const group = this.container.querySelector(`[data-index="${groupIndex}"]`);
        if (!group) return;

        const rules = group.querySelectorAll('.cfm-rule');
        const actualRuleIndex = ruleIndex !== null ? ruleIndex : rules.length;
        const showAnd = actualRuleIndex > 0;

        const ruleHTML = this.getRuleHTML(groupIndex, actualRuleIndex, data, showAnd);

        const addButton = group.querySelector('.cfm-add-rule');
        addButton.insertAdjacentHTML('beforebegin', ruleHTML);
    }

    getRuleHTML(groupIndex, ruleIndex, data = {}, showAnd = false) {
        return `
            ${showAnd ? '<div class="cfm-rule-connector cfm-rule-and">AND</div>' : ''}
            <div class="cfm-rule">
                <div class="cfm-rule-fields">
                    <select name="location[${groupIndex}][${ruleIndex}][param]" class="cfm-rule-param">
                        <option value="post_type" ${data.param === 'post_type' ? 'selected' : ''}>Post Type</option>
                        <option value="post" ${data.param === 'post' ? 'selected' : ''}>Post</option>
                        <option value="post_template" ${data.param === 'post_template' ? 'selected' : ''}>Page Template</option>
                        <option value="post_category" ${data.param === 'post_category' ? 'selected' : ''}>Post Category</option>
                    </select>
                    <select name="location[${groupIndex}][${ruleIndex}][operator]" class="cfm-rule-operator">
                        <option value="==" ${data.operator !== '!=' ? 'selected' : ''}>is equal to</option>
                        <option value="!=" ${data.operator === '!=' ? 'selected' : ''}>is not equal to</option>
                    </select>
                    <input type="text" name="location[${groupIndex}][${ruleIndex}][value]" value="${data.value || ''}" class="cfm-rule-value" placeholder="Enter value...">
                    <button type="button" class="cfm-btn cfm-btn-outline cfm-btn-sm cfm-remove-rule" title="Remove Rule">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                </div>
            </div>
        `;
    }

    updateRuleValues(ruleElement, data) {
        const paramSelect = ruleElement.querySelector('.cfm-rule-param');
        const operatorSelect = ruleElement.querySelector('.cfm-rule-operator');
        const valueInput = ruleElement.querySelector('.cfm-rule-value');

        if (paramSelect && data.param) paramSelect.value = data.param;
        if (operatorSelect && data.operator) operatorSelect.value = data.operator;
        if (valueInput && data.value) valueInput.value = data.value;
    }

    removeRule(ruleElement) {
        if (!ruleElement) return;

        const group = ruleElement.closest('.cfm-rule-group');
        if (!group) return;

        const rules = group.querySelectorAll('.cfm-rule');

        if (rules.length <= 1) {
            // Remove entire group if it's the last rule
            if (!confirm(cfmData?.i18n?.confirmDelete || 'Are you sure you want to delete this rule group?')) return;

            const orConnector = group.previousElementSibling;
            if (orConnector?.classList.contains('cfm-rule-or')) {
                orConnector.remove();
            }
            group.remove();
        } else {
            // Remove just this rule
            const andConnector = ruleElement.previousElementSibling;
            if (andConnector?.classList.contains('cfm-rule-and')) {
                andConnector.remove();
            }
            ruleElement.remove();
        }
    }
}

class CFMFormHandler {
    constructor() {
        this.form = document.getElementById('cfm-field-group-form');
        this.init();
    }

    init() {
        this.bindEvents();
    }

    bindEvents() {
        this.form.addEventListener('submit', (e) => {
            this.handleSubmit(e);
        });
    }

    handleSubmit(e) {
        // Remove required attributes from hidden sub-fields before validation
        const hiddenSubFields = this.form.querySelectorAll('.cfm-sub-field-label-input[required], .cfm-sub-field-name-input[required]');
        hiddenSubFields.forEach(field => {
            const subFieldContainer = field.closest('.cfm-sub-fields-setting');
            if (subFieldContainer && subFieldContainer.style.display === 'none') {
                field.removeAttribute('required');
            }
        });

        // Basic validation
        const titleInput = this.form.querySelector('input[name="title"]');
        if (!titleInput.value.trim()) {
            e.preventDefault();
            alert('Please enter a field group title');
            titleInput.focus();
            return;
        }

        // Validate that repeater fields have valid sub-fields
        const hasValidationErrors = this.validateRepeaterFields();
        if (hasValidationErrors) {
            e.preventDefault();
            return;
        }

        // Show saving state
        const submitButton = this.form.querySelector('button[type="submit"]');
        const originalText = submitButton.innerHTML;

        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="dashicons dashicons-update spinning"></span> Saving...';

        // Re-enable after a short delay (in case validation fails)
        setTimeout(() => {
            submitButton.disabled = false;
            submitButton.innerHTML = originalText;
        }, 3000);
    }

    validateRepeaterFields() {
        let hasErrors = false;
        const repeaterFields = this.form.querySelectorAll('.cfm-field-type-select');
        
        repeaterFields.forEach(select => {
            if (select.value === 'repeater') {
                const fieldElement = select.closest('.cfm-field');
                const subFieldsContainer = fieldElement.querySelector('.cfm-sub-fields-container');
                const subFields = subFieldsContainer.querySelectorAll('.cfm-sub-field');
                
                if (subFields.length === 0) {
                    hasErrors = true;
                    alert('Repeater fields must have at least one sub-field');
                    return;
                }

                // Validate each sub-field in repeater
                subFields.forEach(subField => {
                    const labelInput = subField.querySelector('.cfm-sub-field-label-input');
                    const nameInput = subField.querySelector('.cfm-sub-field-name-input');
                    
                    if (!labelInput.value.trim() || !nameInput.value.trim()) {
                        hasErrors = true;
                        alert('All sub-fields in repeater must have both label and name');
                        return;
                    }
                });
            }
        });

        return hasErrors;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.cfmAdmin = new CFMAdmin();
});