<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>
<main id="main-content" class="hs-container hs-main hs-page" tabindex="-1">
<?php while ( have_posts() ) : the_post(); ?>
	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>><header class="hs-page-heading"><h1><?php the_title(); ?></h1></header><div class="hs-page-content"><?php the_content(); wp_link_pages( array( 'before'=>'<nav class="hs-page-links" aria-label="' . esc_attr__( 'صفحه‌های نوشته', 'hamrah-shop-theme' ) . '">', 'after'=>'</nav>' ) ); ?></div></article>
	<?php if ( comments_open() || get_comments_number() ) { comments_template(); } ?>
<?php endwhile; ?>
</main>
<?php get_footer(); ?>
