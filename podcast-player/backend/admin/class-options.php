<?php
/**
 * The admin-options page of the plugin.
 *
 * @link       https://www.vedathemes.com
 * @since      1.0.0
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/admin
 */

namespace Podcast_Player\Backend\Admin;

// Return if called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Podcast_Player\Helper\Functions\Getters as Get_Fn;
use Podcast_Player\Helper\Functions\Utility as Utility_Fn;
use Podcast_Player\Helper\Functions\Validation as Validation_Fn;
use Podcast_Player\Helper\Store\FeedData;
use Podcast_Player\Helper\Store\StoreManager;
use Podcast_Player\Helper\Feed\Fetch_Feed;
use Podcast_Player\Helper\Feed\Get_Feed;
use Podcast_Player\Frontend\Inc\Loader as Front_Loader;

/**
 * The admin-options page of the plugin.
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/admin
 * @author     vedathemes <contact@easypodcastpro.com>
 */
class Options {

	/**
	 * Holds the instance of this class.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    object
	 */
	protected static $instance = null;

	/**
	 * Holds different modules of Podcast player options page.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array $sections
	 */
	private $modules = array();

	/**
	 * Holds different sections of Podcast player options page.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array $sections
	 */
	private $sections = array();

	/**
	 * Holds different blocks of Podcast player documentation page.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array $doc_blocks
	 */
	private $doc_blocks = array();

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {}

	/**
	 * Determine whether the premium feature set is active.
	 */
	private static function is_pro_enabled() {
		return defined( 'PP_PRO_VERSION' ) || apply_filters( 'podcast_player_is_premium', false );
	}

	/**
	 * Register hooked functions.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		$inst = self::get_instance();
		add_action( 'init', array( $inst, 'declare_admin_sections' ) );
		add_action( 'admin_menu', array( $inst, 'add_options_page' ) );
		add_action( 'admin_init', array( $inst, 'add_settings' ) );
		add_action( 'admin_post_pp_dismiss_pro_offer', array( Pro_Offer::class, 'dismiss' ) );
		add_action( 'admin_enqueue_scripts', array( $inst, 'page_scripts' ) );
		add_action( 'podcast_player_options_page_content', array( $inst, 'display_content' ) );
		add_action( 'wp_ajax_pp_feed_editor', array( $inst, 'feed_editor_new' ) );
		// add_action( 'wp_ajax_nopriv_pp_feed_editor', array( $inst, 'feed_editor_new' ) );
		add_action( 'wp_ajax_pp_migrate_podcast', array( $inst, 'migrate_podcast_source' ) );
		add_action( 'wp_ajax_pp_delete_source', array( $inst, 'delete_podcast_source' ) );
		add_action( 'wp_ajax_pp_resolve_migration', array( $inst, 'resolve_migration' ) );
		add_action( 'wp_ajax_pp_dismiss_migration', array( $inst, 'dismiss_migration' ) );
		add_action( 'wp_ajax_pp_mark_migration_seen', array( $inst, 'mark_migration_seen' ) );
	}

	/**
	 * Approve a pending duplicate merge or host-migration candidate.
	 *
	 * @since 8.3.0
	 */
	public function resolve_migration() {
		check_ajax_referer( 'podcast-player-admin-options-ajax-nonce', 'security' );
		Utility_Fn::require_capabilities( 'manage_options', 'pp_resolve_migration' );

		$store   = StoreManager::get_instance();
		$type    = isset( $_POST['mtype'] ) ? sanitize_text_field( wp_unslash( $_POST['mtype'] ) ) : '';
		$pending = $store->get_pending_merges();

		if ( 'pair' === $type ) {
			$pair_key = isset( $_POST['pair_key'] ) ? sanitize_text_field( wp_unslash( $_POST['pair_key'] ) ) : '';
			if ( '' === $pair_key || ! isset( $pending[ $pair_key ] ) ) {
				$this->json_die( array( 'error' => esc_html__( 'That review item no longer exists.', 'podcast-player' ) ) );
			}
			$pair   = $pending[ $pair_key ];
			$result = $store->merge_podcast_records( $pair['survivor_key'], $pair['absorbed_key'] );
			$status = is_array( $result ) && isset( $result['status'] ) ? $result['status'] : '';
			if ( in_array( $status, array( 'merged', 'noop' ), true ) ) {
				$absorbed = isset( $result['absorbed_key'] ) ? $result['absorbed_key'] : $pair['absorbed_key'];
				$survivor = isset( $result['survivor_key'] ) ? $result['survivor_key'] : $pair['survivor_key'];
				// Drops this resolved pair and re-points any other cluster pairs.
				$store->remap_pending_after_merge( $absorbed, $survivor );
				$this->json_die( array( 'message' => esc_html__( 'Podcasts merged.', 'podcast-player' ) ) );
			}
			if ( 'deferred' === $status ) {
				$this->json_die( array( 'message' => esc_html__( 'Merge queued; it will finish after the surviving podcast updates.', 'podcast-player' ) ) );
			}
			$this->json_die( array( 'error' => esc_html__( 'Unable to merge these podcasts right now. Please try again.', 'podcast-player' ) ) );
		}

		if ( 'group' === $type ) {
			$member_keys = $this->get_posted_merge_group_members();
			if ( count( $member_keys ) < 2 ) {
				$this->json_die( array( 'error' => esc_html__( 'That review group is no longer available.', 'podcast-player' ) ) );
			}

			$merged = 0;
			$guard  = 0;
			while ( $guard < 20 ) {
				$guard++;
				$pairs = $store->get_pending_merge_pairs_for_members( $member_keys );
				if ( empty( $pairs ) ) {
					break;
				}

				$pair   = reset( $pairs );
				$result = $store->merge_podcast_records( $pair['survivor_key'], $pair['absorbed_key'] );
				$status = is_array( $result ) && isset( $result['status'] ) ? $result['status'] : '';
				if ( ! in_array( $status, array( 'merged', 'noop' ), true ) ) {
					if ( 'deferred' === $status ) {
						$this->json_die( array( 'notice' => esc_html__( 'These podcasts must update their episode identity before they can be merged. The suggestion has been kept.', 'podcast-player' ) ) );
					}
					$this->json_die( array( 'error' => esc_html__( 'Podcast Player could not merge these records.', 'podcast-player' ) ) );
				}

				$absorbed = isset( $result['absorbed_key'] ) ? $result['absorbed_key'] : $pair['absorbed_key'];
				$survivor = isset( $result['survivor_key'] ) ? $result['survivor_key'] : $pair['survivor_key'];
				$store->remap_pending_after_merge( $absorbed, $survivor );
				$merged++;
			}

			if ( ! $merged ) {
				$this->json_die( array( 'error' => esc_html__( 'That review group no longer exists.', 'podcast-player' ) ) );
			}

			$this->json_die( array( 'message' => esc_html__( 'Podcast records merged.', 'podcast-player' ) ) );
		}

		if ( 'candidate' === $type ) {
			$feed_key = isset( $_POST['feed_key'] ) ? sanitize_text_field( wp_unslash( $_POST['feed_key'] ) ) : '';
			if ( '' === $feed_key ) {
				$this->json_die( array( 'error' => esc_html__( 'Invalid podcast for this migration.', 'podcast-player' ) ) );
			}
			// Only ever set from an explicit request parameter: the client echoes
			// back the exact target URL the conflict was shown for, and the store
			// compares it to the live candidate before acting on it.
			$confirmed = isset( $_POST['confirm_target'] ) ? esc_url_raw( wp_unslash( $_POST['confirm_target'] ) ) : '';

			$applied = $store->apply_migration_candidate( $feed_key, $confirmed );
			if ( false === $applied ) {
				$this->json_die( array( 'error' => esc_html__( 'That migration is no longer available.', 'podcast-player' ) ) );
			}

			$status = is_array( $applied ) && isset( $applied['status'] ) ? $applied['status'] : '';

			if ( 'conflict' === $status ) {
				$this->json_die( $this->get_migration_conflict_response( $applied ) );
			}
			if ( 'deferred' === $status ) {
				$this->json_die( array( 'notice' => esc_html__( 'The matching podcast must update its episode identity before this source can be moved. The suggestion has been kept.', 'podcast-player' ) ) );
			}
			if ( 'noop' === $status ) {
				$this->json_die( array( 'notice' => esc_html__( 'There was nothing to merge, so the source was left unchanged. The suggestion has been kept.', 'podcast-player' ) ) );
			}

			$this->json_die( array( 'message' => esc_html__( 'Source updated to the redirected feed.', 'podcast-player' ) ) );
		}

		$this->json_die( array( 'error' => esc_html__( 'Unexpected review action.', 'podcast-player' ) ) );
	}

