<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>
<main id="main-content" class="hs-container hs-main" tabindex="-1"><header class="hs-page-heading"><h1><?php $page = absint( get_option( 'page_for_posts' ) ); echo esc_html( $page ? get_the_title( $page ) : __( 'وبلاگ', 'hamrah-shop-theme' ) ); ?></h1></header><?php get_template_part( 'template-parts/posts' ); ?></main>
<?php get_footer(); ?>
