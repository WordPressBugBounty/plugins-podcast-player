<?php
/**
 * Base class to store podcast feed data.
 *
 * @link       https://easypodcastpro.com
 * @since      1.0.0
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 */

namespace Podcast_Player\Helper\Store;

use Podcast_Player\Helper\Core\Singleton;
use Podcast_Player\Helper\Feed\Episode_Resolver;
use Podcast_Player\Helper\Feed\Get_Feed;
use Podcast_Player\Helper\Store\StorageRegister;
use Podcast_Player\Helper\Functions\Validation as Validation_Fn;

/**
 * Store Manager Class
 *
 * @since 1.0.0
 */
class StoreManager extends Singleton {

	/**
	 * One-time legacy duplicate-scan version. Bumping re-runs the scan once.
	 *
	 * @since 8.3.0
	 * @var int
	 */
	const DUPE_SCAN_VERSION = 1;

	/**
	 * Resolved option names for locks owned by this request.
	 *
	 * @since 8.3.0
	 * @var array
	 */
	private $record_lock_names = array();

	/**
	 * Setup initial state of this class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Setup store manager.
	 *
	 * @since 1.0.0
	 */
	public function register() {
		// Register post types to store all playlists and its data.
		$test = register_post_type(
			'podcast_player',
			array(
				'labels'    => array(
					'name'          => esc_html__( 'Podcasts', 'podcast-player' ),
					'singular_name' => esc_html__( 'Podcast', 'podcast-player' ),
				),
				'query_var' => false,
			)
		);
	}

	/**
	 * Get a stored object data.
	 *
	 * @since 1.0.0
	 *
	 * @param string $object_key Key to access the post object bucket.
	 * @param string $data_key   Key to access specific data stored in the object.
	 */
	public function get_data( $object_key, $data_key = 'feed_data' ) {
		$index = $this->get_object_index( $object_key );
		if ( ! $index instanceof StorageRegister ) {
			return false;
		}
		if ( is_array( $data_key ) ) {
			$data = array();
			foreach ( $data_key as $key ) {
				$data[ $key ] = get_post_meta( $index->get( 'object_id' ), $key, true );
			}
			return $data;
		}
		return get_post_meta( $index->get( 'object_id' ), $data_key, true );
	}

	/**
	 * Update an existing data object or create a new one.
	 *
	 * @since 1.0.0
	 *
     * @param string $data             Data to store in the object.
	 * @param string $object_key       Key to access the post object bucket.
	 * @param string $data_key         Key to access specific data stored in the object.
	 * @param string $object_key_alias Alias to the object key.
	 */
	public function update_data( $data, $object_key, $data_key = 'feed_data', $object_key_alias = false ) {
		$index = $this->get_object_index( $object_key );

		// If main object key is not indexed, try with alias key.
		if ( ! $index instanceof StorageRegister && $object_key_alias ) {
			$index = $this->get_object_index( $object_key_alias );
			if ( $index instanceof StorageRegister ) {
				list( $object_key, $object_key_alias ) = array( $object_key_alias, $object_key );
			}
		}

		if ( $index instanceof StorageRegister ) {
			$lock_key = $index->get( 'unique_id', 'none' );
			$token    = $this->acquire_record_lock( $lock_key );
			if ( ! $token ) {
				return false;
			}

			try {
				$index = $this->get_object_index( $object_key );
				if ( ! $index instanceof StorageRegister ) {
					return false;
				}

				update_post_meta( $index->get( 'object_id' ), $data_key, $data );
				if ( $object_key_alias ) {
					$this->add_alias_to_object_key( $object_key, $object_key_alias );
				}
				return true;
			} finally {
				$this->release_record_lock( $lock_key, $token );
			}
		}

		return false;
	}

	/**
	 * Delete stored data or object.
	 *
	 * @since 1.0.0
	 *
	 * @param string $object_key Key to access the post object bucket.
	 * @param string $data_key   Key to access specific data stored in the object.
	 */
	public function delete_data( $object_key, $data_key = false ) {
		$return = false;
		$index  = $this->get_object_index( $object_key );
		if ( ! $index instanceof StorageRegister ) {
			return $return;
		}

		$lock_key = $index->get( 'unique_id', 'none' );
		$token    = $this->acquire_record_lock( $lock_key );
		if ( ! $token ) {
			return false;
		}

		try {
			$index = $this->get_object_index( $object_key );
			if ( ! $index instanceof StorageRegister ) {
				return false;
			}

			if ( $data_key ) {
				if ( is_array( $data_key ) ) {
					foreach ( $data_key as $key ) {
						delete_post_meta( $index->get( 'object_id' ), $key );
					}
					return true;
				}
				return delete_post_meta( $index->get( 'object_id' ), $data_key );
			}
			$return = wp_delete_post( $index->get( 'object_id' ), true );
            $this->delete_object_index( $index->get( 'unique_id' ) );
			return $return;
		} finally {
			$this->release_record_lock( $lock_key, $token );
		}
	}

	/**
	 * Hide stored data or object.
	 *
	 * @since 1.0.0
	 *
	 * @param string $object_key Key to access the post object bucket.
	 */
	public function hide_data( $object_key ) {
		$index = $this->get_object_index( $object_key );
		if ( ! $index instanceof StorageRegister ) {
			return false;
		}
		$lock_key = $index->get( 'unique_id', 'none' );
		$token    = $this->acquire_record_lock( $lock_key );
		if ( ! $token ) {
			return false;
		}
		try {
			$index = $this->get_object_index( $object_key );
			if ( ! $index instanceof StorageRegister ) {
				return false;
			}
			$index->set( 'is_hidden', true );
			$register = $this->get_register();
			$register[ $index->get( 'unique_id' ) ] = $index;
			update_option( 'pp-register', $register, false );
			return true;
		} finally {
			$this->release_record_lock( $lock_key, $token );
		}
	}

	/**
	 * Create a new post object to save data (if not already created).
	 *
	 * @since 1.0.0
	 *
	 * @param string $object_key   Key to create/access the post object bucket.
	 * @param string $object_title Object Title.
	 */
	public function maybe_add_new_object( $object_key, $object_title = '' ) {
        // Check if object already exists.
        $index = $this->get_object_index( $object_key );
        if ( $index instanceof StorageRegister ) {
			$is_hidden = $index->get( 'is_hidden' );
			if ( $is_hidden ) {
				$index->set( 'is_hidden', false );
				$register = $this->get_register();
				$register[ $index->get( 'unique_id' ) ] = $index;
				update_option( 'pp-register', $register, false );
			}
            return $index->get( 'object_id' );
        }

        // Create custom post object to save feed data.
		$object_id = wp_insert_post(
			array(
				'post_type'   => 'podcast_player',
				'post_status' => 'publish',
			)
		);
		if ( is_wp_error( $object_id ) ) {
			return $object_id;
		}

		// Create Object Index.
		return $this->create_object_index( $object_key, $object_id, $object_title );
	}

	/**
	 * Create Object Index.
	 *
	 * @since 1.0.0
	 *
	 * @param string $object_key   Key to create/access the post object bucket.
     * @param string $object_id    Object ID.
     * @param string $object_title Object Title.
	 */
	private function create_object_index( $object_key, $object_id, $object_title ) {
		$register    = $this->get_register();
		$object_keys = array( $object_key );
		$unique_id   = md5( $object_key );
		$index_obj   = new StorageRegister();
		$index_obj->set( 'unique_id', $unique_id );
		$index_obj->set( 'title', $object_title );
		$index_obj->set( 'feed_url', $object_keys );
		$index_obj->set( 'object_keys', array_map( 'md5', $object_keys ) );
		$index_obj->set( 'object_id', $object_id );
		$register[ $unique_id ] = $index_obj;
		update_option( 'pp-register', $register, false );
		return true;
	}

	/**
	 * Add Alias to the podcast register.
	 *
	 * @since 1.0.0
	 *
	 * @param string $object_key       Key to create/access the post object bucket.
	 * @param string $object_key_alias Alias to the object key.
	 */
	private function add_alias_to_object_key( $object_key, $object_key_alias ) {
		$index  = $this->get_object_index( $object_key );
		if ( ! $index instanceof StorageRegister ) {
			return;
		}

		$urls   = $index->get( 'feed_url' );
		$id     = $index->get( 'unique_id' );
        $urls[] = (string) $object_key_alias;
		$index->set( 'feed_url', array_unique( $urls ) );
		$index->set( 'object_keys', array_map( 'md5', array_unique( $urls ) ) );
		$register = $this->get_register();
		$register[ $id ] = $index;
		update_option( 'pp-register', $register, false );
		return true;
	}

