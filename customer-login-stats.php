cat > customer-login-stats.php <<'PHP' <?php
/**
 * Plugin Name: Customer Login Stats
 * Description: Tracks WooCommerce customer logins and displays daily statistics (logins + unique customers) to admins.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPLv2 or later
 * Text Domain: customer-login-stats
 */

if (!defined('ABSPATH')) {
    exit;
}

class CLS_Customer_Login_Stats {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        //activation hook
        register_activation_hook(__FILE__, [$this, 'activate']);
    }
    public function activate() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'customer_login_stats';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            day_date DATE NOT NULL,
            logins INT UNSIGNED NOT NULL DEFAULT 0,
            unique_logins INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (day_date)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}

CLS_Customer_Login_Stats::instance();
PHP