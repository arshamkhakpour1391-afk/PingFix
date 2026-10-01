<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( hs_theme_has_woo() && 'product' === get_query_var( 'post_type' ) ) { require get_template_directory() . '/woocommerce.php'; return; }
get_header();
?>
<main id="main-content" class="hs-container hs-main" tabindex="-1"><header class="hs-page-heading"><h1><?php echo esc_html( sprintf( __( 'نتایج جستجو: %s', 'hamrah-shop-theme' ), get_search_query( false ) ) ); ?></h1></header><?php get_template_part( 'template-parts/posts' ); ?></main>
<?php get_footer(); ?>
