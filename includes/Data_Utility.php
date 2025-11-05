<?php
// includes/CFM_Data_Utility.php

class CFM_Data_Utility
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
        // Constructor can be used for initialization if needed
    }

    /**
     * 1. Get all CFM data for a post
     * 
     * @param int $post_id Post ID
     * @return array All CFM data organized by field groups
     */
    public function get_all_data($post_id)
    {
        global $wpdb;
        
        $cfm_data = [];
        
        // Get all CFM meta fields for the post
        $meta_fields = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE '_cfm_%'",
                $post_id
            ),
            ARRAY_A
        );

        foreach ($meta_fields as $meta_field) {
            $meta_key = $meta_field['meta_key'];
            $meta_value = maybe_unserialize($meta_field['meta_value']);
            
            // Extract field group key from meta key (_cfm_{field_group_key})
            $field_group_key = str_replace('_cfm_', '', $meta_key);
            
            $cfm_data[$field_group_key] = $meta_value;
        }

        return $cfm_data;
    }

    /**
     * 2. Get specific field value from field group
     * Format: group_{id}_{key}
     * 
     * @param int $post_id Post ID
     * @param string $field_group_key Field group key
     * @param string $field_name Field name
     * @return mixed Field value (single value or repeater array)
     */
    public function get_field_value($post_id, $field_group_key, $field_name)
    {
        $field_group_data = get_post_meta($post_id, '_cfm_' . $field_group_key, true);
        
        if (!is_array($field_group_data) || !isset($field_group_data[$field_name])) {
            return '';
        }

        return $field_group_data[$field_name];
    }

    /**
     * 3. Get specific row from repeater field
     * Format: group_{id}_{index}_{row_index}
     * 
     * @param int $post_id Post ID
     * @param string $field_group_key Field group key
     * @param string $repeater_field_name Repeater field name
     * @param int $row_index Row index (0-based)
     * @return array|null Repeater row data or null if not found
     */
    public function get_repeater_row($post_id, $field_group_key, $repeater_field_name, $row_index)
    {
        $repeater_data = $this->get_field_value($post_id, $field_group_key, $repeater_field_name);
        
        if (!is_array($repeater_data) || !isset($repeater_data[$row_index])) {
            return null;
        }

        return $repeater_data[$row_index];
    }

    /**
     * 4. Get specific field value from repeater row
     * Format: group_{id}_{index}_{row_index}_{field_key}
     * 
     * @param int $post_id Post ID
     * @param string $field_group_key Field group key
     * @param string $repeater_field_name Repeater field name
     * @param int $row_index Row index (0-based)
     * @param string $sub_field_name Sub field name
     * @return mixed Field value or empty string if not found
     */
    public function get_repeater_sub_field($post_id, $field_group_key, $repeater_field_name, $row_index, $sub_field_name)
    {
        $row_data = $this->get_repeater_row($post_id, $field_group_key, $repeater_field_name, $row_index);
        
        if (!is_array($row_data) || !isset($row_data[$sub_field_name])) {
            return '';
        }

        return $row_data[$sub_field_name];
    }

    /**
     * Magic method to handle dynamic method calls for different data formats
     * 
     * Supported formats:
     * - all() - Get all data
     * - group_{field_group_key}_{field_name}() - Get specific field
     * - group_{field_group_key}_{repeater_name}_{row_index}() - Get repeater row
     * - group_{field_group_key}_{repeater_name}_{row_index}_{sub_field}() - Get repeater sub field
     * 
     * @param string $method Method name
     * @param array $arguments Method arguments [post_id]
     * @return mixed Requested data
     */
    public function __call($method, $arguments)
    {
        if (empty($arguments)) {
            throw new InvalidArgumentException('Post ID is required');
        }

        $post_id = $arguments[0];

        // 1. Get all data
        if ($method === 'all') {
            return $this->get_all_data($post_id);
        }

        // Parse the method name to extract parameters
        if (strpos($method, 'group_') === 0) {
            $parts = explode('_', $method);
            
            // Remove 'group' prefix
            array_shift($parts);
            
            if (count($parts) < 2) {
                throw new InvalidArgumentException('Invalid method format. Expected: group_{field_group_key}_{field_name}');
            }

            $field_group_key = $parts[0];
            
            // 2. Format: group_{id}_{key}
            if (count($parts) === 2) {
                $field_name = $parts[1];
                return $this->get_field_value($post_id, $field_group_key, $field_name);
            }
            
            // 3. Format: group_{id}_{index}_{row_index}
            if (count($parts) === 3) {
                $repeater_field_name = $parts[1];
                $row_index = intval($parts[2]);
                return $this->get_repeater_row($post_id, $field_group_key, $repeater_field_name, $row_index);
            }
            
            // 4. Format: group_{id}_{index}_{row_index}_{field_key}
            if (count($parts) === 4) {
                $repeater_field_name = $parts[1];
                $row_index = intval($parts[2]);
                $sub_field_name = $parts[3];
                return $this->get_repeater_sub_field($post_id, $field_group_key, $repeater_field_name, $row_index, $sub_field_name);
            }
        }

        throw new BadMethodCallException("Method {$method} does not exist");
    }

    /**
     * Static wrapper for magic method calls
     * 
     * @param string $method Method name
     * @param array $arguments Method arguments
     * @return mixed Requested data
     */
    public static function __callStatic($method, $arguments)
    {
        return self::instance()->__call($method, $arguments);
    }

    /**
     * Get field group data with field configuration
     * 
     * @param int $post_id Post ID
     * @param string $field_group_key Field group key
     * @return array Field group data with field info
     */
    public function get_field_group_with_config($post_id, $field_group_key)
    {
        $data = $this->get_field_group_data($post_id, $field_group_key);
        $field_group = CFM_Field_Group_Repository::instance()->get_by_key($field_group_key);
        
        if (!$field_group) {
            return [
                'data' => $data,
                'fields' => [],
                'config' => null
            ];
        }

        return [
            'data' => $data,
            'fields' => $field_group->get_fields(),
            'config' => [
                'title' => $field_group->get_title(),
                'key' => $field_group->get_key(),
                'position' => $field_group->get_position(),
                'style' => $field_group->get_style()
            ]
        ];
    }

    /**
     * Get all repeater rows for a specific repeater field
     * 
     * @param int $post_id Post ID
     * @param string $field_group_key Field group key
     * @param string $repeater_field_name Repeater field name
     * @return array All repeater rows
     */
    public function get_all_repeater_rows($post_id, $field_group_key, $repeater_field_name)
    {
        $repeater_data = $this->get_field_value($post_id, $field_group_key, $repeater_field_name);
        
        return is_array($repeater_data) ? $repeater_data : [];
    }

    /**
     * Count repeater rows
     * 
     * @param int $post_id Post ID
     * @param string $field_group_key Field group key
     * @param string $repeater_field_name Repeater field name
     * @return int Number of rows
     */
    public function count_repeater_rows($post_id, $field_group_key, $repeater_field_name)
    {
        $rows = $this->get_all_repeater_rows($post_id, $field_group_key, $repeater_field_name);
        return count($rows);
    }

    /**
     * Check if field exists and has value
     * 
     * @param int $post_id Post ID
     * @param string $field_group_key Field group key
     * @param string $field_name Field name
     * @return bool
     */
    public function has_value($post_id, $field_group_key, $field_name)
    {
        $value = $this->get_field_value($post_id, $field_group_key, $field_name);
        return !empty($value);
    }

    /**
     * Get field value with fallback
     * 
     * @param int $post_id Post ID
     * @param string $field_group_key Field group key
     * @param string $field_name Field name
     * @param mixed $default Default value if field is empty
     * @return mixed Field value or default
     */
    public function get_field_value_with_default($post_id, $field_group_key, $field_name, $default = '')
    {
        $value = $this->get_field_value($post_id, $field_group_key, $field_name);
        return !empty($value) ? $value : $default;
    }

    /**
     * Get all field groups available for a post
     * 
     * @param int $post_id Post ID
     * @return array Field group keys
     */
    public function get_available_field_groups($post_id)
    {
        $post = get_post($post_id);
        if (!$post) {
            return [];
        }

        $context = [
            'post_id' => $post_id,
            'post_type' => $post->post_type
        ];

        $field_groups = CFM_Field_Group_Repository::instance()->get_by_location($context);
        $group_keys = [];

        foreach ($field_groups as $field_group) {
            $group_keys[] = $field_group->get_key();
        }

        return $group_keys;
    }
}

