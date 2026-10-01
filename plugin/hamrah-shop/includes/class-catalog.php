<?php
namespace HamrahShop;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Native WooCommerce attribute/price filters plus indexed stock/sale predicates. */
final class Catalog {
	public function __construct() {
		add_action( 'init', array( $this, 'normalize_request' ), 6 );
		add_action( 'template_redirect', array( $this, 'canonical_form_request' ), 5 );
		add_filter( 'woocommerce_product_query_tax_query', array( $this, 'tax_query' ), 20, 2 );
		add_action( 'woocommerce_product_query', array( $this, 'query_flags' ), 30 );
		add_filter( 'posts_clauses', array( $this, 'lookup_clauses' ), 40, 2 );
		add_filter( 'woocommerce_catalog_orderby', array( $this, 'sorting' ) );
		add_filter( 'woocommerce_default_catalog_orderby_options', array( $this, 'sorting' ) );
		add_filter( 'woocommerce_get_catalog_ordering_args', array( $this, 'ordering_args' ), 20, 3 );
		add_filter( 'loop_shop_per_page', static fn() => (int) Settings::get( 'per_page' ), 30 );
		add_shortcode( 'hamrah_shop_filters', array( $this, 'shortcode' ) );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
	}

	public static function attributes(): array {
		$attributes = wc_get_attribute_taxonomies();
		if ( 'selected' === Settings::get( 'filter_mode' ) ) {
			$selected = Settings::get( 'filter_attributes' );
			$attributes = array_filter( $attributes, static fn( $a ) => in_array( (int) $a->attribute_id, $selected, true ) );
		}
		return array_values( $attributes );
	}

	public function normalize_request(): void {
		// Convert progressive-enhancement checkbox arrays to standard, shareable WooCommerce URLs.
		foreach ( wc_get_attribute_taxonomies() as $attribute ) {
			$key = 'filter_' . $attribute->attribute_name;
			$form_key = 'hs_attr_' . $attribute->attribute_name;
			if ( isset( $_GET[ $form_key ] ) ) {
				$_GET[ $key ] = implode( ',', Support::list_param( $_GET[ $form_key ] ) );
				$_GET[ 'query_type_' . $attribute->attribute_name ] = 'or';
			}
			if ( isset( $_GET[ $key ] ) ) { $_GET[ $key ] = implode( ',', Support::list_param( $_GET[ $key ] ) ); }
			$type_key = 'query_type_' . $attribute->attribute_name;
			if ( isset( $_GET[ $type_key ] ) ) { $_GET[ $type_key ] = 'and' === $_GET[ $type_key ] ? 'and' : 'or'; }
		}
		foreach ( array( 'hs_categories', 'hs_brand', 'hs_stock' ) as $key ) {
			if ( isset( $_GET[ $key ] ) ) { $_GET[ $key ] = implode( ',', Support::list_param( $_GET[ $key ] ) ); }
		}
		foreach ( array( 'min_price', 'max_price' ) as $key ) {
			if ( isset( $_GET[ $key ] ) ) {
				$value = Support::decimal( $_GET[ $key ] );
				if ( '' === $value ) { unset( $_GET[ $key ] ); } else { $_GET[ $key ] = $value; }
			}
		}
		if ( isset( $_GET['min_price'], $_GET['max_price'] ) && (float) $_GET['min_price'] > (float) $_GET['max_price'] ) {
			$temp = $_GET['min_price']; $_GET['min_price'] = $_GET['max_price']; $_GET['max_price'] = $temp;
		}
		if ( isset( $_GET['hs_sale'] ) ) { $_GET['hs_sale'] = is_scalar( $_GET['hs_sale'] ) && '1' === (string) $_GET['hs_sale'] ? '1' : ''; }
	}

	public function canonical_form_request(): void {
		if ( is_admin() || ! ( is_shop() || is_product_taxonomy() || ( is_search() && 'product' === get_query_var( 'post_type' ) ) ) ) { return; }
		$changed = false;
		$params = $_GET;
		foreach ( array_keys( $params ) as $key ) {
			if ( str_starts_with( (string) $key, 'hs_attr_' ) ) { unset( $params[ $key ] ); $changed = true; }
		}
		if ( $changed ) {
			unset( $params['paged'], $params['product-page'] );
			wp_safe_redirect( add_query_arg( $params, self::base_url() ), 302 ); exit;
		}
	}

	public static function selected( string $key ): array { return Support::list_param( $_GET[ $key ] ?? '' ); }

