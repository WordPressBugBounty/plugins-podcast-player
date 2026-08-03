<?php
/**
 * Get Feed Data from Database OR from Feed XML file.
 *
 * @link       https://www.vedathemes.com
 * @since      1.0.0
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 */

namespace Podcast_Player\Helper\Feed;

use Podcast_Player\Helper\Functions\Getters as Get_Fn;
use Podcast_Player\Helper\Functions\Utility as Utility_Fn;
use Podcast_Player\Helper\Functions\Validation as Validation_Fn;
use Podcast_Player\Helper\Store\ItemData;
use Podcast_Player\Helper\Store\FeedData;
use Podcast_Player\Helper\Store\StoreManager;
use Podcast_Player\Helper\Core\Background_Jobs;

/**
 * Get Feed Data from Database OR from Feed XML file.
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 * @author     vedathemes <contact@easypodcastpro.com>
 */
class Get_Feed {

	/**
	 * Current persisted episode-identity format.
	 *
	 * @since 8.3.0
	 * @var int
	 */
	public const IDENTITY_VERSION = 2;

	/**
	 * Per-feed lock lifetime for the identity migration fetch.
	 *
	 * @since 8.3.0
	 * @var int
	 */
	private const IDENTITY_MIGRATION_LOCK_TTL = 30;

	/**
	 * Cooldown after an identity migration fetch cannot return parsed feed data.
	 *
	 * @since 8.3.0
	 * @var int
	 */
	private const IDENTITY_MIGRATION_RETRY_TTL = 300;

	/**
	 * Consecutive qualifying (dead-feed) source failures before auto-promotion.
	 *
	 * @since 8.3.0
	 * @var int
	 */
	private const SOURCE_FAILURE_THRESHOLD = 3;

	/**
	 * Holds feed url for current instance.
	 *
	 * @since  3.3.0
	 * @access private
	 * @var    string
	 */
	private $feed_url = '';

	/**
	 * Holds feed filter and sort args.
	 *
	 * @since  3.3.0
	 * @access private
	 * @var    array
	 */
	private $mods = array();

	/**
	 * Holds required episodes field keys.
	 *
	 * @since  3.3.0
	 * @access private
	 * @var    array
	 */
	private $fields = array();

	/**
	 * Holds feed key prefix.
	 *
	 * @since  3.3.0
	 * @access private
	 * @var    string
	 */
	private $prefix = 'pp_feed';

	/**
	 * Check if podcast player pro is available.
	 *
	 * @since  5.8.0
	 * @access private
	 * @var    bool
	 */
	private $is_pro = false;

	/**
	 * Constructor method.
	 *
	 * @since  3.3.0
	 *
	 * @param string $feedurl Feed URL.
	 * @param array  $mods    Feed episode filter args.
	 * @param array  $fields  Required episode field keys.
	 */
	public function __construct( $feedurl = '', $mods = array(), $fields = array() ) {
		// Set Object Properties.
		$this->mods     = $mods;
		$this->fields   = $fields;
		$this->feed_url = $feedurl;

		// Check if pro is available.
		$this->is_pro = apply_filters( 'podcast_player_is_premium', false );
	}

	/**
	 * Init method.
	 *
	 * @since  3.3.0
	 */
	public function init() {

		// Get feed data from DB or from feed url.
		$fdata = $this->get_feed_data();
		if ( is_wp_error( $fdata ) ) {
			return $fdata;
		}

		// Apply sort, filter and other customizations.
		$fdata = $this->modify_feed_data( $fdata );
		if ( is_wp_error( $fdata ) ) {
			return $fdata;
		}

		// Check and use custom data for feed items.
		$fdata = $this->override_customizations( $fdata );
		if ( is_wp_error( $fdata ) ) {
			return $fdata;
		}

		// Prepare data for frontend.
		$fdata = $this->prepare_data( $fdata );
		if ( is_wp_error( $fdata ) ) {
			return $fdata;
		}

		return $fdata;
	}

