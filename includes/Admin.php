<?php
// includes/Admin.php

class CFM_Admin
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_init', [$this, 'handle_requests']);
    }

    public function admin_menu()
    {
        add_menu_page(
            __('Custom Fields', 'custom-fields-manager'),
            __('Custom Fields', 'custom-fields-manager'),
            'manage_options',
            'cfm-field-groups',
            [$this, 'render_admin_page'],
            'dashicons-welcome-widgets-menus',
            30
        );
    }

    public function enqueue_scripts($hook)
    {
        if (strpos($hook, 'cfm-field-groups') === false) {
            return;
        }

        // Enqueue Sortable.js for drag and drop
        wp_enqueue_script(
            'sortablejs',
            'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js',
            [],
            '1.15.0',
            true
        );

        // Main admin CSS
        wp_enqueue_style(
            'cfm-admin',
            CFM_PLUGIN_URL . 'assets/css/admin.css',
            [],
            CFM_VERSION
        );

        // Main admin JS
        wp_enqueue_script(
            'cfm-admin',
            CFM_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'sortablejs'],
            CFM_VERSION,
            true
        );

        // Localize script data
        wp_localize_script('cfm-admin', 'cfmData', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cfm_nonce'),
            'i18n' => [
                'confirmDelete' => __('Are you sure you want to delete this?', 'custom-fields-manager'),
                'saving' => __('Saving...', 'custom-fields-manager'),
                'saved' => __('Saved!', 'custom-fields-manager'),
            ]
        ]);
    }

    public function handle_requests()
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'cfm-field-groups') {
            return;
        }

        // Handle form submissions
        if (isset($_POST['cfm_save_field_group'])) {
            $this->handle_save_field_group();
        }

        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
            $this->handle_delete_field_group();
        }

        if (isset($_GET['action']) && $_GET['action'] === 'duplicate' && isset($_GET['id'])) {
            $this->handle_duplicate_field_group();
        }
    }

    public function render_admin_page()
    {
        $action = $_GET['action'] ?? 'list';
        $id = $_GET['id'] ?? 0;

        switch ($action) {
            case 'edit':
            case 'new':
                $this->render_edit_page($id);
                break;
            default:
                $this->render_list_page();
                break;
        }
    }

    private function render_list_page()
    {
        $repository = CFM_Field_Group_Repository::instance();
        $field_groups = $repository->get_all();

        include CFM_PLUGIN_DIR . 'templates/admin/list.php';
    }

    private function render_edit_page($id)
    {
        $repository = CFM_Field_Group_Repository::instance();
        $field_group = null;

        if ($id > 0) {
            $field_group = $repository->find($id);
        }

        if (!$field_group && $id > 0) {
            wp_die(__('Field group not found.', 'custom-fields-manager'));
        }

        if (!$field_group) {
            $field_group = new CFM_Field_Group();
        }

        // Prepare data for JavaScript
        $field_group_data = $field_group->to_array();

        wp_localize_script('cfm-admin', 'cfmFieldGroup', $field_group_data);

        include CFM_PLUGIN_DIR . 'templates/admin/edit.php';
    }

    private function handle_save_field_group()
    {
        if (!isset($_POST['cfm_save_field_group'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['_wpnonce'], 'cfm_save_field_group')) {
            wp_die(__('Security check failed.', 'custom-fields-manager'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'custom-fields-manager'));
        }

        $repository = CFM_Field_Group_Repository::instance();
        $id = intval($_POST['field_group_id'] ?? 0);

        if ($id > 0) {
            $field_group = $repository->find($id);
            if (!$field_group) {
                wp_die(__('Field group not found.', 'custom-fields-manager'));
            }
        } else {
            $field_group = new CFM_Field_Group();
        }

        // Update field group data
        $field_group->set_title(sanitize_text_field($_POST['title'] ?? ''));
        $field_group->set_fields($_POST['fields'] ?? []);
        $field_group->set_location($_POST['location'] ?? []);
        $field_group->set_position(sanitize_text_field($_POST['position'] ?? 'normal'));
        $field_group->set_style(sanitize_text_field($_POST['style'] ?? 'default'));
        $field_group->set_active(isset($_POST['active']));

        // Validate
        $validation = $field_group->validate();
        if ($validation !== true) {
            $error_message = is_array($validation) ? implode('<br>', $validation) : $validation;
            wp_die($error_message);
        }

        // Save
        try {
            $saved_id = $repository->save($field_group);

            // Redirect to avoid form resubmission
            wp_redirect(admin_url('admin.php?page=cfm-field-groups&action=edit&id=' . $saved_id . '&message=saved'));
            exit;
        } catch (Exception $e) {
            wp_die('Error saving field group: ' . $e->getMessage());
        }
    }

    private function handle_delete_field_group()
    {
        if (!wp_verify_nonce($_GET['_wpnonce'], 'cfm_delete_field_group')) {
            wp_die(__('Security check failed.', 'custom-fields-manager'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'custom-fields-manager'));
        }

        $id = intval($_GET['id']);
        $repository = CFM_Field_Group_Repository::instance();
        $repository->delete($id);

        wp_redirect(admin_url('admin.php?page=cfm-field-groups&message=deleted'));
        exit;
    }

    private function handle_duplicate_field_group()
    {
        if (!wp_verify_nonce($_GET['_wpnonce'], 'cfm_duplicate_field_group')) {
            wp_die(__('Security check failed.', 'custom-fields-manager'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'custom-fields-manager'));
        }

        $id = intval($_GET['id']);
        $repository = CFM_Field_Group_Repository::instance();
        $new_id = $repository->duplicate($id);

        if ($new_id) {
            wp_redirect(admin_url('admin.php?page=cfm-field-groups&action=edit&id=' . $new_id . '&message=duplicated'));
        } else {
            wp_redirect(admin_url('admin.php?page=cfm-field-groups&message=error'));
        }
        exit;
    }
}
