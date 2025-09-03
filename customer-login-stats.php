cat > customer-login-stats.php <<'PHP'
<?php
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
        // will add hooks in later commits
    }
}

CLS_Customer_Login_Stats::instance();
PHP
