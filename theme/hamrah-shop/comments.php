<?php
if ( ! defined( 'ABSPATH' ) || post_password_required() ) { return; }
?>
<section id="comments" class="hs-comments">
<?php if ( have_comments() ) : ?><h2><?php esc_html_e( 'دیدگاه‌ها', 'hamrah-shop-theme' ); ?></h2><ol class="comment-list"><?php wp_list_comments( array( 'style'=>'ol', 'short_ping'=>true, 'avatar_size'=>48 ) ); ?></ol><?php the_comments_navigation(); ?><?php endif; ?>
<?php if ( ! comments_open() && get_comments_number() ) : ?><p><?php esc_html_e( 'دیدگاه‌ها بسته شده‌اند.', 'hamrah-shop-theme' ); ?></p><?php endif; ?>
<?php comment_form( array( 'title_reply'=>__( 'دیدگاه شما', 'hamrah-shop-theme' ), 'label_submit'=>__( 'ارسال دیدگاه', 'hamrah-shop-theme' ) ) ); ?>
</section>
