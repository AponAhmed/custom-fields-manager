<div class="wrap">
    <div class="cfm-header">
        <div class="cfm-header-content">
            <h1 class="wp-heading-inline"><?php _e('Field Groups', 'custom-fields-manager'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=cfm-field-groups&action=new'); ?>" class="cfm-btn cfm-btn-primary">
                <span class="dashicons dashicons-plus"></span>
                <?php _e('Add New', 'custom-fields-manager'); ?>
            </a>
        </div>
    </div>

    <?php if (empty($field_groups)): ?>
        <div class="cfm-empty-state">
            <div class="cfm-empty-state-icon">
                <span class="dashicons dashicons-welcome-widgets-menus"></span>
            </div>
            <h3><?php _e('No field groups found', 'custom-fields-manager'); ?></h3>
            <p><?php _e('Get started by creating your first field group.', 'custom-fields-manager'); ?></p>
            <a href="<?php echo admin_url('admin.php?page=cfm-field-groups&action=new'); ?>" class="cfm-btn cfm-btn-primary cfm-btn-lg">
                <?php _e('Create Field Group', 'custom-fields-manager'); ?>
            </a>
        </div>
    <?php else: ?>
        <div class="cfm-field-groups-grid">
            <?php foreach ($field_groups as $group): ?>
                <div class="cfm-field-group-card">
                    <div class="cfm-field-group-header">
                        <h3 class="cfm-field-group-title">
                            <a href="<?php echo admin_url('admin.php?page=cfm-field-groups&action=edit&id=' . $group->get_id()); ?>">
                                <?php echo esc_html($group->get_title()); ?>
                            </a>
                        </h3>
                        <div class="cfm-field-group-badge <?php echo $group->is_active() ? 'cfm-badge-active' : 'cfm-badge-inactive'; ?>">
                            <?php echo $group->is_active() ? __('Active', 'custom-fields-manager') : __('Inactive', 'custom-fields-manager'); ?>
                        </div>
                    </div>
                    
                    <div class="cfm-field-group-content">
                        <div class="cfm-field-group-meta">
                            <div class="cfm-meta-item">
                                <span class="dashicons dashicons-list-view"></span>
                                <span><?php echo count($group->get_fields()); ?> <?php _e('fields', 'custom-fields-manager'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="cfm-field-group-actions">
                        <a href="<?php echo admin_url('admin.php?page=cfm-field-groups&action=edit&id=' . $group->get_id()); ?>" class="cfm-btn cfm-btn-secondary cfm-btn-sm">
                            <?php _e('Edit', 'custom-fields-manager'); ?>
                        </a>
                        
                        <div class="cfm-dropdown">
                            <button class="cfm-btn cfm-btn-outline cfm-btn-sm cfm-dropdown-toggle">
                                <span class="dashicons dashicons-ellipsis"></span>
                            </button>
                            <div class="cfm-dropdown-menu">
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=cfm-field-groups&action=duplicate&id=' . $group->get_id()), 'cfm_duplicate_field_group'); ?>" class="cfm-dropdown-item">
                                    <span class="dashicons dashicons-admin-page"></span>
                                    <?php _e('Duplicate', 'custom-fields-manager'); ?>
                                </a>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=cfm-field-groups&action=delete&id=' . $group->get_id()), 'cfm_delete_field_group'); ?>" class="cfm-dropdown-item cfm-dropdown-item-danger" data-cfm-confirm="<?php esc_attr_e('Are you sure you want to delete this field group?', 'custom-fields-manager'); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                    <?php _e('Delete', 'custom-fields-manager'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>