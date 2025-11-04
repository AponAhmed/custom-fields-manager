<?php
// includes/Meta_Box_Handler.php

class CFM_Meta_Box_Handler
{
    private static $instance = null;

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_meta_box'], 10, 2);
        add_action('admin_head', [$this, 'add_custom_styles']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_meta_box_scripts']);
        add_action('wp_ajax_cfm_get_attachment_url', [$this, 'ajax_get_attachment_url']);
    }

    public function enqueue_meta_box_scripts($hook)
    {
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }

        wp_enqueue_style(
            'cfm-meta-box',
            CFM_PLUGIN_URL . 'assets/css/meta-box.css',
            [],
            CFM_VERSION
        );

        // Enqueue WordPress media scripts
        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');

        wp_enqueue_script(
            'cfm-meta-box',
            CFM_PLUGIN_URL . 'assets/js/meta-box.js',
            ['jquery', 'jquery-ui-sortable'],
            CFM_VERSION,
            true
        );

        // Localize script for AJAX and translations
        wp_localize_script('cfm-meta-box', 'cfmMetaBox', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cfm_meta_box_nonce'),
            'media_frame_title' => __('Select or Upload Media', 'custom-fields-manager'),
            'media_frame_button' => __('Use this media', 'custom-fields-manager'),
            'i18n' => [
                'chooseFile' => __('Choose File', 'custom-fields-manager'),
                'chooseFiles' => __('Choose Files', 'custom-fields-manager'),
                'chooseImage' => __('Choose Image', 'custom-fields-manager'),
                'chooseImages' => __('Choose Images', 'custom-fields-manager'),
                'remove' => __('Remove', 'custom-fields-manager'),
                'view' => __('View', 'custom-fields-manager'),
                'addRow' => __('Add Row', 'custom-fields-manager'),
                'row' => __('Row', 'custom-fields-manager'),
                'newRow' => __('New Row', 'custom-fields-manager')
            ]
        ]);
    }

    public function add_meta_boxes()
    {
        global $post;

        if (!$post || !$post->ID) {
            return;
        }

        $context = [
            'post_id' => $post->ID,
            'post_type' => $post->post_type
        ];

        $field_groups = CFM_Field_Group_Repository::instance()->get_by_location($context);

        foreach ($field_groups as $field_group) {
            $position = $field_group->get_position();
            $style = $field_group->get_style();

            add_meta_box(
                'cfm-' . $field_group->get_key(),
                $field_group->get_title(),
                [$this, 'render_meta_box'],
                $post->post_type,
                $position,
                'default',
                [
                    'field_group' => $field_group,
                    'style' => $style
                ]
            );
        }
    }

    public function render_meta_box($post, $metabox)
    {
        $field_group = $metabox['args']['field_group'];
        $style = $metabox['args']['style'];
        $fields = $field_group->get_fields();

        $grid_styles = $this->get_field_group_grid_styles($field_group);

        echo '<div class="cfm-meta-box cfm-meta-box-' . esc_attr($style) . '" style="' . esc_attr($grid_styles) . '">';

        foreach ($fields as $field) {
            $this->render_field($post->ID, $field, $field_group);
        }

        echo '</div>';

        wp_nonce_field('cfm_save_meta_box', 'cfm_meta_box_nonce');
    }

    private function get_field_group_grid_styles($field_group)
    {
        $fields = $field_group->get_fields();
        $grid_options = $this->extract_grid_options($fields);

        $template = $grid_options['grid_template'] ?? 'auto';
        $gap = $grid_options['grid_gap'] ?? 'normal';
        $align_items = $grid_options['align_items'] ?? 'stretch';
        $justify_content = $grid_options['justify_content'] ?? 'start';

        $styles = [];

        // Grid template
        if ($template === 'auto') {
            $styles[] = 'grid-template-columns: repeat(auto-fit, minmax(400px, 1fr))';
        } else {
            $styles[] = 'grid-template-columns: ' . esc_attr($template);
        }

        // Grid gap
        $gap_sizes = [
            'none' => '0',
            'tight' => '8px',
            'normal' => '16px',
            'wide' => '24px'
        ];

        if ($gap === 'custom' && !empty($grid_options['grid_custom_gap'])) {
            $styles[] = 'gap: ' . esc_attr($grid_options['grid_custom_gap']);
        } else {
            $styles[] = 'gap: ' . ($gap_sizes[$gap] ?? '16px');
        }

        // Alignment
        $styles[] = 'align-items: ' . esc_attr($align_items);
        $styles[] = 'justify-content: ' . esc_attr($justify_content);

        return implode('; ', $styles);
    }

    private function extract_grid_options($fields)
    {
        foreach ($fields as $field) {
            $options = $field['options'] ?? [];
            if (!empty($options)) {
                foreach (['grid_template', 'grid_gap', 'grid_custom_gap', 'align_items', 'justify_content'] as $option) {
                    if (isset($options[$option])) {
                        return $options;
                    }
                }
            }
        }
        return [];
    }

    private function render_field($post_id, $field, $field_group)
    {
        $value = get_post_meta($post_id, $field['name'], true);
        $field_id = 'cfm-' . $field['key'];
        $options = $field['options'] ?? [];

        $field_classes = ['cfm-field-wrapper'];
        $field_styles = [];

        // Apply width
        if (!empty($options['width']) && is_numeric($options['width'])) {
            $field_styles[] = 'grid-column: span ' . $this->calculate_grid_span($options['width']);
        }

        // Apply custom CSS class
        if (!empty($options['class'])) {
            $field_classes[] = sanitize_html_class($options['class']);
        }

        // Add required class
        if (!empty($field['required'])) {
            $field_classes[] = 'cfm-field-required';
        }

        // Add wrapper class
        if (!empty($options['wrapper_class'])) {
            $field_classes[] = sanitize_html_class($options['wrapper_class']);
        }

        echo '<div class="' . esc_attr(implode(' ', $field_classes)) . '" style="' . esc_attr(implode('; ', $field_styles)) . '">';

        $this->render_field_label($field, $field_id);
        $this->render_field_input($post_id, $field, $value, $field_id);
        $this->render_field_instructions($field);

        echo '</div>';
    }

    private function calculate_grid_span($width)
    {
        if ($width <= 25) return 1;
        if ($width <= 50) return 2;
        if ($width <= 75) return 3;
        return 4; // 100%
    }

    private function render_field_label($field, $field_id)
    {
        $label = esc_html($field['label']);
        if (!empty($field['required'])) {
            $label .= ' <span class="cfm-required-asterisk">*</span>';
        }

        echo '<label for="' . esc_attr($field_id) . '" class="cfm-field-label">';
        echo $label;
        echo '</label>';
    }

    private function render_field_input($post_id, $field, $value, $field_id)
    {
        $field_name = 'cfm[' . esc_attr($field['name']) . ']';
        $options = $field['options'] ?? [];

        echo '<div class="cfm-field-input-wrapper">';

        switch ($field['type']) {
            case 'text':
            case 'email':
            case 'url':
                $this->render_text_input($field, $value, $field_id, $field_name, $options);
                break;

            case 'textarea':
                $this->render_textarea($field, $value, $field_id, $field_name, $options);
                break;

            case 'number':
                $this->render_number_input($field, $value, $field_id, $field_name, $options);
                break;

            case 'select':
                $this->render_select($field, $value, $field_id, $field_name, $options);
                break;

            case 'checkbox':
                $this->render_checkbox($field, $value, $field_name, $options);
                break;

            case 'radio':
                $this->render_radio($field, $value, $field_name, $options);
                break;

            case 'date':
                $this->render_date_input($field, $value, $field_id, $field_name, $options);
                break;

            case 'media':
                $this->render_media_input($post_id, $field, $value, $field_id, $field_name, $options);
                break;

            case 'wysiwyg':
                $this->render_wysiwyg($field, $value, $field_id, $field_name, $options);
                break;

            case 'repeater':
                $this->render_repeater_field($post_id, $field, $value);
                break;

            default:
                $this->render_text_input($field, $value, $field_id, $field_name, $options);
                break;
        }

        echo '</div>';
    }

    private function render_text_input($field, $value, $field_id, $field_name, $options)
    {
        $placeholder = !empty($options['placeholder']) ? esc_attr($options['placeholder']) : '';
        $maxlength = !empty($options['maxlength']) ? 'maxlength="' . esc_attr($options['maxlength']) . '"' : '';
        $default_value = !empty($options['default_value']) ? esc_attr($options['default_value']) : '';

        // Use default value if no value is set
        if (empty($value) && !empty($default_value)) {
            $value = $default_value;
        }

        echo '<input type="' . esc_attr($field['type']) . '" 
                     id="' . esc_attr($field_id) . '" 
                     name="' . $field_name . '" 
                     value="' . esc_attr($value) . '" 
                     class="cfm-field-input cfm-text-input" 
                     placeholder="' . $placeholder . '" 
                     ' . $maxlength . '>';
    }

    private function render_textarea($field, $value, $field_id, $field_name, $options)
    {
        $placeholder = !empty($options['placeholder']) ? esc_attr($options['placeholder']) : '';
        $rows = !empty($options['rows']) ? intval($options['rows']) : 4;
        $default_value = !empty($options['default_value']) ? esc_textarea($options['default_value']) : '';

        // Use default value if no value is set
        if (empty($value) && !empty($default_value)) {
            $value = $default_value;
        }

        echo '<textarea id="' . esc_attr($field_id) . '" 
                       name="' . $field_name . '" 
                       class="cfm-field-textarea" 
                       rows="' . $rows . '" 
                       placeholder="' . $placeholder . '">' . esc_textarea($value) . '</textarea>';
    }

    private function render_number_input($field, $value, $field_id, $field_name, $options)
    {
        $min = !empty($options['min']) ? 'min="' . esc_attr($options['min']) . '"' : '';
        $max = !empty($options['max']) ? 'max="' . esc_attr($options['max']) . '"' : '';
        $step = !empty($options['step']) ? 'step="' . esc_attr($options['step']) . '"' : 'step="1"';
        $placeholder = !empty($options['placeholder']) ? 'placeholder="' . esc_attr($options['placeholder']) . '"' : '';
        $default_value = !empty($options['default_value']) ? esc_attr($options['default_value']) : '';

        // Use default value if no value is set
        if (empty($value) && !empty($default_value)) {
            $value = $default_value;
        }

        echo '<div class="cfm-number-input-wrapper">';
        echo '<input type="number" 
                     id="' . esc_attr($field_id) . '" 
                     name="' . $field_name . '" 
                     value="' . esc_attr($value) . '" 
                     class="cfm-field-input cfm-number-input" 
                     ' . $min . ' ' . $max . ' ' . $step . ' ' . $placeholder . '>';

        echo '<div class="cfm-number-controls">';
        echo '<button type="button" class="cfm-number-btn cfm-number-up" tabindex="-1">+</button>';
        echo '<button type="button" class="cfm-number-btn cfm-number-down" tabindex="-1">-</button>';
        echo '</div>';
        echo '</div>';
    }

    private function render_date_input($field, $value, $field_id, $field_name, $options)
    {
        $placeholder = !empty($options['placeholder']) ? 'placeholder="' . esc_attr($options['placeholder']) . '"' : '';
        $default_value = !empty($options['default_value']) ? esc_attr($options['default_value']) : '';

        // Use default value if no value is set
        if (empty($value) && !empty($default_value)) {
            $value = $default_value;
        }

        echo '<div class="cfm-date-input-wrapper">';
        echo '<input type="date" 
                     id="' . esc_attr($field_id) . '" 
                     name="' . $field_name . '" 
                     value="' . esc_attr($value) . '" 
                     class="cfm-field-input cfm-date-input" 
                     ' . $placeholder . '>';
        echo '<span class="cfm-date-icon dashicons dashicons-calendar"></span>';
        echo '</div>';
    }

    private function render_select($field, $value, $field_id, $field_name, $options)
    {
        $choices = $this->parse_choices($options['choices'] ?? '');
        $multiple = !empty($options['multiple']) ? 'multiple' : '';
        $allow_null = !empty($options['allow_null']) ? true : false;
        $name = $multiple ? $field_name . '[]' : $field_name;

        echo '<select id="' . esc_attr($field_id) . '" 
                     name="' . $name . '" 
                     class="cfm-field-select" 
                     ' . $multiple . '>';

        if ($allow_null && empty($multiple)) {
            echo '<option value="">' . __('Select an option', 'custom-fields-manager') . '</option>';
        }

        foreach ($choices as $choice_value => $choice_label) {
            $selected = $multiple ?
                (is_array($value) && in_array($choice_value, $value) ? 'selected' : '') :
                selected($value, $choice_value, false);

            echo '<option value="' . esc_attr($choice_value) . '" ' . $selected . '>' . esc_html($choice_label) . '</option>';
        }
        echo '</select>';
    }

    private function render_checkbox($field, $value, $field_name, $options)
    {
        $choices = $this->parse_choices($options['choices'] ?? '');
        $layout = !empty($options['layout']) ? 'cfm-checkbox-layout-' . $options['layout'] : 'cfm-checkbox-layout-vertical';

        echo '<div class="cfm-checkbox-group ' . $layout . '">';

        if (empty($choices)) {
            // Single checkbox (boolean)
            $checked = !empty($value) ? 'checked' : '';
            echo '<label class="cfm-checkbox-option">';
            echo '<input type="checkbox" name="' . $field_name . '" value="1" ' . $checked . '>';
            echo '<span class="cfm-checkbox-custom"></span>';
            echo '<span class="cfm-checkbox-label">' . esc_html($field['label']) . '</span>';
            echo '</label>';
        } else {
            // Multiple checkboxes
            foreach ($choices as $choice_value => $choice_label) {
                $checked = is_array($value) && in_array($choice_value, $value) ? 'checked' : '';
                echo '<label class="cfm-checkbox-option">';
                echo '<input type="checkbox" name="' . $field_name . '[]" value="' . esc_attr($choice_value) . '" ' . $checked . '>';
                echo '<span class="cfm-checkbox-custom"></span>';
                echo '<span class="cfm-checkbox-label">' . esc_html($choice_label) . '</span>';
                echo '</label>';
            }
        }

        echo '</div>';
    }

    private function render_radio($field, $value, $field_name, $options)
    {
        $choices = $this->parse_choices($options['choices'] ?? '');
        $layout = !empty($options['layout']) ? 'cfm-radio-layout-' . $options['layout'] : 'cfm-radio-layout-vertical';

        echo '<div class="cfm-radio-group ' . $layout . '">';
        foreach ($choices as $choice_value => $choice_label) {
            $checked = checked($value, $choice_value, false);
            echo '<label class="cfm-radio-option">';
            echo '<input type="radio" name="' . $field_name . '" value="' . esc_attr($choice_value) . '" ' . $checked . '>';
            echo '<span class="cfm-radio-custom"></span>';
            echo '<span class="cfm-radio-label">' . esc_html($choice_label) . '</span>';
            echo '</label>';
        }
        echo '</div>';
    }

    private function render_media_input($post_id, $field, $value, $field_id, $field_name, $options)
    {
        $multiple = !empty($options['multiple']) ? 'multiple' : '';
        $allowed_types = !empty($options['allowed_types']) ? esc_attr($options['allowed_types']) : '';
        $library = !empty($options['library']) ? esc_attr($options['library']) : 'all';
        $current_media = !empty($value) ? (is_array($value) ? $value : [$value]) : [];

        echo '<div class="cfm-media-upload-wrapper" data-field-id="' . esc_attr($field_id) . '" data-multiple="' . esc_attr($multiple) . '">';
        echo '<input type="hidden" 
                     id="' . esc_attr($field_id) . '" 
                     name="' . $field_name . '" 
                     value="' . esc_attr(is_array($value) ? implode(',', $value) : $value) . '" 
                     class="cfm-media-hidden-input">';

        echo '<div class="cfm-media-upload-controls">';
        echo '<button type="button" class="cfm-btn-modern cfm-media-upload-btn" data-field="' . esc_attr($field_id) . '">';
        echo '<span class="dashicons dashicons-admin-media"></span>';
        echo $multiple ? __('Choose Files', 'custom-fields-manager') : __('Choose File', 'custom-fields-manager');
        echo '</button>';

        if (!empty($value)) {
            echo '<button type="button" class="cfm-btn-modern cfm-btn-danger cfm-media-remove-all-btn" data-field="' . esc_attr($field_id) . '">';
            echo '<span class="dashicons dashicons-no"></span>';
            echo __('Remove All', 'custom-fields-manager');
            echo '</button>';
        }
        echo '</div>';

        // Show current media preview
        if (!empty($current_media)) {
            echo '<div class="cfm-media-preview" id="' . esc_attr($field_id) . '-preview">';
            foreach ($current_media as $media_url) {
                if (!empty($media_url)) {
                    $this->render_single_media_preview($media_url, $field_id);
                }
            }
            echo '</div>';
        }
        echo '</div>';
    }

    private function render_single_media_preview($media_url, $field_id)
    {
        $media_id = attachment_url_to_postid($media_url);
        $is_image = wp_attachment_is_image($media_id);

        echo '<div class="cfm-media-preview-item" data-url="' . esc_attr($media_url) . '">';

        if ($is_image && $media_id) {
            $image_thumb = wp_get_attachment_image_url($media_id, 'thumbnail');
            echo '<img src="' . esc_url($image_thumb ?: $media_url) . '" alt="" class="cfm-media-thumbnail">';
        } else {
            echo '<div class="cfm-media-file-icon">';
            echo '<span class="dashicons dashicons-media-document"></span>';
            echo '<span class="cfm-media-filename">' . esc_html(basename($media_url)) . '</span>';
            echo '</div>';
        }

        echo '<div class="cfm-media-actions">';
        echo '<a href="' . esc_url($media_url) . '" target="_blank" class="cfm-btn-modern cfm-media-view">' . __('View', 'custom-fields-manager') . '</a>';
        echo '<button type="button" class="cfm-btn-modern cfm-btn-danger cfm-media-remove-btn" data-field="' . esc_attr($field_id) . '" data-url="' . esc_attr($media_url) . '">' . __('Remove', 'custom-fields-manager') . '</button>';
        echo '</div>';
        echo '</div>';
    }

    private function render_wysiwyg($field, $value, $field_id, $field_name, $options)
    {
        $editor_settings = [
            'textarea_name' => $field_name,
            'textarea_rows' => !empty($options['rows']) ? intval($options['rows']) : 10,
            'editor_height' => !empty($options['height']) ? intval($options['height']) : 300,
            'media_buttons' => !empty($options['media_upload']) ? true : false,
            'editor_class' => 'cfm-wysiwyg-editor',
            'tinymce' => [
                'wp_autoresize_on' => true,
                'toolbar1' => 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,wp_adv',
                'toolbar2' => 'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help'
            ],
            'quicktags' => true
        ];

        echo '<div class="cfm-wysiwyg-wrapper">';
        wp_editor($value, $field_id, $editor_settings);
        echo '</div>';
    }

    private function render_repeater_field($post_id, $field, $value)
    {
        $repeater_data = is_array($value) ? $value : [];
        $sub_fields = $field['sub_fields'] ?? [];
        $options = $field['options'] ?? [];

        echo '<div class="cfm-repeater-field" data-field-name="' . esc_attr($field['name']) . '">';
        echo '<div class="cfm-repeater-items">';

        foreach ($repeater_data as $index => $row) {
            echo '<div class="cfm-repeater-item">';
            echo '<div class="cfm-repeater-item-header">';
            echo '<span class="cfm-repeater-item-title">' . sprintf(__('Row %d', 'custom-fields-manager'), $index + 1) . '</span>';
            echo '<div class="cfm-repeater-item-actions">';
            echo '<button type="button" class="cfm-btn-modern cfm-move-repeater-row" title="' . __('Move', 'custom-fields-manager') . '">';
            echo '<span class="dashicons dashicons-move"></span>';
            echo '</button>';
            echo '<button type="button" class="cfm-btn-modern cfm-remove-repeater-row" title="' . __('Remove', 'custom-fields-manager') . '">';
            echo '<span class="dashicons dashicons-no"></span>';
            echo '</button>';
            echo '</div>';
            echo '</div>';

            echo '<div class="cfm-repeater-item-fields">';
            foreach ($sub_fields as $sub_field) {
                $sub_value = $row[$sub_field['name']] ?? '';
                $sub_field_id = 'cfm-' . $field['key'] . '-' . $index . '-' . $sub_field['key'];

                echo '<div class="cfm-repeater-sub-field">';
                echo '<label for="' . esc_attr($sub_field_id) . '" class="cfm-repeater-sub-field-label">';
                echo esc_html($sub_field['label']);
                if (!empty($sub_field['required'])) {
                    echo ' <span class="cfm-required-asterisk">*</span>';
                }
                echo '</label>';

                $sub_field_name = 'cfm[' . esc_attr($field['name']) . '][' . $index . '][' . esc_attr($sub_field['name']) . ']';

                $this->render_sub_field_input($sub_field, $sub_value, $sub_field_id, $sub_field_name);
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';

        // Add row button
        echo '<button type="button" data-field-name="' . esc_attr($field['name']) . '" class="cfm-btn-modern-primary cfm-add-repeater-row">';
        echo '<span class="dashicons dashicons-plus"></span>';
        echo esc_html($options['button_label'] ?? __('Add Row', 'custom-fields-manager'));
        echo '</button>';

        // Template for new rows
        echo '<template id="cfm-repeater-template-' . esc_attr($field['name']) . '">';
        echo '<div class="cfm-repeater-item">';
        echo '<div class="cfm-repeater-item-header">';
        echo '<span class="cfm-repeater-item-title">' . __('New Row', 'custom-fields-manager') . '</span>';
        echo '<div class="cfm-repeater-item-actions">';
        echo '<button type="button" class="cfm-btn-modern cfm-move-repeater-row" title="' . __('Move', 'custom-fields-manager') . '">';
        echo '<span class="dashicons dashicons-move"></span>';
        echo '</button>';
        echo '<button type="button" class="cfm-btn-modern cfm-remove-repeater-row" title="' . __('Remove', 'custom-fields-manager') . '">';
        echo '<span class="dashicons dashicons-no"></span>';
        echo '</button>';
        echo '</div>';
        echo '</div>';
        echo '<div class="cfm-repeater-item-fields">';
        foreach ($sub_fields as $sub_index => $sub_field) {
            echo '<div class="cfm-repeater-sub-field">';
            echo '<label class="cfm-repeater-sub-field-label">';
            echo esc_html($sub_field['label']);
            if (!empty($sub_field['required'])) {
                echo ' <span class="cfm-required-asterisk">*</span>';
            }
            echo '</label>';

            $sub_field_name = 'cfm[' . esc_attr($field['name']) . '][__INDEX__][' . esc_attr($sub_field['name']) . ']';
            $this->render_sub_field_input($sub_field, '', '', $sub_field_name, true);
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
        echo '</template>';

        echo '</div>';
    }

    private function render_sub_field_input($sub_field, $value, $field_id = '', $field_name = '', $is_template = false)
    {
        $options = $sub_field['options'] ?? [];

        switch ($sub_field['type']) {
            case 'text':
            case 'email':
            case 'url':
                $placeholder = !empty($options['placeholder']) ? esc_attr($options['placeholder']) : '';
                echo '<input type="' . esc_attr($sub_field['type']) . '" 
                             ' . (!$is_template ? 'id="' . esc_attr($field_id) . '"' : '') . '
                             name="' . $field_name . '" 
                             value="' . esc_attr($value) . '" 
                             class="cfm-field-input" 
                             placeholder="' . $placeholder . '">';
                break;

            case 'textarea':
                $placeholder = !empty($options['placeholder']) ? esc_attr($options['placeholder']) : '';
                $rows = !empty($options['rows']) ? intval($options['rows']) : 3;
                echo '<textarea ' . (!$is_template ? 'id="' . esc_attr($field_id) . '"' : '') . '
                               name="' . $field_name . '" 
                               class="cfm-field-textarea" 
                               rows="' . $rows . '" 
                               placeholder="' . $placeholder . '">' . esc_textarea($value) . '</textarea>';
                break;

            case 'number':
                $min = !empty($options['min']) ? 'min="' . esc_attr($options['min']) . '"' : '';
                $max = !empty($options['max']) ? 'max="' . esc_attr($options['max']) . '"' : '';
                $placeholder = !empty($options['placeholder']) ? 'placeholder="' . esc_attr($options['placeholder']) . '"' : '';
                echo '<input type="number" 
                             ' . (!$is_template ? 'id="' . esc_attr($field_id) . '"' : '') . '
                             name="' . $field_name . '" 
                             value="' . esc_attr($value) . '" 
                             class="cfm-field-input" 
                             ' . $min . ' ' . $max . ' ' . $placeholder . '>';
                break;

            case 'select':
                $choices = $this->parse_choices($options['choices'] ?? '');
                echo '<select ' . (!$is_template ? 'id="' . esc_attr($field_id) . '"' : '') . '
                             name="' . $field_name . '" 
                             class="cfm-field-select">';
                echo '<option value="">' . __('Select an option', 'custom-fields-manager') . '</option>';
                foreach ($choices as $choice_value => $choice_label) {
                    $selected = !$is_template ? selected($value, $choice_value, false) : '';
                    echo '<option value="' . esc_attr($choice_value) . '" ' . $selected . '>' . esc_html($choice_label) . '</option>';
                }
                echo '</select>';
                break;

            case 'media':
                $media_field_id = $field_id . '-media';
                echo '<div class="cfm-media-upload-wrapper" data-field-id="' . esc_attr($media_field_id) . '">';
                echo '<input type="hidden" 
                             name="' . $field_name . '" 
                             value="' . esc_attr($value) . '" 
                             class="cfm-media-hidden-input">';
                echo '<button type="button" class="cfm-btn-modern cfm-media-upload-btn" data-field="' . esc_attr($media_field_id) . '">';
                echo '<span class="dashicons dashicons-admin-media"></span>';
                echo __('Choose File', 'custom-fields-manager');
                echo '</button>';

                if (!empty($value)) {
                    echo '<div class="cfm-media-preview">';
                    $this->render_single_media_preview($value, $media_field_id);
                    echo '</div>';
                }
                echo '</div>';
                break;

            default:
                $placeholder = !empty($options['placeholder']) ? esc_attr($options['placeholder']) : '';
                echo '<input type="text" 
                             ' . (!$is_template ? 'id="' . esc_attr($field_id) . '"' : '') . '
                             name="' . $field_name . '" 
                             value="' . esc_attr($value) . '" 
                             class="cfm-field-input" 
                             placeholder="' . $placeholder . '">';
                break;
        }
    }

    private function render_field_instructions($field)
    {
        if (!empty($field['instructions'])) {
            echo '<p class="cfm-field-instructions">' . esc_html($field['instructions']) . '</p>';
        }
    }

    private function parse_choices($choices_text)
    {
        $choices = [];

        if (empty($choices_text)) {
            return $choices;
        }

        $lines = explode("\n", $choices_text);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (strpos($line, ':') !== false) {
                list($value, $label) = explode(':', $line, 2);
                $choices[trim($value)] = trim($label);
            } else {
                $choices[$line] = $line;
            }
        }

        return $choices;
    }

    public function ajax_get_attachment_url()
    {
        check_ajax_referer('cfm_meta_box_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_die(-1);
        }

        $attachment_id = intval($_POST['attachment_id']);
        $url = wp_get_attachment_url($attachment_id);

        if ($url) {
            wp_send_json_success(['url' => $url]);
        } else {
            wp_send_json_error(['message' => __('Failed to get attachment URL', 'custom-fields-manager')]);
        }
    }

    public function save_meta_box($post_id)
    {
        // Check nonce
        if (!isset($_POST['cfm_meta_box_nonce']) || !wp_verify_nonce($_POST['cfm_meta_box_nonce'], 'cfm_save_meta_box')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save regular fields
        if (isset($_POST['cfm']) && is_array($_POST['cfm'])) {
            foreach ($_POST['cfm'] as $field_name => $field_value) {
                // Sanitize field value based on field type
                $field_value = $this->sanitize_field_value($field_name, $field_value);

                // Handle repeater fields - filter out empty rows
                if (is_array($field_value)) {
                    $field_value = array_values(array_filter($field_value, function ($row) {
                        return !empty(array_filter($row, function ($value) {
                            return $value !== '' && $value !== null;
                        }));
                    }));
                }

                if (empty($field_value)) {
                    delete_post_meta($post_id, $field_name);
                } else {
                    update_post_meta($post_id, $field_name, $field_value);
                }
            }
        }
    }

    private function sanitize_field_value($field_name, $value)
    {
        if (is_array($value)) {
            return array_map([$this, 'sanitize_text_field'], $value);
        }

        return sanitize_text_field($value);
    }

    public function add_custom_styles()
    {
        echo '<style>
            .cfm-meta-box {
                display: grid;
                gap: 20px;
            }
            
            .cfm-field-wrapper {
                margin-bottom: 0;
            }
            
            .cfm-field-label {
                display: block;
                font-weight: 600;
                font-size: 14px;
                margin-bottom: 8px;
            }
            
            .cfm-required-asterisk {
                color: #d63638;
            }
            
            .cfm-field-instructions {
                margin: 8px 0 0 0;
                font-size: 12px;
                color: #666;
                font-style: italic;
            }

            /* Fix for WYSIWYG editor in meta boxes */
            .cfm-wysiwyg-wrapper .wp-editor-container {
                border: 1px solid #ddd;
            }
            
            .cfm-wysiwyg-wrapper .wp-editor-tabs {
                background: #f7f7f7;
            }
            
            /* Fix for media modal */
            .media-modal {
                z-index: 160000 !important;
            }
            
            .media-modal-backdrop {
                z-index: 159999 !important;
            }
        </style>';
    }
}