	/**
	 * Get podcast feed data from storage or fetch from feed URL.
	 *
	 * @since  3.3.0
	 */
	public function get_feed_data() {
		$store_manager = StoreManager::get_instance();
		$podcast_data  = $store_manager->get_data( $this->feed_url );
		$podcast_data  = $podcast_data instanceof FeedData ? $podcast_data : false;
		if ( $this->is_identity_migration_required( $podcast_data ) ) {
			return $this->migrate_cached_identity( $podcast_data );
		}
		if ( $this->is_fetch_required( $podcast_data ) ) {
			return $this->fetch_podcast_data( $podcast_data );
		}
		return $this->retrieve_feed_data( $podcast_data );
	}

	/**
     * Fetch Podcast Data from the feed.
     *
     * @since  7.4.0
	 *
	 * @param Object|false $old_podcast_data Old Podcast Feed Data.
     */
    public function fetch_podcast_data( $old_podcast_data, $force_identity_migration = false ) {
		$store_manager = StoreManager::get_instance();
		$last_checked  = $store_manager->get_data( $this->feed_url, 'last_checked' );
		if ( ! $force_identity_migration && ! empty( $last_checked ) ) {
			$etag          = $old_podcast_data ? $old_podcast_data->get( 'etag', 'sanitize' ) : false;
			$last_modified = $old_podcast_data ? $old_podcast_data->get( 'lastbuild', 'sanitize' ) : false;
		} else {
			$etag          = false;
			$last_modified = false;
		}

		$source_url    = $store_manager->get_source_url( $this->feed_url );
        $obj           = new Fetch_Feed( $source_url, $etag, $last_modified );
		$raw_data      = $obj->get_feed_data();
		if ( is_wp_error( $raw_data ) ) {
			if ( $force_identity_migration && $old_podcast_data instanceof FeedData ) {
				$this->set_identity_migration_retry();
				return $this->retrieve_feed_data( $old_podcast_data );
			}
			if ( ! $force_identity_migration
				&& $this->handle_source_fetch_failure( $obj, $old_podcast_data )
				&& $old_podcast_data instanceof FeedData ) {
				// Auto-promotion applied a verified migration target; serve the
				// last good cache until the next fetch uses the new source.
				return $this->retrieve_feed_data( $old_podcast_data );
			}
			return $raw_data;
		}

		// Return old data if no update required.
		if ( '304_use_cache' === $raw_data ) {
			if ( $force_identity_migration && $old_podcast_data instanceof FeedData ) {
				$this->set_identity_migration_retry();
				return $this->retrieve_feed_data( $old_podcast_data );
			}
			$data         = $old_podcast_data;
			$new_episodes = array();
		} else {
			list( $data, $new_episodes ) = $this->prepare_storage_data( $raw_data, $old_podcast_data );
		}

		if ( $force_identity_migration && ! $this->has_current_identity_version( $data ) ) {
			$this->set_identity_migration_retry();
			return $this->retrieve_feed_data( $old_podcast_data );
		}

		$url   = $data->get( 'furl' );
		$alias = $url !== $this->feed_url ? $url : false;

		if ( ! $old_podcast_data ) {
			$title = $data->get( 'title' );
			$store_manager->maybe_add_new_object( $this->feed_url, $title );
		}
		$feed_stored = true;
		if ( '304_use_cache' !== $raw_data ) {
			$feed_stored = $store_manager->update_data( $data, $this->feed_url, 'feed_data', $alias );
		}
		if ( $force_identity_migration && ! $feed_stored ) {
			$this->set_identity_migration_retry();
			return $this->retrieve_feed_data( $old_podcast_data );
		}
		$store_manager->update_data( time(), $this->feed_url, 'last_checked' );
		if ( $force_identity_migration ) {
			delete_transient( $this->get_identity_migration_retry_key() );
		}

		if ( ! $force_identity_migration ) {
			// Any live response (200 or 304) proves the source is alive and ends
			// a consecutive-failure streak; signal detection needs a fresh body.
			$this->reset_source_failures();
			if ( '304_use_cache' !== $raw_data ) {
				$this->detect_migration_signals( $obj, $data, $old_podcast_data );
			}
		}

		// Fecilitate new episode import.
		if ( $this->is_pro && ! empty( $new_episodes ) ) {
			// If opted, queue new episodes for import.
			$import_settings = Get_Fn::get_feed_import_settings( $this->feed_url );
			if ( $import_settings['is_auto'] ) {
				Background_Jobs::add_task( $this->feed_url, 'import_episodes', $new_episodes );
			}
		}

		return $this->retrieve_feed_data( $data );
	}

