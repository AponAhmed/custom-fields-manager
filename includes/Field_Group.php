<?php
// includes/Field_Group.php

class CFM_Field_Group {
    
    private $data = [
        'id' => 0,
        'title' => '',
        'key' => '',
        'fields' => [],
        'location' => [],
        'position' => 'normal',
        'style' => 'default',
        'active' => true,
        'menu_order' => 0,
        'created_at' => '',
        'updated_at' => ''
    ];
    
    public function __construct($data = []) {
        if (!empty($data)) {
            $this->data = array_merge($this->data, $data);
        }
        
        // Generate unique key if not provided
        if (empty($this->data['key'])) {
            $this->data['key'] = 'group_' . uniqid();
        }
        
        // Ensure proper data types
        $this->data['id'] = (int) $this->data['id'];
        $this->data['menu_order'] = (int) $this->data['menu_order'];
        $this->data['active'] = (bool) $this->data['active'];
    }
    
    // Getters
    public function get_id() {
        return $this->data['id'];
    }
    
    public function get_title() {
        return $this->data['title'];
    }
    
    public function get_key() {
        return $this->data['key'];
    }
    
    public function get_fields() {
        return $this->data['fields'];
    }
    
    public function get_location() {
        return $this->data['location'];
    }
    
    public function get_position() {
        return $this->data['position'];
    }
    
    public function get_style() {
        return $this->data['style'];
    }
    
    public function is_active() {
        return (bool) $this->data['active'];
    }
    
    public function get_menu_order() {
        return $this->data['menu_order'];
    }
    
    public function get_created_at() {
        return $this->data['created_at'];
    }
    
    public function get_updated_at() {
        return $this->data['updated_at'];
    }
    
    // Setters
    public function set_id($id) {
        $this->data['id'] = (int) $id;
        return $this;
    }
    
    public function set_title($title) {
        $this->data['title'] = sanitize_text_field($title);
        return $this;
    }
    
    public function set_key($key) {
        $this->data['key'] = sanitize_key($key);
        return $this;
    }
    
    public function set_fields($fields) {
        $this->data['fields'] = $this->sanitize_fields($fields);
        return $this;
    }
    
    public function set_location($location) {
        $this->data['location'] = $this->sanitize_location($location);
        return $this;
    }
    
    public function set_position($position) {
        $allowed_positions = ['normal', 'advanced', 'side'];
        $this->data['position'] = in_array($position, $allowed_positions) ? $position : 'normal';
        return $this;
    }
    
    public function set_style($style) {
        $allowed_styles = ['default', 'seamless'];
        $this->data['style'] = in_array($style, $allowed_styles) ? $style : 'default';
        return $this;
    }
    
    public function set_active($active) {
        $this->data['active'] = (bool) $active;
        return $this;
    }
    
    public function set_menu_order($order) {
        $this->data['menu_order'] = (int) $order;
        return $this;
    }
    
    public function set_created_at($timestamp) {
        $this->data['created_at'] = $timestamp;
        return $this;
    }
    
    public function set_updated_at($timestamp) {
        $this->data['updated_at'] = $timestamp;
        return $this;
    }
    
    // Field management methods
    public function add_field($field_data) {
        $sanitized_field = $this->sanitize_single_field($field_data);
        if ($sanitized_field) {
            $this->data['fields'][] = $sanitized_field;
        }
        return $this;
    }
    
    public function remove_field($field_key) {
        $this->data['fields'] = array_filter($this->data['fields'], function($field) use ($field_key) {
            return $field['key'] !== $field_key;
        });
        return $this;
    }
    
    public function get_field($field_key) {
        foreach ($this->data['fields'] as $field) {
            if ($field['key'] === $field_key) {
                return $field;
            }
        }
        return null;
    }
    
    // Location management methods
    public function add_location_rule($param, $operator, $value, $group_index = 0) {
        if (!isset($this->data['location'][$group_index])) {
            $this->data['location'][$group_index] = [];
        }
        
        $this->data['location'][$group_index][] = [
            'param' => sanitize_text_field($param),
            'operator' => in_array($operator, ['==', '!=']) ? $operator : '==',
            'value' => sanitize_text_field($value)
        ];
        
        return $this;
    }
    
