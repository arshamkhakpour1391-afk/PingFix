<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function hs_theme_setup(): void {
	load_theme_textdomain( 'hamrah-shop-theme', get_template_directory() . '/languages' );
	if ( str_starts_with( get_locale(), 'fa' ) ) { global $wp_locale; if ( $wp_locale instanceof WP_Locale ) { $wp_locale->text_direction = 'rtl'; } }
	add_theme_support( 'title-tag' ); add_theme_support( 'post-thumbnails' ); add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'custom-logo', array( 'height'=>96, 'width'=>280, 'flex-height'=>true, 'flex-width'=>true ) );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'align-wide' ); add_theme_support( 'responsive-embeds' ); add_theme_support( 'editor-styles' ); add_theme_support( 'wp-block-styles' );
	add_theme_support( 'woocommerce', array( 'product_grid'=>array( 'default_rows'=>3, 'min_rows'=>1, 'max_rows'=>6, 'default_columns'=>4, 'min_columns'=>2, 'max_columns'=>5 ) ) );
	foreach ( array( 'wc-product-gallery-zoom', 'wc-product-gallery-lightbox', 'wc-product-gallery-slider' ) as $support ) { add_theme_support( $support ); }
	register_nav_menus( array( 'primary'=>__( 'منوی اصلی فروشگاه', 'hamrah-shop-theme' ), 'footer'=>__( 'لینک‌های پایین سایت', 'hamrah-shop-theme' ), 'legal'=>__( 'قوانین و اطلاعات ارسال', 'hamrah-shop-theme' ) ) );
	add_editor_style( 'assets/css/editor.css' );
	$GLOBALS['content_width'] = 1240;
}
add_action( 'after_setup_theme', 'hs_theme_setup' );
function hs_theme_woo_wrappers(): void {
	remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
	remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
	remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
}
add_action( 'init', 'hs_theme_woo_wrappers', 30 );

function hs_theme_setting( string $key, mixed $fallback = '' ): mixed {
	if ( class_exists( '\HamrahShop\Settings' ) ) { return \HamrahShop\Settings::get( $key ) ?? $fallback; }
	$settings = get_option( 'hamrah_shop_settings', array() );
	return is_array( $settings ) ? ( $settings[ $key ] ?? $fallback ) : $fallback;
}
function hs_theme_has_woo(): bool { return class_exists( '\WooCommerce' ) && function_exists( 'wc_get_product' ); }
function hs_theme_shop_url(): string { return hs_theme_has_woo() && wc_get_page_id( 'shop' ) > 0 ? wc_get_page_permalink( 'shop' ) : home_url( '/' ); }
function hs_theme_icon( string $name ): string {
	if ( class_exists( '\HamrahShop\Support' ) ) { return \HamrahShop\Support::icon( $name ); }
	$paths = array( 'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>', 'search'=>'<circle cx="10" cy="10" r="6"/><path d="m15 15 5 5"/>', 'cart'=>'<path d="M3 3h2l2 12h11l3-9H6M9 20h.01M17 20h.01"/>', 'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>', 'close'=>'<path d="m6 6 12 12M6 18 18 6"/>', 'filter'=>'<path d="M3 6h18M6 12h12M9 18h6"/>', 'arrow'=>'<path d="M19 12H5m6-6-6 6 6 6"/>', 'grid'=>'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>' );
	return '<svg class="hs-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( $paths[ $name ] ?? $paths['grid'] ) . '</svg>';
}

