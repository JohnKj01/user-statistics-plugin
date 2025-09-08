cat > uninstall.php <<'PHP' <?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'customer_login_stats';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

// remove user meta used for unique-day tracking
$users = get_users( array( 'fields' => 'ID' ) );
if ( ! empty( $users ) ) {
    foreach ( $users as $user_id ) {
        delete_user_meta( $user_id, '_cls_last_login_day' );
    }
}
PHP