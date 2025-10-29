<?php
// includes/Database.php

class CFM_Database
{

    public static function create_tables()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cfm_field_groups';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            field_key varchar(100) NOT NULL,  -- CHANGED FROM 'key' to 'field_key'
            data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY field_key (field_key)  -- CHANGED FROM 'key' to 'field_key'
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Check for errors
        if (!empty($wpdb->last_error)) {
            error_log('CFM Database Error: ' . $wpdb->last_error);
            return false;
        }

        // Add version to track updates
        update_option('cfm_db_version', '1.0');

        return true;
    }

    public static function check_tables()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cfm_field_groups';

        // Check if table exists
        $result = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");

        if ($result !== $table_name) {
            // Table doesn't exist, create it
            return self::create_tables();
        }

        return true;
    }

    public static function drop_tables()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cfm_field_groups';

        $wpdb->query("DROP TABLE IF EXISTS $table_name");

        delete_option('cfm_db_version');
        delete_option('cfm_version');
        delete_option('cfm_installed_time');
    }
}