/** Choose accessible text for a configurable solid-color control. */
function hs_theme_contrast( string $hex ): string {
	$hex = ltrim( $hex, '#' ); if ( strlen( $hex ) === 3 ) { $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2]; }
	$rgb = array( hexdec( substr( $hex, 0, 2 ) ) / 255, hexdec( substr( $hex, 2, 2 ) ) / 255, hexdec( substr( $hex, 4, 2 ) ) / 255 );
	$linear = array_map( static fn( $c ) => $c <= .04045 ? $c / 12.92 : pow( ( $c + .055 ) / 1.055, 2.4 ), $rgb );
	$l = .2126 * $linear[0] + .7152 * $linear[1] + .0722 * $linear[2];
	return ( 1.05 / ( $l + .05 ) ) >= ( ( $l + .05 ) / .05 ) ? '#ffffff' : '#000000';
}
function hs_theme_assets(): void {
	wp_enqueue_style( 'hamrah-shop-theme', get_template_directory_uri() . '/assets/css/theme.css', array(), '1.0.0' );
	$defaults = array( 'primary'=>'#047857', 'ink'=>'#172b29', 'background'=>'#f5f7f6', 'surface'=>'#ffffff' ); $tokens = array();
	foreach ( $defaults as $key => $default ) { $tokens[ $key ] = sanitize_hex_color( (string) hs_theme_setting( $key, $default ) ) ?: $default; }
	$css = ':root{'; foreach ( $tokens as $key => $value ) { $css .= '--hs-' . $key . ':' . $value . ';'; }
	$css .= '--hs-accent-contrast:' . hs_theme_contrast( $tokens['primary'] ) . ';--hs-ink-contrast:' . hs_theme_contrast( $tokens['ink'] ) . ';--hs-radius:' . min( 28, max( 0, absint( hs_theme_setting( 'radius', 16 ) ) ) ) . 'px;}';
	wp_add_inline_style( 'hamrah-shop-theme', $css );
	wp_enqueue_script( 'hamrah-shop-navigation', get_template_directory_uri() . '/assets/navigation.js', array(), '1.0.0', array( 'strategy'=>'defer', 'in_footer'=>true ) );
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) { wp_enqueue_script( 'comment-reply' ); }
}
add_action( 'wp_enqueue_scripts', 'hs_theme_assets', 15 );

function hs_theme_sidebars(): void {
	register_sidebar( array( 'name'=>__( 'ابزارک‌های پایین سایت', 'hamrah-shop-theme' ), 'id'=>'footer', 'description'=>__( 'فقط ابزارک‌هایی که خودتان اضافه کنید نمایش داده می‌شوند.', 'hamrah-shop-theme' ), 'before_widget'=>'<section id="%1$s" class="hs-footer-widget %2$s">', 'after_widget'=>'</section>', 'before_title'=>'<h3>', 'after_title'=>'</h3>' ) );
}
add_action( 'widgets_init', 'hs_theme_sidebars' );
add_filter( 'loop_shop_columns', static fn( $columns ) => min( 5, max( 2, absint( hs_theme_setting( 'shop_columns', 4 ) ) ) ), 30 );

function hs_theme_search( string $id = 'header' ): void {
	?>
	<form role="search" method="get" class="hs-search" data-hs-search action="<?php echo esc_url( home_url( '/' ) ); ?>">
		<label class="screen-reader-text" for="hs-search-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'جستجوی محصولات', 'hamrah-shop-theme' ); ?></label>
		<input type="search" id="hs-search-<?php echo esc_attr( $id ); ?>" name="s" value="<?php echo esc_attr( get_search_query( false ) ); ?>" placeholder="<?php esc_attr_e( 'جستجوی محصول، مدل یا کد کالا…', 'hamrah-shop-theme' ); ?>" autocomplete="off" maxlength="180" role="combobox" aria-haspopup="listbox" aria-autocomplete="list" aria-controls="hs-suggestions-<?php echo esc_attr( $id ); ?>" aria-expanded="false">
		<input type="hidden" name="post_type" value="product">
		<button type="submit" aria-label="<?php esc_attr_e( 'جستجو', 'hamrah-shop-theme' ); ?>"><?php echo hs_theme_icon( 'search' ); ?></button>
		<div class="hs-search-suggestions" id="hs-suggestions-<?php echo esc_attr( $id ); ?>" data-hs-search-results hidden></div>
	</form>
	<?php
}

