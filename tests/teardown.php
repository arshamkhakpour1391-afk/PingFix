<?php
/** Delete ONLY known disposable fixtures. Never included in the installation ZIP. */
if ( ! defined( 'HAMRAH_TEST_ALLOW' ) || true !== HAMRAH_TEST_ALLOW || ! defined( 'HAMRAH_TEST_WP_ROOT' ) ) { exit( 1 ); }
$_SERVER['HTTP_HOST']='127.0.0.1:8080'; $_SERVER['REQUEST_METHOD']='GET';
require HAMRAH_TEST_WP_ROOT . '/wp-load.php';
if ( 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Teardown is restricted to a local, disposable installation.' ); }
add_filter( 'pre_wp_mail', '__return_true' );
require_once ABSPATH . 'wp-admin/includes/user.php';
$path = dirname( HAMRAH_TEST_WP_ROOT ) . '/fixture-manifest.json';
if ( ! is_readable( $path ) ) { throw new RuntimeException( 'Known fixture manifest is required.' ); }
$fixture = json_decode( file_get_contents( $path ), true );
foreach ( wc_get_orders( array( 'limit'=>-1, 'status'=>array_keys( wc_get_order_statuses() ) ) ) as $order ) {
	$email = $order->get_billing_email();
	if ( str_starts_with( $email, 'hs-' ) && str_ends_with( $email, '@example.test' ) ) { if ( ! $order->has_status( array( 'cancelled', 'refunded' ) ) ) { $order->update_status( 'cancelled' ); } $order->delete( true ); }
}
foreach ( get_users( array( 'role'=>'customer' ) ) as $user ) {
	if ( str_starts_with( $user->user_email, 'hs-' ) && str_ends_with( $user->user_email, '@example.test' ) ) { wp_delete_user( $user->ID ); }
}
foreach ( array_merge( $fixture['variations'], array_reverse( $fixture['products'] ) ) as $id ) { $product=wc_get_product( $id ); if ( $product && str_starts_with( $product->get_sku(), 'HS-TEST-' ) ) { $product->delete( true ); } }
wp_delete_attachment( $fixture['image'], true );
foreach ( array( 'category'=>'product_cat', 'subcategory'=>'product_cat', 'brand'=>'product_brand' ) as $key=>$taxonomy ) { $term=get_term( $fixture[$key], $taxonomy ); if ( $term && ! is_wp_error($term) && str_starts_with($term->slug,'hs-test-') ) { wp_delete_term( $term->term_id, $taxonomy ); } }
wc_delete_attribute( $fixture['attribute'] );
$zone=new WC_Shipping_Zone(0);$zone->delete_shipping_method($fixture['shipping_method']);
delete_option('woocommerce_flat_rate_'.$fixture['shipping_method'].'_settings');
update_option('woocommerce_cod_settings',array('enabled'=>'no'));
HamrahShop\Search::queue_rebuild();
(new HamrahShop\Search())->rebuild(0,(int)get_option('hamrah_shop_search_generation'));
echo wp_json_encode(array('published_products'=>(int)wp_count_posts('product')->publish,'customers'=>count(get_users(array('role'=>'customer'))),'test_orders'=>count(wc_get_orders(array('limit'=>-1)))),JSON_PRETTY_PRINT)."\n";
