<?php
namespace HamrahShop;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Local-only wishlist. No tracking, accounts or customer data are manufactured. */
final class Wishlist {
	public function __construct() {
		add_shortcode( 'hamrah_wishlist', array( $this, 'shortcode' ) );
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'button' ), 15 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'button' ), 35 );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function button(): void {
		global $product;
		if ( ! Settings::get( 'wishlist' ) || ! $product instanceof \WC_Product ) { return; }
		echo '<button type="button" class="hs-wishlist-toggle" data-hs-wishlist="' . absint( $product->get_id() ) . '" aria-pressed="false" aria-label="' . esc_attr( sprintf( __( 'افزودن %s به علاقه‌مندی‌ها', 'hamrah-shop' ), $product->get_name() ) ) . '">' . Support::icon( 'heart' ) . '<span class="hs-wishlist-label">' . esc_html__( 'علاقه‌مندی', 'hamrah-shop' ) . '</span></button>';
	}

	public function shortcode(): string {
		if ( ! Settings::get( 'wishlist' ) ) { return '<p>' . esc_html__( 'فهرست علاقه‌مندی‌ها غیرفعال است.', 'hamrah-shop' ) . '</p>'; }
		return '<section class="hs-wishlist" data-hs-wishlist-page><p class="hs-muted">' . esc_html__( 'علاقه‌مندی‌ها روی همین مرورگر ذخیره می‌شوند و با حساب یا دستگاه‌های دیگر همگام نمی‌شوند.', 'hamrah-shop' ) . '</p><p data-hs-wishlist-status role="status" aria-live="polite">' . esc_html__( 'در حال دریافت فهرست…', 'hamrah-shop' ) . '</p><div class="hs-wishlist-grid" data-hs-wishlist-grid></div><noscript><p>' . esc_html__( 'برای استفاده از علاقه‌مندی‌ها، جاوااسکریپت مرورگر را فعال کنید.', 'hamrah-shop' ) . '</p></noscript></section>';
	}

	public function routes(): void {
		register_rest_route( 'hamrah-shop/v1', '/products', array( 'methods'=>'GET', 'permission_callback'=>'__return_true', 'callback'=>array( $this, 'products' ), 'args'=>array( 'ids'=>array( 'required'=>true, 'type'=>'string', 'maxLength'=>1200, 'sanitize_callback'=>'sanitize_text_field' ) ) ) );
	}

	public function products( \WP_REST_Request $request ): \WP_REST_Response {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', (string) $request->get_param( 'ids' ) ) ) ) ) );
		$ids = array_slice( $ids, 0, 100 ); $out = array();
		if ( Settings::get( 'wishlist' ) && $ids ) {
			$posts = get_posts( array( 'post_type'=>'product', 'post_status'=>'publish', 'has_password'=>false, 'post__in'=>$ids, 'orderby'=>'post__in', 'posts_per_page'=>count( $ids ), 'no_found_rows'=>true ) );
			foreach ( $posts as $post ) {
				$product = wc_get_product( $post->ID ); $data = $product ? Support::product_data( $product, 'wishlist' ) : null;
				if ( $data ) { $out[] = $data; }
			}
		}
		$response = new \WP_REST_Response( array( 'products'=>$out ), 200 ); $response->header( 'Cache-Control', 'no-store' ); return $response;
	}
}
