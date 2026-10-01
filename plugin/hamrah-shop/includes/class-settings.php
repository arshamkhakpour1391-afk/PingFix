<?php
namespace HamrahShop;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Settings {
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'option_page_capability_hamrah_shop', static fn() => 'manage_woocommerce' );
		add_filter( 'plugin_action_links_' . plugin_basename( HAMRAH_SHOP_FILE ), array( $this, 'links' ) );
		add_action( 'admin_notices', array( $this, 'setup_notice' ) );
	}

	public static function defaults(): array {
		return array(
			'primary'=>'#047857', 'ink'=>'#172b29', 'background'=>'#f5f7f6', 'surface'=>'#ffffff', 'radius'=>16,
			'announcement'=>'', 'contact_phone'=>'', 'contact_email'=>'', 'contact_address'=>'',
			'instagram'=>'', 'telegram'=>'', 'whatsapp'=>'', 'copyright'=>'',
			'footer_widgets'=>0, 'home_categories'=>1, 'home_latest'=>1, 'home_sale'=>1, 'home_limit'=>8,
			'shop_columns'=>4, 'per_page'=>12, 'filters'=>1, 'filter_mode'=>'all', 'filter_attributes'=>array(),
			'filter_categories'=>1, 'filter_brands'=>1, 'filter_price'=>1, 'filter_stock'=>1, 'filter_sale'=>1,
			'live_search'=>1, 'wishlist'=>1, 'buy_now'=>1, 'iran_phone'=>1, 'iran_postcode'=>1,
			'delete_data'=>0,
		);
	}

	public static function get( ?string $key = null ): mixed {
		$value = get_option( 'hamrah_shop_settings', array() );
		$value = array_merge( self::defaults(), is_array( $value ) ? $value : array() );
		return null === $key ? $value : ( $value[ $key ] ?? null );
	}

	public function register(): void {
		register_setting( 'hamrah_shop', 'hamrah_shop_settings', array( 'type'=>'array', 'sanitize_callback'=>array( $this, 'sanitize' ), 'default'=>self::defaults(), 'show_in_rest'=>false ) );
	}

	public function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();
		$out = self::defaults();
		foreach ( array( 'primary', 'ink', 'background', 'surface' ) as $key ) {
			$out[ $key ] = is_scalar( $input[ $key ] ?? null ) ? ( sanitize_hex_color( (string) $input[ $key ] ) ?: $out[ $key ] ) : $out[ $key ];
		}
		foreach ( array( 'footer_widgets', 'home_categories', 'home_latest', 'home_sale', 'filters', 'filter_categories', 'filter_brands', 'filter_price', 'filter_stock', 'filter_sale', 'live_search', 'wishlist', 'buy_now', 'iran_phone', 'iran_postcode', 'delete_data' ) as $key ) {
			$out[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
		}
		foreach ( array( 'radius'=>array( 0, 28 ), 'home_limit'=>array( 1, 24 ), 'shop_columns'=>array( 2, 5 ), 'per_page'=>array( 1, 48 ) ) as $key => $range ) {
			$number = is_scalar( $input[ $key ] ?? null ) ? absint( $input[ $key ] ) : $out[ $key ];
			$out[ $key ] = min( $range[1], max( $range[0], $number ) );
		}
		foreach ( array( 'announcement', 'contact_phone', 'copyright' ) as $key ) {
			$out[ $key ] = is_scalar( $input[ $key ] ?? null ) ? Support::clip( sanitize_text_field( (string) $input[ $key ] ), 250 ) : '';
		}
		$out['contact_email'] = is_scalar( $input['contact_email'] ?? null ) ? sanitize_email( (string) $input['contact_email'] ) : '';
		$out['contact_address'] = is_scalar( $input['contact_address'] ?? null ) ? Support::clip( sanitize_textarea_field( (string) $input['contact_address'] ), 1000 ) : '';
		foreach ( array( 'instagram', 'telegram', 'whatsapp' ) as $key ) {
			$out[ $key ] = is_scalar( $input[ $key ] ?? null ) ? esc_url_raw( (string) $input[ $key ], array( 'https', 'http' ) ) : '';
		}
		$out['filter_mode'] = 'selected' === ( $input['filter_mode'] ?? '' ) ? 'selected' : 'all';
		$ids = is_array( $input['filter_attributes'] ?? null ) ? $input['filter_attributes'] : array();
		$out['filter_attributes'] = array_values( array_unique( array_filter( array_map( 'absint', array_filter( $ids, 'is_scalar' ) ) ) ) );
		return $out;
	}

	public function menu(): void {
		add_submenu_page( class_exists( '\WooCommerce' ) ? 'woocommerce' : 'options-general.php', __( 'فروشگاه همراه', 'hamrah-shop' ), __( 'فروشگاه همراه', 'hamrah-shop' ), 'manage_woocommerce', 'hamrah-shop', array( $this, 'page' ) );
	}

	public function assets( string $hook ): void {
		if ( ! str_contains( $hook, 'hamrah-shop' ) ) { return; }
		wp_enqueue_style( 'hamrah-shop-admin', HAMRAH_SHOP_URL . 'assets/css/admin.css', array(), HAMRAH_SHOP_VERSION );
	}

	public function links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=hamrah-shop' ) ) . '">' . esc_html__( 'راه‌اندازی و تنظیمات', 'hamrah-shop' ) . '</a>' );
		return $links;
	}

	public function setup_notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! get_option( 'hamrah_shop_needs_setup' ) ) { return; }
		echo '<div class="notice notice-info"><p>' . esc_html__( 'فروشگاه همراه فعال شد. برای نصب قالب و آماده‌سازی صفحات، بخش راه‌اندازی را باز کنید. هیچ محصول یا محتوای نمونه‌ای ساخته نمی‌شود.', 'hamrah-shop' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=hamrah-shop' ) ) . '">' . esc_html__( 'راه‌اندازی فروشگاه', 'hamrah-shop' ) . '</a></p></div>';
	}

	private function field( string $key, string $label, string $type = 'text', string $help = '' ): void {
		$value = self::get( $key );
		$name = 'hamrah_shop_settings[' . $key . ']';
		echo '<div class="hs-admin-field"><label for="hs-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>';
		if ( 'checkbox' === $type ) {
			echo '<input id="hs-' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" type="checkbox" value="1" ' . checked( $value, 1, false ) . ' />';
		} elseif ( 'textarea' === $type ) {
			echo '<textarea id="hs-' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" rows="3">' . esc_textarea( $value ) . '</textarea>';
		} else {
			$bounds = array( 'radius'=>'min="0" max="28"', 'home_limit'=>'min="1" max="24"', 'shop_columns'=>'min="2" max="5"', 'per_page'=>'min="1" max="48"' );
			echo '<input id="hs-' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" type="' . esc_attr( $type ) . '" value="' . esc_attr( (string) $value ) . '" ' . ( $bounds[ $key ] ?? '' ) . ( in_array( $type, array( 'email', 'url', 'color', 'tel' ), true ) ? ' dir="ltr"' : '' ) . ' />';
		}
		if ( $help ) { echo '<p class="description">' . esc_html( $help ) . '</p>'; }
		echo '</div>';
	}

	public function page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
		echo '<div class="wrap hs-admin" dir="rtl"><h1>' . esc_html__( 'فروشگاه همراه', 'hamrah-shop' ) . '</h1><p class="hs-admin-intro">' . esc_html__( 'یک فروشگاه واقعی، با داده‌های شما. مدیریت محصولات و سفارش‌ها در بخش‌های استاندارد ووکامرس انجام می‌شود.', 'hamrah-shop' ) . '</p>';
		settings_errors();
		Installer::render();
		echo '<nav class="hs-admin-nav" aria-label="' . esc_attr__( 'بخش‌های تنظیمات', 'hamrah-shop' ) . '"><a href="#hs-appearance">' . esc_html__( 'ظاهر و اطلاعات', 'hamrah-shop' ) . '</a><a href="#hs-catalog">' . esc_html__( 'فروشگاه و فیلترها', 'hamrah-shop' ) . '</a><a href="#hs-iran">' . esc_html__( 'ایران و امکانات', 'hamrah-shop' ) . '</a><a href="#hs-help">' . esc_html__( 'راهنمای مدیریت', 'hamrah-shop' ) . '</a></nav>';
		echo '<form action="' . esc_url( admin_url( 'options.php' ) ) . '" method="post">';
		settings_fields( 'hamrah_shop' );
		echo '<section class="hs-admin-card" id="hs-appearance"><h2>' . esc_html__( 'ظاهر، خانه و اطلاعات واقعی فروشگاه', 'hamrah-shop' ) . '</h2><p>' . esc_html__( 'لوگو، نماد سایت و منوها را از نمایش ← سفارشی‌سازی تنظیم کنید. محتوای خانه و بنر تصویری را با ویرایش برگهٔ خانه در وردپرس بسازید. همهٔ موارد زیر اختیاری‌اند؛ اگر خالی باشند نمایش داده نمی‌شوند.', 'hamrah-shop' ) . '</p><div class="hs-admin-grid">';
		$this->field( 'primary', 'رنگ اصلی', 'color' );
		$this->field( 'ink', 'رنگ متن و سربرگ', 'color' );
		$this->field( 'background', 'رنگ پس‌زمینه', 'color' );
		$this->field( 'surface', 'رنگ کارت‌ها', 'color' );
		$this->field( 'radius', 'گردی گوشه‌ها (پیکسل)', 'number' );
		$this->field( 'announcement', 'پیام بالای سایت', 'text', 'فقط پیام واقعی فروشگاه خودتان را وارد کنید؛ خالی = پنهان.' );
		$this->field( 'contact_phone', 'شماره تماس فروشگاه', 'tel' );
		$this->field( 'contact_email', 'ایمیل فروشگاه', 'email' );
		$this->field( 'contact_address', 'نشانی فروشگاه', 'textarea' );
		$this->field( 'instagram', 'نشانی اینستاگرام', 'url' );
		$this->field( 'telegram', 'نشانی تلگرام', 'url' );
		$this->field( 'whatsapp', 'نشانی واتس‌اپ', 'url' );
		$this->field( 'copyright', 'متن اختیاری کپی‌رایت' );
		$this->field( 'footer_widgets', 'نمایش ابزارک‌های پایین سایت', 'checkbox', 'ابزارک‌های واقعی را در نمایش ← ابزارک‌ها اضافه کنید؛ این بخش در نصب اولیه پنهان است.' );
		$this->field( 'home_categories', 'نمایش دسته‌های دارای محصول در خانه', 'checkbox' );
		$this->field( 'home_latest', 'نمایش جدیدترین محصولات در خانه', 'checkbox' );
		$this->field( 'home_sale', 'نمایش محصولات تخفیف‌دار واقعی در خانه', 'checkbox' );
		$this->field( 'home_limit', 'تعداد محصولات هر بخش خانه', 'number' );
		echo '</div></section><section class="hs-admin-card" id="hs-catalog"><h2>' . esc_html__( 'فروشگاه و فیلترهای پویا', 'hamrah-shop' ) . '</h2><div class="hs-admin-grid">';
		$this->field( 'shop_columns', 'تعداد ستون محصولات در دسکتاپ', 'number' );
		$this->field( 'per_page', 'تعداد محصول در هر صفحه', 'number' );
		$this->field( 'filters', 'فعال بودن فیلتر فروشگاه', 'checkbox' );
		$this->field( 'filter_categories', 'فیلتر دسته‌بندی', 'checkbox' );
		$this->field( 'filter_brands', 'فیلتر برندهای ووکامرس', 'checkbox' );
		$this->field( 'filter_price', 'فیلتر قیمت', 'checkbox' );
		$this->field( 'filter_stock', 'فیلتر موجودی', 'checkbox' );
		$this->field( 'filter_sale', 'فیلتر تخفیف', 'checkbox' );
		echo '</div><div class="hs-admin-field"><label for="hs-filter-mode">' . esc_html__( 'ویژگی‌های قابل فیلتر', 'hamrah-shop' ) . '</label><select id="hs-filter-mode" name="hamrah_shop_settings[filter_mode]"><option value="all" ' . selected( self::get( 'filter_mode' ), 'all', false ) . '>' . esc_html__( 'همهٔ ویژگی‌های سراسری؛ ویژگی جدید خودکار اضافه می‌شود', 'hamrah-shop' ) . '</option><option value="selected" ' . selected( self::get( 'filter_mode' ), 'selected', false ) . '>' . esc_html__( 'فقط ویژگی‌های انتخاب‌شدهٔ زیر', 'hamrah-shop' ) . '</option></select><p class="description">' . esc_html__( 'ویژگی را از محصولات ← ویژگی‌ها بسازید و به محصول اختصاص دهید. ویژگی محلیِ داخل یک محصول، فیلتر سراسری نیست. گزینه‌ها تنها پس از اختصاص به محصول منتشرشده نمایش داده می‌شوند.', 'hamrah-shop' ) . '</p></div><div class="hs-admin-checks">';
		$attributes = function_exists( 'wc_get_attribute_taxonomies' ) ? wc_get_attribute_taxonomies() : array();
		foreach ( $attributes as $attribute ) {
			echo '<label><input type="checkbox" name="hamrah_shop_settings[filter_attributes][]" value="' . absint( $attribute->attribute_id ) . '" ' . checked( in_array( (int) $attribute->attribute_id, self::get( 'filter_attributes' ), true ), true, false ) . '> ' . esc_html( $attribute->attribute_label ) . '</label>';
		}
		if ( ! $attributes ) { echo '<p>' . esc_html__( 'هنوز ویژگی سراسری ایجاد نشده است.', 'hamrah-shop' ) . '</p>'; }
		echo '</div></section><section class="hs-admin-card" id="hs-iran"><h2>' . esc_html__( 'ایران و امکانات فروشگاه', 'hamrah-shop' ) . '</h2><div class="hs-admin-grid">';
		$this->field( 'iran_phone', 'اعتبارسنجی و یکسان‌سازی موبایل ایران', 'checkbox', 'فقط برای کشور ایران؛ ارقام فارسی/عربی و ۰۹، ۹۸، ۰۰۹۸ و +۹۸ پذیرفته می‌شوند. شماره به +۹۸ ذخیره می‌شود.' );
		$this->field( 'iran_postcode', 'اعتبارسنجی کد پستی ۱۰ رقمی ایران', 'checkbox', 'کد پستی واردشده بررسی می‌شود. اجباری بودن فیلد تابع تنظیمات استاندارد ووکامرس است.' );
		$this->field( 'live_search', 'پیشنهاد زندهٔ جستجو', 'checkbox' );
		$this->field( 'wishlist', 'علاقه‌مندی‌ها', 'checkbox', 'روی مرورگر مشتری ذخیره می‌شود؛ بین دستگاه‌ها همگام نمی‌شود.' );
		$this->field( 'buy_now', 'دکمهٔ خرید و رفتن به پرداخت', 'checkbox', 'سبد فعلی مشتری حفظ می‌شود و محصول انتخاب‌شده به آن اضافه خواهد شد.' );
		$this->field( 'delete_data', 'حذف تنظیمات و نمایهٔ جستجو هنگام حذف کامل افزونه', 'checkbox', 'محصول، مشتری، سفارش، برگه و قالب هیچ‌وقت توسط این گزینه حذف نمی‌شوند.' );
		echo '</div><p class="hs-admin-callout">' . esc_html__( 'واحد پول را در ووکامرس ← پیکربندی ← همگانی انتخاب کنید: ریال یا تومان (IRT). تغییر واحد پول، عدد قیمت‌های قبلی را تبدیل نمی‌کند. سازگاری درگاه با واحد انتخابی را پیش از فروش بررسی کنید. هیچ درگاه، کلید API یا روش ارسال ساختگی نصب نشده است.', 'hamrah-shop' ) . '</p></section>';
		submit_button( __( 'ذخیرهٔ تنظیمات', 'hamrah-shop' ) );
		echo '</form><section class="hs-admin-card" id="hs-help"><h2>' . esc_html__( 'از کجا شروع کنم؟', 'hamrah-shop' ) . '</h2><ol><li>' . esc_html__( 'تنظیمات را ذخیره کنید و قالب و برگه‌ها را از بخش راه‌اندازی آماده کنید.', 'hamrah-shop' ) . '</li><li>' . esc_html__( 'در تنظیمات همگانی وردپرس، نام سایت و زبان فارسی را انتخاب کنید. لوگو و منوها در نمایش قابل تنظیم‌اند.', 'hamrah-shop' ) . '</li><li>' . esc_html__( 'محصولات ← افزودن جدید: محصول واقعی، قیمت در واحد انتخابی، موجودی، عکس و ویژگی‌ها را وارد کنید.', 'hamrah-shop' ) . '</li><li>' . esc_html__( 'روش ارسال و درگاه معتبر را تنظیم کنید؛ سپس یک سفارش کنترل‌شده با اطلاعات خودتان آزمایش کنید.', 'hamrah-shop' ) . '</li><li>' . esc_html__( 'متن واقعی تماس، درباره، حریم خصوصی، قوانین، ارسال و مرجوعی را در برگه‌های پیش‌نویس بنویسید و منتشر کنید؛ برگهٔ قوانین و حریم خصوصی را در تنظیمات وردپرس/ووکامرس معرفی کنید.', 'hamrah-shop' ) . '</li></ol><div class="hs-admin-links">';
		$links = array(
			'edit.php?post_type=product'=>'محصولات', 'post-new.php?post_type=product'=>'افزودن محصول',
			'edit-tags.php?taxonomy=product_cat&post_type=product'=>'دسته‌بندی‌ها', 'edit.php?post_type=product&page=product_attributes'=>'ویژگی‌ها',
			'edit-tags.php?taxonomy=product_brand&post_type=product'=>'برندها', 'admin.php?page=wc-orders'=>'سفارش‌ها',
			'admin.php?page=wc-settings'=>'پیکربندی ووکامرس', 'admin.php?page=wc-settings&tab=shipping'=>'ارسال',
			'admin.php?page=wc-settings&tab=checkout'=>'پرداخت', 'edit.php?post_type=page'=>'برگه‌ها',
			'edit.php'=>'نوشته‌ها', 'nav-menus.php'=>'منوها', 'customize.php'=>'لوگو و نماد سایت',
			'admin.php?page=wc-status'=>'وضعیت ووکامرس',
		);
		foreach ( $links as $path => $label ) { echo '<a class="button" href="' . esc_url( admin_url( $path ) ) . '">' . esc_html( $label ) . '</a>'; }
		echo '</div><p><a href="' . esc_url( HAMRAH_SHOP_URL . 'docs/راهنمای-نصب.html' ) . '" target="_blank" rel="noopener">' . esc_html__( 'باز کردن راهنمای کامل فارسی (نصب، ورود و مدیریت با گوشی)', 'hamrah-shop' ) . '</a></p></section></div>';
	}
}
