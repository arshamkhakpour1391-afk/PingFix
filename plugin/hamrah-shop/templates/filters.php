<?php
namespace HamrahShop;
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<form class="hs-filter-form" action="<?php echo esc_url( Catalog::base_url() ); ?>" method="get" aria-label="<?php esc_attr_e( 'فیلتر محصولات', 'hamrah-shop' ); ?>">
	<div class="hs-filter-heading"><h2><?php esc_html_e( 'فیلتر محصولات', 'hamrah-shop' ); ?></h2><?php if ( Catalog::has_filters() ) : ?><a href="<?php echo esc_url( Catalog::clear_url() ); ?>" data-hs-catalog-link><?php esc_html_e( 'پاک کردن', 'hamrah-shop' ); ?></a><?php endif; ?></div>
	<?php if ( is_search() && isset( $_GET['s'] ) && is_string( $_GET['s'] ) ) : ?>
		<input type="hidden" name="s" value="<?php echo esc_attr( Support::clip( sanitize_text_field( wp_unslash( $_GET['s'] ) ), 180 ) ); ?>"><input type="hidden" name="post_type" value="product">
	<?php endif; ?>
	<?php if ( isset( $_GET['orderby'] ) && is_string( $_GET['orderby'] ) ) : ?><input type="hidden" name="orderby" value="<?php echo esc_attr( sanitize_key( wp_unslash( $_GET['orderby'] ) ) ); ?>"><?php endif; ?>
	<?php foreach ( $facets as $facet ) : ?>
	<details class="hs-facet" open data-hs-facet data-taxonomy="<?php echo esc_attr( $facet['taxonomy'] ); ?>" data-param="<?php echo esc_attr( $facet['param'] ); ?>" data-input-name="<?php echo esc_attr( $facet['name'] ); ?>" data-more="<?php echo $facet['more'] ? '1' : '0'; ?>">
		<summary><?php echo esc_html( $facet['label'] ); ?></summary>
		<fieldset><legend class="screen-reader-text"><?php echo esc_html( $facet['label'] ); ?></legend>
		<?php if ( count( $facet['terms'] ) > 12 ) : ?>
			<label class="hs-facet-search-label"><span class="screen-reader-text"><?php echo esc_html( sprintf( __( 'جستجو در %s', 'hamrah-shop' ), $facet['label'] ) ); ?></span><input type="search" data-hs-term-search autocomplete="off" placeholder="<?php esc_attr_e( 'جستجوی گزینه‌ها', 'hamrah-shop' ); ?>"></label>
		<?php endif; ?>
		<div class="hs-facet-options" data-hs-term-options>
		<?php foreach ( $facet['terms'] as $term ) :
			$value = 'hs_categories' === $facet['param'] ? (string) $term->term_id : $term->slug;
			$depth = 'product_cat' === $term->taxonomy ? min( 3, count( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) ) ) : 0;
		?>
			<label class="hs-check hs-depth-<?php echo absint( $depth ); ?>"><input type="checkbox" name="<?php echo esc_attr( $facet['name'] ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( in_array( $value, $facet['selected'], true ) ); ?>><span><?php echo esc_html( $term->name ); ?></span></label>
		<?php endforeach; ?>
		</div><p class="hs-facet-status" data-hs-term-status aria-live="polite"><?php if ( $facet['more'] ) { esc_html_e( 'برای گزینه‌های بیشتر، جستجو کنید.', 'hamrah-shop' ); } ?></p>
		</fieldset>
	</details>
	<?php endforeach; ?>
	<?php if ( Settings::get( 'filter_price' ) ) : ?>
	<details class="hs-facet" open><summary><?php esc_html_e( 'محدودهٔ قیمت', 'hamrah-shop' ); ?> <span class="hs-small"><?php echo esc_html( html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) ); ?></span></summary>
		<fieldset class="hs-price-fields"><legend class="screen-reader-text"><?php esc_html_e( 'محدودهٔ قیمت', 'hamrah-shop' ); ?></legend>
			<label><?php esc_html_e( 'از', 'hamrah-shop' ); ?><input type="text" inputmode="decimal" name="min_price" value="<?php echo esc_attr( Support::decimal( $_GET['min_price'] ?? '' ) ); ?>" maxlength="17" autocomplete="off"></label>
			<label><?php esc_html_e( 'تا', 'hamrah-shop' ); ?><input type="text" inputmode="decimal" name="max_price" value="<?php echo esc_attr( Support::decimal( $_GET['max_price'] ?? '' ) ); ?>" maxlength="17" autocomplete="off"></label>
		</fieldset>
	</details>
	<?php endif; ?>
	<?php if ( Settings::get( 'filter_stock' ) ) : ?>
	<details class="hs-facet" open><summary><?php esc_html_e( 'وضعیت موجودی', 'hamrah-shop' ); ?></summary><fieldset><legend class="screen-reader-text"><?php esc_html_e( 'وضعیت موجودی', 'hamrah-shop' ); ?></legend>
		<?php foreach ( array( 'instock'=>__( 'موجود', 'hamrah-shop' ), 'outofstock'=>__( 'ناموجود', 'hamrah-shop' ), 'onbackorder'=>__( 'قابل پیش‌خرید', 'hamrah-shop' ) ) as $value => $label ) : ?>
		<label class="hs-check"><input type="checkbox" name="hs_stock[]" value="<?php echo esc_attr( $value ); ?>" <?php checked( in_array( $value, Catalog::selected( 'hs_stock' ), true ) ); ?>><span><?php echo esc_html( $label ); ?></span></label>
		<?php endforeach; ?>
	</fieldset></details>
	<?php endif; ?>
	<?php if ( Settings::get( 'filter_sale' ) ) : ?>
	<label class="hs-check hs-sale-filter"><input type="checkbox" name="hs_sale" value="1" <?php checked( ! empty( $_GET['hs_sale'] ) ); ?>><span><?php esc_html_e( 'فقط محصولات تخفیف‌دار', 'hamrah-shop' ); ?></span></label>
	<?php endif; ?>
	<div class="hs-filter-actions"><button type="submit" class="button hs-button"><?php esc_html_e( 'نمایش نتایج', 'hamrah-shop' ); ?></button><a href="<?php echo esc_url( Catalog::clear_url() ); ?>" class="hs-filter-reset" data-hs-catalog-link><?php esc_html_e( 'حذف همهٔ فیلترها', 'hamrah-shop' ); ?></a></div>
</form>
