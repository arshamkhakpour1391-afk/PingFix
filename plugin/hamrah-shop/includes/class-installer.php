<?php
namespace HamrahShop;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Explicit, idempotent setup. Never creates products, terms, users or orders. */
final class Installer {
	public function __construct() {
		add_action( 'admin_post_hamrah_shop_theme', array( $this, 'theme' ) );
		add_action( 'admin_post_hamrah_shop_pages', array( $this, 'pages' ) );
		add_action( 'admin_post_hamrah_shop_reindex', array( $this, 'reindex' ) );
	}

	private static function authorize( string $action, array $caps ): void {
		foreach ( $caps as $cap ) {
			if ( ! current_user_can( $cap ) ) { wp_die( esc_html__( 'دسترسی لازم برای این کار را ندارید.', 'hamrah-shop' ), '', array( 'response'=>403 ) ); }
		}
		check_admin_referer( $action );
	}

	private static function redirect( string $notice ): never {
		wp_safe_redirect( add_query_arg( 'hs_notice', $notice, admin_url( 'admin.php?page=hamrah-shop' ) ) );
		exit;
	}

	public static function render(): void {
		$messages = array(
			'theme_done'=>'قالب همراه نصب و فعال شد. لوگو و منوها را از نمایش تنظیم کنید.',
			'theme_failed'=>'نصب قالب انجام نشد. دسترسی نوشتن پوشهٔ قالب‌ها و وضعیت به‌روزرسانی‌های وردپرس را با پشتیبانی هاست بررسی کنید.',
			'theme_conflict'=>'قالبی با همین شناسه وجود دارد که متعلق به این بسته نیست. برای حفاظت از فایل‌های شما چیزی جایگزین نشد.',
			'pages_done'=>'برگه‌های کاربردی آماده شدند. برگه‌های اطلاعاتی و قوانین به‌صورت پیش‌نویس و بدون متن ساخته شده‌اند؛ آن‌ها را تکمیل و منتشر کنید.',
			'index_done'=>'بازسازی جستجو در صف قرار گرفت. اجرای زمان‌بندی وردپرس باید فعال باشد.',
			'wc_missing'=>'ابتدا WooCommerce نسخهٔ ۱۰ یا بالاتر را نصب و فعال کنید.',
			'pages_language'=>'برگه‌ها آماده شدند، اما بستهٔ زبان فارسی دریافت نشد. در تنظیمات ← عمومی زبان فارسی را انتخاب کنید و سپس پیشخوان ← به‌روزرسانی‌ها ← به‌روزرسانی ترجمه‌ها را اجرا کنید.',
			'pages_failed'=>'ساخت برخی برگه‌ها انجام نشد. دسترسی نوشتن پایگاه داده را با پشتیبانی هاست بررسی کنید؛ اجرای دوبارهٔ راه‌اندازی، برگهٔ تکراری نمی‌سازد.',
		);
		$notice = isset( $_GET['hs_notice'] ) && is_string( $_GET['hs_notice'] ) ? sanitize_key( wp_unslash( $_GET['hs_notice'] ) ) : '';
		if ( isset( $messages[ $notice ] ) ) { echo '<div class="notice ' . ( str_contains( $notice, 'failed' ) || str_contains( $notice, 'conflict' ) || 'wc_missing' === $notice ? 'notice-error' : 'notice-success' ) . '"><p>' . esc_html( $messages[ $notice ] ) . '</p></div>'; }
		if ( 'yes' === get_option( 'woocommerce_coming_soon' ) ) { echo '<div class="notice notice-info"><p>' . esc_html__( 'فروشگاه در حالت به‌زودیِ ووکامرس است. پس از تنظیم درگاه، ارسال و قوانین واقعی، از پیکربندی ووکامرس ← نمایان‌سازی سایت حالت زنده را انتخاب کنید.', 'hamrah-shop' ) . '</p></div>'; }
		echo '<section class="hs-admin-card"><h2>' . esc_html__( 'راه‌اندازی اولیه — بدون دادهٔ نمونه', 'hamrah-shop' ) . '</h2><p>' . esc_html__( 'این کارها فقط با انتخاب شما اجرا می‌شوند. محتوای موجود حذف یا بازنویسی نمی‌شود. نصب افزونه به‌تنهایی ظاهر سایت یا تنظیمات تجاری را تغییر نمی‌دهد.', 'hamrah-shop' ) . '</p><div class="hs-admin-grid">';
		echo '<div><h3>' . esc_html__( '۱. قالب فروشگاه', 'hamrah-shop' ) . '</h3><p>' . esc_html__( 'قالب در همین ZIP موجود است؛ برای نصب آن دانلود دیگری لازم نیست.', 'hamrah-shop' ) . '</p>';
		if ( current_user_can( 'install_themes' ) && current_user_can( 'switch_themes' ) ) {
			echo '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post"><input type="hidden" name="action" value="hamrah_shop_theme">';
			wp_nonce_field( 'hamrah_shop_theme' );
			submit_button( __( 'نصب و فعال‌سازی قالب همراه', 'hamrah-shop' ), 'secondary', 'submit', false );
			echo '</form>';
		}
		echo '</div><div><h3>' . esc_html__( '۲. برگه‌های فروشگاه', 'hamrah-shop' ) . '</h3><p>' . esc_html__( 'سبد، پرداخت، حساب و علاقه‌مندی‌ها کاربردی‌اند؛ متن صفحات حقوقی باید توسط شما نوشته شود.', 'hamrah-shop' ) . '</p>';
		if ( current_user_can( 'edit_pages' ) && current_user_can( 'manage_options' ) ) {
			echo '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post"><input type="hidden" name="action" value="hamrah_shop_pages">';
			wp_nonce_field( 'hamrah_shop_pages' );
			echo '<label class="hs-admin-inline"><input type="checkbox" name="classic" value="1" checked> ' . esc_html__( 'آماده‌سازی برگه‌های سبد و پرداخت کلاسیک؛ پیشنهادی برای درگاه‌های ایرانی (برگه‌های قبلی حفظ می‌شوند)', 'hamrah-shop' ) . '</label><label class="hs-admin-inline"><input type="checkbox" name="make_home" value="1"> ' . esc_html__( 'خانه و وبلاگِ آماده‌شده را صفحهٔ نخست و صفحهٔ نوشته‌ها قرار بده', 'hamrah-shop' ) . '</label><label class="hs-admin-inline"><input type="checkbox" name="persian" value="1"> ' . esc_html__( 'زبان عمومی سایت را فارسی کن', 'hamrah-shop' ) . '</label>';
			submit_button( __( 'آماده‌سازی برگه‌ها', 'hamrah-shop' ), 'secondary', 'submit', false );
			echo '</form>';
		}
		echo '</div><div><h3>' . esc_html__( '۳. نمایهٔ جستجو', 'hamrah-shop' ) . '</h3><p>' . esc_html( get_option( 'hamrah_shop_search_ready' ) ? __( 'نمایهٔ جستجو آماده است. تغییر محصولات به‌صورت خودکار ثبت می‌شود.', 'hamrah-shop' ) : __( 'جستجو فعال است؛ تکمیل نمایه در پس‌زمینه انجام می‌شود.', 'hamrah-shop' ) ) . '</p><form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post"><input type="hidden" name="action" value="hamrah_shop_reindex">';
		wp_nonce_field( 'hamrah_shop_reindex' );
		submit_button( __( 'بازسازی نمایهٔ جستجو', 'hamrah-shop' ), 'secondary', 'submit', false );
		echo '</form></div></div><p class="description">' . esc_html__( 'وردپرس یا افزونه‌های دیگر ممکن است محتوای پیش‌فرض داشته باشند؛ این بسته آن‌ها را ایجاد نمی‌کند و برای جلوگیری از حذف اطلاعات شما، آن‌ها را خودکار پاک نمی‌کند.', 'hamrah-shop' ) . '</p></section>';
	}

