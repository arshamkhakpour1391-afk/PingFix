<?php
/** Only opt-in extension data is removed. Business data and pages are never deleted. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }
$settings = get_option( 'hamrah_shop_settings', array() );
if ( empty( $settings['delete_data'] ) ) { return; }
global $wpdb;
$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . 'hamrah_search`' ); // Fixed WordPress table prefix, no request data.
foreach ( array( 'hamrah_shop_settings', 'hamrah_shop_db_version', 'hamrah_shop_search_ready', 'hamrah_shop_search_generation', 'hamrah_shop_search_epoch', 'hamrah_shop_needs_setup', 'hamrah_shop_wishlist_page', 'hamrah_shop_pages' ) as $option ) { delete_option( $option ); }
wp_clear_scheduled_hook( 'hamrah_shop_rebuild_search' );
if ( function_exists( 'as_unschedule_all_actions' ) ) { as_unschedule_all_actions( 'hamrah_shop_rebuild_search', null, 'hamrah-shop' ); }