	/**
	 * Read duplicate group members posted by the admin UI.
	 *
	 * @since 8.3.0
	 * @return array
	 */
	private function get_posted_merge_group_members() {
		$raw = isset( $_POST['member_keys'] ) ? wp_unslash( $_POST['member_keys'] ) : array();
		if ( is_string( $raw ) ) {
			$raw = explode( ',', $raw );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$members = array();
		foreach ( $raw as $member ) {
			$member = sanitize_text_field( $member );
			if ( '' !== $member ) {
				$members[] = $member;
			}
		}

		return array_values( array_unique( $members ) );
	}

	/**
	 * Build the AJAX payload for a source change that did not verify.
	 *
	 * Nothing has been written at this point. The response carries the two feed
	 * identities side by side so the admin decides against the actual evidence
	 * rather than a bare "are you sure", and echoes the target back so the
	 * confirmation cannot be replayed against a different candidate.
	 *
	 * @since 8.3.0
	 *
	 * @param array $result Conflict result from the store.
	 * @return array
	 */
	private function get_migration_conflict_response( $result ) {
		$detail = isset( $result['detail'] ) && is_array( $result['detail'] ) ? $result['detail'] : array();
		$status = isset( $detail['status'] ) ? $detail['status'] : 'unverifiable';
		$old    = isset( $detail['old'] ) && is_array( $detail['old'] ) ? $detail['old'] : array();
		$new    = isset( $detail['new'] ) && is_array( $detail['new'] ) ? $detail['new'] : array();

		$message = 'mismatch' === $status
			? esc_html__( 'The new feed does not look like the same podcast. Check the title and author before continuing.', 'podcast-player' )
			: esc_html__( 'Podcast Player could not confirm the new feed is the same podcast. Check it before continuing.', 'podcast-player' );

		return array(
			'conflict' => array(
				'message' => $message,
				'target'  => esc_url_raw( isset( $result['target'] ) ? $result['target'] : '' ),
				'old'     => array(
					'title'  => esc_html( isset( $old['title'] ) ? $old['title'] : '' ),
					'author' => esc_html( isset( $old['author'] ) ? $old['author'] : '' ),
				),
				'new'     => array(
					'title'  => esc_html( isset( $new['title'] ) ? $new['title'] : '' ),
					'author' => esc_html( isset( $new['author'] ) ? $new['author'] : '' ),
				),
				'labels'  => array(
					'current' => esc_html__( 'Currently', 'podcast-player' ),
					'new'     => esc_html__( 'New feed', 'podcast-player' ),
					'confirm' => esc_html__( 'Migrate anyway', 'podcast-player' ),
					'unknown' => esc_html__( '(not available)', 'podcast-player' ),
				),
			),
		);
	}

	/**
	 * Mark a migration candidate as seen without resolving it.
	 *
	 * @since 8.3.0
	 */
	public function mark_migration_seen() {
		check_ajax_referer( 'podcast-player-admin-options-ajax-nonce', 'security' );
		Utility_Fn::require_capabilities( 'manage_options', 'pp_mark_migration_seen' );

		$store = StoreManager::get_instance();
		$feed_key = isset( $_POST['feed_key'] ) ? sanitize_text_field( wp_unslash( $_POST['feed_key'] ) ) : '';
		if ( '' === $feed_key ) {
			$this->json_die( array( 'error' => esc_html__( 'Invalid podcast for this migration.', 'podcast-player' ) ) );
		}

		if ( ! $store->mark_migration_candidate_seen( $feed_key ) ) {
			$this->json_die( array( 'error' => esc_html__( 'That migration is no longer available.', 'podcast-player' ) ) );
		}

		$this->json_die( array( 'message' => esc_html__( 'Migration notice marked as seen.', 'podcast-player' ) ) );
	}

	/**
	 * Dismiss a pending duplicate pair so the scan will not re-queue it.
	 *
	 * @since 8.3.0
	 */
	public function dismiss_migration() {
		check_ajax_referer( 'podcast-player-admin-options-ajax-nonce', 'security' );
		Utility_Fn::require_capabilities( 'manage_options', 'pp_dismiss_migration' );

		$store = StoreManager::get_instance();

		$type = isset( $_POST['mtype'] ) ? sanitize_text_field( wp_unslash( $_POST['mtype'] ) ) : 'pair';
		if ( 'group' === $type ) {
			$member_keys = $this->get_posted_merge_group_members();
			$pairs       = $store->get_pending_merge_pairs_for_members( $member_keys );
			if ( empty( $pairs ) ) {
				$this->json_die( array( 'error' => esc_html__( 'That review group no longer exists.', 'podcast-player' ) ) );
			}
			foreach ( array_keys( $pairs ) as $pending_pair_key ) {
				$store->dismiss_pending_merge( $pending_pair_key );
			}
			$this->json_die( array( 'message' => esc_html__( 'Podcast records kept separate.', 'podcast-player' ) ) );
		}

		$pair_key = isset( $_POST['pair_key'] ) ? sanitize_text_field( wp_unslash( $_POST['pair_key'] ) ) : '';
		$pending  = $store->get_pending_merges();
		if ( '' === $pair_key || ! isset( $pending[ $pair_key ] ) ) {
			$this->json_die( array( 'error' => esc_html__( 'That review item no longer exists.', 'podcast-player' ) ) );
		}
		$store->dismiss_pending_merge( $pair_key );
		$this->json_die( array( 'message' => esc_html__( 'Podcast records kept separate.', 'podcast-player' ) ) );
	}

	/**
	 * Emit a JSON response and end the request.
	 *
	 * @since 8.3.0
	 *
	 * @param array $output Response payload.
	 */
	private function json_die( $output ) {
		echo wp_json_encode( $output );
		wp_die();
	}

	/**
	 * Declare Admin Sections for podcast player admin page.
	 *
	 * @since 7.7.3
	 */
	public function declare_admin_sections() {
		// Declare different modules of Podcast player options page (Sections).
		$this->modules = array(
			'options'  => array(
				'label' => esc_html__( 'Home', 'podcast-player' ),
			),
			'shortcode' => array(
				'label' => esc_html__( 'Shortcode', 'podcast-player' ),
			),
			'settings' => array(
				'label' => esc_html__( 'Settings', 'podcast-player' ),
			),
		);

		// Toolkit is available only when Pro plugin is active.
		if ( defined( 'PP_PRO_VERSION' ) ) {
			$this->modules['toolkit'] = array(
				'label' => esc_html__( 'Toolkit', 'podcast-player' ),
			);
		}

		$this->modules['help'] = array(
			'label' => esc_html__( 'Help & Support', 'podcast-player' ),
		);
		// $this->modules['products'] = array(
		// 	'label' => esc_html__( 'Other Products', 'podcast-player' ),
		// );

		// Declare different sections of Podcast player options page (Sections).
		$this->sections = array(
			'general'  => esc_html__( 'General', 'podcast-player' ),
			'design'   => esc_html__( 'Design', 'podcast-player' ),
			'optimize' => esc_html__( 'Optimization & Security', 'podcast-player' ),
			'advanced' => esc_html__( 'Advanced', 'podcast-player' ),
		);

		// Declare different sections for podcast player documentation page.
		$this->doc_blocks = array(
			'getting_started' => esc_html__( 'Getting Started', 'podcast-player' ),
			'faq'             => esc_html__( 'Frequently Asked Questions', 'podcast-player' ),
		);
	}

	/**
	 * Array of setting fields.
	 *
	 * Array of settings fields to be used on pp options page.
	 *
	 * @since    1.0.0
	 */
	public function get_setting_fields() {
		$fields = array(
				'refresh_interval' => array(
					'name'        => esc_html__( 'Podcast update interval', 'podcast-player' ),
					'id'          => 'refresh_interval',
					'description' => esc_html__( 'Choose how often Podcast Player checks your feeds for new episodes. The default is 720 minutes, or every 12 hours.', 'podcast-player' ),
					'link'        => '',
					'type'        => 'number',
					'default'     => 720,
					'section'     => 'general',
					'input_attrs' => array(
						'step' => 5,
						'min'  => 0,
						'size' => 3,
					),
				),
				'update_method'    => array(
					'name'        => esc_html__( 'Use WP-Cron for podcast updates', 'podcast-player' ),
					'id'          => 'update_method',
					'description' => esc_html__( 'Turn this on only if your feeds are not updating because of hosting, caching, or background request limits.', 'podcast-player' ),
					'link'        => '',
					'type'        => 'checkbox',
					'default'     => '',
					'section'     => 'advanced',
				),
				'img_save'         => array(
					'name'        => esc_html__( 'Image Optimization', 'podcast-player' ),
					'id'          => 'img_save',
					'description' => esc_html__( 'Save podcast images in your Media Library so the player can serve smaller, faster images.', 'podcast-player' ),
					'link'        => '',
					'type'        => 'checkbox',
					'default'     => 'yes',
					'section'     => 'optimize',
				),
				'rel_external'     => array(
					'name'        => esc_html__( 'Add rel attributes to external links', 'podcast-player' ),
					'id'          => 'rel_external',
					'description' => esc_html__( 'Add noopener, noreferrer, and nofollow to external links in episode content for better security and cleaner SEO signals.', 'podcast-player' ),
					'link'        => '',
					'type'        => 'checkbox',
					'default'     => '',
					'section'     => 'optimize',
				),
				'check_cache_headers' => array(
					'name'        => esc_html__( 'Check feed cache headers before updating', 'podcast-player' ),
					'id'          => 'check_cache_headers',
					'description' => esc_html__( 'Keep this on to skip unnecessary feed downloads. If a feed is not updating correctly, turn it off and try again.', 'podcast-player' ),
					'link'        => '',
					'type'        => 'checkbox',
					'default'     => 'yes',
					'section'     => 'advanced',
				),
				'prefer_hls_streams' => array(
					'name'        => esc_html__( 'Prefer HLS streams when available', 'podcast-player' ),
					'id'          => 'prefer_hls_streams',
					'description' => esc_html__( 'Use Podcasting 2.0 HLS alternate enclosures when feeds provide them. If disabled, the primary enclosure remains the default.', 'podcast-player' ),
					'link'        => '',
					'type'        => 'checkbox',
					'default'     => '',
					'section'     => 'advanced',
				),
				'is_ajax'          => array(
					'name'        => esc_html__( 'Enable AJAX page compatibility', 'podcast-player' ),
					'id'          => 'is_ajax',
					'description' => esc_html__( 'Use this if your theme loads pages without a full browser refresh and players need to initialize after AJAX navigation.', 'podcast-player' ),
					'link'        => '',
					'type'        => 'checkbox',
					'default'     => '',
					'section'     => 'advanced',
				),
				'fonts'         => array(
					'name'        => esc_html__( 'Player icon set', 'podcast-player' ),
					'id'          => 'fonts',
					'description' => esc_html__( 'Choose the icon style used inside podcast players across your site.', 'podcast-player' ),
					'link'        => '',
					'type'        => 'select',
					'choices'     => array(
						''           => esc_html__( 'Legacy Icons', 'podcast-player' ),
						'tabler'     => esc_html__( 'Tabler Icons', 'podcast-player' ),
						'huge'       => esc_html__( 'Huge Icons', 'podcast-player' ),
						'framework7' => esc_html__( 'Framework 7 Icons', 'podcast-player' ),
					),
					'default'     => 'tabler',
					'section'     => 'design',
				),
				'hide_data'        => array(
					'name'        => esc_html__( 'Protect Podcast Data from Exposure', 'podcast-player' ),
					'id'          => 'hide_data',
					'description' => esc_html__( 'Hide sensitive podcast details, such as feed and audio URLs, from the front-end page source when possible.', 'podcast-player' ),
					'link'        => '',
					'type'        => 'checkbox',
					'default'     => '',
					'section'     => 'advanced',
				),
				'timezone'         => array(
					'name'        => esc_html__( 'Episode date timezone', 'podcast-player' ),
					'id'          => 'timezone',
					'description' => esc_html__( 'Choose which timezone Podcast Player should use when showing episode dates.', 'podcast-player' ),
					'link'        => '',
					'type'        => 'select',
					'choices'     => array(
						''      => esc_html__( 'GMT/ UTC Timezone', 'podcast-player' ),
						'local' => esc_html__( 'Website Local Timezone', 'podcast-player' ),
						'feed'  => esc_html__( 'Feed Data Timezone', 'podcast-player' ),
					),
					'default'     => '',
					'section'     => 'advanced',
				),
			);

		if ( ! self::is_pro_enabled() ) {
			$fields = array_slice( $fields, 0, 1, true ) + array(
				'show_pro_tips' => array(
					'name'        => esc_html__( 'Show helpful Pro Tips', 'podcast-player' ),
					'id'          => 'show_pro_tips',
					'description' => esc_html__( 'Keep this on to see occasional podcast-specific tips for layouts, search, filters, and episode importing. Turn it off if you prefer a quieter dashboard.', 'podcast-player' ),
					'link'        => '',
					'type'        => 'checkbox',
					'default'     => 'yes',
					'section'     => 'general',
				),
			) + array_slice( $fields, 1, null, true );
		}

		return apply_filters( 'podcast_player_setting_fields', $fields );
	}

	/**
	 * Add plugin specific options page.
	 *
	 * @since    1.5
	 */
	public function add_options_page() {
		$suffix = add_menu_page(
			esc_html__( 'Podcast Player', 'podcast-player' ),
			esc_html__( 'Podcast Player', 'podcast-player' ),
			'manage_options',
			'pp-options',
			array( $this, 'options_page' ),
			'data:image/svg+xml;base64,PCEtLSBHZW5lcmF0ZWQgYnkgSWNvTW9vbi5pbyAtLT4KPHN2ZyB2ZXJzaW9uPSIxLjEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgd2lkdGg9IjMyIiBoZWlnaHQ9IjMyIiB2aWV3Qm94PSIwIDAgMzIgMzIiPgo8cGF0aCBmaWxsPSIjZmZmIiBkPSJNMzIgMTZjMC04LjgzNy03LjE2My0xNi0xNi0xNnMtMTYgNy4xNjMtMTYgMTZjMCA2Ljg3NyA0LjMzOSAxMi43MzkgMTAuNDI4IDE1LjAwMmwtMC40MjggMC45OThoMTJsLTAuNDI4LTAuOTk4YzYuMDg5LTIuMjYzIDEwLjQyOC04LjEyNSAxMC40MjgtMTUuMDAyek0xNS4yMTIgMTkuODM4Yy0wLjcxMy0wLjMwNi0xLjIxMi0xLjAxNC0xLjIxMi0xLjgzOCAwLTEuMTA1IDAuODk1LTIgMi0yczIgMC44OTUgMiAyYzAgMC44MjUtMC40OTkgMS41MzMtMS4yMTIgMS44MzlsLTAuNzg4LTEuODM5LTAuNzg4IDEuODM4ek0xNi44MjEgMTkuOTE1YzEuODE1LTAuMzc5IDMuMTc5LTEuOTg4IDMuMTc5LTMuOTE1IDAtMi4yMDktMS43OTEtNC00LTRzLTQgMS43OTEtNCA0YzAgMS45MjggMS4zNjQgMy41MzUgMy4xOCAzLjkxM2wtMi4zMzIgNS40NDFjLTIuODUxLTEuMjIzLTQuODQ4LTQuMDU2LTQuODQ4LTcuMzU1IDAtNC40MTggMy41ODItOC4zNzUgOC04LjM3NXM4IDMuOTU3IDggOC4zNzVjMCAzLjI5OS0xLjk5NyA2LjEzMS00Ljg0OCA3LjM1NWwtMi4zMzEtNS40Mzl6TTIxLjUxNCAzMC44NjZsLTIuMzEtNS4zOWMzLjk1MS0xLjMzNiA2Ljc5Ni01LjA3MyA2Ljc5Ni05LjQ3NiAwLTUuNTIzLTQuNDc3LTEwLTEwLTEwcy0xMCA0LjQ3Ny0xMCAxMGMwIDQuNDAyIDIuODQ1IDguMTQgNi43OTYgOS40NzZsLTIuMzEgNS4zOWMtNC45ODctMi4xNC04LjQ4MS03LjA5NS04LjQ4MS0xMi44NjYgMC03LjcyOSA2LjI2Ni0xNC4zNyAxMy45OTUtMTQuMzdzMTMuOTk1IDYuNjQxIDEzLjk5NSAxNC4zN2MwIDUuNzcxLTMuNDk0IDEwLjcyNi04LjQ4MSAxMi44NjZ6Ij48L3BhdGg+Cjwvc3ZnPgo='
		);

		$submenu_pages = array(
			'pp-shortcode' => __( 'Shortcode', 'podcast-player' ),
			'pp-settings' => __( 'Settings', 'podcast-player' ),
		);

		if ( defined( 'PP_PRO_VERSION' ) ) {
			$submenu_pages['pp-toolkit'] = __( 'Toolkit', 'podcast-player' );
		}
		$submenu_pages['pp-help']     = __( 'Help & Support', 'podcast-player' );
		$submenu_pages['pp-products'] = __( 'Other Products', 'podcast-player' );

		foreach ( $submenu_pages as $key => $label ) {
			add_submenu_page(
				'pp-options',
				$label,
				$label,
				'manage_options',
				$key,
				array( $this, 'options_page' )
			);
		}
	}

	/**
	 * Display podcast player options page.
	 *
	 * @since    1.0.0
	 */
	public function add_settings() {
		$fields = $this->get_setting_fields();

		foreach ( $this->sections as $key => $label ) {
			$section = "pp_{$key}_section";
			$setting = "pp_{$key}_settings";

			register_setting(
				'pp_options_group',
				'pp-common-options',
				array( $this, 'sanitize_common_options' )
			);

			add_settings_section( $section, '', '__return_empty_string', $setting );

			foreach ( $fields as $field ) {
				if ( $field['section'] === $key ) {
					$link  = $field['link'] ? sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer">(?)</a>', esc_url( $field['link'] ) ) : '';
					$title = sprintf( '<span class="pp-opt-title">%1$s</span><span class="pp-opt-desc">%2$s %3$s</span>', $field['name'], $field['description'], $link );
					add_settings_field(
						$field['id'],
						$title,
						array( $this, 'display_setting' ),
						$setting,
						$section,
						array( 'params' => $field )
					);
				}
			}
		}
	}

	/**
	 * Function to display the settings on the page.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Setting field arguments.
	 */
	public function display_setting( $args ) {

		$params = $args['params'];
		$id     = $params['id'];
		$opt    = get_option( 'pp-common-options' );
		$type   = $params['type'];
		$field  = '';

		$iatt      = isset( $params['input_attrs'] ) ? $params['input_attrs'] : array();
		$inputattr = '';
		foreach ( $iatt as $att => $value ) {
			$inputattr .= esc_html( $att ) . '="' . esc_attr( $value ) . '" ';
		}

		if ( false === $opt ) {
			$val = $params['default'];
		} elseif ( 'number' === $type ) {
			$val = is_array( $opt ) && isset( $opt[ $id ] ) ? absint( $opt[ $id ] ) : $params['default'];
		} else {
				$val = is_array( $opt ) && isset( $opt[ $id ] ) ? sanitize_text_field( $opt[ $id ] ) : $params['default'];
		}

		// Prepare markup for custom widget options.
		switch ( $type ) {
			case 'checkbox':
				$field = sprintf( '<input name="pp-common-options[%1$s]" id="%1$s" type="checkbox" value="yes" %2$s /><div class="slider"></div>', $id, checked( $val, 'yes', false ) );
				$field = sprintf( '<label class="switch">%s</label>', $field );
				break;
			case 'number':
				$field = sprintf( '<input name="pp-common-options[%1$s]" id="%1$s" type="number" value="%2$s" class="numbox" %3$s />', $id, absint( $val ), $inputattr );
				break;
			case 'select':
				$options = $params['choices'];
				$field   = '';
				foreach ( $options as $value => $label ) {
					$field .= sprintf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $value, $val, false ), esc_html( $label ) );
				}
				$field = sprintf( '<select id="%1$s" name="pp-common-options[%1$s]">%2$s</select>', $id, $field );
				break;
			default:
				break;
		}

		echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Function to validate plugin options.
	 *
	 * @since    1.0.0
	 *
	 * @param array|false $input Podcast Option Value.
	 */
	public function sanitize_common_options( $input ) {
		$all_options = $this->get_setting_fields();
		$new_input   = array();
		if ( is_array( $input ) ) {
			foreach ( $all_options as $option => $args ) {
				$type = $args['type'];
				switch ( $type ) {
					case 'checkbox':
						$new_input[ $option ] = isset( $input[ $option ] ) && 'yes' === $input[ $option ] ? 'yes' : 'no';
						break;
					case 'number':
						$new_input[ $option ] = isset( $input[ $option ] ) ? absint( $input[ $option ] ) : $args['default'];
						break;
					case 'select':
						$new_input[ $option ] = isset( $input[ $option ] ) ? sanitize_text_field( $input[ $option ] ) : $args['default'];
						break;
				}
			}
		}
		return $new_input;
	}

	/**
	 * Function to add options page content.
	 *
	 * @since    1.0.0
	 */
	public function display_content() {
		global $pagenow;
		if ( 'admin.php' === $pagenow && isset( $_GET['page'] ) ) {
			switch ( $_GET['page'] ) {
				case 'pp-settings':
					$current_page = 'settings';
					break;
				case 'pp-toolkit':
					$current_page = defined( 'PP_PRO_VERSION' ) ? 'toolkit' : 'home';
					break;
				case 'pp-help':
					$current_page = 'help';
					break;
				case 'pp-products':
					$current_page = 'products';
					break;
				case 'pp-shortcode':
					$current_page = 'shortcode';
					break;
				default:
					$current_page = 'home';
					break;
			}
			if ( 'home' === $current_page ) {
				StoreManager::get_instance()->ensure_duplicate_scan_complete();
			}
			include_once PODCAST_PLAYER_DIR . 'backend/admin/templates/main.php';
		}
	}

