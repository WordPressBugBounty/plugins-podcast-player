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
 * @author     vedathemes <contact@vedathemes.com>
 */
class Get_Feed {

	/**
	 * Current persisted episode-identity format.
	 *
	 * @since 8.3.0
	 * @var int
	 */
	private const IDENTITY_VERSION = 2;

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
