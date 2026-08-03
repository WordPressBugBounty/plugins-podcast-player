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
use Podcast_Player\Backend\Admin\Marketing_Context;

$feed_index    = Get_Fn::get_feed_index();
$pp_store      = \Podcast_Player\Helper\Store\StoreManager::get_instance();
$pp_candidates = $pp_store->get_migration_candidates();

$pp_duplicate_groups                = $pp_store->get_pending_merge_groups();
$pp_duplicate_groups_by_visible_key = array();
$pp_duplicate_hidden_only_groups    = array();
foreach ( $pp_duplicate_groups as $pp_group_index => $pp_group ) {
	$pp_visible_keys = array();
	foreach ( $pp_group['member_keys'] as $pp_member_key ) {
		if ( isset( $feed_index[ $pp_member_key ] ) ) {
			$pp_visible_keys[] = $pp_member_key;
		}
	}
	$pp_duplicate_groups[ $pp_group_index ]['visible_keys'] = $pp_visible_keys;
	if ( empty( $pp_visible_keys ) ) {
		$pp_duplicate_hidden_only_groups[] = $pp_duplicate_groups[ $pp_group_index ];
		continue;
	}
	foreach ( $pp_visible_keys as $pp_visible_key ) {
		$pp_duplicate_groups_by_visible_key[ $pp_visible_key ] = $pp_group_index;
	}
}

$pp_render_podcast_row = function( $key, $args ) use ( $pp_candidates ) {
	$pp_candidate      = isset( $pp_candidates[ $key ] ) && is_array( $pp_candidates[ $key ] ) ? $pp_candidates[ $key ] : array();
	$pp_has_candidate  = ! empty( $pp_candidate['to'] );
	$pp_candidate_seen = $pp_has_candidate && ! empty( $pp_candidate['seen'] );
	$pp_current_url    = is_array( $args ) && isset( $args['url'] ) ? $args['url'] : '';
	?>
	<li class="pp-podcast-list-item mb-0 rounded-sm border border-solid border-slate-200 bg-slate-50 p-4" data-podcast="<?php echo esc_attr( $key ); ?>">
		<div class="pp-podcast-info min-w-0 flex-1">
			<span class="pp-podcast-title block text-lg text-slate-800"><?php echo is_array( $args ) && isset( $args['title'] ) ? esc_html( $args['title'] ) : ''; ?></span>
			<div class="pp-podcast-source flex flex-wrap items-center gap-2">
				<span class="pp-podcast-url min-w-0"><a class="inline-block max-w-full truncate text-sm text-sky-700 no-underline hover:text-sky-900 hover:underline" href="<?php echo esc_url( $pp_current_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $pp_current_url ); ?></a></span>
				<button type="button" class="pp-podcast-source-btn relative inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-600 hover:border-slate-400 hover:text-slate-900" title="<?php esc_attr_e( 'Podcast feed settings', 'podcast-player' ); ?>">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php if ( $pp_has_candidate && ! $pp_candidate_seen ) : ?>
						<span class="pp-migration-unseen-dot absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-rose-600" aria-hidden="true"></span>
					<?php endif; ?>
				</button>
			</div>
			<?php $next_action = Marketing_Context::get_next_action_for_feed( $key ); ?>
			<?php if ( $next_action ) : ?>
				<?php $pro_tip_panel_id = 'pp-pro-tip-' . md5( (string) $key . ':' . $next_action['id'] ); ?>
				<div class="pp-podcast-pro-tip">
					<span class="pp-podcast-pro-tip-icon dashicons dashicons-lightbulb" aria-hidden="true"></span>
					<div class="pp-podcast-pro-tip-content">
						<span class="pp-podcast-pro-tip-label"><?php esc_html_e( 'Pro Tip:', 'podcast-player' ); ?></span>
						<button type="button" class="pp-podcast-pro-tip-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $pro_tip_panel_id ); ?>">
							<?php echo esc_html( $next_action['label'] ); ?>
						</button>
						<div id="<?php echo esc_attr( $pro_tip_panel_id ); ?>" class="pp-podcast-pro-tip-panel hidden">
							<p><?php echo esc_html( $next_action['description'] ); ?></p>
							<a href="<?php echo esc_url( $next_action['url'] ); ?>" target="_blank" rel="noopener noreferrer">
								<span><?php esc_html_e( 'Learn More', 'podcast-player' ); ?></span>
								<span class="dashicons dashicons-external" aria-hidden="true"></span>
							</a>
						</div>
					</div>
				</div>
			<?php endif; ?>
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
				<?php if ( $pp_has_candidate ) : ?>
					<div class="pp-podcast-migration-candidate mb-3 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900" data-target="<?php echo esc_attr( $pp_candidate['to'] ); ?>">
						<p class="m-0 font-semibold"><?php esc_html_e( 'This feed redirects to another feed URL.', 'podcast-player' ); ?></p>
						<div class="mt-2 space-y-1">
							<p class="m-0"><span class="font-semibold"><?php esc_html_e( 'Current feed:', 'podcast-player' ); ?></span> <span class="break-all"><?php echo esc_html( $pp_current_url ); ?></span></p>
							<p class="m-0"><span class="font-semibold"><?php esc_html_e( 'Redirect target:', 'podcast-player' ); ?></span> <span class="break-all"><?php echo esc_html( $pp_candidate['to'] ); ?></span></p>
						</div>
						<div class="mt-3 flex flex-wrap gap-2">
							<button type="button" class="pp-migration-same-feed-btn button">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'They are the same feed', 'podcast-player' ); ?>
							</button>
							<button type="button" class="pp-migration-use-source-btn button button-primary">
								<span class="dashicons dashicons-migrate"></span>
								<?php esc_html_e( 'Use redirected feed as source', 'podcast-player' ); ?>
							</button>
						</div>
					</div>
				<?php endif; ?>
				<div class="pp-podcast-new-source">
					<div class="pp-podcast-new-source-input mt-2">
						<input type="text" class="pp-podcast-new-source-url mb-0 rounded-md border-slate-300 text-sm" placeholder="New Source URL" />
						<button type="button" class="pp-podcast-new-source-btn ml-2 inline-flex h-9 items-center justify-center rounded-md border border-emerald-300 bg-emerald-50 px-3 text-emerald-700 hover:border-emerald-400 hover:text-emerald-900"><span class="dashicons dashicons-yes-alt"></span></button>
					</div>
					<span class="pp-podcast-new-source-desc mt-2 text-xs text-slate-500"><?php esc_html_e( 'Moved this podcast to a new host? Add the new source URL here so existing players keep using the same podcast entry.', 'podcast-player' ); ?></span>
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
	<?php
};

