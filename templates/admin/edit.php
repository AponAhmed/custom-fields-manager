<div class="wrap">
    <div class="cfm-header">
        <div class="cfm-header-content">
            <h1>
                <?php echo $field_group->get_id() ? __('Edit Field Group', 'custom-fields-manager') : __('Add New Field Group', 'custom-fields-manager'); ?>
            </h1>
            <div class="cfm-header-actions">
                <?php if ($field_group->get_id()): ?>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=cfm-field-groups&action=delete&id=' . $field_group->get_id()), 'cfm_delete_field_group'); ?>" class="cfm-btn cfm-btn-danger" data-cfm-confirm="<?php esc_attr_e('Are you sure you want to delete this field group?', 'custom-fields-manager'); ?>">
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Delete', 'custom-fields-manager'); ?>
                    </a>
                <?php endif; ?>
                <button type="submit" form="cfm-field-group-form" class="cfm-btn cfm-btn-primary">
                    <?php echo $field_group->get_id() ? __('Update', 'custom-fields-manager') : __('Publish', 'custom-fields-manager'); ?>
                </button>
            </div>
        </div>
    </div>

    <form method="post" action="" id="cfm-field-group-form">
        <?php wp_nonce_field('cfm_save_field_group'); ?>
        <input type="hidden" name="field_group_id" value="<?php echo $field_group->get_id(); ?>">
        <input type="hidden" name="cfm_save_field_group" value="1">
        
        <div class="cfm-edit-layout">
            <div class="cfm-main-content">
                <!-- Title -->
                <div class="cfm-panel">
                    <div class="cfm-panel-header">
                        <h2><?php _e('Field Group Title', 'custom-fields-manager'); ?></h2>
                    </div>
                    <div class="cfm-panel-body">
                        <input type="text" name="title" value="<?php echo esc_attr($field_group->get_title()); ?>" class="cfm-field-group-title-input" placeholder="<?php esc_attr_e('Enter field group title', 'custom-fields-manager'); ?>" required>
                    </div>
                </div>
                
                <!-- Fields -->
                <div class="cfm-panel">
                    <div class="cfm-panel-header">
                        <h2><?php _e('Fields', 'custom-fields-manager'); ?></h2>
                        <p class="cfm-panel-description">
                            <?php _e('Add and manage fields for this field group.', 'custom-fields-manager'); ?>
                        </p>
                    </div>
                    <div class="cfm-panel-body">
                        <div id="cfm-fields-container" class="cfm-fields-container">
                            <!-- Fields will be rendered by JavaScript -->
                        </div>
                        
                        <button type="button" class="cfm-btn cfm-btn-primary" id="cfm-add-field">
                            <span class="dashicons dashicons-plus"></span>
                            <?php _e('Add Field', 'custom-fields-manager'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Location Rules -->
                <div class="cfm-panel">
                    <div class="cfm-panel-header">
                        <h2><?php _e('Location Rules', 'custom-fields-manager'); ?></h2>
                        <p class="cfm-panel-description">
                            <?php _e('Define where this field group will appear.', 'custom-fields-manager'); ?>
                        </p>
                    </div>
                    <div class="cfm-panel-body">
                        <div id="cfm-location-rules" class="cfm-location-rules">
                            <!-- Location rules will be rendered by JavaScript -->
                        </div>
                        
                        <button type="button" class="cfm-btn cfm-btn-outline" id="cfm-add-rule-group">
                            <span class="dashicons dashicons-plus"></span>
                            <?php _e('Add Rule Group', 'custom-fields-manager'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="cfm-sidebar">
                <!-- Status -->
                <div class="cfm-panel">
                    <div class="cfm-panel-header">
                        <h3><?php _e('Status', 'custom-fields-manager'); ?></h3>
                    </div>
                    <div class="cfm-panel-body">
                        <div class="cfm-form-field">
                            <label class="cfm-toggle">
                                <input type="checkbox" name="active" value="1" <?php checked($field_group->is_active()); ?>>
                                <span class="cfm-toggle-slider"></span>
                                <span class="cfm-toggle-label"><?php _e('Active', 'custom-fields-manager'); ?></span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Settings -->
                <div class="cfm-panel">
                    <div class="cfm-panel-header">
                        <h3><?php _e('Settings', 'custom-fields-manager'); ?></h3>
                    </div>
                    <div class="cfm-panel-body">
                        <div class="cfm-form-field">
                            <label for="cfm-position"><?php _e('Position', 'custom-fields-manager'); ?></label>
                            <select name="position" id="cfm-position" class="cfm-form-select">
                                <option value="normal" <?php selected($field_group->get_position(), 'normal'); ?>><?php _e('Normal', 'custom-fields-manager'); ?></option>
                                <option value="side" <?php selected($field_group->get_position(), 'side'); ?>><?php _e('Side', 'custom-fields-manager'); ?></option>
                                <option value="advanced" <?php selected($field_group->get_position(), 'advanced'); ?>><?php _e('Advanced', 'custom-fields-manager'); ?></option>
                            </select>
                        </div>
                        
                        <div class="cfm-form-field">
                            <label for="cfm-style"><?php _e('Style', 'custom-fields-manager'); ?></label>
                            <select name="style" id="cfm-style" class="cfm-form-select">
                                <option value="default" <?php selected($field_group->get_style(), 'default'); ?>><?php _e('Standard', 'custom-fields-manager'); ?></option>
                                <option value="seamless" <?php selected($field_group->get_style(), 'seamless'); ?>><?php _e('Seamless', 'custom-fields-manager'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="cfm-panel">
                    <div class="cfm-panel-body">
                        <button type="submit" class="cfm-btn cfm-btn-primary cfm-btn-block">
                            <?php echo $field_group->get_id() ? __('Update Field Group', 'custom-fields-manager') : __('Publish Field Group', 'custom-fields-manager'); ?>
                        </button>
                        
                        <?php if ($field_group->get_id()): ?>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=cfm-field-groups&action=duplicate&id=' . $field_group->get_id()), 'cfm_duplicate_field_group'); ?>" class="cfm-btn cfm-btn-outline cfm-btn-block">
                                <?php _e('Duplicate Field Group', 'custom-fields-manager'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<div id="cfm-field-group-edit"></div>