<?php
namespace HamrahShop;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Iran {
	public function __construct() {
		add_filter( 'woocommerce_currencies', array( $this, 'currencies' ) );
		add_filter( 'woocommerce_currency_symbol', array( $this, 'symbol' ), 20, 2 );
		add_filter( 'woocommerce_structured_data_product', array( $this, 'schema_currency' ), 20 );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'fields' ) );
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'posted' ), 10 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate' ), 20, 2 );
		add_filter( 'woocommerce_format_postcode', array( $this, 'format_postcode' ), 20, 2 );
		add_filter( 'woocommerce_validate_postcode', array( $this, 'valid_postcode' ), 20, 3 );
		add_filter( 'rest_pre_dispatch', array( $this, 'store_request' ), 5, 3 );
		add_action( 'woocommerce_store_api_checkout_update_customer_from_request', array( $this, 'block_customer' ), 20, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'block_order' ), 20, 2 );
	}

	public function currencies( array $currencies ): array {
		if ( ! isset( $currencies['IRT'] ) ) { $currencies['IRT'] = __( 'تومان ایران', 'hamrah-shop' ); }
		return $currencies;
	}

	public function symbol( string $symbol, string $currency ): string {
		return match ( $currency ) { 'IRT'=>__( 'تومان', 'hamrah-shop' ), 'IRR'=>__( 'ریال', 'hamrah-shop' ), default=>$symbol };
	}

	/** ISO 4217-compatible SEO representation only; never changes stored/cart/order prices. */
	public function schema_currency( array $data ): array {
		if ( 'IRT' !== get_woocommerce_currency() ) { return $data; }
		$convert = static function ( array $node ) use ( &$convert ): array {
			if ( 'IRT' === ( $node['priceCurrency'] ?? '' ) ) {
				foreach ( array( 'price', 'lowPrice', 'highPrice' ) as $key ) { if ( isset( $node[ $key ] ) && is_numeric( $node[ $key ] ) ) { $node[ $key ] = wc_format_decimal( (float) $node[ $key ] * 10, wc_get_price_decimals() ); } }
				$node['priceCurrency'] = 'IRR';
			}
			foreach ( $node as $key=>$value ) { if ( is_array( $value ) ) { $node[ $key ] = $convert( $value ); } }
			return $node;
		};
		return $convert( $data );
	}

	public static function phone( string $value ): ?string {
		$value = Support::digits( $value );
		$value = preg_replace( '/[\s()\-\x{200C}]+/u', '', $value ) ?? '';
		return preg_match( '/^(?:\+98|0098|98|0)?(9\d{9})$/D', $value, $matches ) ? '+98' . $matches[1] : null;
	}

	public static function postcode( string $value ): string {
		return preg_replace( '/[\s\-]+/u', '', Support::digits( $value ) ) ?? '';
	}

	public function format_postcode( string $postcode, string $country ): string { return 'IR' === $country ? self::postcode( $postcode ) : $postcode; }
	public function valid_postcode( bool $valid, string $postcode, string $country ): bool {
		if ( 'IR' === $country && Settings::get( 'iran_postcode' ) ) { return (bool) preg_match( '/^\d{10}$/D', self::postcode( $postcode ) ); }
		return $valid;
	}

	public function fields( array $fields ): array {
		if ( isset( $fields['billing']['billing_phone'] ) ) {
			$fields['billing']['billing_phone']['type'] = 'tel';
			$fields['billing']['billing_phone']['custom_attributes']['inputmode'] = 'tel';
			$fields['billing']['billing_phone']['autocomplete'] = 'tel';
			if ( Settings::get( 'iran_phone' ) && 'IR' === WC()->countries->get_base_country() ) { $fields['billing']['billing_phone']['label'] = __( 'شماره موبایل', 'hamrah-shop' ); }
		}
		return $fields;
	}

	public function posted( array $data ): array {
		if ( 'IR' === ( $data['billing_country'] ?? '' ) && Settings::get( 'iran_phone' ) && isset( $data['billing_phone'] ) ) {
			$data['billing_phone'] = self::phone( (string) $data['billing_phone'] ) ?? (string) $data['billing_phone'];
		}
		foreach ( array( 'billing', 'shipping' ) as $type ) {
			if ( 'IR' === ( $data[ $type . '_country' ] ?? '' ) && isset( $data[ $type . '_postcode' ] ) ) { $data[ $type . '_postcode' ] = self::postcode( (string) $data[ $type . '_postcode' ] ); }
		}
		return $data;
	}

	public function validate( array $data, \WP_Error $errors ): void {
		if ( Settings::get( 'iran_phone' ) && 'IR' === ( $data['billing_country'] ?? '' ) && ! empty( $data['billing_phone'] ) && null === self::phone( (string) $data['billing_phone'] ) ) {
			$errors->add( 'hamrah_billing_phone', __( 'شماره موبایل ایران معتبر نیست. شماره را با ۰۹ یا +۹۸ وارد کنید.', 'hamrah-shop' ), array( 'id'=>'billing_phone' ) );
		}
		if ( ! Settings::get( 'iran_postcode' ) ) { return; }
		foreach ( array( 'billing', 'shipping' ) as $type ) {
			if ( 'shipping' === $type && empty( $data['ship_to_different_address'] ) ) { continue; }
			$code = (string) ( $data[ $type . '_postcode' ] ?? '' );
			if ( 'IR' === ( $data[ $type . '_country' ] ?? '' ) && '' !== $code && ! preg_match( '/^\d{10}$/D', self::postcode( $code ) ) ) {
				$errors->add( 'hamrah_' . $type . '_postcode', __( 'کد پستی ایران باید ۱۰ رقم باشد.', 'hamrah-shop' ), array( 'id'=>$type . '_postcode' ) );
			}
		}
	}

	/** Normalize before native Store API schema validation, not only after it. */
	public function store_request( mixed $result, \WP_REST_Server $server, \WP_REST_Request $request ): mixed {
		if ( null !== $result || 'POST' !== $request->get_method() || ! preg_match( '#^/wc/store/v[0-9]+/(?:checkout(?:/[0-9]+)?|cart/update-customer)$#D', $request->get_route() ) ) { return $result; }
		foreach ( array( 'billing_address', 'shipping_address' ) as $key ) {
			$address = $request->get_param( $key );
			if ( ! is_array( $address ) || 'IR' !== ( $address['country'] ?? '' ) ) { continue; }
			if ( isset( $address['postcode'] ) && is_scalar( $address['postcode'] ) ) { $address['postcode'] = self::postcode( (string) $address['postcode'] ); }
			if ( Settings::get( 'iran_phone' ) && isset( $address['phone'] ) && is_scalar( $address['phone'] ) ) {
				$phone = self::phone( (string) $address['phone'] );
				if ( null !== $phone ) { $address['phone'] = $phone; }
			}
			$request->set_param( $key, $address );
		}
		return $result;
	}

	public function block_customer( \WC_Customer $customer, \WP_REST_Request $request ): void {
		if ( 'IR' === $customer->get_billing_country() && Settings::get( 'iran_phone' ) ) {
			$phone = self::phone( $customer->get_billing_phone() );
			if ( $phone ) { $customer->set_billing_phone( $phone ); }
		}
		if ( 'IR' === $customer->get_billing_country() ) { $customer->set_billing_postcode( self::postcode( $customer->get_billing_postcode() ) ); }
		if ( 'IR' === $customer->get_shipping_country() ) { $customer->set_shipping_postcode( self::postcode( $customer->get_shipping_postcode() ) ); }
	}

	public function block_order( \WC_Order $order, \WP_REST_Request $request ): void {
		if ( 'IR' === $order->get_billing_country() && Settings::get( 'iran_phone' ) && '' !== $order->get_billing_phone() ) {
			$phone = self::phone( $order->get_billing_phone() );
			if ( null === $phone ) { throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'hamrah_invalid_phone', __( 'شماره موبایل ایران معتبر نیست.', 'hamrah-shop' ), 400 ); }
			$order->set_billing_phone( $phone );
		}
		foreach ( array( 'billing', 'shipping' ) as $type ) {
			$country = $order->{ 'get_' . $type . '_country' }();
			$code = $order->{ 'get_' . $type . '_postcode' }();
			if ( 'IR' !== $country ) { continue; }
			$code = self::postcode( $code );
			if ( Settings::get( 'iran_postcode' ) && '' !== $code && ! preg_match( '/^\d{10}$/D', $code ) ) { throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'hamrah_invalid_postcode', __( 'کد پستی ایران باید ۱۰ رقم باشد.', 'hamrah-shop' ), 400 ); }
			$order->{ 'set_' . $type . '_postcode' }( $code );
		}
	}
}
