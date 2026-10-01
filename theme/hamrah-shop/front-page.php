<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>
<main id="main-content" class="hs-container hs-main" tabindex="-1">
<?php if ( is_home() ) : ?>
	<header class="hs-page-heading"><h1><?php esc_html_e( 'وبلاگ', 'hamrah-shop-theme' ); ?></h1></header>
	<?php get_template_part( 'template-parts/posts' ); ?>
<?php else : ?>
	<?php while ( have_posts() ) : the_post(); ?>
	<section class="hs-home-intro"><div class="hs-home-title"><h1><?php the_title(); ?></h1><?php if ( hs_theme_has_woo() ) : ?><a class="hs-text-link" href="<?php echo esc_url( hs_theme_shop_url() ); ?>"><?php esc_html_e( 'همهٔ محصولات', 'hamrah-shop-theme' ); ?> <?php echo hs_theme_icon( 'arrow' ); ?></a><?php endif; ?></div><div class="hs-page-content"><?php the_content(); ?></div></section>
	<?php endwhile; ?>
	<?php if ( hs_theme_has_woo() ) : ?>
		<?php if ( hs_theme_setting( 'home_categories', 1 ) ) : $terms = get_terms( array( 'taxonomy'=>'product_cat', 'hide_empty'=>true, 'parent'=>0, 'number'=>12, 'menu_order'=>'ASC' ) ); if ( ! is_wp_error( $terms ) && $terms ) : ?>
		<section class="hs-home-categories"><div class="hs-section-heading"><h2><?php esc_html_e( 'دسته‌بندی محصولات', 'hamrah-shop-theme' ); ?></h2></div><div class="hs-category-grid">
			<?php foreach ( $terms as $term ) : $link = get_term_link( $term ); if ( is_wp_error( $link ) ) { continue; } $image = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) ); ?>
			<a class="hs-category-card" href="<?php echo esc_url( $link ); ?>"><?php if ( $image ) { echo wp_get_attachment_image( $image, 'woocommerce_thumbnail', false, array( 'loading'=>'lazy', 'alt'=>$term->name ) ); } else { echo '<span class="hs-category-symbol">' . hs_theme_icon( 'grid' ) . '</span>'; } ?><span><?php echo esc_html( $term->name ); ?></span></a>
			<?php endforeach; ?>
		</div></section>
		<?php endif; endif; ?>
		<?php $has_latest = false; if ( hs_theme_setting( 'home_latest', 1 ) ) { $has_latest = hs_theme_home_products( 'latest' ); } if ( hs_theme_setting( 'home_sale', 1 ) ) { hs_theme_home_products( 'sale' ); } ?>
		<?php if ( ! $has_latest && hs_theme_setting( 'home_latest', 1 ) ) : ?>
		<section class="hs-empty"><span class="hs-empty-icon"><?php echo hs_theme_icon( 'grid' ); ?></span><h2><?php esc_html_e( 'محصولی برای نمایش وجود ندارد', 'hamrah-shop-theme' ); ?></h2><p><?php esc_html_e( 'در حال حاضر محصول قابل نمایشی در این بخش وجود ندارد.', 'hamrah-shop-theme' ); ?></p><a class="hs-button" href="<?php echo esc_url( hs_theme_shop_url() ); ?>"><?php esc_html_e( 'رفتن به فروشگاه', 'hamrah-shop-theme' ); ?></a></section>
		<?php endif; ?>
	<?php endif; ?>
<?php endif; ?>
</main>
<?php get_footer(); ?>
