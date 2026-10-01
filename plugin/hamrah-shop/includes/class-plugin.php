<?php
namespace HamrahShop;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Plugin {
	public static function activate(): void {
		Search::install_table();
		add_option( 'hamrah_shop_settings', Settings::defaults() );
		add_option( 'hamrah_shop_needs_setup', 1 );
		Search::queue_rebuild();
	}

	public static function translations(): void {
		if ( 'fa_IR' !== determine_locale() ) { return; }
		$custom = WP_LANG_DIR . '/woocommerce/woocommerce-fa_IR.mo';
		$native = WP_LANG_DIR . '/plugins/woocommerce-fa_IR.mo';
		if ( is_readable( $custom ) ) { load_textdomain( 'woocommerce', $custom ); }
		if ( is_readable( $native ) ) { load_textdomain( 'woocommerce', $native ); }
		else { load_textdomain( 'woocommerce', HAMRAH_SHOP_PATH . 'languages/woocommerce-fa_IR.mo' ); }
		add_filter( 'gettext_woocommerce', array( self::class, 'interface_word' ), 20, 2 );
	}

	public static function interface_word( string $translated, string $text ): string {
		if ( $translated !== $text || 'fa_IR' !== determine_locale() ) { return $translated; }
		static $words = null;
		if ( null === $words ) { $words = require HAMRAH_SHOP_PATH . 'includes/woocommerce-fa-words.php'; }
		if ( isset( $words[ $text ] ) ) { return $words[ $text ]; }
		// Iranian province names already include their authoritative Persian name in WooCommerce.
		if ( preg_match( '/^[^(]*\(([\x{0600}-\x{06FF}][^()]*)\)$/u', $text, $match ) ) { return $match[1]; }
		return $translated;
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'hamrah_shop_rebuild_search' );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'hamrah_shop_rebuild_search', null, 'hamrah-shop' );
		}
	}

	public static function boot(): void {
		new Settings();
		new Installer();
		if ( ! class_exists( '\WooCommerce' ) || version_compare( WC_VERSION, '10.0', '<' ) ) {
			add_action( 'admin_notices', static function () {
				if ( current_user_can( 'activate_plugins' ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'فروشگاه همراه به WooCommerce نسخهٔ ۱۰ یا بالاتر نیاز دارد. ابتدا ووکامرس را نصب، به‌روز و فعال کنید.', 'hamrah-shop' ) . '</p></div>';
				}
			} );
			return;
		}
		if ( '1' !== (string) get_option( 'hamrah_shop_db_version' ) ) { Search::install_table(); Search::queue_rebuild(); }
		add_action( 'init', array( self::class, 'translations' ), -1 );
		new Search();
		new Catalog();
		new Iran();
		new Wishlist();
		new Frontend();
	}
}
