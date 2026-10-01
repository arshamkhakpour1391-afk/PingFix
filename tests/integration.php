<?php
/**
 * Real WordPress/WooCommerce integration checks. NEVER included in release ZIPs.
 * Run only on a disposable installation through scripts/test-runtime.mjs.
 */
if ( ! defined( 'HAMRAH_TEST_ALLOW' ) || true !== HAMRAH_TEST_ALLOW || ! defined( 'HAMRAH_TEST_WP_ROOT' ) ) { exit( 1 ); }
$_SERVER['HTTP_HOST'] = '127.0.0.1:8080'; $_SERVER['REQUEST_METHOD'] = 'GET';
require HAMRAH_TEST_WP_ROOT . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
add_filter( 'pre_wp_mail', '__return_true' );
wp_set_current_user( 1 );

$results = array();
function hs_assert( bool $condition, string $name ): void {
	global $results;
	$results[] = array( 'name'=>$name, 'passed'=>$condition );
	if ( ! $condition ) { throw new RuntimeException( 'FAILED: ' . $name ); }
}
function hs_real_product( string $name, string $sku, string $price, array $cats, array $terms ): WC_Product_Simple {
	$product = new WC_Product_Simple(); $product->set_name( $name ); $product->set_sku( $sku ); $product->set_status( 'publish' ); $product->set_regular_price( $price ); $product->set_category_ids( $cats ); $product->set_manage_stock( true ); $product->set_stock_quantity( 10 );
	$attributes = array();
	foreach ( $terms as $taxonomy => $values ) {
		if ( str_starts_with( $taxonomy, 'pa_' ) ) { $a = new WC_Product_Attribute(); $a->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) ); $a->set_name( $taxonomy ); $a->set_options( $values ); $a->set_visible( true ); $attributes[] = $a; }
	}
	$product->set_attributes( $attributes ); $product->save();
	foreach ( $terms as $taxonomy => $values ) { wp_set_object_terms( $product->get_id(), $values, $taxonomy ); }
	return $product;
}
function hs_rest_search_ids( string $q ): array {
	$request = new WP_REST_Request( 'GET', '/hamrah-shop/v1/search' ); $request->set_param( 'q', $q );
	$response = rest_do_request( $request );
	if ( $response->get_status() !== 200 ) { throw new RuntimeException( 'REST failed: ' . wp_json_encode( $response->get_data() ) ); }
	return array_column( $response->get_data()['products'], 'id' );
}

hs_assert( class_exists( 'WooCommerce' ) && version_compare( WC_VERSION, '10.0', '>=' ), 'Real WooCommerce dependency' );
hs_assert( 0 === (int) wp_count_posts( 'product' )->publish, 'Initial installation has zero products' );
$before = wp_count_posts( 'page' );
$map = HamrahShop\Installer::create_pages( true, false ); $second = HamrahShop\Installer::create_pages( true, false );
hs_assert( is_array( $map ) && $map === $second && $before->publish === wp_count_posts( 'page' )->publish, 'Page setup is idempotent' );
foreach ( array( 'about', 'contact', 'privacy', 'terms', 'shipping', 'returns' ) as $key ) {
	hs_assert( 'draft' === get_post_status( $map[ $key ] ) && '' === get_post_field( 'post_content', $map[ $key ] ), 'Information page remains an empty draft: ' . $key );
}
foreach ( array( 'shop'=>'فروشگاه', 'cart'=>'سبد خرید', 'checkout'=>'تسویه حساب', 'myaccount'=>'حساب کاربری' ) as $key => $title ) { wp_update_post( array( 'ID'=>$map[$key], 'post_title'=>$title ) ); }