	/**
	 * Adopt a resolved URL as an alias of an existing record (idempotent).
	 *
	 * Used to fold a redirect target so a future player pointing at the resolved
	 * URL cannot create a duplicate record. Never changes source_url and never
	 * invalidates the cache: which URL a record fetches from is an admin
	 * decision, not something detection may take. The pre-lock check keeps the
	 * common (already-folded) path free of any write or lock.
	 *
	 * @since 8.3.0
	 *
	 * @param string $object_key Existing record key.
	 * @param string $alias_url  Resolved URL to adopt as an alias.
	 * @return bool
	 */
	public function add_object_alias( $object_key, $alias_url ) {
		if ( ! Validation_Fn::is_valid_url( $alias_url ) ) {
			return false;
		}
		$alias_url = esc_url_raw( $alias_url );
		$index     = $this->get_object_index( $object_key );
		if ( ! $index instanceof StorageRegister ) {
			return false;
		}

		// If the resolved URL already belongs to a DIFFERENT live record, do not
		// fold it in — appending would create overlapping records. Leave both as
		// they are (the reverse-collision guard / duplicate scan handle a real
		// duplicate through a verified merge, never here).
		$owner = $this->get_object_index( $alias_url );
		if ( $owner instanceof StorageRegister
			&& $owner->get( 'unique_id', 'none' ) !== $index->get( 'unique_id', 'none' ) ) {
			return false;
		}

		$urls = $index->get( 'feed_url' );
		$urls = is_array( $urls ) ? $urls : array();
		if ( in_array( $alias_url, $urls, true ) ) {
			return true;
		}

		$id    = $index->get( 'unique_id' );
		$token = $this->acquire_record_lock( $id );
		if ( ! $token ) {
			return false;
		}
		try {
			$index = $this->get_object_index( $object_key );
			if ( ! $index instanceof StorageRegister ) {
				return false;
			}
			$urls = $index->get( 'feed_url' );
			$urls = is_array( $urls ) ? $urls : array();
			if ( in_array( $alias_url, $urls, true ) ) {
				return true;
			}
			$urls[] = $alias_url;
			$urls   = array_values( array_unique( array_filter( $urls ) ) );
			// Union new URL hashes onto existing object_keys so non-URL keys
			// preserved by a prior merge (e.g. an absorbed unique_id) are kept.
			$object_keys = array_values(
				array_unique(
					array_filter(
						array_merge( (array) $index->get( 'object_keys', 'none' ), array_map( 'md5', $urls ) )
					)
				)
			);
			$index->set( 'feed_url', $urls );
			$index->set( 'object_keys', $object_keys );
			$register        = $this->get_register();
			$register[ $id ] = $index;
			update_option( 'pp-register', $register, false );
			return true;
		} finally {
			$this->release_record_lock( $id, $token );
		}
	}

	/**
	 * Add Source URL to the podcast register.
	 *
	 * @since 7.4.9
	 *
	 * @param string $object_key Key to create/access the post object bucket.
	 * @param string $source_url Podcast Source URL.
	 */
	public function add_podcast_source_url( $object_key, $source_url ) {
		$index  = $this->get_object_index( $object_key );
		if ( ! $index instanceof StorageRegister ) {
			return false;
		}

		if ( ! Validation_Fn::is_valid_url( $source_url ) ) {
			return false;
		}
		$source_url   = esc_url_raw( $source_url );
		$source_index = $this->get_object_index( $source_url );
		if ( $source_index instanceof StorageRegister && $source_index->get( 'unique_id', 'none' ) !== $index->get( 'unique_id', 'none' ) ) {
			$merge = $this->merge_podcast_records(
				$index->get( 'unique_id', 'none' ),
				$source_index->get( 'unique_id', 'none' ),
				array(
					'source_url' => $source_url,
				)
			);

			// A completed source-switch merge changes the fetch target. Deferred
			// merges do not apply the new source, so retain their cached timestamp.
			if ( isset( $merge['status'], $merge['survivor_key'] ) && 'merged' === $merge['status'] ) {
				$this->delete_data( $merge['survivor_key'], 'last_checked' );
			}

			return $merge;
		}

		$id         = $index->get( 'unique_id' );
		$token      = $this->acquire_record_lock( $id );
		if ( ! $token ) {
			return false;
		}

		try {
			$index = $this->get_object_index( $object_key );
			if ( ! $index instanceof StorageRegister ) {
				return false;
			}

			$urls       = $index->get( 'feed_url' );
			$urls       = is_array( $urls ) ? $urls : array();
			array_unshift( $urls, $source_url );
			$urls = array_values( array_unique( array_filter( $urls ) ) );

			$index->set( 'source_url', $source_url );
			$index->set( 'feed_url', $urls );
			$index->set( 'object_keys', array_map( 'md5', $urls ) );
			$register = $this->get_register();
			$register[ $id ] = $index;
			update_option( 'pp-register', $register, false );
			delete_post_meta( $index->get( 'object_id' ), 'last_checked' );
			return true;
		} finally {
			$this->release_record_lock( $id, $token );
		}
	}

	/**
	 * Add Source URL to the podcast register.
	 *
	 * @since 7.4.9
	 *
	 * @param string $object_key Key to create/access the post object bucket.
	 */
	public function delete_podcast_source_url( $object_key ) {
		$index = $this->get_object_index( $object_key );
		if ( ! $index instanceof StorageRegister ) {
			return false;
		}

		$urls       = $index->get( 'feed_url' );
		$source_url = $index->get( 'source_url' );
		$id         = $index->get( 'unique_id' );

		if ( ! $source_url ) {
			return false;
		}
		$token = $this->acquire_record_lock( $id );
		if ( ! $token ) {
			return false;
		}

		try {
			$index = $this->get_object_index( $object_key );
			if ( ! $index instanceof StorageRegister ) {
				return false;
			}
			$source_url = $index->get( 'source_url' );
			$urls       = $index->get( 'feed_url' );
			if ( ! $source_url ) {
				return false;
			}

			if ( ! empty( $urls ) && is_array( $urls ) ) {
				foreach( $urls as $key => $url ) {
					if ( $url === $source_url ) {
						unset( $urls[ $key ] );
					}
				}
			}
			$index->set( 'source_url', '' );
			$index->set( 'feed_url', array_unique( $urls ) );
			$index->set( 'object_keys', array_map( 'md5', array_unique( $urls ) ) );
			$register = $this->get_register();
			$register[ $id ] = $index;
			update_option( 'pp-register', $register, false );
			delete_post_meta( $index->get( 'object_id' ), 'last_checked' );
			return true;
		} finally {
			$this->release_record_lock( $id, $token );
		}
	}

	public function get_source_url( $object_key ) {
		$index = $this->get_object_index( $object_key );
		if ( ! $index instanceof StorageRegister ) {
			return $object_key;
		}
		$url = $index->get( 'source_url' );
		if ( ! $url ) {
			$urls = $index->get( 'feed_url' );
			$url  = ! empty( $urls ) && is_array( $urls ) ? $urls[0] : $object_key;
		}
		return $url;
	}

	/**
	 * Get stored object index object.
	 *
	 * @since 1.0.0
	 *
	 * @param string $object_key Key to create/access the post object bucket.
	 */
	public function get_object_index( $object_key = '' ) {
		$object_key = apply_filters( 'podcast_player_index', $object_key );
		$register   = $this->get_register();

		if ( empty( $object_key ) ) {
			// Filter out hidden objects.
			$register = array_filter( $register, function( $sr ) {
				return $sr instanceof StorageRegister && ! $sr->get( 'is_hidden' ) && ! $sr->get( 'is_tombstoned' );
			} );
			return $register;
		}

		// If object key is provided instead of the URL.
		if ( isset( $register[ $object_key ] ) && ! $this->is_tombstoned( $register[ $object_key ] ) ) {
			return $register[ $object_key ];
		}

		// Get object key from the URL.
		$unique_id = md5( $object_key );

		// Check and get podcast by unique ID.
		if ( isset( $register[ $unique_id ] ) && ! $this->is_tombstoned( $register[ $unique_id ] ) ) {
			return $register[ $unique_id ];
		}

		// Deep search for the required podcast in the index.
		foreach ( $register as $podcast ) {
			if ( ! $this->is_tombstoned( $podcast ) && $podcast->lookup( $object_key ) ) {
				return $podcast;
			}
		}

		// A merge records the absorbed record's unique ID among the survivor's
		// object_keys, but StorageRegister::query() only hashes stored values for
		// URL fields — it never hashes the needle. Without this second pass a
		// non-URL key (an integration's external key, say) stops resolving the
		// moment its record is absorbed, silently orphaning the caller.
		foreach ( $register as $podcast ) {
			if ( ! $this->is_tombstoned( $podcast ) && $podcast->lookup( $unique_id ) ) {
				return $podcast;
			}
		}

		return false;
	}

	/**
	 * Return the identity format version used by store-level migrations.
	 *
	 * @since 8.3.0
	 * @return int
	 */
	public function get_identity_version() {
		return Get_Feed::IDENTITY_VERSION;
	}

	/**
	 * Acquire an atomic, expiring lock for one record or a stable pair of records.
	 *
	 * @since 8.3.0
	 * @param string|array $object_keys Record key or keys.
	 * @param int          $timeout    Lock lifetime in seconds.
	 * @return string|false Lock token or false when another request owns the lock.
	 */
	public function acquire_record_lock( $object_keys, $timeout = 30 ) {
		global $wpdb;

		if ( ! isset( $wpdb->options ) ) {
			return false;
		}

		$lock_name = $this->get_record_lock_name( $object_keys );
		$token     = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : md5( uniqid( '', true ) );
		$timeout   = max( 1, absint( $timeout ) );
		$value     = ( time() + $timeout ) . ':' . $token;
		$query     = $wpdb->prepare(
			"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no') ON DUPLICATE KEY UPDATE option_value = IF(CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) < %d, VALUES(option_value), option_value), autoload = 'no'",
			$lock_name,
			$value,
			time()
		);
		$result    = $wpdb->query( $query );

		wp_cache_delete( $lock_name, 'options' );
		if ( $result ) {
			$this->record_lock_names[ $token ] = $lock_name;
		}
		return $result ? $token : false;
	}

