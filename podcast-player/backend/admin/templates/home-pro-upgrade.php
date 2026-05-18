<?php
/**
 * Free home page Pro upgrade discovery section.
 *
 * @package Podcast Player
 * @since 8.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Podcast_Player\Helper\Functions\Getters as Get_Fn;

$feed_index    = Get_Fn::get_feed_index();
$podcast_count = is_array( $feed_index ) ? count( $feed_index ) : 0;

if ( 0 === $podcast_count ) {
	$pro_context_title = esc_html__( 'Build a stronger podcast page', 'podcast-player' );
	$pro_context_desc  = esc_html__( 'Start with the free player. Pro adds SEO-friendly episode pages and better layouts when you need them.', 'podcast-player' );
} elseif ( 1 === $podcast_count ) {
	$pro_context_title = esc_html__( 'Do more with this podcast', 'podcast-player' );
	$pro_context_desc  = esc_html__( 'Add SEO-friendly episode pages, better layouts, and saved settings for this show.', 'podcast-player' );
} else {
	$pro_context_title = esc_html__( 'Manage podcasts faster', 'podcast-player' );
	$pro_context_desc  = sprintf(
		/* translators: %d: Number of saved podcasts. */
		esc_html__( 'You have %d podcasts saved. Pro helps you import episodes, choose better layouts, and reuse settings.', 'podcast-player' ),
		absint( $podcast_count )
	);
}
?>

<section class="pp-home-pro-upgrade rounded-xl border border-slate-200 bg-slate-50 p-5 sm:p-6">
	<div class="flex flex-wrap items-start justify-between gap-4">
		<div class="max-w-3xl">
			<p class="mb-2 inline-flex rounded-full border border-amber-300 bg-white px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-amber-800"><?php esc_html_e( 'Podcast Player Pro', 'podcast-player' ); ?></p>
			<h3 class="m-0 text-xl font-semibold text-slate-900"><?php echo esc_html( $pro_context_title ); ?></h3>
			<p class="mb-0 mt-2 text-sm leading-relaxed text-slate-700"><?php echo esc_html( $pro_context_desc ); ?></p>
		</div>
		<a class="inline-flex items-center rounded-lg border border-slate-900 bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white no-underline transition-colors hover:border-slate-700 hover:bg-slate-700" href="https://easypodcastpro.com/podcast-player/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'See Pro Features', 'podcast-player' ); ?></a>
	</div>

	<div class="mt-5 grid gap-4 md:grid-cols-2">
		<div class="rounded-lg border border-solid border-slate-200 bg-white p-5">
			<div class="flex items-start gap-3">
				<span class="dashicons dashicons-admin-post mt-1 text-sky-700"></span>
				<div>
						<h4 class="m-0 text-base font-semibold text-slate-900"><?php esc_html_e( 'Create pages for every episode', 'podcast-player' ); ?></h4>
						<p class="mb-0 mt-2 text-sm leading-relaxed text-slate-700"><?php esc_html_e( 'Import episodes as posts with their own page, image, and link.', 'podcast-player' ); ?></p>
				</div>
			</div>
		</div>
		<div class="rounded-lg border border-solid border-slate-200 bg-white p-5">
			<div class="flex items-start gap-3">
				<span class="dashicons dashicons-layout mt-1 text-sky-700"></span>
				<div>
						<h4 class="m-0 text-base font-semibold text-slate-900"><?php esc_html_e( 'Use better podcast layouts', 'podcast-player' ); ?></h4>
						<p class="mb-0 mt-2 text-sm leading-relaxed text-slate-700"><?php esc_html_e( 'Try list, grid, compact, and card layouts for different pages.', 'podcast-player' ); ?></p>
				</div>
			</div>
		</div>
	</div>

	<div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
		<div class="rounded-lg border border-solid border-slate-200 bg-white p-4">
			<h4 class="m-0 text-sm font-semibold text-slate-900"><?php esc_html_e( 'Save player settings', 'podcast-player' ); ?></h4>
			<p class="mb-0 mt-2 text-sm leading-relaxed text-slate-600"><?php esc_html_e( 'Reuse the same image, menu, colors, and options.', 'podcast-player' ); ?></p>
		</div>
		<div class="rounded-lg border border-solid border-slate-200 bg-white p-4">
			<h4 class="m-0 text-sm font-semibold text-slate-900"><?php esc_html_e( 'Keep audio easy to reach', 'podcast-player' ); ?></h4>
			<p class="mb-0 mt-2 text-sm leading-relaxed text-slate-600"><?php esc_html_e( 'Add a sticky player or add players to many posts at once.', 'podcast-player' ); ?></p>
		</div>
		<div class="rounded-lg border border-solid border-slate-200 bg-white p-4">
			<h4 class="m-0 text-sm font-semibold text-slate-900"><?php esc_html_e( 'See what listeners use', 'podcast-player' ); ?></h4>
			<p class="mb-0 mt-2 text-sm leading-relaxed text-slate-600"><?php esc_html_e( 'Track plays, searches, and feedback.', 'podcast-player' ); ?></p>
		</div>
	</div>
</section>