hs_assert( '+989123456789' === HamrahShop\Iran::phone( '۰۹۱۲۳۴۵۶۷۸۹' ), 'Persian mobile normalization' );
hs_assert( '+989123456789' === HamrahShop\Iran::phone( '0098 912-345-6789' ), 'International mobile normalization' );
hs_assert( null === HamrahShop\Iran::phone( '02112345678' ), 'Invalid Iranian mobile rejected' );
hs_assert( '1234567890' === HamrahShop\Iran::postcode( '۱۲۳۴۵۶۷۸۹۰' ), 'Persian postcode normalization' );
$iran = new HamrahShop\Iran(); $errors = new WP_Error();
$iran->validate( array( 'billing_country'=>'IR', 'billing_phone'=>'invalid', 'billing_postcode'=>'123' ), $errors );
hs_assert( $errors->has_errors(), 'Classic checkout rejects invalid phone/postcode' );
$customer = new WC_Customer(); $customer->set_billing_country( 'IR' ); $customer->set_billing_phone( '۰۹۱۲۳۴۵۶۷۸۹' ); $customer->set_billing_postcode( '۱۲۳۴۵۶۷۸۹۰' );
$iran->block_customer( $customer, new WP_REST_Request() );
hs_assert( '+989123456789' === $customer->get_billing_phone(), 'Block checkout customer normalization' );
$block_order = new WC_Order(); $block_order->set_billing_country( 'IR' ); $block_order->set_billing_phone( 'bad' );
$rejected = false; try { $iran->block_order( $block_order, new WP_REST_Request() ); } catch ( Automattic\WooCommerce\StoreApi\Exceptions\RouteException $e ) { $rejected = true; }
hs_assert( $rejected, 'Block checkout order validation' );
$schema = $iran->schema_currency( array( 'offers'=>array( 'priceCurrency'=>'IRT', 'price'=>'100' ) ) );
hs_assert( 'IRR' === $schema['offers']['priceCurrency'] && '1000' === $schema['offers']['price'], 'Toman JSON-LD is represented in real ISO IRR units only' );
hs_assert( isset( get_woocommerce_currencies()['IRT'] ) && 'تومان' === get_woocommerce_currency_symbol( 'IRT' ), 'Toman registration; no implicit price conversion' );

$cat_a = wp_insert_term( 'دستهٔ آزمون اصلی', 'product_cat', array( 'slug'=>'hs-test-category' ) );
$cat_b = wp_insert_term( 'زیرگروه آزمون', 'product_cat', array( 'slug'=>'hs-test-subcategory', 'parent'=>$cat_a['term_id'] ) );
$brand = wp_insert_term( 'برند آزمون', 'product_brand', array( 'slug'=>'hs-test-brand' ) );
$attribute_id = wc_create_attribute( array( 'name'=>'رنگ آزمون', 'slug'=>'hs-test-color', 'type'=>'select', 'order_by'=>'menu_order', 'has_archives'=>true ) );
hs_assert( ! is_wp_error( $attribute_id ), 'Create a global WooCommerce attribute' );
$taxonomy = wc_attribute_taxonomy_name( 'hs-test-color' );
register_taxonomy( $taxonomy, array( 'product' ), array( 'label'=>'رنگ آزمون', 'public'=>true, 'hierarchical'=>true ) );
$blue = wp_insert_term( 'آبی آزمون', $taxonomy, array( 'slug'=>'hs-test-blue' ) );
$black = wp_insert_term( 'مشکی آزمون', $taxonomy, array( 'slug'=>'hs-test-black' ) );
$terms = array( 'product_brand'=>array( (int) $brand['term_id'] ), $taxonomy=>array( (int) $blue['term_id'] ) );
$base = hs_real_product( 'محصول آزمون پایه', 'HS-TEST-BASE', '250000', array( $cat_a['term_id'] ), $terms );
$sale = hs_real_product( 'محصول آزمون تخفیف', 'HS-TEST-SALE', '350000', array( $cat_b['term_id'] ), array( $taxonomy=>array( (int) $black['term_id'] ) ) );
$sale->set_sale_price( '275000' ); $sale->set_date_on_sale_from( time() - 3600 ); $sale->set_date_on_sale_to( time() + 86400 ); $sale->save();
$out = hs_real_product( 'محصول آزمون ناموجود', 'HS-TEST-OUT', '100000', array( $cat_a['term_id'] ), $terms ); $out->set_stock_quantity( 0 ); $out->set_stock_status( 'outofstock' ); $out->save();
$back = hs_real_product( 'محصول آزمون پیش‌خرید', 'HS-TEST-BACK', '150000', array( $cat_a['term_id'] ), $terms ); $back->set_backorders( 'notify' ); $back->set_stock_quantity( 0 ); $back->set_stock_status( 'onbackorder' ); $back->save();
$hidden = hs_real_product( 'محصول آزمون پنهان', 'HS-TEST-HIDDEN', '150000', array( $cat_a['term_id'] ), $terms ); $hidden->set_catalog_visibility( 'hidden' ); $hidden->save();
$draft = hs_real_product( 'محصول آزمون پیش‌نویس', 'HS-TEST-DRAFT', '150000', array( $cat_a['term_id'] ), $terms ); $draft->set_status( 'draft' ); $draft->save();
$search_only = hs_real_product( 'محصول فقط جستجو', 'HS-TEST-SEARCH', '175000', array( $cat_a['term_id'] ), $terms ); $search_only->set_catalog_visibility( 'search' ); $search_only->save();
$locked = hs_real_product( 'محصول آزمون رمزدار', 'HS-TEST-LOCKED', '150000', array( $cat_a['term_id'] ), $terms ); wp_update_post( array( 'ID'=>$locked->get_id(), 'post_password'=>wp_generate_password() ) );