	/**
	 * Render  Plus settings page.
	 *
	 * @since    1.0.0
	 */
	public function options_page() {
		do_action( 'podcast_player_options_page_content', 'pp-options' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function page_scripts() {
		$current_screen = get_current_screen();
		$load_on        = array(
			'toplevel_page_pp-options',
			'podcast-player_page_pp-shortcode',
			'podcast-player_page_pp-settings',
			'podcast-player_page_pp-toolkit',
			'podcast-player_page_pp-products',
			'podcast-player_page_pp-help',
		);
		if ( $current_screen && in_array( $current_screen->id, $load_on, true ) ) {

			$gfonts = array();
			if ( 'podcast-player_page_pp-shortcode' === $current_screen->id && class_exists( '\PP_Pro\Helper\Functions\Gfonts' ) ) {
				$gfonts = \PP_Pro\Helper\Functions\Gfonts::get_list();
			}

			/**
			 * Enqueue admin scripts.
			 */
			wp_enqueue_script(
				'ppadminoptions',
				PODCAST_PLAYER_URL . 'backend/js/admin-options.build.js',
				array( 'jquery-ui-tabs' ),
				PODCAST_PLAYER_VERSION,
				true
			);

			/**
			 * Enqueue admin stylesheet.
			 */
			wp_enqueue_style(
				'ppadminoptions',
				PODCAST_PLAYER_URL . 'backend/css/admin-options.css',
				array(),
				PODCAST_PLAYER_VERSION,
				'all'
			);

			// Theme localize scripts data.
			wp_localize_script(
				'ppadminoptions',
				'ppjsAdminOpt',
				apply_filters(
					'podcast_player_admin_options',
					array(
						'ajaxurl'  => admin_url( 'admin-ajax.php' ),
						'security' => wp_create_nonce( 'podcast-player-admin-options-ajax-nonce' ),
						'gfonts'   => $gfonts,
						'messages' => array(
							'running'        => esc_html__( 'Working on it...', 'podcast-player' ),
							'nourl'          => esc_html__( 'Choose a podcast first.', 'podcast-player' ),
							'nosource'       => esc_html__( 'Enter a valid source URL first.', 'podcast-player' ),
							'tlabel'         => esc_html__( 'Edit Title', 'podcast-player' ),
							'alabel'         => esc_html__( 'Edit Author', 'podcast-player' ),
							'slabel'         => esc_html__( 'Add/ Edit Season', 'podcast-player' ),
							'catlabel'       => esc_html__( 'Add/ Edit categories', 'podcast-player' ),
							'feLabel'        => esc_html__( 'Add/ Edit Custom Featured Image', 'podcast-player' ),
							'catph'          => esc_html__( 'Comma separated episode categories', 'podcast-player' ),
							'update'         => esc_html__( 'Save Changes', 'podcast-player' ),
							'add'            => esc_html__( '+ Add', 'podcast-player' ),
							'all'            => esc_html__( 'Select All', 'podcast-player' ),
							'iall'           => esc_html__( 'Unselect All', 'podcast-player' ),
							'iselection'     => esc_html__( 'Inverse Selection', 'podcast-player' ),
							'abort'          => esc_html__( 'Import Aborted', 'podcast-player' ),
							'aborting'       => esc_html__( 'Aborting Import', 'podcast-player' ),
							'nochange'       => esc_html__( 'No changes to save.', 'podcast-player' ),
							'loadmore'       => esc_html__( 'Load More Episodes', 'podcast-player' ),
							'removeFeatured' => esc_html__( 'Remove Custom Image', 'podcast-player' ),
							'uploadFeatured' => esc_html__( 'Upload Custom Image', 'podcast-player' ),
							'setimg'         => esc_html__( 'Set Image', 'podcast-player' ),
							'btn_text'       => esc_html__( 'Select', 'podcast-player' ),
							'img_text'       => esc_html__( 'Set Image', 'podcast-player' ),
							'fetchId'        => esc_html__( 'Fetching Apple Podcast ID', 'podcast-player' ),
							'fetchReviews'   => esc_html__( 'Looking for apple podcast reviews in', 'podcast-player' ),
							'deleteReviews'  => esc_html__( 'Deleting Podcast Reviews', 'podcast-player' ),
							'deleteSuccess'  => esc_html__( 'Podcast reviews deleted.', 'podcast-player' ),
						),
					)
				)
			);
		}

		if ( $current_screen && 'podcast-player_page_pp-shortcode' === $current_screen->id ) {
			$front_loader = Front_Loader::get_instance();
			$front_loader->enqueue_styles();
			$front_loader->enqueue_scripts();

			if ( class_exists( '\PP_Pro\Inc\General\General' ) ) {
				$general = \PP_Pro\Inc\General\General::get_instance();
				if ( method_exists( $general, 'enqueue_shortcodegen_styles' ) ) {
					$general->enqueue_shortcodegen_styles();
				}
			}
		}
	}

	/**
	 * Get properly framed A tag link to be used on documentation pages.
	 *
	 * @since 3.3.0
	 *
	 * @param str  $link URL to be used as href value.
	 * @param str  $text Link Text.
	 * @param str  $classes Link HTML classes.
	 * @param bool $is_echo Echo or return.
	 */
	public function mlink( $link, $text, $classes = '', $is_echo = true ) {
		$markup = '';
		if ( $link && $text ) {
			$text    = esc_html( $text ) . '<span class="dashicons dashicons-external"></span>';
			$classes = $classes ? 'class="' . esc_attr( $classes ) . '"' : '';
			$markup  = sprintf(
				'<a %s href="%s" rel="noopener noreferrer nofollow" target="_blank">%s</a>',
				$classes,
				esc_url( $link ),
				$text
			);
		}

		if ( $is_echo ) {
			echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			return $markup;
		}
	}

	/**
	 * New method to handle feed editor ajax calls.
	 *
	 * @since 1.0.0
	 */
	public function feed_editor_new() {
		check_ajax_referer( 'podcast-player-admin-options-ajax-nonce', 'security' );
		Utility_Fn::require_capabilities( 'manage_options', 'pp_feed_editor' );

		$type = isset( $_POST['atype'] ) ? sanitize_text_field( wp_unslash( $_POST['atype'] ) ) : 'refresh';
		$fprn = isset( $_POST['feedUrl'] ) ? sanitize_text_field( wp_unslash( $_POST['feedUrl'] ) ) : '';

		if ( ! $fprn ) {
			$output = array(
				'error' => esc_html__( 'Invalid feed key provided.', 'podcast-player' ),
			);
			echo wp_json_encode( $output );
			wp_die();
		}

		$store_manager = StoreManager::get_instance();
		$message       = '';
		$error         = '';

		// Prepare markup for custom widget options.
		switch ( $type ) {
			case 'refresh':
				$store_manager->delete_data( $fprn, 'last_checked' );
				$feed = Get_Fn::get_feed_data( $fprn );
				if ( is_wp_error( $feed ) ) {
					$error = '<p><strong>' . esc_html__( 'RSS Error:', 'podcast-player' ) . '</strong> ' . esc_html( $feed->get_error_message() ) . '</p>';
				} else {
					$message = esc_html__( 'Podcast data refreshed.', 'podcast-player' );
				}
				break;
			case 'reset':
				$store_manager->delete_data( $fprn, array( 'feed_data', 'last_checked' ) );
				$all_meta  = $store_manager->get_data( $fprn, '' );
				if ( empty( $all_meta ) ) {
					$store_manager->delete_data( $fprn );
				} else {
					$store_manager->hide_data( $fprn );
				}

				$message = esc_html__( 'Podcast data deleted.', 'podcast-player' );
				break;
			default:
				$error = esc_html__( 'Unexpected user input.', 'podcast-player' );
				break;
		}

		if ( '' !== $error ) {
			echo wp_json_encode( array( 'error' => $error ) );
			wp_die();
		}

		// Ajax output to be returened.
		$output = array( 'message' => $message );
		echo wp_json_encode( $output );
		wp_die();
	}

	/**
	 * New method to handle feed editor ajax calls.
	 *
	 * @since 1.0.0
	 */
	public function migrate_podcast_source() {
		check_ajax_referer( 'podcast-player-admin-options-ajax-nonce', 'security' );
		Utility_Fn::require_capabilities( 'manage_options', 'pp_migrate_podcast' );

		$podcast_id = isset( $_POST['podcast_id'] ) ? sanitize_text_field( wp_unslash( $_POST['podcast_id'] ) ) : false;
		$source_url = isset( $_POST['source_url'] ) ? wp_unslash( $_POST['source_url'] ) : false; // Sanitized in line below after validation.

		if ( $source_url && Validation_Fn::is_valid_url( $source_url ) ) {
			$source_url = esc_url( $source_url );
		} else {
			$output = array(
				'error' => esc_html__( 'Invalid source URL provided.', 'podcast-player' ),
			);
			echo wp_json_encode( $output );
			wp_die();
		}

		if ( ! $podcast_id ) {
			$output = array(
				'error' => esc_html__( 'Valid Podcast Key Not Available.', 'podcast-player' ),
			);
			echo wp_json_encode( $output );
			wp_die();
		}

		$store_manager = StoreManager::get_instance();

		$obj              = new Fetch_Feed( $source_url );
		$new_podcast_data = $obj->get_feed_data();
		if ( is_wp_error( $new_podcast_data ) ) {
			$output = array(
				'error' => esc_html__( 'Source URL does not contain valid feed data.', 'podcast-player' ),
			);
			echo wp_json_encode( $output );
			wp_die();
		}

		// Same two-step confirmation as the review notice. A record whose source
		// was rewritten by an older version often has no cached feed left, so
		// there is nothing to compare — that is reported honestly and still
		// needs a decision, rather than being waved through as it used to be.
		$confirmed = isset( $_POST['confirm_target'] ) ? esc_url_raw( wp_unslash( $_POST['confirm_target'] ) ) : '';
		if ( $confirmed !== $source_url ) {
			$check = Get_Feed::verify_source_target( $podcast_id, $source_url );
			if ( 'match' !== $check['status'] ) {
				$this->json_die(
					$this->get_migration_conflict_response(
						array(
							'target' => $source_url,
							'detail' => $check,
						)
					)
				);
			}
		}

		$is_success = $store_manager->add_podcast_source_url( $podcast_id, $source_url );
		if ( is_array( $is_success ) ) {
			$status = isset( $is_success['status'] ) ? $is_success['status'] : 'error';
			if ( 'merged' === $status ) {
				$output = array(
					'message' => esc_html__( 'Podcast records merged and the source URL was saved.', 'podcast-player' ),
				);
			} elseif ( 'deferred' === $status ) {
				$output = array(
					'error' => esc_html__( 'The matching podcast record must update its episode identity before the source can be merged.', 'podcast-player' ),
				);
			} else {
				$output = array(
					'error' => esc_html__( 'Unable to merge the matching podcast records.', 'podcast-player' ),
				);
			}
			echo wp_json_encode( $output );
			wp_die();
		}

		if ( ! $is_success ) {
			$output = array(
				'error' => esc_html__( 'Unable to update source URL.', 'podcast-player' ),
			);
			echo wp_json_encode( $output );
			wp_die();
		}

		// Ajax output to be returened.
		$output = array( 'message' => esc_html__( 'Source URL saved. Existing players can keep using this podcast entry.', 'podcast-player' ) );
		echo wp_json_encode( $output );
		wp_die();
	}

	/**
	 * New method to handle feed editor ajax calls.
	 *
	 * @since 1.0.0
	 */
	public function delete_podcast_source() {
		check_ajax_referer( 'podcast-player-admin-options-ajax-nonce', 'security' );
		Utility_Fn::require_capabilities( 'manage_options', 'pp_delete_source' );

		$podcast_id = isset( $_POST['podcast_id'] ) ? sanitize_text_field( wp_unslash( $_POST['podcast_id'] ) ) : false;

		if ( ! $podcast_id ) {
			$output = array(
				'error' => esc_html__( 'Choose a valid podcast before removing the source URL.', 'podcast-player' ),
			);
			echo wp_json_encode( $output );
			wp_die();
		}

		$store_manager = StoreManager::get_instance();
		$is_success = $store_manager->delete_podcast_source_url( $podcast_id );

		if ( ! $is_success ) {
			$output = array(
				'error' => esc_html__( 'Unable to delete source URL.', 'podcast-player' ),
			);
			echo wp_json_encode( $output );
			wp_die();
		}

		// Ajax output to be returened.
		$output = array( 'message' => esc_html__( 'Source URL removed from this podcast.', 'podcast-player' ) );
		echo wp_json_encode( $output );
		wp_die();
	}

	/**
	 * Returns the instance of this class.
	 *
	 * @since  1.0.0
	 *
	 * @return object Instance of this class.
	 */
	public static function get_instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
