<?php
// includes/Field_Renderer.php

class CFM_Field_Renderer {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_shortcode('cfm_field', [$this, 'render_field_shortcode']);
        add_shortcode('cfm_repeater', [$this, 'render_repeater_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        
        // Template functions
        add_action('cfm_field', [$this, 'render_field_action'], 10, 2);
        add_action('cfm_repeater', [$this, 'render_repeater_action'], 10, 3);
    }
    
    /**
     * Render field via shortcode [cfm_field name="field_name" format="html"]
     */
    public function render_field_shortcode($atts) {
        $atts = shortcode_atts([
            'name' => '',
            'post_id' => get_the_ID(),
            'default' => '',
            'format' => 'raw',
            'before' => '',
            'after' => '',
            'class' => '',
            'wrapper' => 'div',
            'show_label' => false,
            'label_class' => 'cfm-field-label'
        ], $atts, 'cfm_field');
        
        if (empty($atts['name'])) {
            return '';
        }
        
        $value = $this->get_field_value($atts['name'], $atts['post_id'], $atts['default']);
        $formatted_value = $this->format_value($value, $atts['format'], $atts);
        
        if (empty($formatted_value)) {
            return '';
        }
        
        $output = '';
        
        // Add label if requested
        if ($atts['show_label'] && $atts['show_label'] !== 'false') {
            $field_label = $this->get_field_label($atts['name'], $atts['post_id']);
            if ($field_label) {
                $output .= '<span class="' . esc_attr($atts['label_class']) . '">' . esc_html($field_label) . ': </span>';
            }
        }
        
        $output .= $atts['before'] . $formatted_value . $atts['after'];
        
        // Add wrapper
        if (!empty($atts['wrapper']) && $atts['wrapper'] !== 'none') {
            $wrapper_class = !empty($atts['class']) ? ' class="' . esc_attr($atts['class']) . '"' : '';
            $output = '<' . esc_attr($atts['wrapper']) . $wrapper_class . '>' . $output . '</' . esc_attr($atts['wrapper']) . '>';
        } elseif (!empty($atts['class'])) {
            $output = '<span class="' . esc_attr($atts['class']) . '">' . $output . '</span>';
        }
        
        return $output;
    }
    
    /**
     * Render repeater field via shortcode [cfm_repeater name="repeater_name"]
     */
    public function render_repeater_shortcode($atts) {
        $atts = shortcode_atts([
            'name' => '',
            'post_id' => get_the_ID(),
            'layout' => 'list',
            'wrapper_class' => 'cfm-repeater',
            'item_class' => 'cfm-repeater-item',
            'template' => '',
            'empty_message' => __('No items found', 'custom-fields-manager')
        ], $atts, 'cfm_repeater');
        
        if (empty($atts['name'])) {
            return '';
        }
        
        return $this->render_repeater_field($atts['name'], $atts['post_id'], $atts);
    }
    
    /**
     * Render field via action
     */
    public function render_field_action($field_name, $args = []) {
        echo $this->render_field($field_name, $args);
    }
    
    /**
     * Render repeater via action
     */
    public function render_repeater_action($field_name, $callback = null, $args = []) {
        echo $this->render_repeater_field($field_name, null, $args, $callback);
    }
    
    /**
     * Main field renderer method
     */
    public function render_field($field_name, $args = []) {
        $args = wp_parse_args($args, [
            'post_id' => null,
            'default' => '',
            'format' => 'raw',
            'before' => '',
            'after' => '',
            'class' => '',
            'wrapper' => 'div',
            'show_label' => false,
            'label_class' => 'cfm-field-label'
        ]);
        
        $value = $this->get_field_value($field_name, $args['post_id'], $args['default']);
        $formatted_value = $this->format_value($value, $args['format'], $args);
        
        if (empty($formatted_value)) {
            return '';
        }
        
        $output = '';
        
        // Add label if requested
        if ($args['show_label']) {
            $field_label = $this->get_field_label($field_name, $args['post_id']);
            if ($field_label) {
                $output .= '<span class="' . esc_attr($args['label_class']) . '">' . esc_html($field_label) . ': </span>';
            }
        }
        
        $output .= $args['before'] . $formatted_value . $args['after'];
        
        // Add wrapper
        if (!empty($args['wrapper']) && $args['wrapper'] !== 'none') {
            $wrapper_class = !empty($args['class']) ? ' class="' . esc_attr($args['class']) . '"' : '';
            $output = '<' . esc_attr($args['wrapper']) . $wrapper_class . '>' . $output . '</' . esc_attr($args['wrapper']) . '>';
        } elseif (!empty($args['class'])) {
            $output = '<span class="' . esc_attr($args['class']) . '">' . $output . '</span>';
        }
        
        return $output;
    }
    
    /**
     * Get field value from database
     */
    public function get_field_value($field_name, $post_id = null, $default = '') {
        if (null === $post_id) {
            $post_id = get_the_ID();
        }
        
        if (!$post_id) {
            return $default;
        }
        
        $value = get_post_meta($post_id, $field_name, true);
        
        // Handle empty values
        if ('' === $value || false === $value || null === $value) {
            return $default;
        }
        
        return $value;
    }
    
    /**
     * Get field label from field group
     */
    public function get_field_label($field_name, $post_id = null) {
        if (null === $post_id) {
            $post_id = get_the_ID();
        }
        
        if (!$post_id) {
            return '';
        }
        
        $repository = CFM_Field_Group_Repository::instance();
        $context = [
            'post_id' => $post_id,
            'post_type' => get_post_type($post_id)
        ];
        
        $field_groups = $repository->get_by_location($context);
        
        foreach ($field_groups as $field_group) {
            $fields = $field_group->get_fields();
            foreach ($fields as $field) {
                if ($field['name'] === $field_name) {
                    return $field['label'] ?? '';
                }
            }
        }
        
        return '';
    }
    
    /**
     * Format field value based on format type
     */
    public function format_value($value, $format = 'raw', $args = []) {
        if (empty($value)) {
            return '';
        }
        
        switch ($format) {
            case 'html':
                return wp_kses_post($value);
                
            case 'url':
                return esc_url($value);
                
            case 'email':
                return '<a href="mailto:' . antispambot($value) . '">' . antispambot($value) . '</a>';
                
            case 'image':
                return $this->format_image($value, $args);
                
            case 'file':
                return $this->format_file($value, $args);
                
            case 'date':
                return $this->format_date($value, $args);
                
            case 'array':
                return $this->format_array($value);
                
            case 'json':
                return wp_json_encode($value);
                
            case 'raw':
            default:
                return esc_html($value);
        }
    }
    
    /**
     * Format image field
     */
    private function format_image($value, $args = []) {
        $args = wp_parse_args($args, [
            'size' => 'full',
            'class' => 'cfm-image',
            'alt' => '',
            'lazy' => true
        ]);
        
        if (is_numeric($value)) {
            $image_url = wp_get_attachment_image_url($value, $args['size']);
            if ($image_url) {
                $alt = $args['alt'] ?: get_post_meta($value, '_wp_attachment_image_alt', true);
                $class = $args['class'];
                $loading = $args['lazy'] ? 'loading="lazy"' : '';
                return '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($alt) . '" class="' . esc_attr($class) . '" ' . $loading . '>';
            }
        } elseif (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
            $class = $args['class'];
            $loading = $args['lazy'] ? 'loading="lazy"' : '';
            return '<img src="' . esc_url($value) . '" alt="' . esc_attr($args['alt']) . '" class="' . esc_attr($class) . '" ' . $loading . '>';
        } elseif (is_array($value)) {
            // Handle multiple images
            $output = [];
            foreach ($value as $image) {
                $output[] = $this->format_image($image, $args);
            }
            return implode('', $output);
        }
        
        return esc_html($value);
    }
    
    /**
     * Format file field
     */
    private function format_file($value, $args = []) {
        $args = wp_parse_args($args, [
            'class' => 'cfm-file',
            'download' => true,
            'target' => '_blank'
        ]);
        
        if (is_numeric($value)) {
            $file_url = wp_get_attachment_url($value);
            $file_name = get_the_title($value);
            if ($file_url) {
                $download = $args['download'] ? 'download' : '';
                return '<a href="' . esc_url($file_url) . '" class="' . esc_attr($args['class']) . '" target="' . esc_attr($args['target']) . '" ' . $download . '>' . esc_html($file_name) . '</a>';
            }
        } elseif (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
            $file_name = basename($value);
            $download = $args['download'] ? 'download' : '';
            return '<a href="' . esc_url($value) . '" class="' . esc_attr($args['class']) . '" target="' . esc_attr($args['target']) . '" ' . $download . '>' . esc_html($file_name) . '</a>';
        } elseif (is_array($value)) {
            // Handle multiple files
            $output = [];
            foreach ($value as $file) {
                $output[] = $this->format_file($file, $args);
            }
            return implode(', ', $output);
        }
        
        return esc_html($value);
    }
    
    /**
     * Format date field
     */
    private function format_date($value, $args = []) {
        $args = wp_parse_args($args, [
            'format' => get_option('date_format'),
            'class' => 'cfm-date'
        ]);
        
        if (is_numeric($value)) {
            $formatted = date_i18n($args['format'], $value);
        } else {
            $timestamp = strtotime($value);
            $formatted = $timestamp ? date_i18n($args['format'], $timestamp) : $value;
        }
        
        if (!empty($args['class'])) {
            return '<span class="' . esc_attr($args['class']) . '">' . esc_html($formatted) . '</span>';
        }
        
        return esc_html($formatted);
    }
    
    /**
     * Format array field
     */
    private function format_array($value) {
        if (is_array($value)) {
            return implode(', ', array_map('esc_html', $value));
        }
        
        return esc_html($value);
    }
    
    /**
     * Render repeater field
     */
    public function render_repeater_field($field_name, $post_id = null, $args = [], $callback = null) {
        if (null === $post_id) {
            $post_id = get_the_ID();
        }
        
        $repeater_data = $this->get_field_value($field_name, $post_id, []);
        
        if (empty($repeater_data) || !is_array($repeater_data)) {
            $empty_message = $args['empty_message'] ?? __('No items found', 'custom-fields-manager');
            return '<div class="cfm-repeater-empty">' . esc_html($empty_message) . '</div>';
        }
        
        $args = wp_parse_args($args, [
            'layout' => 'list',
            'wrapper_class' => 'cfm-repeater',
            'item_class' => 'cfm-repeater-item',
            'template' => ''
        ]);
        
        $output = '<div class="' . esc_attr($args['wrapper_class']) . ' cfm-repeater-layout-' . esc_attr($args['layout']) . '">';
        
        foreach ($repeater_data as $index => $row) {
            $output .= '<div class="' . esc_attr($args['item_class']) . '">';
            
            if (is_callable($callback)) {
                $output .= call_user_func($callback, $row, $index);
            } elseif (!empty($args['template'])) {
                $output .= $this->render_repeater_template($row, $args['template']);
            } else {
                $output .= $this->render_repeater_row_default($row);
            }
            
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Render repeater row using template
     */
    private function render_repeater_template($row, $template) {
        foreach ($row as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $template = str_replace("{{$key}}", esc_html($value), $template);
        }
        return $template;
    }
    
    /**
     * Default repeater row renderer
     */
    private function render_repeater_row_default($row) {
        $output = '';
        
        foreach ($row as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $output .= '<div class="cfm-repeater-field cfm-repeater-field-' . esc_attr($key) . '">';
            $output .= '<span class="cfm-repeater-field-label">' . esc_html($key) . ':</span> ';
            $output .= '<span class="cfm-repeater-field-value">' . esc_html($value) . '</span>';
            $output .= '</div>';
        }
        
        return $output;
    }
    
    /**
     * Check if field exists and has value
     */
    public function has_field($field_name, $post_id = null) {
        $value = $this->get_field_value($field_name, $post_id);
        return !empty($value);
    }
    
    /**
     * Get all field values for a post
     */
    public function get_all_fields($post_id = null) {
        if (null === $post_id) {
            $post_id = get_the_ID();
        }
        
        if (!$post_id) {
            return [];
        }
        
        $all_meta = get_post_meta($post_id);
        $cfm_fields = [];
        
        // Get active field groups for this post
        $repository = CFM_Field_Group_Repository::instance();
        $context = [
            'post_id' => $post_id,
            'post_type' => get_post_type($post_id)
        ];
        
        $field_groups = $repository->get_by_location($context);
        
        // Collect all field names from active field groups
        $valid_fields = [];
        foreach ($field_groups as $field_group) {
            $fields = $field_group->get_fields();
            foreach ($fields as $field) {
                $valid_fields[] = $field['name'];
            }
        }
        
        // Filter meta to only include CFM fields
        foreach ($all_meta as $meta_key => $meta_value) {
            if (in_array($meta_key, $valid_fields)) {
                $cfm_fields[$meta_key] = maybe_unserialize($meta_value[0]);
            }
        }
        
        return $cfm_fields;
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only enqueue if shortcodes are used on the page
        global $post;
        if (is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'cfm_field') || has_shortcode($post->post_content, 'cfm_repeater'))) {
            wp_enqueue_style(
                'cfm-frontend',
                CFM_PLUGIN_URL . 'assets/css/frontend.css',
                [],
                CFM_VERSION
            );
        }
    }
    
    /**
     * Template function to display field
     */
    public static function field($field_name, $args = []) {
        $renderer = self::instance();
        echo $renderer->render_field($field_name, $args);
    }
    
    /**
     * Template function to get field value
     */
    public static function get($field_name, $post_id = null, $default = '') {
        $renderer = self::instance();
        return $renderer->get_field_value($field_name, $post_id, $default);
    }
    
    /**
     * Template function to check if field exists
     */
    public static function has($field_name, $post_id = null) {
        $renderer = self::instance();
        return $renderer->has_field($field_name, $post_id);
    }
    
    /**
     * Template function to display repeater
     */
    public static function repeater($field_name, $callback = null, $args = []) {
        $renderer = self::instance();
        echo $renderer->render_repeater_field($field_name, null, $args, $callback);
    }
}