	/**
	 * Record or clear a host-migration candidate from a successful fetch.
	 *
	 * A same-host permanent redirect (http->https, a slug change, host case) is
	 * applied silently: the resolved URL becomes the record source and the old
	 * URLs stay as aliases. A cross-host permanent redirect or an iTunes
	 * new-feed-url that resolves to the same podcast is stored as a candidate for
	 * the notice-gated migrate flow.
	 *
	 * @since 8.3.0
	 *
	 * @param Fetch_Feed     $obj              Fetch instance for the current feed.
	 * @param FeedData       $data             Freshly parsed feed data.
	 * @param FeedData|false $old_podcast_data Previously stored feed data.
	 */
	private function detect_migration_signals( $obj, $data, $old_podcast_data ) {
		$store    = StoreManager::get_instance();
		$redirect = $obj->get_redirect_migration_data();

		// A permanent redirect that only normalises the address — http to https,
		// a trailing slash, host case, a default port — is not a migration.
		// Fold the resolved URL in as an alias so a future player using it
		// cannot create a duplicate record, and raise nothing for review.
		if ( ! empty( $redirect ) && Utility_Fn::is_canonical_variant( $this->feed_url, $redirect['to'] ) ) {
			$store->add_object_alias( $this->feed_url, $redirect['to'] );
			$this->clear_migration_candidate( $store );
			return;
		}

		$target   = '';
		$type     = '';
		$verified = false;

		$comparison = 'unverifiable';

		if ( ! empty( $redirect ) ) {
			// The redirect was followed, so $data is already the destination feed.
			$target = $redirect['to'];
			$type   = 'redirect';

			// Three states, not two. A record being fetched for the first time
			// has nothing to compare against, and "nothing to compare" is not
			// agreement: recording it as verified would let auto-promotion skip
			// its own check later on evidence that was never gathered.
			if ( $old_podcast_data instanceof FeedData ) {
				$comparison = self::feeds_same_podcast( $old_podcast_data, $data ) ? 'match' : 'mismatch';
			}
			$verified = 'match' === $comparison;

			// Fold the resolved URL in as an alias unless we have positive
			// evidence it serves a different podcast. Absence of evidence is
			// not evidence of a mismatch, and refusing to fold would let a
			// future player on the resolved URL create a duplicate record. A
			// same-host mismatch is still a mismatch: a publisher who repoints
			// one feed path at an unrelated show must not have that show's URL
			// aliased onto this record.
			if ( 'mismatch' !== $comparison ) {
				$store->add_object_alias( $this->feed_url, $redirect['to'] );
			}
		} else {
			$new_feed_url = $obj->get_new_feed_url();
			if ( $new_feed_url && $new_feed_url !== $this->feed_url ) {
				// Destination not fetched yet; verified when promoted.
				$target = $new_feed_url;
				$type   = 'new_feed_url';
			}
		}

		// No usable signal, or a redirect that demonstrably leads to a different
		// podcast. An unverifiable redirect still raises a candidate — it is
		// carried as unverified, so promotion has to earn the check itself once
		// there is stored data to compare against.
		if ( ! $target || ( 'redirect' === $type && 'mismatch' === $comparison ) ) {
			$this->clear_migration_candidate( $store );
			return;
		}

		$store->update_data(
			array(
				'to'         => esc_url_raw( $target ),
				'type'       => $type,
				'verified'   => (bool) $verified,
				'created_at' => time(),
			),
			$this->feed_url,
			'pp_migration_candidate'
		);
	}

	/**
	 * Remove a stored migration candidate, if any.
	 *
	 * @since 8.3.0
	 *
	 * @param StoreManager $store Store manager instance.
	 */
	private function clear_migration_candidate( $store ) {
		if ( $store->get_data( $this->feed_url, 'pp_migration_candidate' ) ) {
			$store->delete_data( $this->feed_url, 'pp_migration_candidate' );
		}
	}