$pp_render_hidden_podcast_row = function( $key, $record ) {
	$source_url = $record->get( 'source_url', 'none' );
	$feed_urls  = $record->get( 'feed_url', 'none' );
	$display_url = $source_url;
	if ( ! $display_url && is_array( $feed_urls ) && ! empty( $feed_urls ) ) {
		$display_url = reset( $feed_urls );
	}
	?>
	<li class="pp-podcast-list-item pp-podcast-list-item-hidden mb-0 rounded-sm border border-dashed border-slate-300 bg-slate-100 p-4 text-slate-500" data-podcast="<?php echo esc_attr( $key ); ?>" aria-disabled="true">
		<div class="pp-podcast-info min-w-0 flex-1">
			<div class="flex flex-wrap items-center gap-2">
				<span class="pp-podcast-title block text-lg text-slate-500"><?php echo esc_html( $record->get( 'title' ) ? $record->get( 'title' ) : __( 'Hidden podcast record', 'podcast-player' ) ); ?></span>
				<span class="rounded-full border border-slate-300 bg-white px-2 py-0.5 text-xs font-semibold uppercase text-slate-500"><?php esc_html_e( 'Hidden record', 'podcast-player' ); ?></span>
			</div>
			<?php if ( $display_url ) : ?>
				<div class="pp-podcast-source mt-1 flex flex-wrap items-center gap-2">
					<span class="pp-podcast-url min-w-0 break-all text-sm text-slate-500"><?php echo esc_html( $display_url ); ?></span>
				</div>
			<?php endif; ?>
		</div>
	</li>
	<?php
};