// Actual WordPress media upload/gallery; only lives in this disposable test database.
$bitmap = imagecreatetruecolor( 1000, 1000 ); $background = imagecolorallocate( $bitmap, 238, 244, 240 ); imagefill( $bitmap, 0, 0, $background );
ob_start(); imagepng( $bitmap ); $png = ob_get_clean(); imagedestroy( $bitmap );
$upload = wp_upload_bits( 'hs-integration-image.png', null, $png );
hs_assert( empty( $upload['error'] ), 'WordPress media upload' );
$image_id = wp_insert_attachment( array( 'post_title'=>'تصویر آزمون موقت', 'post_mime_type'=>'image/png', 'post_status'=>'inherit' ), $upload['file'] );
wp_update_attachment_metadata( $image_id, wp_generate_attachment_metadata( $image_id, $upload['file'] ) );
$base->set_image_id( $image_id ); $base->set_gallery_image_ids( array( $image_id ) ); $base->save();

$attribute = new WC_Product_Attribute(); $attribute->set_id( $attribute_id ); $attribute->set_name( $taxonomy ); $attribute->set_options( array( $blue['term_id'], $black['term_id'] ) ); $attribute->set_visible( true ); $attribute->set_variation( true );
$variable = new WC_Product_Variable(); $variable->set_name( 'محصول آزمون متغیر' ); $variable->set_status( 'publish' ); $variable->set_sku( 'HS-TEST-VARIABLE' ); $variable->set_category_ids( array( $cat_b['term_id'] ) ); $variable->set_attributes( array( $attribute ) ); $variable->set_image_id( $image_id ); $variable->save();
$variation_ids = array();
foreach ( array( 'hs-test-blue', 'hs-test-black' ) as $index => $color ) {
	$variation = new WC_Product_Variation(); $variation->set_parent_id( $variable->get_id() ); $variation->set_status( 'publish' ); $variation->set_attributes( array( $taxonomy=>$color ) ); $variation->set_sku( 'HS-TEST-VAR-0' . ( $index + 1 ) ); $variation->set_regular_price( '400000' ); $variation->set_manage_stock( true ); $variation->set_stock_quantity( 3 ); $variation->set_image_id( $image_id ); $variation->save(); $variation_ids[] = $variation->get_id();
}
WC_Product_Variable::sync( $variable->get_id() );
hs_assert( 2 === count( wc_get_product( $variable->get_id() )->get_children() ), 'Real variable product and variations' );
$ids = array_map( static fn( $p ) => $p->get_id(), array( $base, $sale, $out, $back, $hidden, $draft, $search_only, $locked, $variable ) );
foreach ( $ids as $id ) { HamrahShop\Search::index_product( $id ); }
update_option( 'hamrah_shop_search_ready', 1 );
wp_set_current_user( 0 );
hs_assert( in_array( $base->get_id(), hs_rest_search_ids( 'HS-TEST-BASE' ), true ), 'Search by product SKU' );
hs_assert( in_array( $variable->get_id(), hs_rest_search_ids( 'HS-TEST-VAR-01' ), true ), 'Search by variation SKU returns parent' );
hs_assert( in_array( $base->get_id(), hs_rest_search_ids( 'برند آزمون' ), true ), 'Search by native brand' );
hs_assert( in_array( $base->get_id(), hs_rest_search_ids( 'آبی آزمون' ), true ), 'Search by attribute labels' );
hs_assert( in_array( $base->get_id(), hs_rest_search_ids( 'دستهٔ آزمون' ), true ), 'Search by category' );
hs_assert( ! in_array( $hidden->get_id(), hs_rest_search_ids( 'HS-TEST-HIDDEN' ), true ), 'Hidden products never leak via live search' );
hs_assert( ! in_array( $draft->get_id(), hs_rest_search_ids( 'HS-TEST-DRAFT' ), true ), 'Draft products never leak via live search' );
hs_assert( ! in_array( $locked->get_id(), hs_rest_search_ids( 'HS-TEST-LOCKED' ), true ), 'Password-protected products never leak' );
hs_assert( in_array( $search_only->get_id(), hs_rest_search_ids( 'HS-TEST-SEARCH' ), true ), 'Search-only WooCommerce visibility is respected' );
$request = new WP_REST_Request( 'GET', '/hamrah-shop/v1/products' ); $request->set_param( 'ids', implode( ',', $ids ) ); $response = rest_do_request( $request ); $visible = array_column( $response->get_data()['products'], 'id' );
hs_assert( ! in_array( $draft->get_id(), $visible, true ) && ! in_array( $hidden->get_id(), $visible, true ) && ! in_array( $locked->get_id(), $visible, true ), 'Wishlist API enforces published visibility' );
$request = new WP_REST_Request( 'GET', '/hamrah-shop/v1/terms' ); $request->set_param( 'taxonomy', 'category' ); $response = rest_do_request( $request );
hs_assert( 400 === $response->get_status(), 'Term API rejects non-product/private taxonomies' );
$request = new WP_REST_Request( 'GET', '/hamrah-shop/v1/terms' ); $request->set_param( 'taxonomy', $taxonomy ); $request->set_param( 'q', 'آبی' ); $response = rest_do_request( $request );
hs_assert( 200 === $response->get_status() && count( $response->get_data()['terms'] ) > 0, 'Dynamic taxonomy term search' );
hs_assert( array() === hs_rest_search_ids( "' OR 1=1 --" ), 'SQL injection-shaped search is handled as text' );

