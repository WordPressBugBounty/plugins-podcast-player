<?php
/**
 * Podcast pod entry for episode entry list.
 *
 * This template can be overridden by copying it to yourtheme/podcast-player/list/entry.php.
 *
 * HOWEVER, on occasion Podcast Player will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @package Podcast Player
 * @version 1.0.0
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$pp_cats     = isset( $item['categories'] ) && is_array( $item['categories'] ) ? array_keys( $item['categories'] ) : array();
$pp_tags     = isset( $item['tags'] ) && is_array( $item['tags'] ) ? array_keys( $item['tags'] ) : array();
$pp_season   = isset( $item['season'] ) && $item['season'] ? array( 's-' . absint( $item['season'] ) ) : array();
$pp_combined = implode( ' ', array_merge( $pp_cats, $pp_tags, $pp_season ) );
?>

<div id="ppe-<?php echo esc_html( $ppe_id ); ?>" class="episode-list__entry pod-entry" data-search-term="<?php echo esc_attr( strtolower( $item['title'] ) ); ?>" data-cats="<?php echo esc_attr( $pp_combined ); ?>">
	<div class="pod-entry__wrapper">
		<div class="pod-entry__content">
			<div class="pod-entry__title">
				<a href="<?php echo esc_url( $item['link'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
			</div>
			<div class="pod-entry__date"><?php echo esc_html( $item['date'] ); ?></div>
			<?php if ( isset( $item['author'] ) && $item['author'] ) : ?>
				<div class="pod-entry__author"><?php echo esc_html( $item['author'] ); ?></div>
			<?php endif; ?>
		</div>
	</div>
</div>
