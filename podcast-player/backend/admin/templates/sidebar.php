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
use Podcast_Player\Backend\Admin\Pro_Offer;
?>

<div class="pp-sidebar-section">
	<?php
	if ( function_exists( 'pp_pro_license_options' ) ) {
		pp_pro_license_options();
	} else {
		$sidebar_show_offer = Pro_Offer::should_show();
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
			<?php if ( $sidebar_show_offer ) : ?>
				<p class="mb-0 mt-3 text-sm text-slate-800">
					<?php
					/* translators: %s: the final date of the Black Friday offer. */
					echo esc_html( sprintf( __( 'Black Friday: save 30%% with code offer30 through %s.', 'podcast-player' ), date_i18n( 'F j, Y', strtotime( Pro_Offer::END_DATE ) ) ) );
					?>
				</p>
			<?php endif; ?>
			<?php $this->mlink( $sidebar_show_offer ? Pro_Offer::checkout_url() : Marketing_Context::PRO_URL, $sidebar_show_offer ? 'Get 30% off' : 'Get Now', 'pp-pro-more mt-4 flex w-full items-center justify-center rounded-md border border-sky-700 bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white no-underline transition-colors hover:border-sky-800 hover:bg-sky-800 hover:text-white' ); ?>
		</div>
		<?php
	}
	?>
</div>
