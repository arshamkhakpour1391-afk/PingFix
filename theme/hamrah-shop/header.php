<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'hs-site' ); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#main-content"><?php esc_html_e( 'رفتن به محتوای اصلی', 'hamrah-shop-theme' ); ?></a>
<?php $announcement = (string) hs_theme_setting( 'announcement' ); if ( $announcement ) : ?>
<div class="hs-announcement"><div class="hs-container"><?php echo esc_html( $announcement ); ?></div></div>
<?php endif; ?>
<header class="hs-header">
	<div class="hs-container hs-header-main">
		<button type="button" class="hs-icon-button hs-menu-toggle" data-hs-dialog-open="hs-mobile-menu" aria-controls="hs-mobile-menu" aria-label="<?php esc_attr_e( 'باز کردن منو', 'hamrah-shop-theme' ); ?>"><?php echo hs_theme_icon( 'menu' ); ?></button>
		<div class="hs-brand">
			<?php if ( has_custom_logo() ) { the_custom_logo(); } else { ?><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="hs-site-name" rel="home"><?php bloginfo( 'name' ); ?></a><?php } ?>
		</div>
		<?php if ( hs_theme_has_woo() ) { hs_theme_search(); } else { get_search_form(); } ?>
		<div class="hs-header-actions">
			<?php if ( hs_theme_has_woo() ) : ?>
			<a class="hs-account-link" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" aria-label="<?php esc_attr_e( 'حساب کاربری و ورود', 'hamrah-shop-theme' ); ?>"><?php echo hs_theme_icon( 'user' ); ?><span><?php echo esc_html( is_user_logged_in() ? __( 'حساب من', 'hamrah-shop-theme' ) : __( 'ورود / ثبت‌نام', 'hamrah-shop-theme' ) ); ?></span></a>
			<a class="hs-cart-link" href="<?php echo esc_url( wc_get_cart_url() ); ?>" aria-label="<?php esc_attr_e( 'سبد خرید', 'hamrah-shop-theme' ); ?>"><?php echo hs_theme_icon( 'cart' ); ?><span class="hs-cart-count" aria-label="<?php esc_attr_e( 'تعداد کالاهای سبد', 'hamrah-shop-theme' ); ?>"><?php echo esc_html( number_format_i18n( WC()->cart ? WC()->cart->get_cart_contents_count() : 0 ) ); ?></span><span class="hs-cart-label"><?php esc_html_e( 'سبد خرید', 'hamrah-shop-theme' ); ?></span></a>
			<?php endif; ?>
		</div>
	</div>
	<nav class="hs-navbar" aria-label="<?php esc_attr_e( 'منوی اصلی', 'hamrah-shop-theme' ); ?>"><div class="hs-container hs-navbar-inner"><?php hs_theme_primary_menu(); ?><?php $blog = absint( get_option( 'page_for_posts' ) ); if ( $blog && ! has_nav_menu( 'primary' ) ) : ?><a href="<?php echo esc_url( get_permalink( $blog ) ); ?>" class="hs-blog-link"><?php esc_html_e( 'وبلاگ', 'hamrah-shop-theme' ); ?></a><?php endif; ?></div></nav>
	<dialog class="hs-dialog hs-menu-dialog" id="hs-mobile-menu" aria-labelledby="hs-mobile-menu-title">
		<div class="hs-dialog-heading"><h2 id="hs-mobile-menu-title"><?php esc_html_e( 'منوی فروشگاه', 'hamrah-shop-theme' ); ?></h2><button type="button" class="hs-icon-button" data-hs-dialog-close aria-label="<?php esc_attr_e( 'بستن منو', 'hamrah-shop-theme' ); ?>"><?php echo hs_theme_icon( 'close' ); ?></button></div>
		<nav aria-label="<?php esc_attr_e( 'منوی موبایل', 'hamrah-shop-theme' ); ?>"><?php hs_theme_primary_menu(); ?></nav>
		<?php if ( hs_theme_has_woo() ) : ?><a class="hs-mobile-account" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>"><?php echo hs_theme_icon( 'user' ); ?> <?php esc_html_e( 'حساب کاربری', 'hamrah-shop-theme' ); ?></a><?php endif; ?>
	</dialog>
</header>
