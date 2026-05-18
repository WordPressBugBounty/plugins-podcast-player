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

use Podcast_Player\Backend\Admin\Marketing_Context;
?>

<div class="pp-sidebar-section">
	<?php
	if ( function_exists( 'pp_pro_license_options' ) ) {
		pp_pro_license_options();
	} else {
		?>
		<div class="rounded-lg border border-sky-200 bg-white p-4 shadow-sm">
			<div class="border-b border-slate-200 pb-3">
				<p class="mb-2 inline-flex rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-sky-800"><?php esc_html_e( 'Podcast Player Pro', 'podcast-player' ); ?></p>
				<h3 class="pp-pro-title m-0 text-lg font-semibold leading-tight text-slate-950"><?php esc_html_e( 'Build a stronger podcast page', 'podcast-player' ); ?></h3>
				<p class="mb-0 mt-1.5 text-sm leading-relaxed text-slate-600"><?php esc_html_e( 'Upgrade when you are ready to turn episodes into better pages, layouts, and search tools.', 'podcast-player' ); ?></p>
			</div>
			<ul class="m-0 mt-3 list-none space-y-2 p-0 text-sm text-slate-800">
				<li class="flex items-start gap-2">
					<span class="dashicons dashicons-yes-alt mt-0.5 h-4 w-4 shrink-0 text-sky-700" aria-hidden="true"></span>
					<span><?php esc_html_e( 'SEO-friendly pages for every episode', 'podcast-player' ); ?></span>
				</li>
				<li class="flex items-start gap-2">
					<span class="dashicons dashicons-yes-alt mt-0.5 h-4 w-4 shrink-0 text-sky-700" aria-hidden="true"></span>
					<span><?php esc_html_e( 'More player layouts and display templates', 'podcast-player' ); ?></span>
				</li>
				<li class="flex items-start gap-2">
					<span class="dashicons dashicons-yes-alt mt-0.5 h-4 w-4 shrink-0 text-sky-700" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Search inside long episode notes', 'podcast-player' ); ?></span>
				</li>
				<li class="flex items-start gap-2">
					<span class="dashicons dashicons-yes-alt mt-0.5 h-4 w-4 shrink-0 text-sky-700" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Filters by season, category, or topic', 'podcast-player' ); ?></span>
				</li>
				<li class="flex items-start gap-2">
					<span class="dashicons dashicons-yes-alt mt-0.5 h-4 w-4 shrink-0 text-sky-700" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Import feed episodes as WordPress posts', 'podcast-player' ); ?></span>
				</li>
				<li class="flex items-start gap-2">
					<span class="dashicons dashicons-yes-alt mt-0.5 h-4 w-4 shrink-0 text-sky-700" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Saved defaults, bulk tools, and listener stats', 'podcast-player' ); ?></span>
				</li>
			</ul>
			<?php $this->mlink( Marketing_Context::PRO_URL, 'Get Now', 'pp-pro-more mt-4 flex w-full items-center justify-center rounded-md border border-sky-700 bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white no-underline transition-colors hover:border-sky-800 hover:bg-sky-800 hover:text-white' ); ?>
		</div>
		<?php
	}
	?>
</div>
