<?php
// includes/API.php

class CFM_API {
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_ajax_cfm_api', [$this, 'handle_ajax']);
        add_action('wp_ajax_nopriv_cfm_api', [$this, 'handle_ajax']);
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Get field groups
        register_rest_route('cfm/v1', '/field-groups', [
            'methods' => 'GET',
            'callback' => [$this, 'get_field_groups'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);
        
        // Get specific field group
        register_rest_route('cfm/v1', '/field-groups/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_field_group'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);
        
        // Create field group
        register_rest_route('cfm/v1', '/field-groups', [
            'methods' => 'POST',
            'callback' => [$this, 'create_field_group'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);
        
        // Update field group
        register_rest_route('cfm/v1', '/field-groups/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_field_group'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);
        
        // Delete field group
        register_rest_route('cfm/v1', '/field-groups/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_field_group'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);
        
        // Get field values for post
        register_rest_route('cfm/v1', '/posts/(?P<id>\d+)/fields', [
            'methods' => 'GET',
            'callback' => [$this, 'get_post_fields'],
            'permission_callback' => [$this, 'check_public_permissions'],
        ]);
        
        // Get field value
        register_rest_route('cfm/v1', '/posts/(?P<post_id>\d+)/fields/(?P<field_name>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_post_field'],
            'permission_callback' => [$this, 'check_public_permissions'],
        ]);
        
        // Update field value
        register_rest_route('cfm/v1', '/posts/(?P<post_id>\d+)/fields/(?P<field_name>[a-zA-Z0-9_-]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'update_post_field'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);
    }
    
    /**
     * Handle AJAX requests
     */
    public function handle_ajax() {
        check_ajax_referer('cfm_nonce', 'nonce');
        
        $action = sanitize_text_field($_POST['action'] ?? '');
        $method = sanitize_text_field($_POST['method'] ?? '');
        
        if ($action !== 'cfm_api') {
            wp_send_json_error('Invalid action');
        }
        
        switch ($method) {
            case 'get_field_groups':
                $this->ajax_get_field_groups();
                break;
                
            case 'get_field_group':
                $this->ajax_get_field_group();
                break;
                
            case 'save_field_group':
                $this->ajax_save_field_group();
                break;
                
            case 'delete_field_group':
                $this->ajax_delete_field_group();
                break;
                
            case 'duplicate_field_group':
                $this->ajax_duplicate_field_group();
                break;
                
            case 'get_post_fields':
                $this->ajax_get_post_fields();
                break;
                
            default:
                wp_send_json_error('Invalid method');
        }
    }
    
    /**
     * AJAX: Get field groups
     */
    private function ajax_get_field_groups() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $repository = CFM_Field_Group_Repository::instance();
        $field_groups = $repository->get_all();
        
        $data = array_map(function($group) {
            return $this->prepare_field_group_data($group);
        }, $field_groups);
        
        wp_send_json_success($data);
    }
    
    /**
     * AJAX: Get specific field group
     */
    private function ajax_get_field_group() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error('Invalid field group ID');
        }
        
        $repository = CFM_Field_Group_Repository::instance();
        $field_group = $repository->find($id);
        
        if (!$field_group) {
            wp_send_json_error('Field group not found');
        }
        
