<?php
// includes/Field_Group_Repository.php

class CFM_Field_Group_Repository {
    
    private static $instance = null;
    private $table;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'cfm_field_groups';
    }
    
    public function find($id) {
        global $wpdb;
        
        if (!$this->table_exists()) {
            error_log('CFM: Table does not exist: ' . $this->table);
            return null;
        }
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if (!$row) {
            return null;
        }
        
        return $this->create_field_group_from_row($row);
    }
    
    public function get_by_key($key) {
        global $wpdb;
        
        if (!$this->table_exists()) {
            error_log('CFM: Table does not exist: ' . $this->table);
            return null;
        }
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE field_key = %s",
            $key
        ), ARRAY_A);
        
        if (!$row) {
            return null;
        }
        
        return $this->create_field_group_from_row($row);
    }
    
    public function get_all() {
        global $wpdb;
        
        if (!$this->table_exists()) {
            error_log('CFM: Table does not exist: ' . $this->table);
            return [];
        }
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$this->table} ORDER BY title ASC",
            ARRAY_A
        );
        
        $field_groups = [];
        
        foreach ($results as $row) {
            $field_groups[] = $this->create_field_group_from_row($row);
        }
        
        return $field_groups;
    }
    
    public function save(CFM_Field_Group $field_group) {
        global $wpdb;
        
        if (!$this->table_exists()) {
            error_log('CFM: Table does not exist: ' . $this->table);
            return false;
        }
        
        $data = $field_group->to_array();
        $id = $data['id'] ?? 0;
        
        // Prepare data for saving
        $save_data = [
            'title' => sanitize_text_field($field_group->get_title()),
            'field_key' => sanitize_key($field_group->get_key()),
            'data' => maybe_serialize($data),
            'updated_at' => current_time('mysql')
        ];
        
        if ($id > 0) {
            // Update existing field group
            $result = $wpdb->update(
                $this->table,
                $save_data,
                ['id' => $id],
                ['%s', '%s', '%s', '%s'],
                ['%d']
            );
            
            if ($result === false) {
                error_log('CFM: Update failed - ' . $wpdb->last_error);
                return false;
            }
            
            return $id;
        } else {
            // Insert new field group
            $save_data['created_at'] = current_time('mysql');
            
            $result = $wpdb->insert(
                $this->table,
                $save_data,
                ['%s', '%s', '%s', '%s', '%s']
            );
            
            if ($result === false) {
                error_log('CFM: Insert failed - ' . $wpdb->last_error);
                return false;
            }
            
            return $wpdb->insert_id;
        }
    }
    
    public function delete($id) {
        global $wpdb;
        
        if (!$this->table_exists()) {
            error_log('CFM: Table does not exist: ' . $this->table);
            return false;
        }
        
        $result = $wpdb->delete(
            $this->table,
            ['id' => $id],
            ['%d']
        );
        
        if ($result === false) {
            error_log('CFM: Delete failed - ' . $wpdb->last_error);
            return false;
        }
        
        return $result;
    }
    
    public function duplicate($id) {
        $original = $this->find($id);
        
        if (!$original) {
            return false;
        }
        
        $data = $original->to_array();
        $data['id'] = 0;
        $data['title'] = $data['title'] . ' (' . __('Copy', 'custom-fields-manager') . ')';
        $data['key'] = 'group_' . uniqid();
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');
        
        $duplicate = CFM_Field_Group::from_array($data);
        
        return $this->save($duplicate);
    }
    
    public function get_by_location($context) {
        $all_groups = $this->get_all();
        $matching_groups = [];
        
        foreach ($all_groups as $group) {
            if (!$group->is_active()) {
                continue;
            }
            
            if ($this->matches_location($group->get_location(), $context)) {
                $matching_groups[] = $group;
            }
        }
        
        // Sort by priority or menu order if needed
        usort($matching_groups, function($a, $b) {
            return $a->get_menu_order() - $b->get_menu_order();
        });
        
        return $matching_groups;
    }
    
    private function create_field_group_from_row($row) {
        $data = maybe_unserialize($row['data']);
        
        // Ensure all required fields are present
        $data['id'] = (int) $row['id'];
        $data['title'] = $row['title'] ?? '';
        $data['key'] = $row['field_key'] ?? '';
        $data['created_at'] = $row['created_at'] ?? '';
        $data['updated_at'] = $row['updated_at'] ?? '';
        
        // Set default values for missing fields
        $defaults = [
            'active' => true,
            'style' => 'default',
            'position' => 'normal',
            'menu_order' => 0,
            'fields' => [],
            'location' => []
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        return CFM_Field_Group::from_array($data);
    }
    
    private function matches_location($location_rules, $context) {
        // If no location rules, show everywhere
        if (empty($location_rules)) {
            return true;
        }
        
        foreach ($location_rules as $rule_group) {
            if ($this->matches_rule_group($rule_group, $context)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function matches_rule_group($rule_group, $context) {
        // If rule group is empty, it matches
        if (empty($rule_group)) {
            return true;
        }
        
        foreach ($rule_group as $rule) {
            if (!$this->matches_rule($rule, $context)) {
                return false;
            }
        }
        
        return true;
    }
    
    private function matches_rule($rule, $context) {
        $param = $rule['param'] ?? '';
        $operator = $rule['operator'] ?? '==';
        $value = $rule['value'] ?? '';
        
        $current_value = $this->get_current_value($param, $context);
        
        if ($operator === '==') {
            return $current_value == $value;
        } else {
            return $current_value != $value;
        }
    }
    
    private function get_current_value($param, $context) {
        $post_id = $context['post_id'] ?? 0;
        $post_type = $context['post_type'] ?? '';
        
        switch ($param) {
            case 'post_type':
                return $post_type;
                
            case 'post_template':
                return get_page_template_slug($post_id);
                
            case 'post_category':
                $categories = wp_get_post_categories($post_id, ['fields' => 'slugs']);
                return $categories[0] ?? '';
                
            case 'post':
                return $post_id;
                
            case 'page':
                return is_page($post_id) ? $post_id : '';
                
            case 'post_status':
                return get_post_status($post_id);
                
            default:
                // Allow for custom location parameters
                return apply_filters('cfm_location_parameter_value', '', $param, $context);
        }
    }
    
    private function table_exists() {
        global $wpdb;
        return $wpdb->get_var("SHOW TABLES LIKE '{$this->table}'") === $this->table;
    }
    
    public function get_active_count() {
        global $wpdb;
        
        if (!$this->table_exists()) {
            return 0;
        }
        
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table} WHERE active = 1"
        );
        
        return (int) $count;
    }
    
    public function get_total_count() {
        global $wpdb;
        
        if (!$this->table_exists()) {
            return 0;
        }
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
        
        return (int) $count;
    }
    
    public function search($search_term) {
        global $wpdb;
        
        if (!$this->table_exists()) {
            return [];
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE title LIKE %s OR field_key LIKE %s ORDER BY title ASC",
            '%' . $wpdb->esc_like($search_term) . '%',
            '%' . $wpdb->esc_like($search_term) . '%'
        ), ARRAY_A);
        
        $field_groups = [];
        
        foreach ($results as $row) {
            $field_groups[] = $this->create_field_group_from_row($row);
        }
        
        return $field_groups;
    }
}