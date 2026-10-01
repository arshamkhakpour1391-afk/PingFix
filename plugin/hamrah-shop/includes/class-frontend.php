<?php
namespace HamrahShop;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Frontend {
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 25 );
		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'cart_fragment' ) );
		add_filter( 'woocommerce_product_is_visible', array( $this, 'public_visibility' ), 20, 2 );
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'buy_button' ) );
		add_action( 'wp_loaded', array( $this, 'buy_request' ), 15 );
		add_filter( 'woocommerce_add_to_cart_redirect', array( $this, 'buy_redirect' ), 20, 2 );
	}

	public function assets(): void {
		wp_enqueue_style( 'hamrah-shop-extension', HAMRAH_SHOP_URL . 'assets/css/store.css', array(), HAMRAH_SHOP_VERSION );
		wp_enqueue_script( 'hamrah-shop-store', HAMRAH_SHOP_URL . 'assets/js/store.js', array(), HAMRAH_SHOP_VERSION, array( 'strategy'=>'defer', 'in_footer'=>true ) );
		$config = array(
			'searchUrl'=>wp_make_link_relative( rest_url( 'hamrah-shop/v1/search' ) ),
			'termsUrl'=>wp_make_link_relative( rest_url( 'hamrah-shop/v1/terms' ) ),
			'productsUrl'=>wp_make_link_relative( rest_url( 'hamrah-shop/v1/products' ) ),
			'wishlistKey'=>'hamrah:wishlist:' . home_url( '/' ),
			'restNonce'=>is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
			'liveSearch'=>(bool) Settings::get( 'live_search' ),
			'wishlist'=>(bool) Settings::get( 'wishlist' ),
			'words'=>array(
				'loading'=>__( 'در حال دریافت…', 'hamrah-shop' ), 'noResults'=>__( 'نتیجه‌ای پیدا نشد.', 'hamrah-shop' ),
				'error'=>__( 'ارتباط برقرار نشد. دوباره تلاش کنید.', 'hamrah-shop' ),
				'updated'=>__( 'نتایج به‌روز شد.', 'hamrah-shop' ), 'noOptions'=>__( 'گزینه‌ای پیدا نشد.', 'hamrah-shop' ),
				'moreOptions'=>__( 'برای محدود کردن گزینه‌ها، جستجوی دقیق‌تری بنویسید.', 'hamrah-shop' ),
				'emptyWishlist'=>__( 'هنوز محصولی به علاقه‌مندی‌ها اضافه نکرده‌اید.', 'hamrah-shop' ),
				'unavailableWishlist'=>__( 'در حال حاضر محصول قابل نمایشی در فهرست شما نیست.', 'hamrah-shop' ),
				'clearWishlist'=>__( 'پاک کردن فهرست', 'hamrah-shop' ), 'retry'=>__( 'تلاش دوباره', 'hamrah-shop' ),
				'added'=>__( 'به علاقه‌مندی‌ها اضافه شد.', 'hamrah-shop' ), 'removed'=>__( 'از علاقه‌مندی‌ها حذف شد.', 'hamrah-shop' ),
				'view'=>__( 'مشاهدهٔ محصول', 'hamrah-shop' ), 'remove'=>__( 'حذف از علاقه‌مندی‌ها', 'hamrah-shop' ),
				'noImage'=>__( 'تصویر ثبت نشده', 'hamrah-shop' ),
				'storageError'=>__( 'مرورگر اجازهٔ ذخیرهٔ علاقه‌مندی‌ها را نمی‌دهد. تنظیمات حریم خصوصی مرورگر را بررسی کنید.', 'hamrah-shop' ),
				'limit'=>__( 'حداکثر ۱۰۰ محصول در علاقه‌مندی‌ها نگهداری می‌شود.', 'hamrah-shop' ),
			),
		);
		wp_add_inline_script( 'hamrah-shop-store', 'window.HamrahShop = ' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';', 'before' );
		// Core handles cached cart counts and standard gateway/cart events.
		if ( 'hamrah-shop' === get_stylesheet() ) { wp_enqueue_script( 'wc-cart-fragments' ); }
	}

	public function public_visibility( bool $visible, int $id ): bool {
		return ! is_admin() && post_password_required( $id ) ? false : $visible;
	}

	public function cart_fragment( array $fragments ): array {
		$count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
		$fragments['.hs-cart-count'] = '<span class="hs-cart-count" aria-label="' . esc_attr( sprintf( __( '%s کالا در سبد', 'hamrah-shop' ), $count ) ) . '">' . esc_html( number_format_i18n( $count ) ) . '</span>';
		return $fragments;
	}

	public function buy_button(): void {
		global $product;
		if ( ! Settings::get( 'buy_now' ) || ! $product instanceof \WC_Product || ! $product->is_type( array( 'simple', 'variable' ) ) || ! $product->is_purchasable() || ! $product->is_in_stock() ) { return; }
		wp_nonce_field( 'hamrah_buy_' . $product->get_id(), 'hamrah_buy_nonce', false );
		echo '<button type="submit" name="hs_buy_now" value="' . absint( $product->get_id() ) . '" class="button hs-buy-now single_add_to_cart_button' . ( $product->is_type( 'variable' ) ? ' disabled wc-variation-selection-needed' : '' ) . '">' . esc_html__( 'خرید و رفتن به پرداخت', 'hamrah-shop' ) . '</button>';
	}

	private static function buying(): int {
		if ( ! Settings::get( 'buy_now' ) || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! is_scalar( $_POST['hs_buy_now'] ?? null ) || ! is_string( $_POST['hamrah_buy_nonce'] ?? null ) ) { return 0; }
		$id = absint( $_POST['hs_buy_now'] );
		if ( ! $id || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hamrah_buy_nonce'] ) ), 'hamrah_buy_' . $id ) ) { return 0; }
		$product = wc_get_product( $id );
		return $product && $product->is_type( array( 'simple', 'variable' ) ) ? $id : 0;
	}

	public function buy_request(): void {
		$id = self::buying();
		if ( $id && ( ! isset( $_REQUEST['add-to-cart'] ) || ( is_scalar( $_REQUEST['add-to-cart'] ) && absint( $_REQUEST['add-to-cart'] ) === $id ) ) ) {
			$_POST['add-to-cart'] = $id; $_REQUEST['add-to-cart'] = $id;
		}
	}

	public function buy_redirect( string $url, mixed $product = null ): string {
		$id = self::buying();
		return $id && $product instanceof \WC_Product && $product->get_id() === $id ? wc_get_checkout_url() : $url;
	}
}
