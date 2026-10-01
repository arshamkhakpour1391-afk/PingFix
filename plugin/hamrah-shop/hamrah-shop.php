<?php
/**
 * Plugin Name: فروشگاه همراه
 * Description: فروشگاه فارسی، فیلتر پویا، جستجوی محصولات و قالب همراه برای WooCommerce؛ بدون دادهٔ نمونه.
 * Version: 1.0.0
 * Requires at least: 6.8
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * WC requires at least: 10.0
 * WC tested up to: 11.1
 * Text Domain: hamrah-shop
 * Domain Path: /languages
 * License: GPL-3.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HAMRAH_SHOP_VERSION', '1.0.0' );
define( 'HAMRAH_SHOP_FILE', __FILE__ );
define( 'HAMRAH_SHOP_PATH', plugin_dir_path( __FILE__ ) );
define( 'HAMRAH_SHOP_URL', plugin_dir_url( __FILE__ ) );

foreach ( array( 'support', 'settings', 'installer', 'search', 'catalog', 'iran', 'wishlist', 'frontend', 'plugin' ) as $module ) {
	require_once HAMRAH_SHOP_PATH . 'includes/class-' . $module . '.php';
}

add_action( 'before_woocommerce_init', static function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', HAMRAH_SHOP_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', HAMRAH_SHOP_FILE, true );
	}
} );

register_activation_hook( __FILE__, array( '\HamrahShop\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\HamrahShop\Plugin', 'deactivate' ) );
add_action( 'plugins_loaded', array( '\HamrahShop\Plugin', 'boot' ), 30 );
