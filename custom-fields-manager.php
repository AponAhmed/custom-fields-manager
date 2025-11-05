<?php

/**
 * Plugin Name: Custom Fields Manager
 * Plugin URI: https://example.com/custom-fields-manager
 * Description: Advanced custom fields management for WordPress with modern UI
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: custom-fields-manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CFM_VERSION', '1.0.0');
define('CFM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CFM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CFM_PLUGIN_FILE', __FILE__);

// Manual class loading function
function cfm_load_classes()
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $classes = [
        'Gutenberg_WYSIWYG',
        'Data_Utility',
        'Shortcodes',
        'Database',
        'Field_Group',
        'Field_Group_Repository',
        'Field_Renderer',
        'Meta_Box_Handler',
        'API'
    ];

    foreach ($classes as $class) {
        $file = CFM_PLUGIN_DIR . 'includes/' . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
        } else {
            error_log('CFM: Missing class file: ' . $file);
        }
    }

    // Admin classes (only load in admin)
    if (is_admin()) {
        $admin_file = CFM_PLUGIN_DIR . 'includes/Admin.php';
        if (file_exists($admin_file)) {
            require_once $admin_file;
        }
    }

    $loaded = true;
}

// Initialize the plugin
class CustomFieldsManager
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
        $this->init_hooks();
    }

    private function init_hooks()
    {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        register_uninstall_hook(__FILE__, ['CustomFieldsManager', 'uninstall']);

        add_action('plugins_loaded', [$this, 'init_plugin']);
        add_action('admin_init', [$this, 'check_db']);
    }

    public function activate()
    {
        // Load database class directly for activation
        require_once CFM_PLUGIN_DIR . 'includes/Database.php';

        $result = CFM_Database::create_tables();

        if ($result) {
            // Set default options
            update_option('cfm_version', CFM_VERSION);
            update_option('cfm_installed_time', time());
            update_option('cfm_db_version', '1.0');

            error_log('CFM: Plugin activated successfully');
        } else {
            error_log('CFM: Failed to create database tables');
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('Custom Fields Manager Pro could not be activated. Database tables could not be created.');
        }
    }

    public function deactivate()
    {
        // Cleanup if needed
        error_log('CFM: Plugin deactivated');
    }

    public static function uninstall()
    {
        // Load database class for uninstall
        require_once CFM_PLUGIN_DIR . 'includes/Database.php';
        CFM_Database::drop_tables();
    }

    public function check_db()
    {
        // Check if we need to create tables (for cases where activation hook didn't fire)
        if (!get_option('cfm_db_version')) {
            require_once CFM_PLUGIN_DIR . 'includes/Database.php';
            CFM_Database::create_tables();
        }
    }

    public function init_plugin()
    {
        // Load all classes
        cfm_load_classes();

        // Check if database is ready
        if (!get_option('cfm_db_version')) {
            add_action('admin_notices', [$this, 'db_notice']);
            return;
        }

        // Load text domain
        load_plugin_textdomain('custom-fields-manager', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Initialize components
        if (is_admin()) {
            new CFM_Admin();
        }

        //new CFM_Field_Renderer();
        CFM_Meta_Box_Handler::instance();
        //new CFM_API();
    }

    public function db_notice()
    {
?>
        <div class="notice notice-error">
            <p><?php _e('Custom Fields Manager Pro: Database tables are not set up. Please deactivate and reactivate the plugin.', 'custom-fields-manager'); ?></p>
        </div>
<?php
    }
}

// Initialize the plugin
function CFM()
{
    return CustomFieldsManager::instance();
}

// Start the plugin
CFM();