// Helper functions for easier usage

/**
 * Get all CFM data for a post
 * 
 * @param int $post_id Post ID
 * @return array All CFM data
 */
function cfm_get_all_data($post_id)
{
    return CFM_Data_Utility::instance()->get_all_data($post_id);
}

/**
 * Get specific field value using dynamic method
 * 
 * @param string $method Method name in format: group_{field_group_key}_{field_name}
 * @param int $post_id Post ID
 * @return mixed Field value
 */
function cfm_get($method, $post_id)
{
    return CFM_Data_Utility::instance()->$method($post_id);
}

/**
 * Get field value with explicit parameters
 * 
 * @param int $post_id Post ID
 * @param string $field_group_key Field group key
 * @param string $field_name Field name
 * @return mixed Field value
 */
function cfm_get_field($post_id, $field_group_key, $field_name)
{
    return CFM_Data_Utility::instance()->get_field_value($post_id, $field_group_key, $field_name);
}

/**
 * Get repeater row
 * 
 * @param int $post_id Post ID
 * @param string $field_group_key Field group key
 * @param string $repeater_field_name Repeater field name
 * @param int $row_index Row index
 * @return array|null Repeater row data
 */
function cfm_get_repeater_row($post_id, $field_group_key, $repeater_field_name, $row_index)
{
    return CFM_Data_Utility::instance()->get_repeater_row($post_id, $field_group_key, $repeater_field_name, $row_index);
}

/**
 * Get repeater sub field value
 * 
 * @param int $post_id Post ID
 * @param string $field_group_key Field group key
 * @param string $repeater_field_name Repeater field name
 * @param int $row_index Row index
 * @param string $sub_field_name Sub field name
 * @return mixed Sub field value
 */
function cfm_get_repeater_sub_field($post_id, $field_group_key, $repeater_field_name, $row_index, $sub_field_name)
{
    return CFM_Data_Utility::instance()->get_repeater_sub_field($post_id, $field_group_key, $repeater_field_name, $row_index, $sub_field_name);
}