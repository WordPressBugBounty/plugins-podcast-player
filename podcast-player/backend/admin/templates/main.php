<?php
/**
 * Podcast player options page
 *
 * @package Podcast Player
 * @since 3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Podcast_Player\Helper\Functions\Markup as Markup_Fn;

$is_pro_installed  = defined( 'PP_PRO_VERSION' );
$is_license_markup = function_exists( 'pp_pro_license_options' );
$is_freemius_pro   = $is_pro_installed && function_exists( 'pp_fs' ) && ! $is_license_markup;
$license_status    = get_option( 'pp_pro_license_status' );
$license_key       = trim( (string) get_option( 'pp_pro_license_key' ) );

$pro_badge_text  = esc_html__( 'Podcast Player Pro', 'podcast-player' );
$pro_badge_class = 'border-none bg-green-600 text-white font-semibold';
$show_pro_popover = true;

if ( $is_freemius_pro ) {
	$pro_badge_text  = esc_html__( 'Pro (Freemius)', 'podcast-player' );
	$pro_badge_class = 'border-emerald-300 bg-emerald-50 text-emerald-800';
	$show_pro_popover = false;
} elseif ( $is_pro_installed && $is_license_markup ) {
	if ( $license_key && 'valid' === $license_status ) {
		$pro_badge_text  = esc_html__( 'Pro Active', 'podcast-player' );
		$pro_badge_class = 'border-emerald-300 bg-emerald-50 text-emerald-800 hover:border-emerald-400 hover:text-emerald-900';
	} elseif ( ! $license_key ) {
		$pro_badge_text  = esc_html__( 'Activate Pro', 'podcast-player' );
		$pro_badge_class = 'border-amber-300 bg-amber-50 text-amber-800 hover:border-amber-400 hover:text-amber-900';
	} elseif ( in_array( (string) $license_status, array( 'expired', 'invalid', 'disabled', 'revoked' ), true ) ) {
		$pro_badge_text  = esc_html__( 'Pro Expired', 'podcast-player' );
		$pro_badge_class = 'border-rose-300 bg-rose-50 text-rose-800 hover:border-rose-400 hover:text-rose-900';
	} else {
		$pro_badge_text  = esc_html__( 'Pending Pro Activation', 'podcast-player' );
		$pro_badge_class = 'border-indigo-300 bg-indigo-50 text-indigo-800 hover:border-indigo-400 hover:text-indigo-900';
	}
}

?>

<div id="pp-options-page" class="pp-options-page">
	<div class="pp-options-header border-b border-slate-200/90 bg-slate-900" style="background-color:#1f2937;">
		<div class="mx-auto 2xl:w-full max-w-[1280px] min-w-0 px-4 py-5 sm:px-6 lg:px-8">
			<div class="flex flex-wrap items-start justify-between gap-4">
				<div class="pp-options-title min-w-0 flex-1">
					<p class="mb-1 text-xs font-semibold uppercase tracking-[0.22em] text-slate-300"><?php esc_html_e( 'Admin Dashboard', 'podcast-player' ); ?></p>
					<h1 class="m-0 text-2xl font-semibold tracking-tight text-white sm:text-[28px]">
						<a class="pp-options-title-link text-inherit no-underline hover:text-slate-200" href="https://easypodcastpro.com/podcast-player/"><?php esc_html_e( 'Podcast Player', 'podcast-player' ); ?></a>
					</h1>
				</div>
			</div>
			<div class="mt-5 border-t border-slate-700/60 pt-4 sm:pt-5">
				<div class="flex flex-wrap items-start gap-3">
					<ul class="pp-options-menu m-0 flex min-w-max flex-nowrap gap-2 pb-1">
					<?php
					foreach ( $this->modules as $key => $args ) {
						$module_page = 'options' === $key ? 'home' : $key;
						$is_active   = $module_page === $current_page;
						$link_class  = $is_active
							? 'pp-module-item-link inline-flex rounded-lg bg-white px-4 py-2 text-sm font-medium text-slate-900 no-underline'
							: 'pp-module-item-link inline-flex rounded-lg bg-slate-700/70 px-4 py-2 text-sm font-medium text-slate-100 no-underline transition-colors hover:bg-slate-600 hover:text-white';
						printf(
							'<li class="pp-module-item mb-0"><a href="%1$s" class="%2$s"><span class="pp-module-text whitespace-nowrap">%3$s</span></a></li>',
							esc_url( admin_url( 'admin.php?page=pp-' . $key ) ),
							esc_attr( $link_class ),
							esc_html( $args['label'] )
						);
					}
					?>
					</ul>
					<div class="pp-options-links relative shrink-0 self-start">
						<?php if ( $show_pro_popover ) : ?>
						<button type="button" id="pp-header-popover-toggle" class="pp-options-link inline-flex items-center rounded-full border px-4 py-2 text-sm font-medium no-underline transition-colors cursor-pointer <?php echo esc_attr( $pro_badge_class ); ?>" aria-expanded="false" aria-controls="pp-header-popover-panel">
							<?php echo esc_html( $pro_badge_text ); ?>
						</button>
						<div id="pp-header-popover-panel" class="absolute right-0 top-full z-40 mt-2 hidden w-80 max-w-[calc(100vw-2rem)] sm:w-[340px] rounded-2xl border border-slate-200 bg-white p-4 shadow-xl">
							<?php require PODCAST_PLAYER_DIR . '/backend/admin/templates/sidebar.php'; ?>
						</div>
						<?php else : ?>
						<span class="pp-options-link inline-flex items-center rounded-full border px-4 py-2 text-sm font-medium <?php echo esc_attr( $pro_badge_class ); ?>">
							<?php echo esc_html( $pro_badge_text ); ?>
						</span>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="pp-options-main mx-auto flex 2xl:w-full max-w-[1280px] flex-col gap-6 px-4 py-6 sm:py-8 lg:py-10 sm:px-6 lg:px-8">
		<div id="pp-options-content" class="pp-options-content min-w-0 flex-1">
			<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
				<div class="pp-options-content-wrapper">
					<div class="pp-options-content-area min-w-0 p-4 sm:p-6 lg:p-8">
						<?php
						$located = Markup_Fn::locate_admin_template( $current_page );
						if ( $located ) {
							printf( '<div id="pp-options-module-%s" class="pp-module-content">', esc_attr( $current_page ) );
							include_once $located;
							echo '</div>';
						}
						?>
					</div>
					<div class="pp-options-footer border-t border-slate-200 bg-slate-50/70 px-4 py-4 text-sm text-slate-600 sm:px-6 lg:px-8">
						<div class="pp-options-copyright"><span><?php esc_html_e( 'EasyPodcastPro', 'podcast-player' ); ?> &copy; <?php echo esc_html( date_i18n( __( 'Y', 'podcast-player' ) ) ); ?></span></div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="pp-action-feedback fixed left-1/2 top-5 z-50 -translate-x-1/2 rounded-lg border border-slate-300 bg-white/95 px-3 py-2 shadow-md" id="pp-action-feedback">
		<span class="dashicons dashicons-update"></span>
		<span class="dashicons dashicons-no"></span>
		<span class="dashicons dashicons-yes"></span>
		<span class="pp-feedback"></span>
		<span class="pp-error-close"><span class="dashicons dashicons-no"></span></span>
	</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
	const toggle = document.getElementById('pp-header-popover-toggle');
	const panel = document.getElementById('pp-header-popover-panel');
	if (!toggle || !panel) {
		return;
	}

	const closePopover = function() {
		panel.classList.add('hidden');
		toggle.setAttribute('aria-expanded', 'false');
	};

	toggle.addEventListener('click', function(e) {
		e.preventDefault();
		e.stopPropagation();
		panel.classList.toggle('hidden');
		toggle.setAttribute('aria-expanded', panel.classList.contains('hidden') ? 'false' : 'true');
	});

	document.addEventListener('click', function(e) {
		if (!panel.contains(e.target) && !toggle.contains(e.target)) {
			closePopover();
		}
	});

	document.addEventListener('keydown', function(e) {
		if ('Escape' === e.key) {
			closePopover();
		}
	});
});
</script>
