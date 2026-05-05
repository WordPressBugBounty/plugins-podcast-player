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

?>

<div class="pp-toolkit-wrapper">
	<h3 class="pp-toolkit-title"><span><?php esc_html_e( 'Feed Update Tool', 'podcast-player' ); ?></span><span class="dashicons dashicons-arrow-down-alt2"></span></h3>
	<div class="pp-toolkit-content">
		<?php if ( $feed_index && is_array( $feed_index ) && ! empty( $feed_index ) ) : ?>
			<div class="pp-notice-info"><?php esc_html_e( 'Use this when a podcast has new episodes, changed artwork, or stale feed data. Updating refreshes the stored podcast data used by existing players.', 'podcast-player' ); ?></div>
			<?php
			$feed_index = array_merge(
				array( '' => esc_html__( 'Select a podcast to update or delete', 'podcast-player' ) ),
				$feed_index
			);
			?>
			<select id="pp-feed-index" name="pp-feed-index" class="select-pp-feed-index">
				<?php
				foreach ( $feed_index as $key => $label ) {
					if ( is_array( $label ) ) {
						$label = isset( $label['title'] ) ? $label['title'] : '';
					}
					echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
				}
				?>
			</select>
			<button class="pp-toolkit-buttons pp-feed-refresh button">
				<span class="dashicons dashicons-update"></span>
				<span class="pp-toolkit-btn-text">Update Podcast</span>
			</button>
			<button class="pp-toolkit-buttons pp-feed-del button">
				<span class="dashicons dashicons-trash"></span>
				<span class="pp-toolkit-btn-text">Delete Podcast</span>
			</button>
			<div class="pp-toolkit-del-confirm">
				<div class="pp-toolkit-del-msg">
					<?php esc_html_e( 'This will delete all stored data for the selected podcast. Existing players using this podcast may need to fetch the feed again.', 'podcast-player' ); ?>
				</div>
				<button class="pp-toolkit-buttons pp-feed-reset button">
					<span class="pp-toolkit-btn-text">Delete</span>
				</button>
				<button class="pp-toolkit-buttons pp-feed-cancel button">
					<span class="pp-toolkit-btn-text">Cancel</span>
				</button>
			</div>
			<div class="pp-toolkit-feedback">
				<span class="dashicons dashicons-update"></span>
				<span class="dashicons dashicons-no"></span>
				<span class="dashicons dashicons-yes"></span>
				<span class="pp-feedback"></span>
			</div>
		<?php else : ?>
			<div style="font-size: 20px !important; font-weight: bold; margin-bottom: 15px;"><?php esc_html_e( 'No podcasts are ready to update.', 'podcast-player' ); ?></div>
			<div style="font-size: 15px;"><?php esc_html_e( 'Add a podcast player first, then return here to refresh its stored feed data whenever the feed changes.', 'podcast-player' ); ?> <a href="https://easypodcastpro.com/docs7/" target="_blank"><?php esc_html_e( 'Open the setup docs', 'podcast-player' ); ?></a>.</div>
		<?php endif; ?>
	</div>
</div>
