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

<div class="pp-sidebar-section rounded-xl border border-slate-200 bg-white p-4">
	<?php
	if ( function_exists( 'pp_pro_license_options' ) ) {
		pp_pro_license_options();
	} else {
		?>
		<div class="rounded-xl border border-amber-200 bg-amber-50 p-5">
			<p class="mb-2 inline-flex rounded-full border border-amber-300 bg-white px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-amber-800"><?php esc_html_e( 'Upgrade Available', 'podcast-player' ); ?></p>
			<h3 class="pp-pro-title m-0 text-xl font-semibold leading-tight text-slate-900"><?php esc_html_e( 'Unlock Podcast Player Pro', 'podcast-player' ); ?></h3>
			<p class="mb-0 mt-2 text-sm leading-relaxed text-slate-700"><?php esc_html_e( 'Create a more powerful podcast experience with advanced tools designed for growth and engagement.', 'podcast-player' ); ?></p>
			<ul class="pp-pro-features mt-4 space-y-2 text-sm text-slate-700">
				<li class="mb-0 flex items-start gap-2"><span class="mt-1 h-1.5 w-1.5 rounded-full bg-emerald-500"></span><span>Sleek and Professional Templates</span></li>
				<li class="mb-0 flex items-start gap-2"><span class="mt-1 h-1.5 w-1.5 rounded-full bg-emerald-500"></span><span>Import Episodes as Posts</span></li>
				<li class="mb-0 flex items-start gap-2"><span class="mt-1 h-1.5 w-1.5 rounded-full bg-emerald-500"></span><span>Episode Play Statistics</span></li>
				<li class="mb-0 flex items-start gap-2"><span class="mt-1 h-1.5 w-1.5 rounded-full bg-emerald-500"></span><span>Deep Episode Search & Smart Filters</span></li>
				<li class="mb-0 flex items-start gap-2"><span class="mt-1 h-1.5 w-1.5 rounded-full bg-emerald-500"></span><span>Sticky Player & Bulk Audio Tools</span></li>
				<li class="mb-0 flex items-start gap-2"><span class="mt-1 h-1.5 w-1.5 rounded-full bg-emerald-500"></span><span>Priority Support</span></li>
			</ul>
			<p class="mb-0 mt-4 text-xs font-medium text-slate-500"><?php esc_html_e( 'Upgrade now to unlock the complete toolkit and premium support.', 'podcast-player' ); ?></p>
			<?php $this->mlink( 'https://easypodcastpro.com/podcast-player/', 'Upgrade to Pro', 'pp-pro-more m-0 mt-4 inline-flex items-center justify-center rounded-lg border border-slate-900 bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white no-underline transition-colors hover:border-slate-700 hover:bg-slate-700' ); ?>
		</div>
		<?php
	}
	?>
</div>
