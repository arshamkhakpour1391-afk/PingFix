<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>
<main id="main-content" class="hs-container hs-main" tabindex="-1"><header class="hs-page-heading"><h1><?php echo wp_kses_post( get_the_archive_title() ); ?></h1><?php the_archive_description( '<div class="hs-archive-description">', '</div>' ); ?></header><?php get_template_part( 'template-parts/posts' ); ?></main>
<?php get_footer(); ?>
