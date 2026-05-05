<?php
/**
 * Podcast player toolkit page
 *
 * @package Podcast Player
 * @since 3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Podcast_Player\Helper\Functions\Country_Codes;
use Podcast_Player\Helper\Functions\Markup as Markup_Fn;

$country_codes = Country_Codes::get_cc();

?>

<div class="pp-toolkit-wrapper pp-toolkit-reviews">
	<h3 class="pp-toolkit-title"><span><?php esc_html_e( 'Apple Podcast Reviews', 'podcast-player' ); ?></span><span class="dashicons dashicons-arrow-down-alt2"></span></h3>
	<div class="pp-toolkit-content">
		<div class="pp-notice-info"><?php esc_html_e( 'Fetch Apple Podcast reviews from selected countries so you can display or manage review data for a saved podcast.', 'podcast-player' ); ?></div>
		<div class="pp-toolkit-review-dropdown">
			<?php if ( $feed_index && is_array( $feed_index ) && ! empty( $feed_index ) ) : ?>
				<?php
				$feed_index = array_merge(
					$feed_index,
					array( '' => esc_html__( 'Select a Podcast', 'podcast-player' ) )
				);
				?>
				<span style="display: block;margin-bottom: 5px;"><?php esc_html_e( 'Select Podcast', 'podcast-player' ); ?></span>
				<select class="select-pp-feed-index">
					<?php
					foreach ( $feed_index as $key => $label ) {
						if ( is_array( $label ) ) {
							$label = isset( $label['title'] ) ? $label['title'] : '';
						}
						echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
					}
					?>
				</select>
			<?php else : ?>
				<div style="font-size: 20px !important; font-weight: bold; margin-bottom: 15px;"><?php esc_html_e( 'No saved podcasts found.', 'podcast-player' ); ?></div>
				<div style="font-size: 15px;"><?php esc_html_e( 'Add a podcast first, then return here to fetch Apple Podcast reviews for it.', 'podcast-player' ); ?> <a href="https://easypodcastpro.com/docs7/" target="_blank"><?php esc_html_e( 'Open the setup docs', 'podcast-player' ); ?></a>.</div>
			<?php endif; ?>
		</div>
		<div class="pp-toolkit-feedback" style=" margin-bottom: 15px; margin-top: 0">
			<span class="dashicons dashicons-update"></span>
			<span class="dashicons dashicons-no"></span>
			<span class="dashicons dashicons-yes"></span>
			<span class="pp-feedback"></span>
		</div>
		<div class="pp-toolkit-review-form" style="display: none;">
			<div class="pp-podcast-apple-url">
				<span style="display: block;margin-bottom: 5px;"><?php esc_html_e( 'Apple Podcast ID', 'podcast-player' ); ?></span>
				<label class="pp-apple-podcast-url-label">
					<input class="pp-apple-podcast-url-input" type="url" placeholder="<?php esc_attr_e( 'Enter Apple Podcast ID', 'podcast-player' ); ?>" title="<?php esc_attr_e( 'Apple Podcast ID', 'podcast-player' ); ?>">
				</label>
			</div>
			<div class="pp-podcast-apple-country">
				<span style="display: block;margin-bottom: 5px;"><?php esc_html_e( 'Countries to fetch reviews from', 'podcast-player' ); ?></span>
				<?php Markup_Fn::multiple_checkbox( 'pp-select-country', $country_codes, array(), array(), 'Search Country' ); ?>
			</div>
			<div class="pp-review-action-buttons" style="margin-top: 15px;">
				<button class="button podcast-reviews-refresh">
					<span class="pp-refresh-label"><?php esc_html_e( 'Refresh Reviews', 'podcast-player' ); ?></span>
					<span class="pp-fetch-label"><?php esc_html_e( 'Fetch Reviews', 'podcast-player' ); ?></span>
				</button>
				<button class="button podcast-reviews-delete">
					<span class="pp-delete-label"><?php esc_html_e( 'Delete Reviews', 'podcast-player' ); ?></span>
				</button>
			</div>
		</div>
	</div>
</div>
