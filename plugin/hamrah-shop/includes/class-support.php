<?php
namespace HamrahShop;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Support {
	public static function digits( string $value ): string {
		return strtr( $value, array( '۰'=>'0', '۱'=>'1', '۲'=>'2', '۳'=>'3', '۴'=>'4', '۵'=>'5', '۶'=>'6', '۷'=>'7', '۸'=>'8', '۹'=>'9', '٠'=>'0', '١'=>'1', '٢'=>'2', '٣'=>'3', '٤'=>'4', '٥'=>'5', '٦'=>'6', '٧'=>'7', '٨'=>'8', '٩'=>'9' ) );
	}

	public static function normalize( string $value ): string {
		$value = self::digits( wp_strip_all_tags( $value ) );
		$value = strtr( $value, array( 'ي'=>'ی', 'ى'=>'ی', 'ك'=>'ک', "\u{200C}"=>' ', "\u{200D}"=>'', "\u{0640}"=>'', 'أ'=>'ا', 'إ'=>'ا', 'آ'=>'ا', 'ؤ'=>'و', 'ۀ'=>'ه' ) );
		$value = preg_replace( '/[\x{064B}-\x{065F}\x{0670}]/u', '', $value ) ?? '';
		$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
		return trim( preg_replace( '/\s+/u', ' ', $value ) ?? '' );
	}

	public static function clip( string $value, int $limit ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit, 'UTF-8' ) : substr( $value, 0, $limit );
	}

	/** Parse a bounded list, including no-JavaScript form submissions. */
	public static function list_param( mixed $value, int $limit = 100 ): array {
		if ( is_string( $value ) ) { $value = explode( ',', wp_unslash( $value ) ); }
		if ( ! is_array( $value ) ) { return array(); }
		$out = array();
		foreach ( array_slice( $value, 0, $limit ) as $item ) {
			if ( is_scalar( $item ) ) {
				$item = sanitize_title( wp_unslash( (string) $item ) );
				if ( '' !== $item ) { $out[] = $item; }
			}
		}
		return array_values( array_unique( $out ) );
	}

	public static function decimal( mixed $value ): string {
		if ( ! is_scalar( $value ) ) { return ''; }
		$value = self::digits( trim( wp_unslash( (string) $value ) ) );
		$value = str_replace( array( ',', '٬' ), '', $value );
		$value = str_replace( '٫', '.', $value );
		return preg_match( '/^\d{1,12}(?:\.\d{1,4})?$/D', $value ) ? $value : '';
	}

	public static function shop_url(): string {
		$url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';
		return $url && '-1' !== $url ? $url : home_url( '/' );
	}

	public static function wishlist_url(): string {
		$id = absint( get_option( 'hamrah_shop_wishlist_page', 0 ) );
		return $id && 'publish' === get_post_status( $id ) ? (string) get_permalink( $id ) : '';
	}

	public static function product_data( \WC_Product $product, string $context = 'catalog' ): ?array {
		$allowed = match ( $context ) { 'search'=>array( 'visible', 'search' ), 'wishlist'=>array( 'visible', 'catalog', 'search' ), default=>array( 'visible', 'catalog' ) };
		$visible = in_array( $product->get_catalog_visibility(), $allowed, true );
		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) && ! $product->is_in_stock() ) { $visible = false; }
		$visible = apply_filters( 'woocommerce_product_is_visible', $visible, $product->get_id() );
		if ( 'publish' !== $product->get_status() || post_password_required( $product->get_id() ) || ! $visible ) { return null; }
		$image = $product->get_image_id() ? wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ) : false;
		return array(
			'id' => $product->get_id(),
			'name' => $product->get_name(),
			'url' => esc_url_raw( $product->get_permalink() ),
			'image' => $image ? esc_url_raw( $image ) : '',
			'price' => html_entity_decode( wp_strip_all_tags( $product->get_price_html() ), ENT_QUOTES, 'UTF-8' ),
			'in_stock' => $product->is_in_stock(),
			'stock_label' => $product->is_in_stock() ? __( 'موجود', 'hamrah-shop' ) : __( 'ناموجود', 'hamrah-shop' ),
		);
	}

	/** Icon paths are fixed, never taken from a request or database. */
	public static function icon( string $name ): string {
		$paths = array(
			'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/>',
			'cart' => '<path d="M3 3h2l2 12h11l3-9H6M9 20h.01M17 20h.01"/><circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/>',
			'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
			'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0l-1 1-1-1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>',
			'filter' => '<path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="2"/><circle cx="15" cy="17" r="2"/>',
			'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
			'close' => '<path d="m6 6 12 12M6 18 18 6"/>',
			'arrow' => '<path d="M19 12H5m6-6-6 6 6 6"/>',
			'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
		);
		return '<svg class="hs-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( $paths[ $name ] ?? $paths['grid'] ) . '</svg>';
	}
}