$pp_render_duplicate_group = function( $group, $feed_index ) use ( $pp_render_podcast_row, $pp_render_hidden_podcast_row ) {
	$visible_count = isset( $group['visible_keys'] ) ? count( $group['visible_keys'] ) : 0;
	$message       = ! empty( $group['has_hidden'] )
		? __( 'This podcast matches a hidden stored record from a previous delete or reset. You can merge stored data into one entry or keep them separate.', 'podcast-player' )
		: __( 'These podcast entries share the same feed or source URL. You can merge them into one entry or keep them separate.', 'podcast-player' );
	if ( 0 === $visible_count ) {
		$message = __( 'These hidden stored records share the same feed or source URL. You can merge their stored data or keep them separate.', 'podcast-player' );
	}
	?>
	<li class="pp-duplicate-feed-group pp-migration-review-item mb-0 rounded-lg border border-amber-300 bg-amber-50 p-3" data-mtype="group" data-member-keys="<?php echo esc_attr( implode( ',', $group['member_keys'] ) ); ?>" data-refresh-on-success="1">
		<ul class="pp-duplicate-feed-items m-0 space-y-2 p-0">
			<?php foreach ( $group['member_keys'] as $member_key ) : ?>
				<?php
				if ( isset( $feed_index[ $member_key ] ) ) {
					$pp_render_podcast_row( $member_key, $feed_index[ $member_key ] );
				} elseif ( isset( $group['records'][ $member_key ] ) ) {
					$pp_render_hidden_podcast_row( $member_key, $group['records'][ $member_key ] );
				}
				?>
			<?php endforeach; ?>
		</ul>
		<div class="pp-duplicate-feed-review mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-amber-200 pt-3">
			<p class="m-0 min-w-0 flex-1 text-sm text-amber-900"><?php echo esc_html( $message ); ?></p>
			<div class="flex flex-wrap gap-2">
				<button type="button" class="pp-migration-merge-btn button button-primary"><?php esc_html_e( 'Merge entries', 'podcast-player' ); ?></button>
				<button type="button" class="pp-migration-dismiss-btn button"><?php esc_html_e( 'Keep separate', 'podcast-player' ); ?></button>
			</div>
		</div>
	</li>
	<?php
};
?>

	<div class="pp-welcome-wrapper">
		<div class="pp-welcome-main space-y-5">
			<?php if ( ! empty( $pp_duplicate_hidden_only_groups ) ) : ?>
				<div class="pp-hidden-duplicate-review rounded-xl border border-slate-200 bg-slate-50 p-4">
					<h3 class="m-0 text-base font-semibold text-slate-900"><?php esc_html_e( 'Stored podcasts to review', 'podcast-player' ); ?></h3>
					<ul class="pp-hidden-duplicate-list mt-3 m-0 space-y-2 p-0">
						<?php foreach ( $pp_duplicate_hidden_only_groups as $pp_hidden_group ) : ?>
							<?php $pp_render_duplicate_group( $pp_hidden_group, $feed_index ); ?>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
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
							<?php
							$pp_group_index = isset( $pp_duplicate_groups_by_visible_key[ $key ] ) ? $pp_duplicate_groups_by_visible_key[ $key ] : null;
							if ( null !== $pp_group_index ) {
								$pp_group = $pp_duplicate_groups[ $pp_group_index ];
								if ( isset( $pp_group['visible_keys'][0] ) && $key === $pp_group['visible_keys'][0] ) {
									$pp_render_duplicate_group( $pp_group, $feed_index );
								}
								continue;
							}
							$pp_render_podcast_row( $key, $args );
							?>
						<?php endforeach; ?>
					</ul>
				</div>
		<?php else : ?>
			<div class="rounded-xl border border-slate-200 bg-slate-50 p-6 sm:p-8">
				<h3 class="m-0 text-2xl font-semibold text-slate-900"><?php esc_html_e( 'Welcome to Podcast Player', 'podcast-player' ); ?></h3>
				<div class="mt-4 space-y-3 text-base leading-relaxed text-slate-700">
					<p class="m-0">Want to play your existing podcast on this website? Add the podcast feed URL once and Podcast Player will pull in the podcast details and episodes automatically.</p>
					<p class="m-0">You can place the player with a widget, editor block, shortcode, shortcode generator, or Elementor. Start with <a href="<?php echo esc_attr( esc_url( admin_url( 'admin.php?page=pp-help' ) ) ); ?>" class="pp-bold underline">Help &amp; Support</a> if you need the first setup steps.</p>
					<p class="m-0">Need help with a feed or display issue? <a class="font-medium text-sky-700 no-underline hover:underline" href="https://wordpress.org/support/plugin/podcast-player/" target="_blank" rel="noopener noreferrer">Open a support ticket</a> or <a class="font-medium text-sky-700 no-underline hover:underline" href="https://easypodcastpro.com/contact-us-2/" target="_blank" rel="noopener noreferrer">contact us</a>.</p>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
