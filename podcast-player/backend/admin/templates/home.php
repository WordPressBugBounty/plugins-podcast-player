<?php
/**
 * Podcast player options home page
 *
 * @package Podcast Player
 * @since 3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Podcast_Player\Helper\Functions\Getters as Get_Fn;
$feed_index = Get_Fn::get_feed_index();
?>

<div class="pp-welcome-wrapper">
	<div class="pp-welcome-main space-y-5">
		<?php if ( $feed_index && is_array( $feed_index ) && ! empty( $feed_index ) ) : ?>
			<div class="rounded-xl border border-solid border-slate-200 bg-slate-50 px-4 py-4 sm:px-5">
				<div class="flex flex-wrap items-center justify-between gap-3">
					<?php if ( count( $feed_index ) > 1 ) : ?>
						<h2 class="pp-podcasts-list-title m-0 text-xl font-semibold text-slate-900"><?php esc_html_e( 'Your Podcasts', 'podcast-player' ); ?></h2>
					<?php else : ?>
						<h2 class="pp-podcasts-list-title m-0 text-xl font-semibold text-slate-900"><?php esc_html_e( 'Your Podcast', 'podcast-player' ); ?></h2>
					<?php endif; ?>
					<span class="inline-flex rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600">
						<?php echo esc_html( count( $feed_index ) ); ?> <?php esc_html_e( 'Total', 'podcast-player' ); ?>
					</span>
				</div>
			</div>
			<div class="pp-podcasts-list-wrapper">
				<ul class="pp-podcasts-list m-0 space-y-2 p-0">
					<?php foreach ( $feed_index as $key => $args ) : ?>
						<li class="pp-podcast-list-item mb-0 rounded-sm border border-solid border-slate-200 bg-slate-50 p-4" data-podcast="<?php echo esc_attr( $key ); ?>">
							<div class="pp-podcast-info min-w-0 flex-1">
								<span class="pp-podcast-title block text-lg text-slate-800"><?php echo is_array( $args ) && isset( $args['title'] ) ? esc_html( $args['title'] ) : ''; ?></span>
								<div class="pp-podcast-source flex flex-wrap items-center gap-2">
									<span class="pp-podcast-url min-w-0"><a class="inline-block max-w-full truncate text-sm text-sky-700 no-underline hover:text-sky-900 hover:underline" href="<?php echo is_array( $args ) && isset( $args['url'] ) ? esc_html( $args['url'] ) : ''; ?>" target="_blank"><?php echo is_array( $args ) && isset( $args['url'] ) ? esc_html( $args['url'] ) : ''; ?></a></span>
									<button type="button" class="pp-podcast-source-btn inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-600 hover:border-slate-400 hover:text-slate-900"><span class="dashicons dashicons-edit"></span></button>
								</div>
								<div class="pp-podcast-source-container mt-3 rounded-lg border border-slate-300 bg-white p-3">
									<?php
									$source_url = isset( $args['source_url'] ) ? $args['source_url'] : '';
									?>
									<div class="pp-podcast-existing-source" <?php echo empty( $source_url ) ? 'style="display: none;"' : ''; ?>>
										<div><span class="text-sm font-semibold text-slate-800"><?php esc_html_e( 'Existing Source URL', 'podcast-player' ); ?></span></div>
										<div class="mt-1 flex items-center gap-2">
											<span class="pp-podcast-existing-source-url min-w-0 flex-1 break-all text-sm text-slate-700"><?php echo esc_html( $source_url ); ?></span>
											<a class="pp-podcast-delete-source-url inline-flex h-7 w-7 items-center justify-center rounded-md border border-rose-300 bg-rose-50 text-rose-700 no-underline hover:border-rose-400 hover:text-rose-900" href="#"><span class="dashicons dashicons-trash"></span></a>
										</div>
									</div>
									<div class="pp-podcast-new-source">
										<div class="pp-podcast-new-source-input mt-2">
											<input type="text" class="pp-podcast-new-source-url mb-0 rounded-md border-slate-300 text-sm" placeholder="New Source URL" />
											<button type="button" class="pp-podcast-new-source-btn ml-2 inline-flex h-9 items-center justify-center rounded-md border border-emerald-300 bg-emerald-50 px-3 text-emerald-700 hover:border-emerald-400 hover:text-emerald-900"><span class="dashicons dashicons-yes-alt"></span></button>
										</div>
										<span class="pp-podcast-new-source-desc mt-2 text-xs text-slate-500"><?php esc_html_e( 'If you have migrated this podcast to a new host, add new source URL here.', 'podcast-player' ); ?></span>
									</div>
								</div>
							</div>
							<div class="pp-podcast-actions ml-0 mt-4 sm:ml-4 sm:mt-0">
								<button class="pp-toolkit-buttons pp-podcast-refresh-btn button">
									<span class="dashicons dashicons-update"></span>
									<span class="pp-toolkit-btn-text !text-sm">Update</span>
									<span class="pp-loader"></span>
								</button>
								<button class="pp-toolkit-buttons pp-podcast-delete-btn button">
									<span class="dashicons dashicons-trash"></span>
									<span class="pp-toolkit-btn-text !text-sm">Delete</span>
									<span class="pp-loader"></span>
								</button>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php else : ?>
			<div class="rounded-xl border border-slate-200 bg-slate-50 p-6 sm:p-8">
				<h3 class="m-0 text-2xl font-semibold text-slate-900"><?php esc_html_e( 'Welcome to Podcast Player', 'podcast-player' ); ?></h3>
				<div class="mt-4 space-y-3 text-base leading-relaxed text-slate-700">
					<p class="m-0">Podcast player offers an easy and versatile way to show and play your <span class="pp-bold">existing podcast</span> on your website. You only need your <span class="pp-bold">podcast’s feed URL</span> to get started. Once you provide the feed URL, the player will automatically pull in your podcast information and episodes.</p>
					<p class="m-0">You can display the player using Widget, Editor Block, Shortcode or even Elementor plugin. We have created a <a href="<?php echo esc_attr( esc_url( admin_url( 'admin.php?page=pp-help' ) ) ); ?>" class="pp-bold underline">Help &amp; Support</a> section to get started with the plugin.</p>
					<p class="m-0">If you need any more help with our plugin, please feel free to <a class="font-medium text-sky-700 no-underline hover:underline" href="https://wordpress.org/support/plugin/podcast-player/" target="_blank">open a support ticket</a> or <a class="font-medium text-sky-700 no-underline hover:underline" href="https://easypodcastpro.com/contact-us-2/" target="_blank">contact us</a>.</p>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
