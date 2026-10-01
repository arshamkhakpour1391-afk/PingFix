<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<footer class="hs-footer">
	<div class="hs-container hs-footer-grid">
		<section class="hs-footer-identity"><h2><?php bloginfo( 'name' ); ?></h2>
			<?php $address = (string) hs_theme_setting( 'contact_address' ); $phone = (string) hs_theme_setting( 'contact_phone' ); $email = (string) hs_theme_setting( 'contact_email' ); ?>
			<?php if ( $address ) : ?><address><?php echo nl2br( esc_html( $address ) ); ?></address><?php endif; ?>
			<?php if ( $phone ) : ?><p><a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', class_exists( '\HamrahShop\Support' ) ? \HamrahShop\Support::digits( $phone ) : $phone ) ); ?>" dir="ltr"><?php echo esc_html( $phone ); ?></a></p><?php endif; ?>
			<?php if ( $email ) : ?><p><a href="<?php echo esc_url( 'mailto:' . sanitize_email( $email ) ); ?>" dir="ltr"><?php echo esc_html( $email ); ?></a></p><?php endif; ?>
			<?php $socials = array( 'instagram'=>'اینستاگرام', 'telegram'=>'تلگرام', 'whatsapp'=>'واتس‌اپ' ); ?><div class="hs-socials"><?php foreach ( $socials as $key => $label ) { $url = (string) hs_theme_setting( $key ); if ( $url ) { echo '<a href="' . esc_url( $url, array( 'http', 'https' ) ) . '" rel="noopener noreferrer" target="_blank">' . esc_html( $label ) . '</a>'; } } ?></div>
		</section>
		<?php if ( hs_theme_has_woo() ) : ?>
		<section><h3><?php esc_html_e( 'فروشگاه', 'hamrah-shop-theme' ); ?></h3><ul class="hs-footer-links"><li><a href="<?php echo esc_url( hs_theme_shop_url() ); ?>"><?php esc_html_e( 'همهٔ محصولات', 'hamrah-shop-theme' ); ?></a></li><li><a href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php esc_html_e( 'سبد خرید', 'hamrah-shop-theme' ); ?></a></li><li><a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>"><?php esc_html_e( 'حساب کاربری و سفارش‌ها', 'hamrah-shop-theme' ); ?></a></li><?php if ( class_exists( '\HamrahShop\Support' ) && hs_theme_setting( 'wishlist', 1 ) && \HamrahShop\Support::wishlist_url() ) : ?><li><a href="<?php echo esc_url( \HamrahShop\Support::wishlist_url() ); ?>"><?php esc_html_e( 'علاقه‌مندی‌ها', 'hamrah-shop-theme' ); ?></a></li><?php endif; ?></ul></section>
		<?php endif; ?>
		<?php if ( has_nav_menu( 'footer' ) ) : ?><nav aria-label="<?php esc_attr_e( 'لینک‌های کاربردی', 'hamrah-shop-theme' ); ?>"><h3><?php esc_html_e( 'لینک‌های کاربردی', 'hamrah-shop-theme' ); ?></h3><?php wp_nav_menu( array( 'theme_location'=>'footer', 'container'=>false, 'menu_class'=>'hs-footer-links', 'fallback_cb'=>false, 'depth'=>2 ) ); ?></nav><?php endif; ?>
		<?php if ( has_nav_menu( 'legal' ) ) : ?><nav aria-label="<?php esc_attr_e( 'قوانین و ارسال', 'hamrah-shop-theme' ); ?>"><h3><?php esc_html_e( 'قوانین و ارسال', 'hamrah-shop-theme' ); ?></h3><?php wp_nav_menu( array( 'theme_location'=>'legal', 'container'=>false, 'menu_class'=>'hs-footer-links', 'fallback_cb'=>false, 'depth'=>2 ) ); ?></nav><?php endif; ?>
		<?php if ( hs_theme_setting( 'footer_widgets', 0 ) && is_active_sidebar( 'footer' ) ) { dynamic_sidebar( 'footer' ); } ?>
	</div>
	<div class="hs-container hs-footer-bottom"><p><?php $copyright = (string) hs_theme_setting( 'copyright' ); if ( $copyright ) { echo esc_html( $copyright ); } else { echo '© ' . esc_html( wp_date( 'Y' ) ) . ' ' . esc_html( get_bloginfo( 'name' ) ); } ?></p><?php if ( get_privacy_policy_url() && 'publish' === get_post_status( absint( get_option( 'wp_page_for_privacy_policy' ) ) ) ) : ?><a href="<?php echo esc_url( get_privacy_policy_url() ); ?>"><?php esc_html_e( 'حریم خصوصی', 'hamrah-shop-theme' ); ?></a><?php endif; ?></div>
</footer>
<?php if ( hs_theme_has_woo() ) : ?>
<nav class="hs-mobile-bar" aria-label="<?php esc_attr_e( 'دسترسی سریع موبایل', 'hamrah-shop-theme' ); ?>">
	<a href="<?php echo esc_url( hs_theme_shop_url() ); ?>"><?php echo hs_theme_icon( 'grid' ); ?><span><?php esc_html_e( 'فروشگاه', 'hamrah-shop-theme' ); ?></span></a>
	<a href="#hs-search-header" data-hs-focus-search><?php echo hs_theme_icon( 'search' ); ?><span><?php esc_html_e( 'جستجو', 'hamrah-shop-theme' ); ?></span></a>
	<?php if ( class_exists( '\HamrahShop\Support' ) && hs_theme_setting( 'wishlist', 1 ) && \HamrahShop\Support::wishlist_url() ) : ?><a href="<?php echo esc_url( \HamrahShop\Support::wishlist_url() ); ?>"><?php echo hs_theme_icon( 'heart' ); ?><span><?php esc_html_e( 'علاقه‌مندی', 'hamrah-shop-theme' ); ?></span></a><?php endif; ?>
	<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>"><?php echo hs_theme_icon( 'user' ); ?><span><?php esc_html_e( 'حساب من', 'hamrah-shop-theme' ); ?></span></a>
	<a href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php echo hs_theme_icon( 'cart' ); ?><span><?php esc_html_e( 'سبد خرید', 'hamrah-shop-theme' ); ?></span></a>
</nav>
<?php endif; ?>
<dialog id="hs-filter-dialog" class="hs-dialog hs-filter-dialog" aria-labelledby="hs-filter-dialog-title"><div class="hs-dialog-heading"><h2 id="hs-filter-dialog-title"><?php esc_html_e( 'فیلتر محصولات', 'hamrah-shop-theme' ); ?></h2><button type="button" class="hs-icon-button" data-hs-dialog-close aria-label="<?php esc_attr_e( 'بستن فیلترها', 'hamrah-shop-theme' ); ?>"><?php echo hs_theme_icon( 'close' ); ?></button></div><div data-hs-filter-host></div></dialog>
<div class="hs-toast" data-hs-toast role="status" aria-live="polite"></div>
<?php wp_footer(); ?>
</body>
</html>