	/**
	 * Reset the consecutive source-failure counter after a good fetch.
	 *
	 * @since 8.3.0
	 */
	private function reset_source_failures() {
		$store = StoreManager::get_instance();
		if ( (int) $store->get_data( $this->feed_url, 'pp_source_failures' ) > 0 ) {
			$store->delete_data( $this->feed_url, 'pp_source_failures' );
		}
	}

	/**
	 * Count a qualifying source failure and auto-promote when appropriate.
	 *
	 * Only explicit HTTP 404/410 responses count as dead-feed signals; transport
	 * errors, timeouts and 5xx are transient and never increment the counter.
	 *
	 * @since 8.3.0
	 *
	 * @param Fetch_Feed     $obj              Fetch instance for the current feed.
	 * @param FeedData|false $old_podcast_data Previously stored feed data.
	 * @return bool True when a verified candidate was promoted.
	 */
	private function handle_source_fetch_failure( $obj, $old_podcast_data ) {
		if ( ! in_array( $obj->get_response_code(), array( 404, 410 ), true ) ) {
			return false;
		}

		$store = StoreManager::get_instance();

		// Serialize the whole increment/verify/promote section per feed with a
		// dedicated claim lock (distinct from the record lock that
		// add_podcast_source_url() acquires, so there is no re-entrant deadlock).
		// A concurrent render that cannot claim simply skips this attempt.
		// Key the claim by the resolved record, not the URL this request happened
		// to use. Review approval locks by unique ID, and a lock named after an
		// alias would not exclude it.
		$claim_key = 'pp_source_promote_' . $store->get_promotion_lock_key( $this->feed_url );
		$claim     = $store->acquire_record_lock( $claim_key );
		if ( ! $claim ) {
			return false;
		}

		try {
			$count = (int) $store->get_data( $this->feed_url, 'pp_source_failures' ) + 1;
			$store->update_data( $count, $this->feed_url, 'pp_source_failures' );

			if ( $count < self::SOURCE_FAILURE_THRESHOLD ) {
				return false;
			}

			$candidate = $store->get_data( $this->feed_url, 'pp_migration_candidate' );
			if ( ! is_array( $candidate ) || empty( $candidate['to'] ) ) {
				return false;
			}

			if ( empty( $candidate['verified'] )
				&& ! $this->verify_migration_target( $candidate['to'], $old_podcast_data ) ) {
				return false;
			}

			// Re-read state immediately before applying: a concurrent successful
			// fetch may have recovered the source (reset the counter) or replaced
			// the candidate while we were verifying. Abort rather than promote a
			// stale target.
			if ( (int) $store->get_data( $this->feed_url, 'pp_source_failures' ) < self::SOURCE_FAILURE_THRESHOLD ) {
				return false;
			}
			$current = $store->get_data( $this->feed_url, 'pp_migration_candidate' );
			if ( ! is_array( $current ) || empty( $current['to'] ) || $current['to'] !== $candidate['to'] ) {
				return false;
			}

			return $this->promote_migration_candidate( $current );
		} finally {
			$store->release_record_lock( $claim_key, $claim );
		}
	}

	/**
	 * Verify a candidate target resolves to the same podcast as the old feed.
	 *
	 * @since 8.3.0
	 *
	 * @param string         $target           Candidate feed URL.
	 * @param FeedData|false $old_podcast_data Previously stored feed data.
	 * @return bool
	 */
	private function verify_migration_target( $target, $old_podcast_data ) {
		if ( ! $old_podcast_data instanceof FeedData ) {
			return false;
		}
		$probe = new Fetch_Feed( $target );
		$new   = $probe->get_feed_data();
		if ( ! $new instanceof FeedData ) {
			return false;
		}
		return self::feeds_same_podcast( $old_podcast_data, $new );
	}