    // Validation
    public function validate() {
        $errors = [];
        
        // Title validation
        if (empty(trim($this->data['title']))) {
            $errors[] = __('Field group title is required', 'custom-fields-manager');
        }
        
        // Key validation
        if (empty($this->data['key'])) {
            $errors[] = __('Field group key is required', 'custom-fields-manager');
        } elseif (!preg_match('/^[a-z0-9_]+$/', $this->data['key'])) {
            $errors[] = __('Field group key can only contain lowercase letters, numbers, and underscores', 'custom-fields-manager');
        }
        
        // Fields validation
        if (empty($this->data['fields'])) {
            $errors[] = __('At least one field is required', 'custom-fields-manager');
        } else {
            $field_errors = $this->validate_fields();
            if (!empty($field_errors)) {
                $errors = array_merge($errors, $field_errors);
            }
        }
        
        // Location validation
        $location_errors = $this->validate_location();
        if (!empty($location_errors)) {
            $errors = array_merge($errors, $location_errors);
        }
        
        return empty($errors) ? true : $errors;
    }
    
    private function validate_fields() {
        $errors = [];
        $field_names = [];
        
        foreach ($this->data['fields'] as $index => $field) {
            $field_number = $index + 1;
            
            // Label validation
            if (empty(trim($field['label']))) {
                $errors[] = sprintf(__('Field %d: Label is required', 'custom-fields-manager'), $field_number);
            }
            
            // Name validation
            if (empty(trim($field['name']))) {
                $errors[] = sprintf(__('Field %d: Name is required', 'custom-fields-manager'), $field_number);
            } elseif (!preg_match('/^[a-z0-9_]+$/', $field['name'])) {
                $errors[] = sprintf(__('Field %d: Name can only contain lowercase letters, numbers, and underscores', 'custom-fields-manager'), $field_number);
            }
            
            // Check for duplicate field names
            if (in_array($field['name'], $field_names)) {
                $errors[] = sprintf(__('Field %d: Field name "%s" is already used', 'custom-fields-manager'), $field_number, $field['name']);
            }
            $field_names[] = $field['name'];
            
            // Type validation
            $allowed_types = ['text', 'textarea', 'number', 'email', 'url', 'select', 'checkbox', 'radio', 'date', 'media', 'wysiwyg', 'repeater'];
            if (!in_array($field['type'], $allowed_types)) {
                $errors[] = sprintf(__('Field %d: Invalid field type', 'custom-fields-manager'), $field_number);
            }
            
            // Repeater sub-fields validation
            if ($field['type'] === 'repeater' && !empty($field['sub_fields'])) {
                $sub_field_errors = $this->validate_sub_fields($field['sub_fields'], $field_number);
                if (!empty($sub_field_errors)) {
                    $errors = array_merge($errors, $sub_field_errors);
                }
            }
        }
        
        return $errors;
    }
    
    private function validate_sub_fields($sub_fields, $parent_field_number) {
        $errors = [];
        $sub_field_names = [];
        
        foreach ($sub_fields as $index => $sub_field) {
            $sub_field_number = $index + 1;
            
            if (empty(trim($sub_field['label']))) {
                $errors[] = sprintf(__('Field %d - Sub Field %d: Label is required', 'custom-fields-manager'), $parent_field_number, $sub_field_number);
            }
            
            if (empty(trim($sub_field['name']))) {
                $errors[] = sprintf(__('Field %d - Sub Field %d: Name is required', 'custom-fields-manager'), $parent_field_number, $sub_field_number);
            } elseif (!preg_match('/^[a-z0-9_]+$/', $sub_field['name'])) {
                $errors[] = sprintf(__('Field %d - Sub Field %d: Name can only contain lowercase letters, numbers, and underscores', 'custom-fields-manager'), $parent_field_number, $sub_field_number);
            }
            
            // Check for duplicate sub-field names
            if (in_array($sub_field['name'], $sub_field_names)) {
                $errors[] = sprintf(__('Field %d - Sub Field %d: Field name "%s" is already used', 'custom-fields-manager'), $parent_field_number, $sub_field_number, $sub_field['name']);
            }
            $sub_field_names[] = $sub_field['name'];
        }
        
        return $errors;
    }
    
    private function validate_location() {
        $errors = [];
        $allowed_params = ['post_type', 'post', 'post_template', 'post_category', 'post_status', 'page'];
        
        foreach ($this->data['location'] as $group_index => $rule_group) {
            foreach ($rule_group as $rule_index => $rule) {
                $rule_number = $group_index + 1 . '-' . ($rule_index + 1);
                
                if (empty($rule['param'])) {
                    $errors[] = sprintf(__('Location Rule %s: Parameter is required', 'custom-fields-manager'), $rule_number);
                } elseif (!in_array($rule['param'], $allowed_params)) {
                    $errors[] = sprintf(__('Location Rule %s: Invalid parameter', 'custom-fields-manager'), $rule_number);
                }
                
                if (empty($rule['operator'])) {
                    $errors[] = sprintf(__('Location Rule %s: Operator is required', 'custom-fields-manager'), $rule_number);
                } elseif (!in_array($rule['operator'], ['==', '!='])) {
                    $errors[] = sprintf(__('Location Rule %s: Invalid operator', 'custom-fields-manager'), $rule_number);
                }
                
                if (empty($rule['value'])) {
                    $errors[] = sprintf(__('Location Rule %s: Value is required', 'custom-fields-manager'), $rule_number);
                }
            }
        }
        
        return $errors;
    }
    
