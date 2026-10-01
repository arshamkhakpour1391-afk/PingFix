<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php if ( have_posts() ) : ?>
<div class="hs-post-grid">
<?php while ( have_posts() ) : the_post(); ?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'hs-post-card' ); ?>>
		<?php if ( has_post_thumbnail() ) : ?><a href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true"><?php the_post_thumbnail( 'medium_large', array( 'loading'=>'lazy' ) ); ?></a><?php endif; ?>
		<div class="hs-post-card-content"><p class="hs-post-meta"><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time><span><?php echo esc_html( get_the_author() ); ?></span></p><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><div class="hs-excerpt"><?php the_excerpt(); ?></div><a class="hs-text-link" href="<?php the_permalink(); ?>"><?php esc_html_e( 'خواندن مطلب', 'hamrah-shop-theme' ); ?><span class="screen-reader-text">: <?php the_title(); ?></span></a></div>
	</article>
<?php endwhile; ?>
</div>
<?php the_posts_pagination( array( 'prev_text'=>__( 'قبلی', 'hamrah-shop-theme' ), 'next_text'=>__( 'بعدی', 'hamrah-shop-theme' ) ) ); ?>
<?php else : ?>
<section class="hs-empty"><h2><?php echo esc_html( is_search() ? __( 'نتیجه‌ای پیدا نشد', 'hamrah-shop-theme' ) : __( 'هنوز نوشته‌ای منتشر نشده است', 'hamrah-shop-theme' ) ); ?></h2><?php if ( is_search() ) { get_search_form(); } ?></section>
<?php endif; ?>
