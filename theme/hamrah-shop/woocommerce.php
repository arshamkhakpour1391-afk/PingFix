<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>
<main id="main-content" class="hs-container hs-main woocommerce" tabindex="-1">
	<?php do_action( 'woocommerce_before_main_content' ); ?>
	<?php woocommerce_breadcrumb( array( 'delimiter'=>' <span aria-hidden="true">/</span> ' ) ); ?>
	<?php if ( is_product() ) : ?>
		<?php woocommerce_content(); ?>
	<?php else : ?>
		<?php $filters = class_exists( '\HamrahShop\Settings' ) && \HamrahShop\Settings::get( 'filters' ); ?>
		<?php if ( $filters ) : ?><button type="button" class="hs-mobile-filter-button button" data-hs-open-filters><?php echo hs_theme_icon( 'filter' ); ?> <?php esc_html_e( 'فیلتر محصولات', 'hamrah-shop-theme' ); ?></button><?php endif; ?>
		<p class="screen-reader-text" data-hs-shop-status role="status" aria-live="polite"></p>
		<div class="hs-shop-layout <?php echo $filters ? 'hs-with-filters' : ''; ?>" id="hs-shop-shell" data-hs-shop-shell>
			<?php if ( $filters ) : ?><aside class="hs-filter-aside" aria-label="<?php esc_attr_e( 'فیلترهای فروشگاه', 'hamrah-shop-theme' ); ?>"><?php echo do_shortcode( '[hamrah_shop_filters]' ); ?></aside><?php endif; ?>
			<section class="hs-catalog" data-hs-catalog-results aria-label="<?php esc_attr_e( 'محصولات فروشگاه', 'hamrah-shop-theme' ); ?>"><?php woocommerce_content(); ?></section>
		</div>
	<?php endif; ?>
	<?php do_action( 'woocommerce_after_main_content' ); ?>
</main>
<?php get_footer(); ?>