	public function tax_query( array $tax_query, mixed $wc_query ): array {
		if ( ! Settings::get( 'filters' ) || is_admin() ) { return $tax_query; }
		$categories = array_filter( array_map( 'absint', self::selected( 'hs_categories' ) ) );
		if ( $categories ) { $tax_query[] = array( 'taxonomy'=>'product_cat', 'field'=>'term_id', 'terms'=>$categories, 'operator'=>'IN', 'include_children'=>true ); }
		$brands = self::selected( 'hs_brand' );
		if ( $brands && taxonomy_exists( 'product_brand' ) ) { $tax_query[] = array( 'taxonomy'=>'product_brand', 'field'=>'slug', 'terms'=>$brands, 'operator'=>'IN' ); }
		return $tax_query;
	}

	public function query_flags( \WP_Query $query ): void {
		if ( is_admin() ) { return; }
		$query->set( 'has_password', false );
		$stock = Settings::get( 'filters' ) ? array_values( array_intersect( self::selected( 'hs_stock' ), array( 'instock', 'outofstock', 'onbackorder' ) ) ) : array();
		$query->set( 'hs_stock_filter', $stock );
		$query->set( 'hs_sale_filter', Settings::get( 'filters' ) && ! empty( $_GET['hs_sale'] ) );
		$orderby = isset( $_GET['orderby'] ) && is_string( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : get_option( 'woocommerce_default_catalog_orderby', 'menu_order' );
		if ( in_array( $orderby, array( 'hs_stock', 'hs_sale' ), true ) ) { $query->set( 'hs_priority_sort', $orderby ); }
	}

	public function lookup_clauses( array $clauses, \WP_Query $query ): array {
		$stock = $query->get( 'hs_stock_filter' ); $sale = $query->get( 'hs_sale_filter' ); $sort = $query->get( 'hs_priority_sort' );
		if ( ! $stock && ! $sale && ! $sort ) { return $clauses; }
		global $wpdb;
		$clauses['join'] .= " LEFT JOIN {$wpdb->wc_product_meta_lookup} hs_catalog_lookup ON {$wpdb->posts}.ID = hs_catalog_lookup.product_id ";
		if ( is_array( $stock ) && $stock ) {
			$holders = implode( ',', array_fill( 0, count( $stock ), '%s' ) );
			$clauses['where'] .= $wpdb->prepare( " AND hs_catalog_lookup.stock_status IN ($holders) ", $stock );
		}
		if ( $sale ) { $clauses['where'] .= ' AND hs_catalog_lookup.onsale = 1 '; }
		if ( 'hs_stock' === $sort ) { $clauses['orderby'] = "CASE hs_catalog_lookup.stock_status WHEN 'instock' THEN 0 WHEN 'onbackorder' THEN 1 ELSE 2 END ASC, {$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC"; }
		if ( 'hs_sale' === $sort ) { $clauses['orderby'] = "hs_catalog_lookup.onsale DESC, {$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC"; }
		return $clauses;
	}

	public function sorting( array $options ): array {
		$options['date'] = __( 'جدیدترین', 'hamrah-shop' );
		$options['price'] = __( 'ارزان‌ترین', 'hamrah-shop' );
		$options['price-desc'] = __( 'گران‌ترین', 'hamrah-shop' );
		$options['popularity'] = __( 'محبوب‌ترین', 'hamrah-shop' );
		$options['hs_stock'] = __( 'موجودها اول', 'hamrah-shop' );
		$options['hs_sale'] = __( 'تخفیف‌دارها اول', 'hamrah-shop' );
		return $options;
	}

	public function ordering_args( array $args, string $orderby, string $order ): array {
		if ( in_array( $orderby, array( 'hs_stock', 'hs_sale' ), true ) ) { return array( 'orderby'=>'date', 'order'=>'DESC', 'meta_key'=>'' ); }
		return $args;
	}

	public static function base_url(): string {
		if ( is_product_taxonomy() ) {
			$term = get_queried_object();
			$link = $term instanceof \WP_Term ? get_term_link( $term ) : false;
			if ( $link && ! is_wp_error( $link ) ) { return $link; }
		}
		return is_search() ? home_url( '/' ) : Support::shop_url();
	}

	public static function clear_url(): string {
		$params = array();
		if ( is_search() && isset( $_GET['s'] ) && is_string( $_GET['s'] ) ) { $params['s'] = sanitize_text_field( wp_unslash( $_GET['s'] ) ); $params['post_type'] = 'product'; }
		return add_query_arg( $params, self::base_url() );
	}

	public static function has_filters(): bool {
		foreach ( array_keys( $_GET ) as $key ) {
			if ( str_starts_with( (string) $key, 'filter_' ) || in_array( $key, array( 'hs_categories', 'hs_brand', 'hs_stock', 'hs_sale', 'min_price', 'max_price' ), true ) ) { if ( ! empty( $_GET[ $key ] ) || '0' === ( $_GET[ $key ] ?? null ) ) { return true; } }
		}
		return false;
	}

	public function robots( array $robots ): array {
		if ( ( is_shop() || is_product_taxonomy() || is_search() ) && self::has_filters() ) { $robots['noindex'] = true; unset( $robots['index'] ); }
		return $robots;
	}

	private static function facet( string $taxonomy, string $param, string $name, string $label ): ?array {
		if ( ! taxonomy_exists( $taxonomy ) ) { return null; }
		$selected = self::selected( $param );
		$args = array( 'taxonomy'=>$taxonomy, 'hide_empty'=>true, 'number'=>61, 'orderby'=>'name', 'order'=>'ASC' );
		if ( 'product_cat' === $taxonomy ) { $args['menu_order'] = 'ASC'; }
		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) || ! $terms ) { return null; }
		$more = count( $terms ) > 60; $terms = array_slice( $terms, 0, 60 );
		if ( $selected ) {
			$chosen_args = array( 'taxonomy'=>$taxonomy, 'hide_empty'=>false );
			$chosen_args[ 'hs_categories' === $param ? 'include' : 'slug' ] = 'hs_categories' === $param ? array_map( 'absint', $selected ) : $selected;
			$chosen = get_terms( $chosen_args );
			if ( ! is_wp_error( $chosen ) ) { $by_id = array(); foreach ( array_merge( $chosen, $terms ) as $term ) { $by_id[ $term->term_id ] = $term; } $terms = array_values( $by_id ); }
		}
		return array( 'taxonomy'=>$taxonomy, 'param'=>$param, 'name'=>$name, 'label'=>$label, 'terms'=>$terms, 'selected'=>$selected, 'more'=>$more );
	}

	public function shortcode(): string {
		if ( ! Settings::get( 'filters' ) ) { return ''; }
		$facets = array();
		if ( Settings::get( 'filter_categories' ) ) { $facets[] = self::facet( 'product_cat', 'hs_categories', 'hs_categories[]', __( 'دسته‌بندی', 'hamrah-shop' ) ); }
		if ( Settings::get( 'filter_brands' ) ) { $facets[] = self::facet( 'product_brand', 'hs_brand', 'hs_brand[]', __( 'برند', 'hamrah-shop' ) ); }
		foreach ( self::attributes() as $attribute ) {
			$facets[] = self::facet( wc_attribute_taxonomy_name( $attribute->attribute_name ), 'filter_' . $attribute->attribute_name, 'hs_attr_' . $attribute->attribute_name . '[]', $attribute->attribute_label );
		}
		$facets = array_filter( $facets );
		ob_start(); include HAMRAH_SHOP_PATH . 'templates/filters.php'; return (string) ob_get_clean();
	}

	public function routes(): void {
		register_rest_route( 'hamrah-shop/v1', '/terms', array(
			'methods'=>'GET', 'permission_callback'=>'__return_true', 'callback'=>array( $this, 'terms' ),
			'args'=>array( 'taxonomy'=>array( 'required'=>true, 'type'=>'string', 'maxLength'=>64, 'sanitize_callback'=>'sanitize_key' ), 'q'=>array( 'type'=>'string', 'maxLength'=>80, 'default'=>'', 'sanitize_callback'=>'sanitize_text_field' ) ),
		) );
	}

	public function terms( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$allowed = array();
		if ( Settings::get( 'filter_categories' ) ) { $allowed[] = 'product_cat'; }
		if ( Settings::get( 'filter_brands' ) ) { $allowed[] = 'product_brand'; }
		foreach ( self::attributes() as $attribute ) { $allowed[] = wc_attribute_taxonomy_name( $attribute->attribute_name ); }
		$taxonomy = (string) $request->get_param( 'taxonomy' );
		if ( ! Settings::get( 'filters' ) || ! in_array( $taxonomy, $allowed, true ) || ! taxonomy_exists( $taxonomy ) ) { return new \WP_Error( 'hs_invalid_taxonomy', __( 'این فیلتر در دسترس نیست.', 'hamrah-shop' ), array( 'status'=>400 ) ); }
		$terms = get_terms( array( 'taxonomy'=>$taxonomy, 'hide_empty'=>true, 'number'=>41, 'search'=>Support::clip( (string) $request->get_param( 'q' ), 80 ), 'orderby'=>'name', 'order'=>'ASC' ) );
		if ( is_wp_error( $terms ) ) { return new \WP_Error( 'hs_terms_error', __( 'دریافت گزینه‌ها انجام نشد.', 'hamrah-shop' ), array( 'status'=>500 ) ); }
		$out = array();
		foreach ( array_slice( $terms, 0, 40 ) as $term ) { $out[] = array( 'value'=>'product_cat' === $taxonomy ? (string) $term->term_id : $term->slug, 'label'=>$term->name ); }
		return new \WP_REST_Response( array( 'terms'=>$out, 'more'=>count( $terms ) > 40 ), 200 );
	}
}
