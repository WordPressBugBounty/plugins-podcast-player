<?php
/**
 * Podcast player sidebar
 *
 * @package Podcast Player
 * @since 3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="pp-sidebar-section">
	<?php
	if ( function_exists( 'pp_pro_license_options' ) ) {
		pp_pro_license_options();
	} else {
		?>
		<h3 class="pp-pro-title"><?php esc_html_e( 'Upgrade to Podcast Player Pro', 'podcast-player' ); ?></h3>
		<ul class="pp-pro-features">
			<li>Sleek and Professional Templates</li>
			<li>Import Episodes as Posts</li>
			<li>Episode Play Statistics.</li>
			<li>Smarter Episode Filters</li>
			<li>Deep Episode Search</li>
			<li>Custom Audio Messages</li>
			<li>Self-Hosted Episodes Support.</li>
			<li>Styling & Share Tools</li>
			<li>Priority Support</li>
			<li>…and more!</li>
		</ul>
		<?php $this->mlink( 'https://easypodcastpro.com/podcast-player/', 'Upgrade Now', 'button pp-pro-more' ); ?>
		<?php
	}
	?>
</div>
