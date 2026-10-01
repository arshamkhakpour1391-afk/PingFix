<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>
<main id="main-content" class="hs-container hs-main hs-reading" tabindex="-1">
<?php while ( have_posts() ) : the_post(); ?>
	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>><header class="hs-page-heading"><p class="hs-post-meta"><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time><span><?php echo esc_html( get_the_author() ); ?></span></p><h1><?php the_title(); ?></h1><?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'large', array( 'class'=>'hs-article-image' ) ); } ?></header><div class="hs-page-content"><?php the_content(); wp_link_pages(); ?></div><footer class="hs-post-taxonomies"><?php the_category( '، ' ); the_tags( '<p>', '، ', '</p>' ); ?></footer></article>
	<?php the_post_navigation( array( 'prev_text'=>__( 'نوشتهٔ قبلی: %title', 'hamrah-shop-theme' ), 'next_text'=>__( 'نوشتهٔ بعدی: %title', 'hamrah-shop-theme' ) ) ); ?>
	<?php if ( comments_open() || get_comments_number() ) { comments_template(); } ?>
<?php endwhile; ?>
</main>
<?php get_footer(); ?>