    // Sanitization
    private function sanitize_fields($fields) {
        if (!is_array($fields)) {
            return [];
        }
        
        $sanitized = [];
        
        foreach ($fields as $field) {
            $sanitized_field = $this->sanitize_single_field($field);
            if ($sanitized_field) {
                $sanitized[] = $sanitized_field;
            }
        }
        
        return $sanitized;
    }
    
    private function sanitize_single_field($field) {
        if (empty($field['label']) || empty($field['name'])) {
            return null;
        }
        
        $sanitized_field = [
            'key' => sanitize_key($field['key'] ?? 'field_' . uniqid()),
            'label' => sanitize_text_field($field['label']),
            'name' => sanitize_key($field['name']),
            'type' => sanitize_text_field($field['type'] ?? 'text'),
            'instructions' => sanitize_textarea_field($field['instructions'] ?? ''),
            'required' => isset($field['required']) ? 1 : 0,
            'options' => $this->sanitize_field_options($field['options'] ?? [], $field['type'] ?? 'text'),
        ];

        // Handle repeater sub-fields
        if (($field['type'] ?? '') === 'repeater' && isset($field['sub_fields']) && is_array($field['sub_fields'])) {
            $sanitized_field['sub_fields'] = $this->sanitize_sub_fields($field['sub_fields']);
        }

        return $sanitized_field;
    }
    
    private function sanitize_sub_fields($sub_fields) {
        $sanitized = [];
        foreach ($sub_fields as $sub_field) {
            if (empty($sub_field['label']) || empty($sub_field['name'])) {
                continue;
            }
            
            $sanitized_sub_field = [
                'key' => sanitize_key($sub_field['key'] ?? 'sub_field_' . uniqid()),
                'label' => sanitize_text_field($sub_field['label']),
                'name' => sanitize_key($sub_field['name']),
                'type' => sanitize_text_field($sub_field['type'] ?? 'text'),
                'required' => isset($sub_field['required']) ? 1 : 0,
                'options' => $this->sanitize_field_options($sub_field['options'] ?? [], $sub_field['type'] ?? 'text'),
            ];
            
            $sanitized[] = $sanitized_sub_field;
        }
        return $sanitized;
    }
    
    private function sanitize_field_options($options, $field_type) {
        if (!is_array($options)) {
            $options = [];
        }
        
        $sanitized = [];
        $option_config = $this->get_field_option_config($field_type);
        
        foreach ($option_config as $option_name => $config) {
            if (isset($options[$option_name])) {
                $sanitized[$option_name] = $this->sanitize_option_value($options[$option_name], $config);
            } else {
                $sanitized[$option_name] = $config['default'];
            }
        }
        
        return $sanitized;
    }
    
    private function sanitize_option_value($value, $config) {
        switch ($config['type']) {
            case 'text':
                return sanitize_text_field($value);
                
            case 'number':
                $number = floatval($value);
                if (isset($config['min']) && $number < $config['min']) {
                    return $config['min'];
                }
                if (isset($config['max']) && $number > $config['max']) {
                    return $config['max'];
                }
                return $number;
                
            case 'checkbox':
                return (bool) $value;
                
            case 'select':
                return in_array($value, $config['options']) ? $value : $config['default'];
                
            case 'textarea':
                return sanitize_textarea_field($value);
                
            case 'choices-editor':
                return sanitize_textarea_field($value);
                
            default:
                return $value;
        }
    }
    