function hs_theme_primary_menu(): void {
	if ( has_nav_menu( 'primary' ) ) { wp_nav_menu( array( 'theme_location'=>'primary', 'container'=>false, 'menu_class'=>'hs-menu', 'fallback_cb'=>false, 'depth'=>3 ) ); }
	elseif ( hs_theme_has_woo() ) { echo '<ul class="hs-menu"><li><a href="' . esc_url( hs_theme_shop_url() ) . '">' . esc_html__( 'فروشگاه', 'hamrah-shop-theme' ) . '</a></li></ul>'; }
}

/** Missing-image state is an icon, never a fabricated product photograph. */
function hs_theme_missing_image( string $src ): string {
	return get_template_directory_uri() . '/assets/image-unavailable.svg';
}
add_filter( 'woocommerce_placeholder_img_src', 'hs_theme_missing_image' );
function hs_theme_image_alt( string $html ): string {
	$tags = new WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag( 'IMG' ) ) { $tags->set_attribute( 'alt', __( 'تصویر ثبت نشده', 'hamrah-shop-theme' ) ); }
	return $tags->get_updated_html();
}
add_filter( 'woocommerce_placeholder_img', 'hs_theme_image_alt' );

function hs_theme_home_products( string $kind ): bool {
	if ( ! hs_theme_has_woo() ) { return false; }
	$visibility = wc_get_product_visibility_term_ids(); $exclude = array( $visibility['exclude-from-catalog'] );
	if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) { $exclude[] = $visibility['outofstock']; }
	$args = array( 'post_type'=>'product', 'post_status'=>'publish', 'has_password'=>false, 'posts_per_page'=>min( 24, max( 1, absint( hs_theme_setting( 'home_limit', 8 ) ) ) ), 'orderby'=>'date', 'order'=>'DESC', 'no_found_rows'=>true, 'ignore_sticky_posts'=>true, 'tax_query'=>array( array( 'taxonomy'=>'product_visibility', 'field'=>'term_taxonomy_id', 'terms'=>$exclude, 'operator'=>'NOT IN' ) ) );
	if ( 'sale' === $kind ) { $ids = wc_get_product_ids_on_sale(); if ( ! $ids ) { return false; } $args['post__in'] = $ids; }
	$query = new WP_Query( $args ); if ( ! $query->have_posts() ) { return false; }
	echo '<section class="hs-home-products"><div class="hs-section-heading"><h2>' . esc_html( 'sale' === $kind ? __( 'محصولات تخفیف‌دار', 'hamrah-shop-theme' ) : __( 'جدیدترین محصولات', 'hamrah-shop-theme' ) ) . '</h2><a href="' . esc_url( 'sale' === $kind ? add_query_arg( 'hs_sale', '1', hs_theme_shop_url() ) : hs_theme_shop_url() ) . '">' . esc_html__( 'مشاهدهٔ همه', 'hamrah-shop-theme' ) . '</a></div><div class="woocommerce">';
	wc_set_loop_prop( 'columns', min( 5, max( 2, absint( hs_theme_setting( 'shop_columns', 4 ) ) ) ) ); wc_set_loop_prop( 'name', 'hamrah_home_' . $kind );
	woocommerce_product_loop_start(); while ( $query->have_posts() ) { $query->the_post(); wc_get_template_part( 'content', 'product' ); } woocommerce_product_loop_end();
	wp_reset_postdata(); wc_reset_loop(); echo '</div></section>'; return true;
}

function hs_theme_archive_title( string $title ): string {
	if ( is_search() && 'product' === get_query_var( 'post_type' ) ) { return sprintf( __( 'نتایج جستجو: %s', 'hamrah-shop-theme' ), get_search_query( false ) ); }
	return $title;
}
add_filter( 'woocommerce_page_title', 'hs_theme_archive_title' );