	/**
	 * Whether two feeds describe the same podcast (title + author match).
	 *
	 * @since 8.3.0
	 *
	 * @param FeedData $first  First feed.
	 * @param FeedData $second Second feed.
	 * @return bool
	 */
	/**
	 * Compare a record against a prospective new source feed.
	 *
	 * Public because every path that switches a source — the review notice, the
	 * manual admin field — must be able to show the admin what it is about to
	 * do before doing it. Returns three distinct states, not a boolean:
	 * `unverifiable` is not `mismatch`, and neither is permission.
	 *
	 * A record whose source URL was rewritten by an older version frequently has
	 * no cached feed at all, so there is genuinely nothing to compare. That must
	 * be reported honestly rather than passed off as agreement.
	 *
	 * Fails closed: a fetch failure is `unverifiable`, never `match`.
	 *
	 * @since 8.3.0
	 *
	 * @param string $object_key Record key being repointed.
	 * @param string $target     Prospective new source URL.
	 * @return array
	 */
	public static function verify_source_target( $object_key, $target ) {
		$result = array(
			'status' => 'unverifiable',
			'old'    => array( 'title' => '', 'author' => '' ),
			'new'    => array( 'title' => '', 'author' => '' ),
		);

		if ( ! Validation_Fn::is_valid_url( $target ) ) {
			return $result;
		}

		$store  = StoreManager::get_instance();
		$index  = $store->get_object_index( $object_key );
		$record = $index instanceof \Podcast_Player\Helper\Store\StorageRegister
			? $index->get( 'unique_id', 'none' )
			: (string) $object_key;

		// Keyed by record AND target: the answer depends on both feeds, so a
		// match computed for one podcast must never approve another podcast
		// that happens to share the target URL.
		$cache_key = 'pp_srcverify_' . md5( $record . '|' . esc_url_raw( $target ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && isset( $cached['status'] ) ) {
			return $cached;
		}

		$old = $store->get_data( $object_key, 'feed_data' );
		$obj = new Fetch_Feed( $target );
		$new = $obj->get_feed_data();

		if ( $old instanceof FeedData ) {
			$result['old'] = array(
				'title'  => (string) $old->get( 'title', 'sanitize' ),
				'author' => (string) $old->get( 'author', 'sanitize' ),
			);
		}
		if ( $new instanceof FeedData ) {
			$result['new'] = array(
				'title'  => (string) $new->get( 'title', 'sanitize' ),
				'author' => (string) $new->get( 'author', 'sanitize' ),
			);
			if ( $old instanceof FeedData ) {
				$result['status'] = self::feeds_same_podcast( $old, $new ) ? 'match' : 'mismatch';
			}
		}

		set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );
		return $result;
	}

	private static function feeds_same_podcast( $first, $second ) {
		$first_title  = trim( (string) $first->get( 'title', 'sanitize' ) );
		$second_title = trim( (string) $second->get( 'title', 'sanitize' ) );
		if ( '' === $first_title || $first_title !== $second_title ) {
			return false;
		}

		// An identical title alone is too weak for an irreversible source switch;
		// require a second corroborating signal: a non-empty matching author or a
		// non-empty matching owner email. Two empty values never corroborate.
		$first_author  = trim( (string) $first->get( 'author', 'sanitize' ) );
		$second_author = trim( (string) $second->get( 'author', 'sanitize' ) );
		if ( '' !== $first_author && $first_author === $second_author ) {
			return true;
		}

		$first_email  = self::feed_owner_email( $first );
		$second_email = self::feed_owner_email( $second );
		return '' !== $first_email && $first_email === $second_email;
	}

	/**
	 * Extract a normalized owner email from a feed for verification.
	 *
	 * @since 8.3.0
	 *
	 * @param FeedData $feed Feed data.
	 * @return string
	 */
	private static function feed_owner_email( $feed ) {
		$owner = $feed->get( 'owner', 'none' );
		if ( ! is_array( $owner ) || empty( $owner['email'] ) ) {
			return '';
		}
		return strtolower( trim( (string) $owner['email'] ) );
	}

