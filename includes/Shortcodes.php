<?php
// includes/Shortcodes.php

class CFM_Shortcodes
{
    private static $instance = null;
    private $data_utility;

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->data_utility = CFM_Data_Utility::instance();
        add_action('init', [$this, 'register_shortcodes']);
    }

    public function register_shortcodes()
    {
        // Main shortcode for all data types
        add_shortcode('cfm', [$this, 'handle_shortcode']);

        // Aliases for specific use cases
        add_shortcode('cfm_field', [$this, 'handle_field_shortcode']);
        add_shortcode('cfm_repeater', [$this, 'handle_repeater_shortcode']);
        add_shortcode('cfm_group', [$this, 'handle_group_shortcode']);
        add_shortcode('cfm_all', [$this, 'handle_all_shortcode']);
    }

    /**
     * Main shortcode handler - supports all data types
     * 
     * Usage:
     * [cfm type="field" group="product_details" field="price" post_id="123"]
     * [cfm type="repeater_row" group="product_details" repeater="features" row="0"]
     * [cfm type="repeater_sub" group="product_details" repeater="features" row="0" sub_field="title"]
     * [cfm type="group" group="product_details"]
     * [cfm type="all"]
     * 
     * @param array $atts Shortcode attributes
     * @param string $content Shortcode content
     * @return string
     */
    public function handle_shortcode($atts, $content = null)
    {
        $atts = shortcode_atts([
            'type' => 'field', // field, repeater_row, repeater_sub, group, all
            'group' => '',
            'field' => '',
            'repeater' => '',
            'row' => '0',
            'sub_field' => '',
            'post_id' => '',
            'default' => '',
            'format' => 'raw', // raw, html, url, image, date
            'date_format' => get_option('date_format'),
            'image_size' => 'thumbnail',
            'wrapper' => 'span', // span, div, p, none
            'class' => '',
            'empty_message' => ''
        ], $atts, 'cfm');

        // Get post ID - current post by default
        $post_id = $this->get_post_id($atts['post_id']);

        if (!$post_id) {
            return $this->wrap_output('Post not found', $atts);
        }

        try {
            switch ($atts['type']) {
                case 'field':
                    return $this->render_field($post_id, $atts);

                case 'repeater_row':
                    return $this->render_repeater_row($post_id, $atts);

                case 'repeater_sub':
                    return $this->render_repeater_sub_field($post_id, $atts);

                case 'group':
                    return $this->render_group($post_id, $atts);

                case 'all':
                    return $this->render_all($post_id, $atts);

                default:
                    return $this->wrap_output('Invalid type specified', $atts);
            }
        } catch (Exception $e) {
            return $this->wrap_output($atts['empty_message'] ?: 'Data not available', $atts);
        }
    }

    /**
     * Field-specific shortcode
     * 
     * @param array $atts
     * @return string
     */
    public function handle_field_shortcode($atts)
    {
        $atts['type'] = 'field';
        return $this->handle_shortcode($atts);
    }

    /**
     * Repeater-specific shortcode
     * 
     * @param array $atts
     * @return string
     */
    public function handle_repeater_shortcode($atts)
    {
        $atts = shortcode_atts([
            'group' => '',
            'repeater' => '',
            'row' => '0',
            'sub_field' => '',
            'post_id' => '',
            'default' => '',
            'format' => 'raw',
            'wrapper' => 'span',
            'class' => '',
            'empty_message' => ''
        ], $atts, 'cfm_repeater');

        if ($atts['sub_field']) {
            $atts['type'] = 'repeater_sub';
        } else {
            $atts['type'] = 'repeater_row';
        }

        return $this->handle_shortcode($atts);
    }

    /**
     * Group-specific shortcode
     * 
     * @param array $atts
     * @return string
     */
    public function handle_group_shortcode($atts)
    {
        $atts['type'] = 'group';
        return $this->handle_shortcode($atts);
    }

    /**
     * All data shortcode
     * 
     * @param array $atts
     * @return string
     */
    public function handle_all_shortcode($atts)
    {
        $atts['type'] = 'all';
        return $this->handle_shortcode($atts);
    }

    /**
     * Render single field
     */
    private function render_field($post_id, $atts)
    {
        if (empty($atts['group']) || empty($atts['field'])) {
            return $this->wrap_output('Group and field parameters required', $atts);
        }

        $value = $this->data_utility->get_field_value($post_id, $atts['group'], $atts['field']);
        
        if (empty($value) && $atts['default'] !== '') {
            $value = $atts['default'];
        }

        return $this->format_output($value, $atts);
    }

    /**
     * Render repeater row
     */
    private function render_repeater_row($post_id, $atts)
    {
        if (empty($atts['group']) || empty($atts['repeater'])) {
            return $this->wrap_output('Group and repeater parameters required', $atts);
        }

        $row_data = $this->data_utility->get_repeater_row(
            $post_id,
            $atts['group'],
            $atts['repeater'],
            intval($atts['row'])
        );

        if (!$row_data) {
            return $this->wrap_output($atts['empty_message'] ?: 'Row not found', $atts);
        }

        // If it's an array, return as JSON or formatted list
        if (is_array($row_data)) {
            if ($atts['format'] === 'json') {
                return $this->wrap_output(json_encode($row_data), $atts);
            }
            
            $output = '<ul class="cfm-repeater-row">';
            foreach ($row_data as $key => $value) {
                $output .= '<li><strong>' . esc_html($key) . ':</strong> ' . esc_html($value) . '</li>';
            }
            $output .= '</ul>';
            return $output;
        }

        return $this->wrap_output($row_data, $atts);
    }

    /**
     * Render repeater sub field
     */
    private function render_repeater_sub_field($post_id, $atts)
    {
        if (empty($atts['group']) || empty($atts['repeater']) || empty($atts['sub_field'])) {
            return $this->wrap_output('Group, repeater and sub_field parameters required', $atts);
        }

        $value = $this->data_utility->get_repeater_sub_field(
            $post_id,
            $atts['group'],
            $atts['repeater'],
            intval($atts['row']),
            $atts['sub_field']
        );

        if (empty($value) && $atts['default'] !== '') {
            $value = $atts['default'];
        }

        return $this->format_output($value, $atts);
    }

    /**
     * Render entire field group
     */
    private function render_group($post_id, $atts)
    {
        if (empty($atts['group'])) {
            return $this->wrap_output('Group parameter required', $atts);
        }

        $group_data = $this->data_utility->get_field_group_data($post_id, $atts['group']);

        if (empty($group_data)) {
            return $this->wrap_output($atts['empty_message'] ?: 'Group data not found', $atts);
        }

        if ($atts['format'] === 'json') {
            return $this->wrap_output(json_encode($group_data), $atts);
        }

        $output = '<div class="cfm-field-group ' . esc_attr($atts['class']) . '">';
        foreach ($group_data as $field_name => $field_value) {
            $output .= '<div class="cfm-group-field">';
            $output .= '<strong class="cfm-field-name">' . esc_html($field_name) . ':</strong> ';
            
            if (is_array($field_value)) {
                $output .= '<ul class="cfm-field-array">';
                foreach ($field_value as $item) {
                    if (is_array($item)) {
                        $output .= '<li>' . esc_html(implode(', ', $item)) . '</li>';
                    } else {
                        $output .= '<li>' . esc_html($item) . '</li>';
                    }
                }
                $output .= '</ul>';
            } else {
                $output .= '<span class="cfm-field-value">' . esc_html($field_value) . '</span>';
            }
            
            $output .= '</div>';
        }
        $output .= '</div>';

        return $output;
    }

    /**
     * Render all CFM data
     */
    private function render_all($post_id, $atts)
    {
        $all_data = $this->data_utility->get_all_data($post_id);

        if (empty($all_data)) {
            return $this->wrap_output($atts['empty_message'] ?: 'No CFM data found', $atts);
        }

        if ($atts['format'] === 'json') {
            return $this->wrap_output(json_encode($all_data), $atts);
        }

        $output = '<div class="cfm-all-data ' . esc_attr($atts['class']) . '">';
        foreach ($all_data as $group_key => $group_data) {
            $output .= '<div class="cfm-group">';
            $output .= '<h4 class="cfm-group-title">' . esc_html($group_key) . '</h4>';
            $output .= '<div class="cfm-group-content">';
            
            foreach ($group_data as $field_name => $field_value) {
                $output .= '<div class="cfm-field">';
                $output .= '<strong>' . esc_html($field_name) . ':</strong> ';
                
                if (is_array($field_value)) {
                    $output .= '<ul>';
                    foreach ($field_value as $item) {
                        if (is_array($item)) {
                            $output .= '<li>' . esc_html(json_encode($item)) . '</li>';
                        } else {
                            $output .= '<li>' . esc_html($item) . '</li>';
                        }
                    }
                    $output .= '</ul>';
                } else {
                    $output .= esc_html($field_value);
                }
                
                $output .= '</div>';
            }
            
            $output .= '</div></div>';
        }
        $output .= '</div>';

        return $output;
    }

    /**
     * Format output based on format attribute
     */
    private function format_output($value, $atts)
    {
        if (empty($value)) {
            return $this->wrap_output($atts['empty_message'] ?: '', $atts);
        }

        switch ($atts['format']) {
            case 'html':
                $value = wp_kses_post($value);
                break;

            case 'url':
                $value = esc_url($value);
                break;

            case 'image':
                if (filter_var($value, FILTER_VALIDATE_URL)) {
                    $image_size = $atts['image_size'];
                    $attachment_id = attachment_url_to_postid($value);
                    
                    if ($attachment_id) {
                        $image_src = wp_get_attachment_image_src($attachment_id, $image_size);
                        if ($image_src) {
                            $value = '<img src="' . esc_url($image_src[0]) . '" alt="" class="cfm-image">';
                        } else {
                            $value = '<img src="' . esc_url($value) . '" alt="" class="cfm-image">';
                        }
                    } else {
                        $value = '<img src="' . esc_url($value) . '" alt="" class="cfm-image">';
                    }
                }
                break;

            case 'date':
                if (strtotime($value)) {
                    $value = date($atts['date_format'], strtotime($value));
                }
                break;

            case 'raw':
            default:
                $value = esc_html($value);
                break;
        }

        return $this->wrap_output($value, $atts);
    }

    /**
     * Wrap output in HTML wrapper
     */
    private function wrap_output($content, $atts)
    {
        if (empty($content)) {
            return '';
        }

        if ($atts['wrapper'] === 'none') {
            return $content;
        }

        $class = !empty($atts['class']) ? ' class="' . esc_attr($atts['class']) . '"' : '';
        
        return '<' . $atts['wrapper'] . $class . '>' . $content . '</' . $atts['wrapper'] . '>';
    }

    /**
     * Get post ID from various sources
     */
    private function get_post_id($specified_id = '')
    {
        // Use specified ID if provided
        if (!empty($specified_id)) {
            return intval($specified_id);
        }

        // Use current post ID
        global $post;
        if ($post && $post->ID) {
            return $post->ID;
        }

        // Try to get from query var
        $post_id = get_queried_object_id();
        if ($post_id) {
            return $post_id;
        }

        return false;
    }
}

// Initialize shortcodes
CFM_Shortcodes::instance();