	/**
	 * Release a record lock only when this request owns its token.
	 *
	 * @since 8.3.0
	 * @param string|array $object_keys Record key or keys.
	 * @param string       $token       Lock token returned by acquire_record_lock().
	 * @return bool
	 */
	public function release_record_lock( $object_keys, $token ) {
		global $wpdb;

		if ( ! $token || ! isset( $wpdb->options ) ) {
			return false;
		}

		$lock_name = isset( $this->record_lock_names[ $token ] ) ? $this->record_lock_names[ $token ] : $this->get_record_lock_name( $object_keys );
		$query     = $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name = %s AND SUBSTRING_INDEX(option_value, ':', -1) = %s",
			$lock_name,
			$token
		);
		$result    = $wpdb->query( $query );

		wp_cache_delete( $lock_name, 'options' );
		unset( $this->record_lock_names[ $token ] );
		return (bool) $result;
	}

	/**
	 * Build a stable option name from a record or ordered record pair.
	 *
	 * @since 8.3.0
	 * @param string|array $object_keys Record key or keys.
	 * @return string
	 */
	private function get_record_lock_name( $object_keys ) {
		$object_keys = is_array( $object_keys ) ? $object_keys : array( $object_keys );
		$lock_keys   = array();

		foreach ( $object_keys as $object_key ) {
			$index = $this->get_object_index( $object_key );
			$lock_keys[] = $index instanceof StorageRegister ? $index->get( 'unique_id', 'none' ) : (string) $object_key;
		}

		$lock_keys = array_unique( array_filter( $lock_keys ) );
		sort( $lock_keys, SORT_STRING );
		return 'pp_store_lock_' . md5( implode( '|', $lock_keys ) );
	}

	/**
	 * Determine whether a register item is retained as a tombstone.
	 *
	 * @since 8.3.0
	 * @param mixed $index Register item.
	 * @return bool
	 */
	private function is_tombstoned( $index ) {
		return $index instanceof StorageRegister && (bool) $index->get( 'is_tombstoned', 'none' );
	}

	/**
	 * Merge two stored podcast records into one survivor.
	 *
	 * This primitive intentionally has no caller outside the explicit merge
	 * paths. Detection and source-url collision routing are added separately.
	 *
	 * @since 8.3.0
	 * @param string $survivor_key Preferred survivor key.
	 * @param string $absorbed_key  Preferred absorbed key.
	 * @param array  $args          Optional test-only failure injection arguments.
	 * @return array Merge result.
	 */
	public function merge_podcast_records( $survivor_key, $absorbed_key, $args = array() ) {
		$lock_keys = array( $survivor_key, $absorbed_key );
		$token     = $this->acquire_record_lock( $lock_keys );
		if ( ! $token ) {
			return array(
				'status' => 'locked',
			);
		}

		try {
			$register = $this->get_register();
			$survivor = $this->get_register_index( $register, $survivor_key );
			$absorbed = $this->get_register_index( $register, $absorbed_key );
			if ( ! $survivor instanceof StorageRegister || ! $absorbed instanceof StorageRegister ) {
				return array(
					'status' => 'missing',
				);
			}

			if ( $survivor->get( 'unique_id', 'none' ) === $absorbed->get( 'unique_id', 'none' ) ) {
				return array(
					'status'       => 'noop',
					'survivor_key' => $survivor->get( 'unique_id', 'none' ),
				);
			}

			// Defensive: if either record is already a tombstone (e.g. a stale
			// pending pair from a partially-merged cluster), do not merge again.
			if ( $this->is_tombstoned( $survivor ) || $this->is_tombstoned( $absorbed ) ) {
				$live = $this->is_tombstoned( $survivor ) ? $absorbed : $survivor;
				return array(
					'status'       => 'noop',
					'survivor_key' => $live->get( 'unique_id', 'none' ),
				);
			}

			list( $survivor, $absorbed ) = $this->select_merge_survivor( $survivor, $absorbed );
			$survivor_id = $survivor->get( 'unique_id', 'none' );
			$absorbed_id  = $absorbed->get( 'unique_id', 'none' );
			$survivor_feed = get_post_meta( $survivor->get( 'object_id', 'none' ), 'feed_data', true );

			if ( ! $survivor_feed instanceof FeedData || $this->get_identity_version() > absint( $survivor_feed->get( 'identity_version', 'none' ) ) ) {
				$this->upsert_pending_merge( $survivor_id, $absorbed_id );
				return array(
					'status'       => 'deferred',
					'survivor_key' => $survivor_id,
					'absorbed_key' => $absorbed_id,
				);
			}

			return $this->complete_podcast_merge( $register, $survivor, $absorbed, $survivor_feed, $args );
		} finally {
			$this->release_record_lock( $lock_keys, $token );
		}
	}

	/**
	 * Select a deterministic survivor without depending on register order.
	 *
	 * Data-bearing records win first, followed by identity version, item count,
	 * and finally lexical unique ID order.
	 *
	 * @since 8.3.0
	 * @param StorageRegister $first  First candidate.
	 * @param StorageRegister $second Second candidate.
	 * @return array
	 */
	private function select_merge_survivor( $first, $second ) {
		$first_score  = $this->get_merge_record_score( $first );
		$second_score = $this->get_merge_record_score( $second );

		foreach ( array( 'data', 'identity', 'items' ) as $key ) {
			if ( $first_score[ $key ] > $second_score[ $key ] ) {
				return array( $first, $second );
			}
			if ( $second_score[ $key ] > $first_score[ $key ] ) {
				return array( $second, $first );
			}
		}

		if ( strcmp( $first->get( 'unique_id', 'none' ), $second->get( 'unique_id', 'none' ) ) <= 0 ) {
			return array( $first, $second );
		}
		return array( $second, $first );
	}

	/**
	 * Get the deterministic record score used for survivor selection.
	 *
	 * @since 8.3.0
	 * @param StorageRegister $index Register entry.
	 * @return array
	 */
	private function get_merge_record_score( $index ) {
		$object_id = $index->get( 'object_id', 'none' );
		$feed      = get_post_meta( $object_id, 'feed_data', true );
		$modified  = get_post_meta( $object_id, 'modified_feed_data', true );
		$data      = 0;
		$items     = 0;
		$identity  = 0;

		if ( $feed instanceof FeedData ) {
			$data++;
			$feed_items = $feed->get( 'items', 'none' );
			$items      = is_array( $feed_items ) ? count( $feed_items ) : 0;
			$identity   = absint( $feed->get( 'identity_version', 'none' ) );
		}
		if ( $modified instanceof FeedData ) {
			$data = 1;
		}
		if ( $feed instanceof FeedData || ! empty( $this->get_imported_post_ids( $index ) ) ) {
			$data = 1;
		}

		return array(
			'data'     => $data,
			'identity' => $identity,
			'items'    => $items,
		);
	}

	/**
	 * Complete a merge after the pair lock and identity gate are satisfied.
	 *
	 * @since 8.3.0
	 * @param array           $register      Current register.
	 * @param StorageRegister $survivor      Selected survivor.
	 * @param StorageRegister $absorbed      Selected absorbed record.
	 * @param FeedData        $survivor_feed Current survivor feed data.
	 * @param array           $args          Optional failure injection arguments.
	 * @return array
	 */
	private function complete_podcast_merge( $register, $survivor, $absorbed, $survivor_feed, $args ) {
		$survivor_id       = $survivor->get( 'unique_id', 'none' );
		$absorbed_id        = $absorbed->get( 'unique_id', 'none' );
		$survivor_object_id = $survivor->get( 'object_id', 'none' );
		$absorbed_object_id = $absorbed->get( 'object_id', 'none' );
		$absorbed_feed      = get_post_meta( $absorbed_object_id, 'feed_data', true );
		$register_snapshot  = $this->copy_merge_value( $register );
		$absorbed_snapshot  = $this->copy_merge_value( $absorbed );
		$pending_snapshot   = $this->copy_merge_value( get_option( 'pp_pending_merges', false ) );
		$meta_snapshot      = array(
			'survivor_feed'     => $this->snapshot_merge_meta( $survivor_object_id, 'feed_data' ),
			'survivor_modified' => $this->snapshot_merge_meta( $survivor_object_id, 'modified_feed_data' ),
			'survivor_log'      => $this->snapshot_merge_meta( $survivor_object_id, 'pp_merge_log' ),
			'absorbed_feed'     => $this->snapshot_merge_meta( $absorbed_object_id, 'feed_data' ),
			'absorbed_modified' => $this->snapshot_merge_meta( $absorbed_object_id, 'modified_feed_data' ),
		);
		$status_snapshot = array(
			$survivor_object_id => get_post_status( $survivor_object_id ),
			$absorbed_object_id => get_post_status( $absorbed_object_id ),
		);

		try {
			// Resolve both post sets before any register write, while the absorbed
			// record still owns its imported posts.
			$survivor_posts = $this->get_imported_post_ids( $survivor );
			$absorbed_posts = $this->get_imported_post_ids( $absorbed );

			list( $merged_feed, $bridges ) = $this->merge_feed_items( $survivor_feed, $absorbed_feed );
			$bridges         = $this->bridge_imported_posts_by_episode_id(
				$merged_feed,
				array_values( array_unique( array_merge( $survivor_posts, $absorbed_posts ) ) ),
				$bridges
			);
			$merged_modified = $this->merge_modified_feed_data(
				get_post_meta( $survivor_object_id, 'modified_feed_data', true ),
				get_post_meta( $absorbed_object_id, 'modified_feed_data', true ),
				$bridges
			);
			$merged_modified = $this->restore_imported_post_links(
				$merged_feed,
				$merged_modified,
				array_values( array_unique( array_merge( $survivor_posts, $absorbed_posts ) ) ),
				$bridges
			);
			$this->merge_register_aliases( $survivor, $absorbed, $args );
			$absorbed->set( 'is_tombstoned', true );
			$register[ $survivor_id ] = $survivor;
			$register[ $absorbed_id ] = $absorbed;

			update_option( 'pp-register', $register, false );
			$this->maybe_inject_merge_failure( $args, 'register' );

			update_post_meta( $survivor_object_id, 'feed_data', $merged_feed );
			$this->maybe_inject_merge_failure( $args, 'feed_data' );

			if ( $merged_modified instanceof FeedData ) {
				update_post_meta( $survivor_object_id, 'modified_feed_data', $merged_modified );
				$this->maybe_inject_merge_failure( $args, 'modified_feed_data' );
			}

			$meta_snapshot['imported_posts'] = array();
			$this->rekey_imported_post_data( $survivor_posts, $absorbed_posts, $survivor_id, $bridges, $args, $meta_snapshot['imported_posts'] );

			$status_result = wp_update_post(
				array(
					'ID'          => $absorbed_object_id,
					'post_status' => 'draft',
				),
				true
			);
			if ( is_wp_error( $status_result ) ) {
				throw new \RuntimeException( $status_result->get_error_message() );
			}
			$this->maybe_inject_merge_failure( $args, 'absorbed_post_status' );

			$merge_log = get_post_meta( $survivor_object_id, 'pp_merge_log', true );
			$merge_log = is_array( $merge_log ) ? $merge_log : array();
			$merge_log[] = array(
				'absorbed_register' => $absorbed_snapshot,
				'object_id'         => $absorbed_object_id,
				'meta'              => $meta_snapshot,
				'timestamp'         => time(),
			);
			update_post_meta( $survivor_object_id, 'pp_merge_log', $merge_log );
			$this->maybe_inject_merge_failure( $args, 'pp_merge_log' );

			$this->remove_pending_merge( $survivor_id, $absorbed_id );
			return array(
				'status'       => 'merged',
				'survivor_key' => $survivor_id,
				'absorbed_key' => $absorbed_id,
				'bridges'      => $bridges,
			);
		} catch ( \Throwable $error ) {
			$this->restore_merge_state( $register_snapshot, $meta_snapshot, $status_snapshot, $pending_snapshot );
			return array(
				'status'       => 'error',
				'survivor_key' => $survivor_id,
				'absorbed_key' => $absorbed_id,
				'error'        => $error->getMessage(),
			);
		}
	}

	/**
	 * Merge active feed items and produce absorbed-key to survivor-UID bridges.
	 *
	 * @since 8.3.0
	 * @param FeedData       $survivor_feed Survivor feed data.
	 * @param FeedData|mixed $absorbed_feed Absorbed feed data.
	 * @return array
	 */
	private function merge_feed_items( $survivor_feed, $absorbed_feed ) {
		$items       = $survivor_feed->get( 'items', 'none' );
		$items       = is_array( $items ) ? $items : array();
		$identity_map = $survivor_feed->get( 'identity_map', 'none' );
		$identity_map = is_array( $identity_map ) ? $identity_map : array();
		$legacy_keys  = $survivor_feed->get( 'legacy_key_by_uid', 'none' );
		$legacy_keys  = is_array( $legacy_keys ) ? $legacy_keys : array();
		$strong_ids   = array();

		foreach ( $items as $key => $item ) {
			$episode_id  = $this->get_merge_item_value( $item, 'episode_id' );
			$episode_uid = $this->get_merge_item_value( $item, 'episode_uid' );
			$reliability = $this->get_merge_item_value( $item, 'identity_reliability' );
			if ( $episode_id && $episode_uid && 'strong' === $reliability && ! isset( $strong_ids[ $episode_id ] ) ) {
				$strong_ids[ $episode_id ] = $episode_uid;
			}
		}

		$bridges   = array();
		$translate = array();
		if ( $absorbed_feed instanceof FeedData ) {
			$absorbed_items = $absorbed_feed->get( 'items', 'none' );
			$absorbed_items = is_array( $absorbed_items ) ? $absorbed_items : array();
			foreach ( $absorbed_items as $key => $item ) {
				$episode_id   = $this->get_merge_item_value( $item, 'episode_id' );
				$absorbed_uid = $this->get_merge_item_value( $item, 'episode_uid' );

				if ( $episode_id && isset( $strong_ids[ $episode_id ] ) ) {
					$episode_uid     = $strong_ids[ $episode_id ];
					$bridges[ $key ] = $episode_uid;

					// Identical GUIDs produce identical UIDs, so for a plain
					// duplicate $key already IS $episode_uid and there is nothing
					// to bridge. add_identity_map_entry() rejects that self-write,
					// rejects overwriting an existing mapping, and rejects bridging
					// a key that is a live item with a different UID.
					Episode_Resolver::add_identity_map_entry( $identity_map, $key, $episode_uid, $items );

					// A retained legacy key is durable identity: never replace one.
					if ( ! isset( $legacy_keys[ $episode_uid ] ) ) {
						$legacy_keys[ $episode_uid ] = $key;
					}

					$translate[ $key ] = $episode_uid;
					if ( $absorbed_uid ) {
						$translate[ $absorbed_uid ] = $episode_uid;
					}
					continue;
				}

				$retained_key           = $this->get_retained_merge_item_key( $items, $key );
				$items[ $retained_key ] = $item;

				$translate[ $key ] = $retained_key;
				if ( $absorbed_uid ) {
					$translate[ $absorbed_uid ] = $retained_key;
				}
			}

			$this->merge_identity_tables( $absorbed_feed, $translate, $items, $identity_map, $legacy_keys );
		}

		$survivor_feed->set( 'items', $items, 'none' );
		$survivor_feed->set( 'identity_map', $identity_map, 'none' );
		$survivor_feed->set( 'legacy_key_by_uid', $legacy_keys, 'none' );
		return array( $survivor_feed, $bridges );
	}

	/**
	 * Fold the absorbed record's identity tables into the survivor's.
	 *
	 * Without this the absorbed record's own legacy bridges are abandoned on the
	 * drafted post, so a reference saved against it stops resolving. The survivor
	 * is authoritative throughout: every write is guarded, so this can only add.
	 *
	 * Absorbed table values are episode UIDs, while the loop keys its results by
	 * an item's storage key — not the same thing for a record still keyed by audio
	 * hash — so $translate carries both forms. A value that cannot be translated
	 * is left pointing at the absorbed UID, which is the normal shape for an
	 * episode that has aged out of the active items.
	 *
	 * @since 8.3.0
	 * @param FeedData $absorbed_feed Absorbed feed data.
	 * @param array    $translate     Absorbed storage key or UID to merged item key.
	 * @param array    $items         Merged items, for the live-item guard.
	 * @param array    $identity_map  Survivor identity map, updated in place.
	 * @param array    $legacy_keys   Survivor retained keys, updated in place.
	 */
	private function merge_identity_tables( $absorbed_feed, $translate, $items, &$identity_map, &$legacy_keys ) {
		$absorbed_map    = $absorbed_feed->get( 'identity_map', 'none' );
		$absorbed_legacy = $absorbed_feed->get( 'legacy_key_by_uid', 'none' );

		foreach ( (array) $absorbed_map as $legacy => $uid ) {
			$target = isset( $translate[ $uid ] ) ? $translate[ $uid ] : $uid;
			Episode_Resolver::add_identity_map_entry( $identity_map, $legacy, $target, $items );
		}

		foreach ( (array) $absorbed_legacy as $uid => $legacy ) {
			$target = isset( $translate[ $uid ] ) ? $translate[ $uid ] : $uid;
			if ( $legacy && $target && $legacy !== $target && ! isset( $legacy_keys[ $target ] ) ) {
				$legacy_keys[ $target ] = $legacy;
			}
		}
	}

	/**
	 * Merge absorbed custom item data into the survivor custom-data record.
	 *
	 * @since 8.3.0
	 * @param FeedData|mixed $survivor_modified Survivor custom data.
	 * @param FeedData|mixed $absorbed_modified Absorbed custom data.
	 * @param array          $bridges           Legacy-key to UID bridges.
	 * @return FeedData|false
	 */
	private function merge_modified_feed_data( $survivor_modified, $absorbed_modified, $bridges ) {
		if ( ! $absorbed_modified instanceof FeedData ) {
			return $survivor_modified instanceof FeedData ? $survivor_modified : false;
		}

		$merged = $survivor_modified instanceof FeedData ? $survivor_modified : new FeedData();
		$items  = $merged->get( 'items', 'none' );
		$items  = is_array( $items ) ? $items : array();
		$absorbed_items = $absorbed_modified->get( 'items', 'none' );
		$absorbed_items = is_array( $absorbed_items ) ? $absorbed_items : array();

		foreach ( $absorbed_items as $key => $item ) {
			$target_key = isset( $bridges[ $key ] ) ? $bridges[ $key ] : $key;
			if ( ! isset( $items[ $target_key ] ) ) {
				$items[ $target_key ] = $item;
				continue;
			}

			// Both sides customised the same episode. Discarding the absorbed
			// entry wholesale used to drop everything it held — including the
			// post_id that links an imported episode back to its post, which is
			// stored nowhere else and could never be recovered.
			$items[ $target_key ] = $this->merge_custom_item( $items[ $target_key ], $item );
		}

		$merged->set( 'items', $items, 'none' );
		return $merged;
	}

	/**
	 * Fold one custom-data entry into another, survivor first.
	 *
	 * Per-field rather than wholesale: the survivor keeps everything it actually
	 * set, and the absorbed entry only fills fields the survivor left empty. A
	 * plain array_replace would not do — the survivor frequently carries an
	 * explicit `post_id => 0`, which is a *present* value and would overwrite a
	 * real post ID from the absorbed side. "Empty means unset" matches how
	 * Modify_Feed_Data already applies these fields (it array_filter()s them).
	 *
	 * @since 8.3.0
	 * @param ItemData|mixed $survivor_item Survivor custom entry.
	 * @param ItemData|mixed $absorbed_item Absorbed custom entry.
	 * @return ItemData|mixed
	 */
	private function merge_custom_item( $survivor_item, $absorbed_item ) {
		if ( ! $absorbed_item instanceof ItemData ) {
			return $survivor_item;
		}
		if ( ! $survivor_item instanceof ItemData ) {
			return $absorbed_item;
		}

		$fields = array( 'title', 'description', 'author', 'featured', 'featured_id', 'episode', 'categories', 'season', 'post_id' );
		foreach ( $fields as $field ) {
			if ( ! empty( $survivor_item->get( $field, 'none' ) ) ) {
				continue;
			}
			$value = $absorbed_item->get( $field, 'none' );
			if ( empty( $value ) ) {
				continue;
			}
			$survivor_item->set( $field, $value, 'none' );
		}

		return $survivor_item;
	}

	/**
	 * Rebuild the feed-side link from each imported post back to its episode.
	 *
	 * `post_id` on a custom entry is the only thing that puts an imported episode
	 * in the admin list, and it lives nowhere else in the feed record — so losing
	 * it orphans the post permanently, and no later refetch restores it. The post
	 * itself still records which episode it came from, so treat that as the
	 * source of truth and repair the feed side from it.
	 *
	 * Only ever fills an empty slot: an episode already claimed by another post
	 * keeps its existing link rather than being silently reassigned.
	 *
	 * @since 8.3.0
	 * @param FeedData       $feed     Merged survivor feed data.
	 * @param FeedData|mixed $modified Merged custom data.
	 * @param array          $post_ids Imported post IDs from both records.
	 * @param array          $bridges  Legacy-key to UID bridges.
	 * @return FeedData|mixed
	 */
	private function restore_imported_post_links( $feed, $modified, $post_ids, $bridges ) {
		if ( empty( $post_ids ) ) {
			return $modified;
		}

		$items = $feed->get( 'items', 'none' );
		$items = is_array( $items ) ? $items : array();
		if ( empty( $items ) ) {
			return $modified;
		}

		$identity_map = $feed->get( 'identity_map', 'none' );
		$identity_map = is_array( $identity_map ) ? $identity_map : array();
		$custom       = $modified instanceof FeedData ? $modified->get( 'items', 'none' ) : array();
		$custom       = is_array( $custom ) ? $custom : array();
		$changed      = false;

		foreach ( $post_ids as $post_id ) {
			$import = get_post_meta( $post_id, 'pp_import_data', true );
			if ( ! is_array( $import ) ) {
				continue;
			}

			// Resolve the live episode this post was imported from, preferring
			// its UID over the legacy key it was first stored against.
			$uid = '';
			foreach ( array( 'episode_uid', 'episode' ) as $field ) {
				$ref = isset( $import[ $field ] ) ? (string) $import[ $field ] : '';
				if ( '' === $ref ) {
					continue;
				}
				if ( isset( $items[ $ref ] ) ) {
					$uid = $ref;
					break;
				}
				if ( isset( $bridges[ $ref ] ) && isset( $items[ $bridges[ $ref ] ] ) ) {
					$uid = $bridges[ $ref ];
					break;
				}
				if ( isset( $identity_map[ $ref ] ) && isset( $items[ $identity_map[ $ref ] ] ) ) {
					$uid = $identity_map[ $ref ];
					break;
				}
			}

			if ( '' === $uid ) {
				continue;
			}

			$entry = isset( $custom[ $uid ] ) ? $custom[ $uid ] : null;
			if ( $entry instanceof ItemData && (int) $entry->get( 'post_id', 'none' ) ) {
				continue;
			}
			if ( ! $entry instanceof ItemData ) {
				$entry = new ItemData();
			}
			$entry->set( 'post_id', (int) $post_id, 'none' );
			$custom[ $uid ] = $entry;
			$changed        = true;
		}

		if ( ! $changed ) {
			return $modified;
		}
		if ( ! $modified instanceof FeedData ) {
			$modified = new FeedData();
		}
		$modified->set( 'items', $custom, 'none' );

		return $modified;
	}

	/**
	 * Add every absorbed URL/key as a survivor alias.
	 *
	 * @since 8.3.0
	 * @param StorageRegister $survivor Survivor register entry.
	 * @param StorageRegister $absorbed Absorbed register entry.
	 */
	private function merge_register_aliases( $survivor, $absorbed, $args = array() ) {
		$survivor_urls = $survivor->get( 'feed_url', 'none' );
		$absorbed_urls = $absorbed->get( 'feed_url', 'none' );
		$survivor_urls = is_array( $survivor_urls ) ? $survivor_urls : array();
		$absorbed_urls = is_array( $absorbed_urls ) ? $absorbed_urls : array();
		$urls          = array_values( array_unique( array_filter( array_merge( $survivor_urls, $absorbed_urls ) ) ) );
		$object_keys   = array_merge(
			(array) $survivor->get( 'object_keys', 'none' ),
			(array) $absorbed->get( 'object_keys', 'none' ),
			array_map( 'md5', $urls ),
			array( $absorbed->get( 'unique_id', 'none' ) )
		);

		$survivor->set( 'feed_url', $urls );
		$survivor->set( 'object_keys', array_values( array_unique( array_filter( $object_keys ) ) ) );
		if ( ! empty( $args['source_url'] ) && Validation_Fn::is_valid_url( $args['source_url'] ) ) {
			$survivor->set( 'source_url', esc_url_raw( $args['source_url'] ) );
		} elseif ( ! $survivor->get( 'source_url', 'none' ) && $absorbed->get( 'source_url', 'none' ) ) {
			$survivor->set( 'source_url', $absorbed->get( 'source_url', 'none' ) );
		}
	}

	/**
	 * Reconnect imported episodes stranded by an earlier source-URL switch.
	 *
	 * Before the UID migration an imported episode stored a legacy audio-hash key.
	 * Switching a record's source URL dropped its feed_data, and the refetch came
	 * back from a different host with entirely new audio URLs, so that key ends up
	 * in neither the live items nor the identity map and the post drops out of the
	 * imported-episodes list. The custom data holding its post_id was never
	 * deleted, and `pp_episode_id` (the RSS GUID) still identifies the episode, so
	 * the bridge can be rebuilt from the GUID. Both records are scanned: the
	 * survivor carries these orphans too, and a merge is the one moment we hold
	 * the lock and know the final item set.
	 *
	 * @since 8.3.0
	 * @param FeedData $feed     Merged survivor feed data, updated in place.
	 * @param array    $post_ids Imported post IDs from both records.
	 * @param array    $bridges  Existing legacy-key to UID bridges.
	 * @return array
	 */
	private function bridge_imported_posts_by_episode_id( $feed, $post_ids, $bridges ) {
		$items = $feed->get( 'items', 'none' );
		$items = is_array( $items ) ? $items : array();
		if ( empty( $items ) || empty( $post_ids ) ) {
			return $bridges;
		}

		$identity_map = $feed->get( 'identity_map', 'none' );
		$identity_map = is_array( $identity_map ) ? $identity_map : array();
		$legacy_keys  = $feed->get( 'legacy_key_by_uid', 'none' );
		$legacy_keys  = is_array( $legacy_keys ) ? $legacy_keys : array();
		$by_episode   = array();

		foreach ( $items as $key => $item ) {
			$episode_id = $this->get_merge_item_value( $item, 'episode_id' );
			if ( ! $episode_id ) {
				continue;
			}
			// A GUID that appears on more than one live item is ambiguous, so
			// mark it unusable rather than guessing which episode an orphaned
			// post belonged to.
			$by_episode[ $episode_id ] = isset( $by_episode[ $episode_id ] ) ? false : $key;
		}

		foreach ( $post_ids as $post_id ) {
			$import = get_post_meta( $post_id, 'pp_import_data', true );
			if ( ! is_array( $import ) ) {
				continue;
			}

			// Already resolvable through its UID: nothing to repair.
			$stored_uid = isset( $import['episode_uid'] ) ? (string) $import['episode_uid'] : '';
			if ( '' !== $stored_uid && isset( $items[ $stored_uid ] ) ) {
				continue;
			}

			$episode_key = isset( $import['episode'] ) ? (string) $import['episode'] : '';
			if ( '' === $episode_key
				|| isset( $items[ $episode_key ] )
				|| isset( $identity_map[ $episode_key ] )
				|| isset( $bridges[ $episode_key ] ) ) {
				continue;
			}

			$episode_id = (string) get_post_meta( $post_id, 'pp_episode_id', true );
			if ( '' === $episode_id || empty( $by_episode[ $episode_id ] ) ) {
				continue;
			}

			$uid                          = $by_episode[ $episode_id ];
			$bridges[ $episode_key ]      = $uid;
			$identity_map[ $episode_key ] = $uid;
			if ( ! isset( $legacy_keys[ $uid ] ) ) {
				$legacy_keys[ $uid ] = $episode_key;
			}
		}

		$feed->set( 'identity_map', $identity_map, 'none' );
		$feed->set( 'legacy_key_by_uid', $legacy_keys, 'none' );
		return $bridges;
	}

	/**
	 * Repoint imported post metadata at the survivor and its resolved episodes.
	 *
	 * Absorbed posts additionally move to the survivor podcast key; survivor posts
	 * are visited so a bridge recovered above is written back to them too.
	 *
	 * @since 8.3.0
	 * @param array  $survivor_posts Imported post IDs owned by the survivor.
	 * @param array  $absorbed_posts Imported post IDs owned by the absorbed record.
	 * @param string $survivor_id    Survivor unique ID.
	 * @param array  $bridges        Legacy-key to UID bridges.
	 * @param array  $args           Optional failure injection arguments.
	 * @param array  $snapshots      Affected post-meta snapshots.
	 */
	private function rekey_imported_post_data( $survivor_posts, $absorbed_posts, $survivor_id, $bridges, $args, &$snapshots ) {
		$snapshots = is_array( $snapshots ) ? $snapshots : array();
		$post_ids  = array_values( array_unique( array_merge( $absorbed_posts, $survivor_posts ) ) );
		$absorbed  = array_flip( $absorbed_posts );

		foreach ( $post_ids as $post_id ) {
			$import = get_post_meta( $post_id, 'pp_import_data', true );
			if ( ! is_array( $import ) ) {
				continue;
			}

			$original = $import;
			if ( isset( $absorbed[ $post_id ] ) ) {
				$import['podkey'] = $survivor_id;
			}
			$episode_key = isset( $import['episode'] ) ? $import['episode'] : '';
			if ( $episode_key && isset( $bridges[ $episode_key ] ) ) {
				$import['episode_uid'] = $bridges[ $episode_key ];
			}

			if ( $import !== $original ) {
				$snapshots[ $post_id ] = $this->snapshot_merge_meta( $post_id, 'pp_import_data' );
				update_post_meta( $post_id, 'pp_import_data', $import );
			}
			$this->maybe_inject_merge_failure( $args, 'pp_import_data' );
		}

	}

	/**
	 * Get imported posts whose stored podkey belongs to a register entry.
	 *
	 * @since 8.3.0
	 * @param StorageRegister $index Register entry.
	 * @return array
	 */
	private function get_imported_post_ids( $index ) {
		$references = array_merge(
			array( $index->get( 'unique_id', 'none' ) ),
			(array) $index->get( 'feed_url', 'none' ),
			(array) $index->get( 'object_keys', 'none' )
		);
		$references = array_values( array_unique( array_filter( $references ) ) );
		$query      = new \WP_Query(
			array(
				'post_type'              => 'any',
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'meta_key'               => 'pp_import_data',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
			)
		);
		$post_ids = array();

		foreach ( $query->posts as $post_id ) {
			$import = get_post_meta( $post_id, 'pp_import_data', true );
			if ( is_array( $import ) && isset( $import['podkey'] ) && in_array( $import['podkey'], $references, true ) ) {
				$post_ids[] = $post_id;
			}
		}

		return $post_ids;
	}

	/**
	 * Restore every mutated record, meta value and pending option after failure.
	 *
	 * @since 8.3.0
	 * @param array $register_snapshot Register snapshot.
	 * @param array $meta_snapshot     Meta snapshots.
	 * @param array $status_snapshot   Post-status snapshots.
	 * @param mixed $pending_snapshot  Pending-merge option snapshot.
	 */
	private function restore_merge_state( $register_snapshot, $meta_snapshot, $status_snapshot, $pending_snapshot ) {
		update_option( 'pp-register', $register_snapshot, false );
		foreach ( $meta_snapshot as $key => $snapshot ) {
			if ( 'imported_posts' === $key ) {
				foreach ( $snapshot as $post_id => $post_snapshot ) {
					$this->restore_merge_meta( $post_id, 'pp_import_data', $post_snapshot );
				}
				continue;
			}
			if ( ! isset( $snapshot['post_id'], $snapshot['meta_key'] ) ) {
				continue;
			}
			$this->restore_merge_meta( $snapshot['post_id'], $snapshot['meta_key'], $snapshot );
		}
		foreach ( $status_snapshot as $post_id => $status ) {
			if ( $status ) {
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => $status,
					)
				);
			}
		}
		if ( false === $pending_snapshot ) {
			delete_option( 'pp_pending_merges' );
		} else {
			update_option( 'pp_pending_merges', $pending_snapshot, false );
		}
	}

	/**
	 * Store one canonical deferred pair without creating duplicates.
	 *
	 * @since 8.3.0
	 * @param string $survivor_id Survivor unique ID.
	 * @param string $absorbed_id  Absorbed unique ID.
	 */
	private function upsert_pending_merge( $survivor_id, $absorbed_id ) {
		$key     = $this->get_pending_merge_key( $survivor_id, $absorbed_id );
		$pending = get_option( 'pp_pending_merges', array() );
		$pending = is_array( $pending ) ? $pending : array();
		if ( ! isset( $pending[ $key ] ) ) {
			$pending[ $key ] = array(
				'survivor_key' => $survivor_id,
				'absorbed_key' => $absorbed_id,
				'created_at'   => time(),
			);
			update_option( 'pp_pending_merges', $pending, false );
		}
	}

	/**
	 * Remove the canonical deferred pair once the merge succeeds.
	 *
	 * @since 8.3.0
	 * @param string $survivor_id Survivor unique ID.
	 * @param string $absorbed_id  Absorbed unique ID.
	 */
	private function remove_pending_merge( $survivor_id, $absorbed_id ) {
		$key     = $this->get_pending_merge_key( $survivor_id, $absorbed_id );
		$pending = get_option( 'pp_pending_merges', array() );
		$pending = is_array( $pending ) ? $pending : array();
		if ( isset( $pending[ $key ] ) ) {
			unset( $pending[ $key ] );
			update_option( 'pp_pending_merges', $pending, false );
		}
	}

	/**
	 * Build an order-independent pending-merge key.
	 *
	 * @since 8.3.0
	 * @param string $first  First unique ID.
	 * @param string $second Second unique ID.
	 * @return string
	 */
	private function get_pending_merge_key( $first, $second ) {
		$keys = array( (string) $first, (string) $second );
		sort( $keys, SORT_STRING );
		return implode( ':', $keys );
	}

	/**
	 * Find legacy duplicate podcast records that share a resolvable URL.
	 *
	 * The reverse-collision bug left two live records claiming the same feed or
	 * source URL. Records that share a normalized URL are returned as canonical
	 * pairs. Title/author-only matches are deliberately excluded to avoid
	 * proposing merges of genuinely different podcasts.
	 *
	 * @since 8.3.0
	 * @return array Map of canonical pair key => array( uidA, uidB ).
	 */
	public function find_duplicate_podcasts() {
		$register = $this->get_register();
		$claims   = array();

		foreach ( $register as $uid => $record ) {
			if ( ! $record instanceof StorageRegister || $this->is_tombstoned( $record ) ) {
				continue;
			}
			$urls = array();
			$src  = $record->get( 'source_url', 'none' );
			if ( $src ) {
				$urls[] = $src;
			}
			$furls = $record->get( 'feed_url', 'none' );
			if ( is_array( $furls ) ) {
				$urls = array_merge( $urls, $furls );
			}
			foreach ( array_unique( array_filter( $urls ) ) as $url ) {
				$claims[ md5( (string) $url ) ][ $uid ] = true;
			}
		}

		$pairs = array();
		foreach ( $claims as $owners ) {
			$uids = array_keys( $owners );
			if ( count( $uids ) < 2 ) {
				continue;
			}
			// Star pairs (anchor -> each other member): linear, and enough to
			// collapse the whole cluster once merges remap absorbed -> survivor.
			$anchor = $uids[0];
			for ( $i = 1, $n = count( $uids ); $i < $n; $i++ ) {
				$key           = $this->get_pending_merge_key( $anchor, $uids[ $i ] );
				$pairs[ $key ] = array( $anchor, $uids[ $i ] );
			}
		}

		return $pairs;
	}

	/**
	 * After a merge, rewrite pending pairs so none reference the absorbed record.
	 *
	 * Any pair naming the absorbed uid is re-pointed at the survivor; pairs that
	 * collapse to the same record are dropped. This keeps the queue free of stale
	 * pairs that would otherwise target a tombstoned record.
	 *
	 * @since 8.3.0
	 * @param string $absorbed_uid Absorbed record unique ID.
	 * @param string $survivor_uid Surviving record unique ID.
	 */
	public function remap_pending_after_merge( $absorbed_uid, $survivor_uid ) {
		$pending = $this->get_pending_merges();
		if ( empty( $pending ) || ! $absorbed_uid || ! $survivor_uid ) {
			return;
		}
		$next = array();
		foreach ( $pending as $pair_key => $pair ) {
			$s = isset( $pair['survivor_key'] ) ? $pair['survivor_key'] : '';
			$a = isset( $pair['absorbed_key'] ) ? $pair['absorbed_key'] : '';
			if ( $s === $absorbed_uid ) {
				$s = $survivor_uid;
			}
			if ( $a === $absorbed_uid ) {
				$a = $survivor_uid;
			}
			if ( ! $s || ! $a || $s === $a ) {
				continue; // fully resolved by this merge.
			}
			$new_key                  = $this->get_pending_merge_key( $s, $a );
			$pair['survivor_key']     = $s;
			$pair['absorbed_key']     = $a;
			$next[ $new_key ]         = $pair;
		}
		update_option( 'pp_pending_merges', $next, false );
	}

	/**
	 * Queue newly-detected duplicate pairs for admin review (idempotent).
	 *
	 * Pairs already pending or previously dismissed are skipped, so re-running
	 * never creates a second copy of the same pair.
	 *
	 * @since 8.3.0
	 * @return bool True when the scan completed and any new pending pairs were saved.
	 */
	public function run_duplicate_scan() {
		$pairs     = $this->find_duplicate_podcasts();
		$pending   = get_option( 'pp_pending_merges', array() );
		$pending   = is_array( $pending ) ? $pending : array();
		$dismissed = get_option( 'pp_dismissed_merges', array() );
		$dismissed = is_array( $dismissed ) ? $dismissed : array();
		$changed   = false;

		$new_keys = array();
		foreach ( $pairs as $pair_key => $pair ) {
			if ( isset( $pending[ $pair_key ] ) || isset( $dismissed[ $pair_key ] ) ) {
				continue;
			}
			$pending[ $pair_key ] = array(
				'survivor_key' => $pair[0],
				'absorbed_key' => $pair[1],
				'created_at'   => time(),
				'source'       => 'scan',
			);
			$new_keys[] = $pair_key;
			$changed    = true;
		}

		if ( $changed ) {
			update_option( 'pp_pending_merges', $pending, false );
			// Confirm the write persisted before the caller marks the scan done.
			$verify = get_option( 'pp_pending_merges', array() );
			foreach ( $new_keys as $key ) {
				if ( ! is_array( $verify ) || ! isset( $verify[ $key ] ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Ensure the one-time duplicate scan has completed successfully.
	 *
	 * A short claim lock prevents two admin loads from scanning at once. Failures
	 * leave the version unchanged so a later admin page load can retry.
	 *
	 * @since 8.3.0
	 * @return bool True when the scan is already complete or completed now.
	 */
	public function ensure_duplicate_scan_complete() {
		if ( (int) get_option( 'pp_dupe_scan_version', 0 ) >= self::DUPE_SCAN_VERSION ) {
			return true;
		}
		$lock = $this->acquire_record_lock( 'pp_dupe_scan_lock', 300 );
		if ( ! $lock ) {
			return false;
		}
		try {
			if ( (int) get_option( 'pp_dupe_scan_version', 0 ) >= self::DUPE_SCAN_VERSION ) {
				return true;
			}
			// Advance the one-time version only when the scan confirms its writes.
			if ( ! $this->run_duplicate_scan() ) {
				return false;
			}
			update_option( 'pp_dupe_scan_version', self::DUPE_SCAN_VERSION, false );
			$complete = (int) get_option( 'pp_dupe_scan_version', 0 ) >= self::DUPE_SCAN_VERSION;
			if ( $complete ) {
				wp_clear_scheduled_hook( 'pp_run_duplicate_scan' );
			}
			return $complete;
		} catch ( \Exception $e ) {
			return false;
		} finally {
			$this->release_record_lock( 'pp_dupe_scan_lock', $lock );
		}
	}

	/**
	 * Run the one-time duplicate scan.
	 *
	 * Retained for compatibility with existing tests or stale scheduled events.
	 *
	 * @since 8.3.0
	 * @return bool True when the scan is already complete or completed now.
	 */
	public function run_scheduled_duplicate_scan() {
		return $this->ensure_duplicate_scan_complete();
	}

	/**
	 * Get the pending duplicate/migration merges awaiting admin review.
	 *
	 * @since 8.3.0
	 * @return array
	 */
	public function get_pending_merges() {
		$pending = get_option( 'pp_pending_merges', array() );
		return is_array( $pending ) ? $pending : array();
	}

	/**
	 * Get pending duplicate merge groups for admin display.
	 *
	 * Pending pairs are stored pair-by-pair, but the admin UI is easier to review
	 * when connected pairs are shown as one stack. Invalid/stale pairs are left in
	 * storage for the existing resolver to handle, but omitted from display.
	 *
	 * @since 8.3.0
	 * @return array
	 */
	public function get_pending_merge_groups() {
		$pending = $this->get_pending_merges();
		if ( empty( $pending ) ) {
			return array();
		}

		$parents = array();
		$records = array();
		$pairs   = array();

		$find = function( $key ) use ( &$parents, &$find ) {
			if ( ! isset( $parents[ $key ] ) ) {
				$parents[ $key ] = $key;
			}
			if ( $parents[ $key ] !== $key ) {
				$parents[ $key ] = $find( $parents[ $key ] );
			}
			return $parents[ $key ];
		};

		$union = function( $first, $second ) use ( &$parents, $find ) {
			$first_root  = $find( $first );
			$second_root = $find( $second );
			if ( $first_root !== $second_root ) {
				$parents[ $second_root ] = $first_root;
			}
		};

		foreach ( $pending as $pair_key => $pair ) {
			$survivor_key = isset( $pair['survivor_key'] ) ? (string) $pair['survivor_key'] : '';
			$absorbed_key = isset( $pair['absorbed_key'] ) ? (string) $pair['absorbed_key'] : '';
			if ( '' === $survivor_key || '' === $absorbed_key ) {
				continue;
			}

			$survivor = $this->get_object_index( $survivor_key );
			$absorbed = $this->get_object_index( $absorbed_key );
			if ( ! $survivor instanceof StorageRegister || ! $absorbed instanceof StorageRegister ) {
				continue;
			}

			$records[ $survivor_key ] = $survivor;
			$records[ $absorbed_key ] = $absorbed;
			$pairs[ $pair_key ]       = array( $survivor_key, $absorbed_key );
			$union( $survivor_key, $absorbed_key );
		}

		$groups = array();
		foreach ( $pairs as $pair_key => $pair ) {
			$root = $find( $pair[0] );
			if ( ! isset( $groups[ $root ] ) ) {
				$groups[ $root ] = array(
					'id'          => $root,
					'member_keys' => array(),
					'pair_keys'   => array(),
					'records'     => array(),
					'has_hidden'  => false,
				);
			}

			$groups[ $root ]['pair_keys'][] = $pair_key;
			foreach ( $pair as $member_key ) {
				if ( isset( $groups[ $root ]['records'][ $member_key ] ) || ! isset( $records[ $member_key ] ) ) {
					continue;
				}
				$groups[ $root ]['member_keys'][]         = $member_key;
				$groups[ $root ]['records'][ $member_key ] = $records[ $member_key ];
				if ( $records[ $member_key ]->get( 'is_hidden' ) ) {
					$groups[ $root ]['has_hidden'] = true;
				}
			}
		}

		return array_values( $groups );
	}

	/**
	 * Get current pending pairs whose members are both in a submitted group.
	 *
	 * @since 8.3.0
	 * @param array $member_keys Duplicate group member keys.
	 * @return array
	 */
	public function get_pending_merge_pairs_for_members( $member_keys ) {
		$member_keys = array_filter( array_map( 'strval', (array) $member_keys ) );
		if ( count( $member_keys ) < 2 ) {
			return array();
		}

		$members = array_fill_keys( $member_keys, true );
		$pairs   = array();
		foreach ( $this->get_pending_merges() as $pair_key => $pair ) {
			$survivor_key = isset( $pair['survivor_key'] ) ? (string) $pair['survivor_key'] : '';
			$absorbed_key = isset( $pair['absorbed_key'] ) ? (string) $pair['absorbed_key'] : '';
			if ( isset( $members[ $survivor_key ], $members[ $absorbed_key ] ) ) {
				$pairs[ $pair_key ] = $pair;
			}
		}

		return $pairs;
	}

	/**
	 * Dismiss a pending duplicate pair so the scan will not re-queue it.
	 *
	 * @since 8.3.0
	 * @param string $pair_key Canonical pending-pair key.
	 * @return bool
	 */
	public function dismiss_pending_merge( $pair_key ) {
		$pending = $this->get_pending_merges();
		$dismissed = get_option( 'pp_dismissed_merges', array() );
		$dismissed = is_array( $dismissed ) ? $dismissed : array();

		if ( isset( $pending[ $pair_key ] ) ) {
			unset( $pending[ $pair_key ] );
			update_option( 'pp_pending_merges', $pending, false );
		}
		$dismissed[ $pair_key ] = time();
		update_option( 'pp_dismissed_merges', $dismissed, false );
		return true;
	}

	/**
	 * Whether any migration work (pending duplicate pairs) awaits the admin.
	 *
	 * @since 8.3.0
	 * @return bool
	 */
	public function has_pending_merges() {
		return ! empty( $this->get_pending_merges() );
	}

	/**
	 * Get live records that carry an undismissed host-migration candidate.
	 *
	 * @since 8.3.0
	 * @return array Map of record unique_id => candidate data.
	 */
	public function get_migration_candidates() {
		$out = array();
		foreach ( $this->get_register() as $record ) {
			if ( ! $record instanceof StorageRegister || $this->is_tombstoned( $record ) ) {
				continue;
			}
			$key       = $record->get( 'unique_id', 'none' );
			$candidate = $this->get_data( $key, 'pp_migration_candidate' );
			if ( is_array( $candidate ) && ! empty( $candidate['to'] ) ) {
				$candidate['seen'] = $this->is_migration_candidate_seen( $key, $candidate );
				$out[ $key ] = $candidate;
			}
		}
		return $out;
	}

	/**
	 * Mark the current migration candidate as seen in the feed settings panel.
	 *
	 * This is intentionally separate from dismissing a candidate. The candidate
	 * remains available for internal auto-promotion if the old source later dies.
	 *
	 * @since 8.3.0
	 * @param string $object_key Record key carrying the candidate.
	 * @return bool
	 */
	public function mark_migration_candidate_seen( $object_key ) {
		$candidate = $this->get_data( $object_key, 'pp_migration_candidate' );
		if ( ! is_array( $candidate ) || empty( $candidate['to'] ) ) {
			return false;
		}

		return (bool) $this->update_data(
			array(
				'to'      => esc_url_raw( $candidate['to'] ),
				'seen_at' => time(),
			),
			$object_key,
			'pp_migration_seen'
		);
	}

	/**
	 * Whether the current migration target has already been seen by the admin.
	 *
	 * @since 8.3.0
	 * @param string     $object_key Record key carrying the candidate.
	 * @param array|null $candidate  Optional candidate data.
	 * @return bool
	 */
	public function is_migration_candidate_seen( $object_key, $candidate = null ) {
		if ( null === $candidate ) {
			$candidate = $this->get_data( $object_key, 'pp_migration_candidate' );
		}
		if ( ! is_array( $candidate ) || empty( $candidate['to'] ) ) {
			return false;
		}

		$seen = $this->get_data( $object_key, 'pp_migration_seen' );
		if ( is_array( $seen ) && ! empty( $seen['to'] ) ) {
			return esc_url_raw( $candidate['to'] ) === esc_url_raw( $seen['to'] );
		}

		return is_string( $seen ) && esc_url_raw( $candidate['to'] ) === esc_url_raw( $seen );
	}

	/**
	 * Apply a host-migration candidate as the record's new source URL.
	 *
	 * @since 8.3.0
	 * @param string $object_key Record key carrying the candidate.
	 * @return mixed Result of add_podcast_source_url(), or false.
	 */
	public function apply_migration_candidate( $object_key, $confirmed_target = '' ) {
		// Work from a durable identity throughout: applying the candidate rewrites
		// source_url, and a caller that passed the old source URL would stop
		// resolving mid-operation, silently skipping the cleanup below.
		$object_key = $this->get_promotion_lock_key( $object_key );
		$candidate  = $this->get_data( $object_key, 'pp_migration_candidate' );
		if ( ! is_array( $candidate ) || empty( $candidate['to'] ) ) {
			return false;
		}

		// Verify unless the admin was already shown this exact conflict and
		// accepted it. Binding the confirmation to the URL stops a stale dialog
		// approving a different candidate raised in the meantime.
		if ( $confirmed_target !== $candidate['to'] ) {
			$check = Get_Feed::verify_source_target( $object_key, $candidate['to'] );
			if ( 'match' !== $check['status'] ) {
				return array(
					'status' => 'conflict',
					'target' => $candidate['to'],
					'detail' => $check,
				);
			}
		}

		// Verification and the admin round trip both take time. Serialise
		// concurrent approvals on a dedicated claim lock — not the record lock,
		// which add_podcast_source_url() takes itself — and re-read the
		// candidate inside it so a change since then aborts rather than applies.
		$claim = 'pp_source_promote_' . $object_key;
		$token = $this->acquire_record_lock( $claim, 60 );
		if ( ! $token ) {
			return false;
		}

		try {
			$current = $this->get_data( $object_key, 'pp_migration_candidate' );
			if ( ! is_array( $current ) || empty( $current['to'] ) || $current['to'] !== $candidate['to'] ) {
				return false;
			}

			// The post that holds this record's promotion state, captured before
			// a merge can tombstone it and reroute key lookups to the survivor.
			$index     = $this->get_object_index( $object_key );
			$object_id = $index instanceof StorageRegister ? $index->get( 'object_id', 'none' ) : 0;

			$result = $this->add_podcast_source_url( $object_key, $candidate['to'] );

			// Only these two actually apply the new source. A `deferred` merge
			// waits on an identity migration, and a `noop` merge (already one
			// record, or a tombstoned counterpart) returns without touching
			// source_url — clearing the candidate for either would discard a
			// migration that never happened.
			$ok = true === $result
				|| ( is_array( $result ) && 'merged' === ( isset( $result['status'] ) ? $result['status'] : '' ) );
			if ( ! $ok ) {
				return $result;
			}

			$this->delete_object_meta( $object_id, array( 'pp_migration_candidate', 'pp_migration_seen', 'pp_source_failures' ) );
			return $result;
		} finally {
			$this->release_record_lock( $claim, $token );
		}
	}

	/**
	 * Delete post meta directly against a known object id.
	 *
	 * The key-based deleters resolve through get_object_index(), which after a
	 * merge sends an absorbed record's key to the survivor — so clearing
	 * promotion state by key would miss the absorbed post and could wipe the
	 * survivor's own state. Callers that hold the pre-merge object id use this to
	 * target exactly the post they mean.
	 *
	 * @since 8.3.0
	 * @param int          $object_id Post id to clear meta on.
	 * @param string|array $data_keys One or more meta keys.
	 */
	public function delete_object_meta( $object_id, $data_keys ) {
		$object_id = absint( $object_id );
		if ( ! $object_id ) {
			return;
		}
		foreach ( (array) $data_keys as $key ) {
			delete_post_meta( $object_id, $key );
		}
	}

	/**
	 * Resolve a record reference to a stable key for the source-promotion lock.
	 *
	 * Auto-promotion knows a record by the URL it fetched; review approval knows
	 * it by unique ID. Both must produce the same lock name or the two paths run
	 * concurrently on one record while each believes it holds the claim.
	 *
	 * @since 8.3.0
	 *
	 * @param string $object_key Record key or URL.
	 * @return string
	 */
	public function get_promotion_lock_key( $object_key ) {
		$index = $this->get_object_index( $object_key );

		return $index instanceof StorageRegister
			? (string) $index->get( 'unique_id', 'none' )
			: (string) $object_key;
	}

	/**
	 * Whether any duplicate merge work awaits global review.
	 *
	 * @since 8.3.0
	 * @return int Count of review items.
	 */
	public function get_review_count() {
		return count( $this->get_pending_merges() );
	}

	/**
	 * Find a register entry without excluding tombstones.
	 *
	 * @since 8.3.0
	 * @param array  $register Register data.
	 * @param string $key      Lookup key.
	 * @return StorageRegister|false
	 */
	private function get_register_index( $register, $key ) {
		if ( isset( $register[ $key ] ) && $register[ $key ] instanceof StorageRegister ) {
			return $register[ $key ];
		}
		$unique_id = md5( $key );
		if ( isset( $register[ $unique_id ] ) && $register[ $unique_id ] instanceof StorageRegister ) {
			return $register[ $unique_id ];
		}
		foreach ( $register as $index ) {
			if ( $index instanceof StorageRegister && $index->lookup( $key ) ) {
				return $index;
			}
		}
		return false;
	}

	/**
	 * Return an item property without changing its stored value.
	 *
	 * @since 8.3.0
	 * @param mixed  $item Item data.
	 * @param string $key  Item property.
	 * @return string
	 */
	private function get_merge_item_value( $item, $key ) {
		if ( $item instanceof ItemData ) {
			return (string) $item->get( $key, 'none' );
		}
		return is_array( $item ) && isset( $item[ $key ] ) ? (string) $item[ $key ] : '';
	}

	/**
	 * Retain an unmatched absorbed item under a non-colliding storage key.
	 *
	 * @since 8.3.0
	 * @param array  $items Existing items.
	 * @param string $key   Desired original key.
	 * @return string
	 */
	private function get_retained_merge_item_key( $items, $key ) {
		if ( ! isset( $items[ $key ] ) ) {
			return $key;
		}
		$number = 1;
		while ( isset( $items[ $key . '-absorbed-' . $number ] ) ) {
			$number++;
		}
		return $key . '-absorbed-' . $number;
	}

	/**
	 * Snapshot one post-meta key, including whether it existed.
	 *
	 * @since 8.3.0
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @return array
	 */
	private function snapshot_merge_meta( $post_id, $meta_key ) {
		return array(
			'post_id'  => $post_id,
			'meta_key' => $meta_key,
			'exists'   => metadata_exists( 'post', $post_id, $meta_key ),
			'value'    => $this->copy_merge_value( get_post_meta( $post_id, $meta_key, true ) ),
		);
	}

	/**
	 * Restore one post-meta snapshot.
	 *
	 * @since 8.3.0
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @param array  $snapshot Meta snapshot.
	 */
	private function restore_merge_meta( $post_id, $meta_key, $snapshot ) {
		if ( ! empty( $snapshot['exists'] ) ) {
			update_post_meta( $post_id, $meta_key, $snapshot['value'] );
			return;
		}
		delete_post_meta( $post_id, $meta_key );
	}

	/**
	 * Copy mutable object values for rollback and merge-log snapshots.
	 *
	 * @since 8.3.0
	 * @param mixed $value Value to copy.
	 * @return mixed
	 */
	private function copy_merge_value( $value ) {
		return unserialize( serialize( $value ) );
	}

	/**
	 * Throw a controlled exception after a named write for integration tests.
	 *
	 * @since 8.3.0
	 * @param array  $args  Merge arguments.
	 * @param string $point Write point name.
	 */
	private function maybe_inject_merge_failure( $args, $point ) {
		if ( empty( $args['fail_after'] ) ) {
			return;
		}
		if ( is_callable( $args['fail_after'] ) && call_user_func( $args['fail_after'], $point ) ) {
			throw new \RuntimeException( 'Injected merge failure after ' . $point );
		}
		if ( $args['fail_after'] === $point ) {
			throw new \RuntimeException( 'Injected merge failure after ' . $point );
		}
	}

    /**
	 * Remove an object from the Index.
	 *
	 * @since 1.0.0
	 *
	 * @param string $object_key Key to create/access the post object bucket.
	 */
	private function delete_object_index( $object_key ) {
		$register = $this->get_register();
		if ( ! $object_key || ! isset( $register[ $object_key ] ) ) {
			return false;
		}
		unset( $register[ $object_key ] );
		update_option( 'pp-register', $register, false );
	}

	/**
	 * Get database register.
	 *
	 * @since 1.0.0
	 */
	private function get_register() {
		$register = get_option( 'pp-register' );
		return false !== $register ? $register : array();
	}
}
