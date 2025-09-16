<?php
/**
 * Plugin Name: Customer Login Stats
 * Description: Tracks WooCommerce customer logins and displays daily statistics (logins + unique customers) to admins.
 * Version: 1.0.0
 * Author: John Irungu
 * License: GPLv2 or later
 * Text Domain: customer-login-stats
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Main plugin class
class CLS_Customer_Login_Stats {
    private static $instance = null;

    // Singleton instance
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Constructor: add hooks
    private function __construct() {
        add_action('wp_login', [$this, 'handle_login'], 10, 2); // Track logins
        add_action('admin_menu', [$this, 'register_admin_page']); // Add admin menu
    }

    // Plugin activation: create stats table
    public static function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'customer_login_stats';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            day_date DATE NOT NULL,
            logins INT UNSIGNED NOT NULL DEFAULT 0,
            unique_logins INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (day_date)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql); // Create or update table
    }

    // Handle WooCommerce customer login event
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

        // Use site timezone for date
        $today = current_time('Y-m-d');

        // Increment total logins for today
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$this->table_name} (day_date, logins, unique_logins)
                 VALUES (%s, 1, 0)
                 ON DUPLICATE KEY UPDATE logins = logins + 1",
                $today
            )
        );

        // Track unique logins per user per day using user_meta
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

    // Register admin menu page
    public function register_admin_page() {
        $cap = current_user_can('manage_woocommerce') ? 'manage_woocommerce' : 'manage_options';
        add_menu_page(
            __('Customer Login Stats', 'customer-login-stats'),
            __('Customer Login Stats', 'customer-login-stats'),
            $cap,
            'customer-login-stats',
            [$this, 'render_admin_page'],
            'dashicons-chart-area',
            58
        );
    }

    // Render admin statistics page
    public function render_admin_page() {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'customer-login-stats'));
        }

        global $wpdb;
        $this->table_name = $wpdb->prefix . 'customer_login_stats';
        $today = current_time('Y-m-d');

        // Fetch last 30 days of stats
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT day_date, logins, unique_logins
                 FROM {$this->table_name}
                 WHERE day_date >= DATE_SUB(%s, INTERVAL 29 DAY)
                 ORDER BY day_date ASC",
                $today
            ),
            ARRAY_A
        );

        // Map data for chart and table
        $data_map = [];
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $data_map[$r['day_date']] = [
                    'logins' => (int)$r['logins'],
                    'unique' => (int)$r['unique_logins'],
                ];
            }
        }

        $labels = [];
        $logins = [];
        $uniques = [];
        $total_logins = 0;
        $total_uniques = 0;

        // Prepare data for each day
        for ($i = 29; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime($today . " -{$i} days"));
            $labels[] = $d;
            $l = isset($data_map[$d]) ? $data_map[$d]['logins'] : 0;
            $u = isset($data_map[$d]) ? $data_map[$d]['unique'] : 0;
            $logins[] = $l;
            $uniques[] = $u;
            $total_logins += $l;
            $total_uniques += $u;
        }

        $avg_logins = $total_logins / 30;
        $avg_uniques = $total_uniques / 30;

        // Output admin page HTML
        ?> 
        <div class="wrap">
            <h1><?php esc_html_e('Customer Login Stats', 'customer-login-stats'); ?></h1>

            <div style="display:flex; gap:16px; flex-wrap: wrap; margin: 16px 0;">
                <div style="flex:1; min-width:240px; background:#fff; border:1px solid #ddd; padding:16px; border-radius:8px;">
                    <h2 style="margin:0 0 8px;"><?php esc_html_e('Last 30 Days', 'customer-login-stats'); ?></h2>
                    <p style="margin:4px 0;"><strong><?php esc_html_e('Total Logins:', 'customer-login-stats'); ?></strong>
                        <?php echo esc_html(number_format_i18n($total_logins)); ?></p>
                    <p style="margin:4px 0;">
                        <strong><?php esc_html_e('Total Unique Customers:', 'customer-login-stats'); ?></strong>
                        <?php echo esc_html(number_format_i18n($total_uniques)); ?>
                    </p>
                    <p style="margin:4px 0;"><strong><?php esc_html_e('Avg Logins/Day:', 'customer-login-stats'); ?></strong>
                        <?php echo esc_html(number_format_i18n($avg_logins, 2)); ?></p>
                    <p style="margin:4px 0;"><strong><?php esc_html_e('Avg Unique/Day:', 'customer-login-stats'); ?></strong>
                        <?php echo esc_html(number_format_i18n($avg_uniques, 2)); ?></p>
                </div>
                <div style="flex:2; min-width:320px; background:#fff; border:1px solid #ddd; padding:16px; border-radius:8px;">
                    <h2 style="margin-top:0;"><?php esc_html_e('30-Day Trend', 'customer-login-stats'); ?></h2>
                    <canvas id="clsChart" height="120"></canvas>
                </div>
            </div>

            <div style="background:#fff; border:1px solid #ddd; padding:16px; border-radius:8px;">
                <h2 style="margin-top:0;"><?php esc_html_e('Daily Breakdown', 'customer-login-stats'); ?></h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Date', 'customer-login-stats'); ?></th>
                            <th><?php esc_html_e('Logins', 'customer-login-stats'); ?></th>
                            <th><?php esc_html_e('Unique Customers', 'customer-login-stats'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            for ($i = 29; $i >= 0; $i--) :
                                $d = date('Y-m-d', strtotime($today . " -{$i} days"));
                                $l = isset($data_map[$d]) ? (int)$data_map[$d]['logins'] : 0;
                                $u = isset($data_map[$d]) ? (int)$data_map[$d]['unique'] : 0;
                                ?>
                        <tr>
                            <td><?php echo esc_html($d); ?></td>
                            <td><?php echo esc_html($l); ?></td>
                            <td><?php echo esc_html($u); ?></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Chart.js for trend visualization -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        (function() {
            const ctx = document.getElementById('clsChart').getContext('2d');
            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo wp_json_encode($labels); ?>,
                    datasets: [{
                            label: '<?php echo esc_js(__('Logins', 'customer-login-stats')); ?>',
                            data: <?php echo wp_json_encode($logins); ?>,
                            tension: 0.3
                        },
                        {
                            label: '<?php echo esc_js(__('Unique Customers', 'customer-login-stats')); ?>',
                            data: <?php echo wp_json_encode($uniques); ?>,
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Count'
                            },
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        tooltip: {
                            enabled: true
                        }
                    }
                }
            });
        })();
        </script>
        <?php
    }
}

// Register activation hook in global scope
register_activation_hook(__FILE__, ['CLS_Customer_Login_Stats', 'activate']);

// Initialize plugin
CLS_Customer_Login_Stats::instance();