    private function get_field_option_config($field_type) {
        $common_options = [
            'placeholder' => ['type' => 'text', 'default' => ''],
            'default_value' => ['type' => 'text', 'default' => ''],
            'class' => ['type' => 'text', 'default' => ''],
            'wrapper_class' => ['type' => 'text', 'default' => ''],
            'width' => ['type' => 'select', 'default' => '100', 'options' => ['25', '33', '50', '66', '75', '100']],
            'grid_template' => ['type' => 'select', 'default' => 'auto', 'options' => ['auto', '1fr', '1fr 1fr', '1fr 2fr', '2fr 1fr', '1fr 1fr 1fr', 'repeat(auto-fit, minmax(200px, 1fr))']],
            'grid_gap' => ['type' => 'select', 'default' => 'normal', 'options' => ['none', 'tight', 'normal', 'wide', 'custom']],
            'grid_custom_gap' => ['type' => 'text', 'default' => ''],
            'align_items' => ['type' => 'select', 'default' => 'stretch', 'options' => ['stretch', 'start', 'center', 'end']],
            'justify_content' => ['type' => 'select', 'default' => 'start', 'options' => ['start', 'center', 'end', 'space-between', 'space-around']],
        ];
        
        $type_specific = [
            'text' => [
                'maxlength' => ['type' => 'number', 'default' => '', 'min' => 0],
            ],
            'textarea' => [
                'rows' => ['type' => 'number', 'default' => 4, 'min' => 2, 'max' => 20],
                'maxlength' => ['type' => 'number', 'default' => '', 'min' => 0],
            ],
            'number' => [
                'min' => ['type' => 'number', 'default' => ''],
                'max' => ['type' => 'number', 'default' => ''],
                'step' => ['type' => 'number', 'default' => 1],
            ],
            'select' => [
                'choices' => ['type' => 'choices-editor', 'default' => ''],
                'multiple' => ['type' => 'checkbox', 'default' => false],
                'allow_null' => ['type' => 'checkbox', 'default' => false],
            ],
            'checkbox' => [
                'choices' => ['type' => 'choices-editor', 'default' => ''],
                'layout' => ['type' => 'select', 'default' => 'vertical', 'options' => ['vertical', 'horizontal']],
            ],
            'radio' => [
                'choices' => ['type' => 'choices-editor', 'default' => ''],
                'layout' => ['type' => 'select', 'default' => 'vertical', 'options' => ['vertical', 'horizontal']],
            ],
            'media' => [
                'allowed_types' => ['type' => 'text', 'default' => 'jpg,jpeg,png,gif,pdf,doc,docx'],
                'max_size' => ['type' => 'number', 'default' => 2, 'min' => 0],
                'multiple' => ['type' => 'checkbox', 'default' => false],
                'library' => ['type' => 'select', 'default' => 'all', 'options' => ['all', 'uploadedTo']],
            ],
            'wysiwyg' => [
                'toolbar' => ['type' => 'select', 'default' => 'full', 'options' => ['full', 'basic']],
                'media_upload' => ['type' => 'checkbox', 'default' => true],
                'height' => ['type' => 'number', 'default' => 300, 'min' => 100],
            ],
            'repeater' => [
                'min' => ['type' => 'number', 'default' => 0, 'min' => 0],
                'max' => ['type' => 'number', 'default' => '', 'min' => 0],
                'layout' => ['type' => 'select', 'default' => 'table', 'options' => ['table', 'block', 'grid']],
                'button_label' => ['type' => 'text', 'default' => 'Add Row'],
            ]
        ];
        
        return array_merge($common_options, $type_specific[$field_type] ?? []);
    }
    
    private function sanitize_location($location) {
        if (!is_array($location)) {
            return [[]];
        }
        
        $sanitized = [];
        
        foreach ($location as $group) {
            if (!is_array($group)) {
                continue;
            }
            
            $sanitized_group = [];
            
            foreach ($group as $rule) {
                if (empty($rule['param']) || empty($rule['operator']) || empty($rule['value'])) {
                    continue;
                }
                
                $sanitized_group[] = [
                    'param' => sanitize_text_field($rule['param']),
                    'operator' => in_array($rule['operator'], ['==', '!=']) ? $rule['operator'] : '==',
                    'value' => sanitize_text_field($rule['value']),
                ];
            }
            
            if (!empty($sanitized_group)) {
                $sanitized[] = $sanitized_group;
            }
        }
        
        return !empty($sanitized) ? $sanitized : [[]];
    }
    
    // Convert to array
    public function to_array() {
        return $this->data;
    }
    
    // Create from array
    public static function from_array($data) {
        return new self($data);
    }
    
    // Magic methods for property access
    public function __get($name) {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }
        return null;
    }
    
    public function __set($name, $value) {
        if (array_key_exists($name, $this->data)) {
            $setter = 'set_' . $name;
            if (method_exists($this, $setter)) {
                $this->$setter($value);
            } else {
                $this->data[$name] = $value;
            }
        }
    }
    
    public function __isset($name) {
        return isset($this->data[$name]);
    }
}