# Customer Login Stats

Tracks WooCommerce customer logins and unique daily customers. Shows 30-day trend and daily breakdown in WP Admin.

## Install
1. Copy plugin folder to wp-content/plugins/customer-login-stats
2. Activate plugin from WP Admin -> Plugins

## Test
- Create a customer user (WooCommerce role = customer).
- Log in with that user (or simulate with WP-CLI tests below).
- Visit WP Admin -> Customer Login Stats.

## WP-CLI test helper
Create a customer:
  wp user create testcustomer test@example.com --role=customer --user_pass=pass123

Simulate login (increments counters):
  wp eval 'do_action("wp_login","testcustomer",get_user_by("login","testcustomer"));'

Check DB:
  wp db query "SELECT * FROM wp_customer_login_stats ORDER BY day_date DESC LIMIT 5;"
MD