	/**
	 * Apply a verified migration candidate as the new source URL.
	 *
	 * Routes through StoreManager::add_podcast_source_url(), which merges when the
	 * target is already a registered podcast and forces a refetch otherwise.
	 *
	 * @since 8.3.0
	 *
	 * @param array $candidate Stored candidate data.
	 * @return bool
	 */
	private function promote_migration_candidate( $candidate ) {
		$store = StoreManager::get_instance();

		// Resolve a durable identity before anything changes. $this->feed_url is
		// the resolved *source* URL, and applying the candidate replaces it —
		// after which the record no longer matches that URL at all, so cleanup
		// keyed on it would silently delete nothing and leave a stale migration
		// notice plus a failure counter parked at the threshold.
		$key   = $store->get_promotion_lock_key( $this->feed_url );
		$index = $store->get_object_index( $key );
		// The post that actually holds this record's promotion state, captured
		// before the merge can tombstone it and reroute key lookups elsewhere.
		$object_id = $index instanceof \Podcast_Player\Helper\Store\StorageRegister ? $index->get( 'object_id', 'none' ) : 0;
		$applied   = $store->add_podcast_source_url( $key, $candidate['to'] );

		// Only these two actually apply the new source. A `deferred` merge waits
		// on an identity migration and a `noop` merge (already one record, or a
		// tombstoned counterpart) returns without touching source_url — clearing
		// the candidate for either would discard a migration that never
		// happened, and a dead feed can never re-raise the signal.
		$ok = true === $applied
			|| ( is_array( $applied ) && 'merged' === ( isset( $applied['status'] ) ? $applied['status'] : '' ) );
		if ( ! $ok ) {
			return false;
		}
		$store->delete_object_meta( $object_id, array( 'pp_migration_candidate', 'pp_migration_seen', 'pp_source_failures' ) );
		return true;
	}

	/**
	 * Retrieve feed data for internal processing with durable legacy aliases.
	 *
	 * The aliases are removed by Prepare_Front_New before a player response is
	 * emitted, but must accompany the internal array so resolver instances can
	 * honour historical saved references.
	 *
	 * @since 8.3.0
	 *
	 * @param FeedData $feed_data Stored feed data.
	 * @return array
	 */
	private function retrieve_feed_data( $feed_data ) {
		$data = $feed_data->retrieve();
		$data['legacy_key_by_uid'] = $feed_data->get( 'legacy_key_by_uid', 'none' );
		return $data;
	}

	/**
	 * Prepare data for storage.
	 *
	 * @since  7.4.0
	 *
	 * @param  Object       $new_data Raw podcast feed data.
	 * @param  Object|false $old_data Old podcast feed data.
	 */
	private function prepare_storage_data( $new_data, $old_data ) {
		$obj = new Prepare_Storage( $new_data, $old_data );
		return $obj->init();
	}

	/**
	 * Save fetched data.
	 *
	 * @since  3.3.0
	 *
	 * @param array $data Apply sort and filters on fetched data.
	 */
	private function modify_feed_data( $data ) {
		$obj         = Modify_Feed_Data::get_instance();
		$custom_data = Get_Fn::get_modified_feed_data( $this->feed_url );
		$custom_data = $custom_data instanceof FeedData ? $custom_data->retrieve() : array();
		$data        = $obj->init( $data, $custom_data, $this->mods, $this->fields );
		$items       = isset( $data['items'] ) ? $data['items'] : array();

		if ( empty( $items ) ) {
			return new \WP_Error(
				'no-filtered-items',
				esc_html__( 'No feed items for your specific filters.', 'podcast-player' )
			);
		}

		return $data;
	}

	/**
	 * Check and use custom data for feed items.
	 *
	 * Original override customization has been merged with modify_feed_data method. Keeping this for
	 * compatibility and possibility for further customization.
	 *
	 * @since  3.3.0
	 *
	 * @param array $data Feed data to be overridden by customizations.
	 */
	private function override_customizations( $data ) {
		
		/**
		 * Filters the custom data for the feed to override original data.
		 *
		 * @since 3.3.0
		 *
		 * @param array  $custom_data Feed items custom data.
		 * @param string $feed_url    Feed URL.
		 * @return array Modified custom data for the feed.
		 */
		$custom_data = apply_filters( 'podcast_player_custom_data', array(), $this->feed_url );

		// Return if custom data do not exist.
		if ( ! $custom_data || ! is_array( $custom_data ) ) {
			return $data;
		}

		$data  = array_replace_recursive( $data, $custom_data );
		return $data;
	}

	/**
	 * Save fetched data.
	 *
	 * @since  3.3.0
	 *
	 * @param array $data Apply sort and filters on fetched data.
	 */
	private function prepare_data( $data ) {
		$obj = Prepare_Front_New::get_instance();
		return $obj->init( $data );
	}

