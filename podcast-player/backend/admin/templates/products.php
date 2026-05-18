<?php
/**
 * Podcast player new products page
 *
 * @package Podcast Player
 * @since 7.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Podcast_Player\Backend\Admin\Marketing_Context;

$products = Marketing_Context::get_product_recommendations();
?>

<div class="pp-products-page">
	<ol class="pp-products-list">
		<?php foreach ( $products as $product ) : ?>
			<li class="pp-product">
				<?php if ( ! empty( $product['reason'] ) ) : ?>
					<p class="pp-product-reason"><?php echo esc_html( $product['reason'] ); ?></p>
				<?php endif; ?>
				<h2 class="pp-product-title"><?php echo esc_html( $product['title'] ); ?></h2>
				<div class="pp-product-subtitle"><?php echo esc_html( $product['subtitle'] ); ?></div>
				<p class="pp-product-description"><?php echo esc_html( $product['description'] ); ?></p>
				<div><a class="pp-product-link" href="<?php echo esc_url( $product['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $product['cta'] ); ?></a></div>
			</li>
		<?php endforeach; ?>
	</ol>
</div>