// Native order data store, totals, order notes and actual stock reduction/restoration.
$order = wc_create_order(); $order->set_currency( 'IRT' ); $order->set_billing_country( 'IR' ); $order->set_billing_phone( '+989123456789' ); $order->set_payment_method( 'cod' ); $order->add_product( wc_get_product( $variation_ids[0] ), 1 ); $order->calculate_totals(); $order->save();
$order->update_status( 'processing' );
hs_assert( 'processing' === wc_get_order( $order->get_id() )->get_status() && (float) $order->get_total() > 0, 'WooCommerce order creation and status management' );
hs_assert( 2 === wc_get_product( $variation_ids[0] )->get_stock_quantity(), 'Order reduces real variation inventory' );
hs_assert( $order->add_order_note( 'یادداشت آزمون موقت' ) > 0, 'Order notes use WooCommerce API' );
$order->update_status( 'cancelled' );
hs_assert( 3 === wc_get_product( $variation_ids[0] )->get_stock_quantity(), 'Cancellation restores real inventory' );
hs_assert( Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(), 'HPOS is enabled and order APIs work' );
$order->delete( true );

// Standard WooCommerce CSV importer; extension never invents a product schema.
require_once WC_ABSPATH . 'includes/import/abstract-wc-product-importer.php';
require_once WC_ABSPATH . 'includes/import/class-wc-product-csv-importer.php';
$csv = sys_get_temp_dir() . '/hamrah-import-' . wp_generate_uuid4() . '.csv';
file_put_contents( $csv, "Type,SKU,Name,Published,Regular price,In stock?,Stock\nsimple,HS-TEST-CSV,محصول واردشدهٔ آزمون,1,210000,1,4\n" );
$importer = new WC_Product_CSV_Importer( $csv, array( 'mapping'=>array( 'Type'=>'type', 'SKU'=>'sku', 'Name'=>'name', 'Published'=>'published', 'Regular price'=>'regular_price', 'In stock?'=>'stock_status', 'Stock'=>'stock_quantity' ), 'parse'=>true ) );
$imported = $importer->import(); unlink( $csv );
$csv_id = wc_get_product_id_by_sku( 'HS-TEST-CSV' );
hs_assert( $csv_id > 0 && 4 === wc_get_product( $csv_id )->get_stock_quantity(), 'Standard WooCommerce CSV import compatibility' );
$ids[] = $csv_id;
require_once WC_ABSPATH . 'includes/export/class-wc-product-csv-exporter.php';
$exporter = new WC_Product_CSV_Exporter(); $exporter->set_product_ids_to_export( array( $csv_id ) ); $exporter->set_filename( 'hamrah-test-export.csv' ); $exporter->generate_file();
hs_assert( str_contains( $exporter->get_file(), 'HS-TEST-CSV' ), 'Standard WooCommerce CSV export compatibility' );