	/**
     * Check if feed data is required or not.
     *
     * @since 7.4.0
     *
     * @param  Object|false $podcast_data
     */
	private function is_fetch_required( $podcast_data ) {
		if ( ! $podcast_data ) {
			return true;
		}

		$store_manager    = StoreManager::get_instance();
		$last_checked     = $store_manager->get_data( $this->feed_url, 'last_checked' );
		$last_checked     = $last_checked ? $last_checked : 0;
		$refresh_interval = absint( Get_Fn::get_plugin_option( 'refresh_interval' ) );
		$cache_duration   = min( $podcast_data->get( 'cache_duration' ), $refresh_interval * 60 );
		if ( ( $last_checked + $cache_duration ) < time() ) {
			return true;
		}

		return false;
    }

	/**
	 * Whether a stored feed still requires parser-backed identity migration.
	 *
	 * @since 8.3.0
	 *
	 * @param FeedData|false $podcast_data Stored feed data.
	 * @return bool
	 */
	private function is_identity_migration_required( $podcast_data ) {
		return $podcast_data instanceof FeedData && ! $this->has_current_identity_version( $podcast_data );
	}

	/**
	 * Check whether the feed was parsed using the current identity contract.
	 *
	 * @since 8.3.0
	 *
	 * @param FeedData|false $podcast_data Feed data.
	 * @return bool
	 */
	private function has_current_identity_version( $podcast_data ) {
		return $podcast_data instanceof FeedData
			&& self::IDENTITY_VERSION <= absint( $podcast_data->get( 'identity_version', 'none' ) );
	}

	/**
	 * Fetch and migrate an old cached feed without conditional request headers.
	 *
	 * The existing cache remains usable if the source cannot provide a parsed
	 * response. A short cooldown prevents an old cache from triggering a remote
	 * request on every frontend render.
	 *
	 * @since 8.3.0
	 *
	 * @param FeedData $podcast_data Stored legacy feed data.
	 * @return array
	 */
	private function migrate_cached_identity( $podcast_data ) {
		if ( get_transient( $this->get_identity_migration_retry_key() ) ) {
			return $podcast_data->retrieve();
		}

		$lock_token = $this->acquire_identity_migration_lock();
		if ( ! $lock_token ) {
			return $podcast_data->retrieve();
		}

		try {
			return $this->fetch_podcast_data( $podcast_data, true );
		} finally {
			$this->release_identity_migration_lock( $lock_token );
		}
	}

	/**
	 * Build a per-feed transient key for the migration lock or cooldown.
	 *
	 * @since 8.3.0
	 *
	 * @param string $suffix Key suffix.
	 * @return string
	 */
	private function get_identity_migration_key( $suffix ) {
		return 'pp_identity_migration_' . $suffix . '_' . md5( $this->feed_url );
	}

	/**
	 * Get the per-feed migration retry key.
	 *
	 * @since 8.3.0
	 * @return string
	 */
	private function get_identity_migration_retry_key() {
		return $this->get_identity_migration_key( 'retry' );
	}

	/**
	 * Record a short retry cooldown without changing the old cache.
	 *
	 * @since 8.3.0
	 */
	private function set_identity_migration_retry() {
		set_transient(
			$this->get_identity_migration_retry_key(),
			1,
			self::IDENTITY_MIGRATION_RETRY_TTL
		);
	}

	/**
	 * Acquire a short-lived, per-feed migration lock.
	 *
	 * @since 8.3.0
	 * @return string|false Lock token or false when another request owns it.
	 */
	private function acquire_identity_migration_lock() {
		$lock_key = $this->get_identity_migration_key( 'lock' );
		$token    = wp_generate_uuid4();
		if ( get_transient( $lock_key ) ) {
			return false;
		}

		set_transient( $lock_key, $token, self::IDENTITY_MIGRATION_LOCK_TTL );
		return get_transient( $lock_key ) === $token ? $token : false;
	}

	/**
	 * Release a migration lock only when this request owns it.
	 *
	 * @since 8.3.0
	 *
	 * @param string|false $token Lock token.
	 */
	private function release_identity_migration_lock( $token ) {
		if ( ! $token ) {
			return;
		}

		$lock_key = $this->get_identity_migration_key( 'lock' );
		if ( get_transient( $lock_key ) === $token ) {
			delete_transient( $lock_key );
		}
	}
}
