<?php
namespace HamrahShop;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Prefix-indexed, normalized product search. No customer/order data is indexed. */
final class Search {
	private static array $dirty = array();

	public function __construct() {
		add_filter( 'posts_search', array( $this, 'search_sql' ), 50, 2 );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'hamrah_shop_rebuild_search', array( $this, 'rebuild' ), 10, 2 );
		foreach ( array( 'woocommerce_new_product', 'woocommerce_update_product', 'woocommerce_new_product_variation', 'woocommerce_update_product_variation' ) as $hook ) {
			add_action( $hook, array( $this, 'mark_dirty' ) );
		}
		add_action( 'set_object_terms', array( $this, 'terms_changed' ), 30, 4 );
		add_action( 'edited_term', array( $this, 'taxonomy_changed' ), 30, 3 );
		add_action( 'delete_term', array( $this, 'taxonomy_changed' ), 30, 3 );
		add_action( 'before_delete_post', array( $this, 'deleted' ), 10, 2 );
		add_action( 'transition_post_status', array( $this, 'status_changed' ), 30, 3 );
		add_action( 'shutdown', array( $this, 'flush_dirty' ), 5 );
	}

	public static function table(): string { global $wpdb; return $wpdb->prefix . 'hamrah_search'; }

	public static function install_table(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table();
		$collation = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE $table (
			product_id bigint(20) unsigned NOT NULL,
			token varchar(100) NOT NULL,
			PRIMARY KEY  (product_id,token),
			KEY token_product (token,product_id)
		) $collation;" );
		update_option( 'hamrah_shop_db_version', '1' );
	}

	private static function tokens( string $text, bool $query = false ): array {
		$text = Support::normalize( $text );
		preg_match_all( '/[\p{L}\p{N}_-]+/u', $text, $matches );
		$tokens = array();
		foreach ( $matches[0] ?? array() as $word ) {
			$tokens[] = Support::clip( $word, 100 );
			if ( ! $query && preg_match( '/\p{L}/u', $word ) && preg_match( '/\p{N}/u', $word ) ) { preg_match_all( '/\p{L}+|\p{N}+/u', $word, $parts ); foreach ( $parts[0] as $part ) { $tokens[] = Support::clip( $part, 100 ); } }
			if ( ! $query && str_contains( $word, '-' ) ) {
				foreach ( explode( '-', $word ) as $part ) { if ( '' !== $part ) { $tokens[] = Support::clip( $part, 100 ); } }
			}
		}
		$tokens = array_values( array_unique( array_filter( $tokens ) ) );
		return $query ? array_slice( $tokens, 0, 8 ) : $tokens;
	}

	public static function index_product( int $id, bool $bump = true ): void {
		global $wpdb;
		if ( 'product_variation' === get_post_type( $id ) ) { $id = (int) wp_get_post_parent_id( $id ); }
		if ( ! $id ) { return; }
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : false;
		$table = self::table();
		if ( ! $product || 'publish' !== $product->get_status() || '' !== (string) get_post_field( 'post_password', $id ) ) {
			$wpdb->delete( $table, array( 'product_id'=>$id ), array( '%d' ) );
			if ( $bump ) { self::bump_epoch(); }
			return;
		}
		$text = $product->get_name() . ' ' . $product->get_sku();
		$taxonomies = array_merge( array( 'product_cat', 'product_tag', 'product_brand' ), wc_get_attribute_taxonomy_names() );
		$taxonomies = array_values( array_filter( $taxonomies, 'taxonomy_exists' ) );
		$terms = wp_get_object_terms( $id, $taxonomies );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$text .= ' ' . $term->name . ' ' . rawurldecode( $term->slug );
				if ( 'product_cat' === $term->taxonomy ) {
					foreach ( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) as $parent_id ) {
						$parent = get_term( $parent_id, 'product_cat' );
						if ( $parent && ! is_wp_error( $parent ) ) { $text .= ' ' . $parent->name; }
					}
				}
			}
		}
		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! $attribute->is_taxonomy() ) { $text .= ' ' . $attribute->get_name() . ' ' . implode( ' ', $attribute->get_options() ); }
		}
		if ( $product->is_type( 'variable' ) ) {
			$skus = $wpdb->get_col( $wpdb->prepare( "SELECT m.meta_value FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_parent = %d AND p.post_type = 'product_variation' AND p.post_status = 'publish' AND m.meta_key = '_sku'", $id ) );
			$text .= ' ' . implode( ' ', $skus );
		}
		$tokens = self::tokens( $text );
		$wpdb->delete( $table, array( 'product_id'=>$id ), array( '%d' ) );
		foreach ( array_chunk( $tokens, 150 ) as $chunk ) {
			$args = array(); $holders = array();
			foreach ( $chunk as $token ) { $holders[] = '(%d,%s)'; $args[] = $id; $args[] = $token; }
			if ( $holders ) { $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO $table (product_id,token) VALUES " . implode( ',', $holders ), $args ) ); }
		}
		if ( $bump ) { self::bump_epoch(); }
	}

	private static function bump_epoch(): void { update_option( 'hamrah_shop_search_epoch', (int) get_option( 'hamrah_shop_search_epoch', 0 ) + 1, false ); }

	public function mark_dirty( int $id ): void {
		if ( 'product_variation' === get_post_type( $id ) ) { $id = (int) wp_get_post_parent_id( $id ); }
		if ( $id ) { self::$dirty[ $id ] = true; }
	}

	public function flush_dirty(): void {
		foreach ( array_keys( self::$dirty ) as $id ) { self::index_product( (int) $id, false ); }
		if ( self::$dirty ) { self::bump_epoch(); self::$dirty = array(); }
	}

	public function terms_changed( int $id, mixed $terms, mixed $tt_ids, string $taxonomy ): void {
		if ( self::product_taxonomy( $taxonomy ) && in_array( get_post_type( $id ), array( 'product', 'product_variation' ), true ) ) { $this->mark_dirty( $id ); }
	}

	public function taxonomy_changed( int $id, int $tt_id, string $taxonomy ): void { if ( self::product_taxonomy( $taxonomy ) ) { self::queue_rebuild(); } }
	private static function product_taxonomy( string $taxonomy ): bool { return in_array( $taxonomy, array( 'product_cat', 'product_tag', 'product_brand' ), true ) || str_starts_with( $taxonomy, 'pa_' ); }
	public function status_changed( string $new, string $old, \WP_Post $post ): void { if ( in_array( $post->post_type, array( 'product', 'product_variation' ), true ) ) { $this->mark_dirty( $post->ID ); } }
	public function deleted( int $id, \WP_Post $post ): void {
		global $wpdb;
		if ( 'product' === $post->post_type ) { $wpdb->delete( self::table(), array( 'product_id'=>$id ), array( '%d' ) ); self::bump_epoch(); unset( self::$dirty[ $id ] ); }
		if ( 'product_variation' === $post->post_type && $post->post_parent ) { self::$dirty[ $post->post_parent ] = true; }
	}

	public static function queue_rebuild(): void {
		$generation = (int) get_option( 'hamrah_shop_search_generation', 0 ) + 1;
		update_option( 'hamrah_shop_search_generation', $generation, false );
		update_option( 'hamrah_shop_search_ready', 0, false );
		if ( function_exists( 'as_unschedule_all_actions' ) && did_action( 'action_scheduler_init' ) ) { as_unschedule_all_actions( 'hamrah_shop_rebuild_search', null, 'hamrah-shop' ); }
		wp_clear_scheduled_hook( 'hamrah_shop_rebuild_search' );
		self::schedule( 0, $generation );
	}

	private static function schedule( int $cursor, int $generation ): void {
		$args = array( $cursor, $generation );
		if ( function_exists( 'as_enqueue_async_action' ) && did_action( 'action_scheduler_init' ) ) {
			as_enqueue_async_action( 'hamrah_shop_rebuild_search', $args, 'hamrah-shop', true );
		} elseif ( ! wp_next_scheduled( 'hamrah_shop_rebuild_search', $args ) ) {
			wp_schedule_single_event( time() + 10, 'hamrah_shop_rebuild_search', $args );
		}
	}

	public function rebuild( int $cursor = 0, int $generation = 0 ): void {
		global $wpdb;
		if ( $generation !== (int) get_option( 'hamrah_shop_search_generation', 0 ) ) { return; }
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND ID > %d ORDER BY ID ASC LIMIT 30", $cursor ) );
		foreach ( $ids as $id ) { self::index_product( (int) $id, false ); }
		self::bump_epoch();
		if ( count( $ids ) === 30 ) { self::schedule( (int) end( $ids ), $generation ); }
		else { update_option( 'hamrah_shop_search_ready', 1, false ); }
	}

	public function search_sql( string $search, \WP_Query $query ): string {
		if ( is_admin() && ! $query->get( 'hs_product_search' ) ) { return $search; }
		$type = $query->get( 'post_type' );
		if ( 'product' !== $type || ! is_scalar( $query->get( 's' ) ) || '' === (string) $query->get( 's' ) ) { return $search; }
		global $wpdb;
		$tokens = self::tokens( Support::clip( (string) $query->get( 's' ), 180 ), true );
		if ( ! $tokens ) { return ' AND 1=0 '; }
		$clauses = array();
		if ( get_option( 'hamrah_shop_search_ready' ) ) {
			$table = self::table();
			foreach ( $tokens as $token ) {
				$clauses[] = $wpdb->prepare( "{$wpdb->posts}.ID IN (SELECT product_id FROM $table WHERE token LIKE %s)", $wpdb->esc_like( $token ) . '%' );
			}
		} else {
			// Temporary fallback while an existing shop's index is being built.
			foreach ( $tokens as $token ) {
				$like = '%' . $wpdb->esc_like( $token ) . '%';
				$clauses[] = $wpdb->prepare( "({$wpdb->posts}.post_title LIKE %s OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} sm INNER JOIN {$wpdb->posts} sp ON sp.ID = sm.post_id WHERE sm.meta_key = '_sku' AND sm.meta_value LIKE %s AND (sp.ID = {$wpdb->posts}.ID OR (sp.post_parent = {$wpdb->posts}.ID AND sp.post_type = 'product_variation' AND sp.post_status = 'publish'))) OR EXISTS (SELECT 1 FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id INNER JOIN {$wpdb->terms} st ON st.term_id = tt.term_id WHERE tr.object_id = {$wpdb->posts}.ID AND st.name LIKE %s))", $like, $like, $like );
			}
		}
		return ' AND (' . implode( ' AND ', $clauses ) . ") AND {$wpdb->posts}.post_password = '' ";
	}

	public function routes(): void {
		register_rest_route( 'hamrah-shop/v1', '/search', array(
			'methods'=>'GET', 'permission_callback'=>'__return_true', 'callback'=>array( $this, 'live' ),
			'args'=>array( 'q'=>array( 'type'=>'string', 'required'=>true, 'maxLength'=>180, 'sanitize_callback'=>'sanitize_text_field' ) ),
		) );
	}

	public function live( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! Settings::get( 'live_search' ) ) { return new \WP_REST_Response( array( 'products'=>array() ), 200 ); }
		$q = Support::clip( (string) $request->get_param( 'q' ), 180 );
		if ( strlen( Support::normalize( $q ) ) < 2 ) { return new \WP_REST_Response( array( 'products'=>array() ), 200 ); }
		$visibility = wc_get_product_visibility_term_ids();
		$exclude = array( $visibility['exclude-from-search'] );
		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) { $exclude[] = $visibility['outofstock']; }
		$query = new \WP_Query( array( 'post_type'=>'product', 'post_status'=>'publish', 'has_password'=>false, 's'=>$q, 'posts_per_page'=>6, 'no_found_rows'=>true, 'ignore_sticky_posts'=>true, 'hs_product_search'=>true, 'tax_query'=>array( array( 'taxonomy'=>'product_visibility', 'field'=>'term_taxonomy_id', 'terms'=>$exclude, 'operator'=>'NOT IN' ) ) ) );
		$products = array();
		foreach ( $query->posts as $post ) {
			$product = wc_get_product( $post->ID );
			$data = $product ? Support::product_data( $product, 'search' ) : null;
			if ( $data ) { $products[] = $data; }
		}
		$response = new \WP_REST_Response( array( 'products'=>$products ), 200 );
		$response->header( 'Cache-Control', 'no-store' ); // Prices can depend on the customer or currency plugin.
		return $response;
	}
}
