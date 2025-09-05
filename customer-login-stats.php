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
        add_action('wp_login', [$this, 'handle_login'], 10, 2);

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
    public function handle_login($user_login, $user) {
    if (!($user instanceof WP_User)) {
        $user = get_user_by('login', $user_login);
        if (!$user) return;
    }

    // Only count WooCommerce customers
    $roles = (array) $user->roles;
    if (!in_array('customer', $roles, true)) {
        return;
    }

    global $wpdb;
    $this->table_name = $wpdb->prefix . 'customer_login_stats';

    // Use site timezone
    $today = current_time('Y-m-d');

    // increment total logins
    $wpdb->query(
        $wpdb->prepare(
            "INSERT INTO {$this->table_name} (day_date, logins, unique_logins)
             VALUES (%s, 1, 0)
             ON DUPLICATE KEY UPDATE logins = logins + 1",
            $today
        )
    );

    // track unique per user per day via user_meta
    $meta_key = '_cls_last_login_day';
    $last_day = get_user_meta($user->ID, $meta_key, true);

    if ($last_day !== $today) {
        update_user_meta($user->ID, $meta_key, $today);
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$this->table_name} (day_date, logins, unique_logins)
                 VALUES (%s, 0, 1)
                 ON DUPLICATE KEY UPDATE unique_logins = unique_logins + 1",
                $today
            )
        );
    }
}


CLS_Customer_Login_Stats::instance();
PHP