	public function theme(): void {
		self::authorize( 'hamrah_shop_theme', array( 'manage_woocommerce', 'install_themes', 'switch_themes' ) );
		$theme = wp_get_theme( 'hamrah-shop' );
		if ( $theme->exists() && 'hamrah-shop-theme' !== $theme->get( 'TextDomain' ) ) { self::redirect( 'theme_conflict' ); }
		if ( ! $theme->exists() ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			$upgrader = new \Theme_Upgrader( new \WP_Ajax_Upgrader_Skin() );
			$result = $upgrader->install( HAMRAH_SHOP_PATH . 'resources/hamrah-shop-theme.zip' );
			if ( is_wp_error( $result ) || ! $result || is_wp_error( $upgrader->skin->result ) ) { self::redirect( 'theme_failed' ); }
		}
		if ( is_multisite() && ! current_user_can( 'manage_network_themes' ) && ! $theme->is_allowed() ) { self::redirect( 'theme_failed' ); }
		if ( is_multisite() && current_user_can( 'manage_network_themes' ) ) { \WP_Theme::network_enable_theme( 'hamrah-shop' ); }
		switch_theme( 'hamrah-shop' );
		self::redirect( 'theme_done' );
	}

	/** Reuses existing pages, including WooCommerce block-based checkout pages. */
	public static function create_pages( bool $make_home = false, bool $persian = false, bool $classic = false ): array|\WP_Error {
		$map = get_option( 'hamrah_shop_pages', array() );
		$map = is_array( $map ) ? $map : array();
		$creation_failed = false;
		$definitions = array(
			'home'=>array( 'خانه', 'home', '', 'publish' ),
			'blog'=>array( 'وبلاگ', 'blog', '', 'publish' ),
			'shop'=>array( 'فروشگاه', 'shop', '', 'publish' ),
			'cart'=>array( 'سبد خرید', 'cart', '[woocommerce_cart]', 'publish' ),
			'checkout'=>array( 'تسویه حساب', 'checkout', '[woocommerce_checkout]', 'publish' ),
			'myaccount'=>array( 'حساب کاربری', 'my-account', '[woocommerce_my_account]', 'publish' ),
			'wishlist'=>array( 'علاقه‌مندی‌ها', 'wishlist', '[hamrah_wishlist]', 'publish' ),
			'about'=>array( 'درباره ما', 'about', '', 'draft' ),
			'contact'=>array( 'تماس با ما', 'contact', '', 'draft' ),
			'privacy'=>array( 'حریم خصوصی', 'privacy-policy', '', 'draft' ),
			'terms'=>array( 'قوانین و شرایط', 'terms', '', 'draft' ),
			'shipping'=>array( 'اطلاعات ارسال', 'shipping', '', 'draft' ),
			'returns'=>array( 'شرایط مرجوعی', 'returns', '', 'draft' ),
		);
		foreach ( $definitions as $key => $definition ) {
			$existing = isset( $map[ $key ] ) ? absint( $map[ $key ] ) : 0;
			if ( in_array( $key, array( 'shop', 'cart', 'checkout', 'myaccount' ), true ) ) {
				$wc_id = absint( get_option( 'woocommerce_' . $key . '_page_id', 0 ) );
				if ( $wc_id && 'page' === get_post_type( $wc_id ) && ! in_array( get_post_status( $wc_id ), array( 'trash', false ), true ) ) { $existing = $wc_id; }
			}
			if ( 'privacy' === $key && ! $existing ) { $existing = absint( get_option( 'wp_page_for_privacy_policy', 0 ) ); }
			$prefer_classic = $classic && in_array( $key, array( 'cart', 'checkout' ), true );
			$shortcode = 'cart' === $key ? 'woocommerce_cart' : 'woocommerce_checkout';
			if ( $prefer_classic && ( ! $existing || ! has_shortcode( (string) get_post_field( 'post_content', $existing ), $shortcode ) ) ) {
				$existing = 0; $definition[1] = 'hamrah-' . $key;
			}

			if ( ! $existing || 'page' !== get_post_type( $existing ) || 'trash' === get_post_status( $existing ) ) {
				$page = get_page_by_path( $definition[1], OBJECT, 'page' );
				if ( $prefer_classic && $page && ! has_shortcode( $page->post_content, $shortcode ) ) { $page = null; }
				if ( $page && 'trash' !== $page->post_status ) {
					$existing = $page->ID;
				} else {
					$id = wp_insert_post( array( 'post_type'=>'page', 'post_title'=>$definition[0], 'post_name'=>$definition[1], 'post_content'=>$definition[2], 'post_status'=>$definition[3], 'comment_status'=>'closed' ), true );
					if ( is_wp_error( $id ) ) { $creation_failed = true; continue; }
					$existing = $id;
				}
			}
			$map[ $key ] = (int) $existing;
			if ( in_array( $key, array( 'shop', 'cart', 'checkout', 'myaccount' ), true ) ) { update_option( 'woocommerce_' . $key . '_page_id', $existing ); }
			if ( 'wishlist' === $key ) { update_option( 'hamrah_shop_wishlist_page', $existing ); }
		}
		update_option( 'hamrah_shop_pages', $map );
		if ( $make_home && ! empty( $map['home'] ) && ! empty( $map['blog'] ) ) {
			update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $map['home'] ); update_option( 'page_for_posts', $map['blog'] );
		}
		$language_failed = false;
		if ( $persian ) {
			require_once ABSPATH . 'wp-admin/includes/translation-install.php';
			if ( in_array( 'fa_IR', get_available_languages(), true ) || wp_download_language_pack( 'fa_IR' ) ) { update_option( 'WPLANG', 'fa_IR' ); }
			else { $language_failed = true; }
		}
		if ( ! $creation_failed ) { delete_option( 'hamrah_shop_needs_setup' ); }
		flush_rewrite_rules( false );
		if ( $creation_failed ) { return new \WP_Error( 'hamrah_pages', __( 'ساخت برخی برگه‌ها انجام نشد.', 'hamrah-shop' ) ); }
		return $language_failed ? new \WP_Error( 'hamrah_language', __( 'بستهٔ زبان فارسی دریافت نشد.', 'hamrah-shop' ) ) : $map;
	}

	public function pages(): void {
		self::authorize( 'hamrah_shop_pages', array( 'manage_woocommerce', 'edit_pages', 'manage_options' ) );
		if ( ! class_exists( '\WooCommerce' ) || version_compare( WC_VERSION, '10.0', '<' ) ) { self::redirect( 'wc_missing' ); }
		$result = self::create_pages( ! empty( $_POST['make_home'] ), ! empty( $_POST['persian'] ), ! empty( $_POST['classic'] ) );
		self::redirect( is_wp_error( $result ) ? ( 'hamrah_language' === $result->get_error_code() ? 'pages_language' : 'pages_failed' ) : 'pages_done' );
	}

	public function reindex(): void {
		self::authorize( 'hamrah_shop_reindex', array( 'manage_woocommerce' ) );
		Search::queue_rebuild();
		self::redirect( 'index_done' );
	}
}