        wp_send_json_success($this->prepare_field_group_data($field_group));
    }
    
    /**
     * AJAX: Save field group
     */
    private function ajax_save_field_group() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $data = json_decode(wp_unslash($_POST['data'] ?? '{}'), true);
        
        if (empty($data)) {
            wp_send_json_error('Invalid data');
        }
        
        $repository = CFM_Field_Group_Repository::instance();
        $id = intval($data['id'] ?? 0);
        
        if ($id > 0) {
            $field_group = $repository->find($id);
            if (!$field_group) {
                wp_send_json_error('Field group not found');
            }
        } else {
            $field_group = new CFM_Field_Group();
        }
        
        // Update field group data
        $field_group->set_title(sanitize_text_field($data['title'] ?? ''));
        $field_group->set_fields($data['fields'] ?? []);
        $field_group->set_location($data['location'] ?? []);
        $field_group->set_position(sanitize_text_field($data['position'] ?? 'normal'));
        $field_group->set_style(sanitize_text_field($data['style'] ?? 'default'));
        $field_group->set_active(!empty($data['active']));
        
        // Validate
        $validation = $field_group->validate();
        if ($validation !== true) {
            wp_send_json_error($validation);
        }
        
        // Save
        $saved_id = $repository->save($field_group);
        
        wp_send_json_success([
            'id' => $saved_id,
            'message' => $id ? 'Field group updated' : 'Field group created'
        ]);
    }
    
    /**
     * AJAX: Delete field group
     */
    private function ajax_delete_field_group() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error('Invalid field group ID');
        }
        
        $repository = CFM_Field_Group_Repository::instance();
        $result = $repository->delete($id);
        
        if ($result) {
            wp_send_json_success('Field group deleted');
        } else {
            wp_send_json_error('Failed to delete field group');
        }
    }
    
    /**
     * AJAX: Duplicate field group
     */
    private function ajax_duplicate_field_group() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error('Invalid field group ID');
        }
        
        $repository = CFM_Field_Group_Repository::instance();
        $new_id = $repository->duplicate($id);
        
        if ($new_id) {
            wp_send_json_success([
                'id' => $new_id,
                'message' => 'Field group duplicated'
            ]);
        } else {
            wp_send_json_error('Failed to duplicate field group');
        }
    }
    
    /**
     * AJAX: Get post fields
     */
    private function ajax_get_post_fields() {
        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }
        
        $renderer = new CFM_Field_Renderer();
        $fields = $renderer->get_all_fields($post_id);
        
        wp_send_json_success($fields);
    }
    
    /**
     * REST: Get field groups
     */
    public function get_field_groups($request) {
        $repository = CFM_Field_Group_Repository::instance();
        $field_groups = $repository->get_all();
        
        $data = array_map(function($group) {
            return $this->prepare_field_group_data($group);
        }, $field_groups);
        
        return rest_ensure_response($data);
    }
    
    /**
     * REST: Get specific field group
     */
    public function get_field_group($request) {
        $id = $request->get_param('id');
        $repository = CFM_Field_Group_Repository::instance();
        $field_group = $repository->find($id);
        
        if (!$field_group) {
            return new WP_Error('not_found', 'Field group not found', ['status' => 404]);
        }
        
        return rest_ensure_response($this->prepare_field_group_data($field_group));
    }
    
    /**
     * REST: Create field group
     */
    public function create_field_group($request) {
        $data = $request->get_json_params();
        
        $field_group = new CFM_Field_Group();
        $field_group->set_title(sanitize_text_field($data['title'] ?? ''));
        $field_group->set_fields($data['fields'] ?? []);
        $field_group->set_location($data['location'] ?? []);
        $field_group->set_position(sanitize_text_field($data['position'] ?? 'normal'));
        $field_group->set_style(sanitize_text_field($data['style'] ?? 'default'));
        $field_group->set_active(!empty($data['active']));
        
        $validation = $field_group->validate();
        if ($validation !== true) {
            return new WP_Error('validation_failed', 'Validation failed', [
                'status' => 400,
                'errors' => $validation
            ]);
        }
        
        $repository = CFM_Field_Group_Repository::instance();
        $id = $repository->save($field_group);
        
        return rest_ensure_response([
            'id' => $id,
            'message' => 'Field group created'
        ]);
    }
    
    /**
     * REST: Update field group
     */
    public function update_field_group($request) {
        $id = $request->get_param('id');
        $data = $request->get_json_params();
        
        $repository = CFM_Field_Group_Repository::instance();
        $field_group = $repository->find($id);
        
        if (!$field_group) {
            return new WP_Error('not_found', 'Field group not found', ['status' => 404]);
        }
        
        $field_group->set_title(sanitize_text_field($data['title'] ?? ''));
        $field_group->set_fields($data['fields'] ?? []);
        $field_group->set_location($data['location'] ?? []);
        $field_group->set_position(sanitize_text_field($data['position'] ?? 'normal'));
        $field_group->set_style(sanitize_text_field($data['style'] ?? 'default'));
        $field_group->set_active(!empty($data['active']));
        
        $validation = $field_group->validate();
        if ($validation !== true) {
            return new WP_Error('validation_failed', 'Validation failed', [
                'status' => 400,
                'errors' => $validation
            ]);
        }
        
        $repository->save($field_group);
        
        return rest_ensure_response([
            'message' => 'Field group updated'
        ]);
    }
    
    /**
     * REST: Delete field group
     */
    public function delete_field_group($request) {
        $id = $request->get_param('id');
        $repository = CFM_Field_Group_Repository::instance();
        $result = $repository->delete($id);
        
        if (!$result) {
            return new WP_Error('delete_failed', 'Failed to delete field group', ['status' => 500]);
        }
        
        return rest_ensure_response([
            'message' => 'Field group deleted'
        ]);
    }
    
    /**
     * REST: Get post fields
     */
    public function get_post_fields($request) {
        $post_id = $request->get_param('id');
        $post = get_post($post_id);
        
        if (!$post) {
            return new WP_Error('not_found', 'Post not found', ['status' => 404]);
        }
        
        $renderer = new CFM_Field_Renderer();
        $fields = $renderer->get_all_fields($post_id);
        
        return rest_ensure_response($fields);
    }
    
    /**
     * REST: Get post field
     */
    public function get_post_field($request) {
        $post_id = $request->get_param('post_id');
        $field_name = $request->get_param('field_name');
        
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('not_found', 'Post not found', ['status' => 404]);
        }
        
        $renderer = new CFM_Field_Renderer();
        $value = $renderer->get_field_value($field_name, $post_id);
        
        return rest_ensure_response([
            'field' => $field_name,
            'value' => $value
        ]);
    }
    
    /**
     * REST: Update post field
     */
    public function update_post_field($request) {
        $post_id = $request->get_param('post_id');
        $field_name = $request->get_param('field_name');
        $data = $request->get_json_params();
        
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('not_found', 'Post not found', ['status' => 404]);
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error('forbidden', 'Insufficient permissions', ['status' => 403]);
        }
        
        $value = $data['value'] ?? '';
        $result = update_post_meta($post_id, $field_name, $value);
        
        if ($result) {
            return rest_ensure_response([
                'message' => 'Field updated',
                'value' => $value
            ]);
        } else {
            return new WP_Error('update_failed', 'Failed to update field', ['status' => 500]);
        }
    }
    
    /**
     * Prepare field group data for API response
     */
    private function prepare_field_group_data(CFM_Field_Group $field_group) {
        $data = $field_group->to_array();
        
        // Clean up data for API
        unset($data['created_at'], $data['updated_at']);
        
        return $data;
    }
    
    /**
     * Check permissions for admin operations
     */
    public function check_permissions($request) {
        return current_user_can('manage_options');
    }
    
    /**
     * Check permissions for public operations
     */
    public function check_public_permissions($request) {
        // For public endpoints, we only require that the post is published
        $post_id = $request->get_param('id') ?? $request->get_param('post_id');
        
        if ($post_id) {
            $post = get_post($post_id);
            if ($post && 'publish' === $post->post_status) {
                return true;
            }
        }
        
        return current_user_can('read');
    }
    
    /**
     * Utility method to get API namespace
     */
    public static function get_namespace() {
        return 'cfm/v1';
    }
    
    /**
     * Utility method to get API URL
     */
    public static function get_url($endpoint = '') {
        return rest_url(self::get_namespace() . $endpoint);
    }
}