update_option( 'woocommerce_coming_soon', 'no' );

// Enable native offline gateway and shipping only in the disposable browser test instance.
update_option( 'woocommerce_cod_settings', array( 'enabled'=>'yes', 'title'=>'پرداخت هنگام تحویل', 'description'=>'', 'enable_for_methods'=>array(), 'enable_for_virtual'=>'yes' ) );
$zone = new WC_Shipping_Zone( 0 ); $method_id = $zone->add_shipping_method( 'flat_rate' );
update_option( 'woocommerce_flat_rate_' . $method_id . '_settings', array( 'enabled'=>'yes', 'title'=>'ارسال', 'tax_status'=>'none', 'cost'=>'0' ) );
$manifest = array( 'products'=>$ids, 'base'=>$base->get_id(), 'sale'=>$sale->get_id(), 'out'=>$out->get_id(), 'back'=>$back->get_id(), 'variable'=>$variable->get_id(), 'variations'=>$variation_ids, 'taxonomy'=>$taxonomy, 'attribute'=>$attribute_id, 'category'=>$cat_a['term_id'], 'subcategory'=>$cat_b['term_id'], 'brand'=>$brand['term_id'], 'image'=>$image_id, 'shipping_method'=>$method_id, 'cart_path'=>wp_parse_url(wc_get_cart_url(), PHP_URL_PATH), 'checkout_path'=>wp_parse_url(wc_get_checkout_url(), PHP_URL_PATH), 'results'=>$results );
file_put_contents( dirname( HAMRAH_TEST_WP_ROOT ) . '/fixture-manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
echo wp_json_encode( array( 'checks'=>count( $results ), 'passed'=>count( array_filter( $results, static fn( $r ) => $r['passed'] ) ), 'wordpress'=>get_bloginfo('version'), 'woocommerce'=>WC_VERSION, 'php'=>PHP_VERSION ), JSON_PRETTY_PRINT ) . "\n";
