<?php
/**
 * Fetch Feed Data from Feed XML file.
 *
 * @link       https://www.vedathemes.com
 * @since      1.0.0
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 */

namespace Podcast_Player\Helper\Feed;

use Podcast_Player\Helper\Functions\Getters as Get_Fn;
use Podcast_Player\Helper\Functions\Validation as Validation_Fn;
use Podcast_Player\Helper\Store\StoreManager;
use Podcast_Player\Helper\Store\FeedData;
use Podcast_Player\Helper\Store\ItemData;

/**
 * Fetch Feed Data from Feed XML file.
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 * @author     vedathemes <contact@vedathemes.com>
 */
class Prepare_Storage {

	/**
	 * Current persisted episode-identity format.
	 *
	 * @since 8.3.0
	 * @var int
	 */
	private const IDENTITY_VERSION = 2;

	/**
	 * Holds old feed data.
	 *
	 * @since  6.4.3
	 * @access private
	 * @var    array
	 */
	private $new_data = array();

	/**
	 * Holds old feed data.
	 *
	 * @since  6.4.3
	 * @access private
	 * @var    array
	 */
	private $old_data = array();

	/**
	 * Holds new episodes ID.
	 *
	 * @since  7.4.0
	 * @access private
	 * @var    array
	 */
	private $new_episodes = array();

	/**
	 * Constructor method.
	 *
	 * @since  6.4.3
	 *
	 * @param object $new_data Newly fetched podcast feed data.
	 * @param string $old_data Previously stored podcast feed data.
	 */
	public function __construct( $new_data, $old_data ) {
		$this->new_data = $new_data;
		$this->old_data = $old_data;
	}

	/**
	 * Prepare feed data for storage.
	 *
	 * @since  6.4.3
	 */
	public function init() {
		// If old data is not available, prepare new data for storage.
		if ( ! $this->old_data instanceof FeedData ) {
			return $this->new_podcast();
		}

		// Compare new data with old stored data for changes.
		return $this->get_changes();
	}

	/**
	 * Do not derive episode identity from an existing cache.
	 *
	 * Historical episode_id values do not identify whether their value came
	 * from RSS, Atom, or a legacy fallback. A cache-only conversion could
	 * therefore incorrectly promote one of those values to an RSS GUID. Cached
	 * feeds are migrated by Get_Feed only after an unconditional source fetch
	 * has been parsed with the current identity rules.
	 *
	 * @since 8.3.0
	 *
	 * @param FeedData $feed_data Existing stored feed data.
	 * @return array
	 */
	public static function upgrade_existing_identity( $feed_data ) {
		return array( $feed_data, false );
	}

	/**
	 * Prepare freshly fetched data for storage, no old data is available.
	 *
	 * @since 7.4.0
	 */
	private function new_podcast() {
		$episode_ids = array_keys( $this->new_data->get( 'items' ) );
		$this->new_data->set(
			'legacy_key_by_uid',
			$this->get_legacy_key_by_uid( $this->new_data->get( 'items' ), array(), array() ),
			'none'
		);
		$this->new_data->set( 'identity_upgraded', true, 'none' );
		$this->new_data->set( 'identity_version', self::IDENTITY_VERSION, 'none' );
		return array( $this->new_data, $episode_ids );
	}

	/**
	 * Compare new data with old stored data for changes.
	 *
	 * @since  7.4.0
	 */
	private function get_changes() {
		list( $new_items, $deleted_items, $added_items ) = $this->get_items_list();

		$keep_old = Get_Fn::get_plugin_option( 'keep_old' );
		if ( $keep_old ) {
			$new_items = array_merge( $new_items, $deleted_items );
		}

		$this->new_data->set( 'items', $new_items );
		$this->new_data->set(
			'legacy_key_by_uid',
			$this->get_legacy_key_by_uid(
				$new_items,
				$this->new_data->get( 'identity_map', 'none' ),
				$this->old_data->get( 'legacy_key_by_uid', 'none' )
			),
			'none'
		);
		$this->new_data->set( 'identity_upgraded', true, 'none' );
		$this->new_data->set( 'identity_version', self::IDENTITY_VERSION, 'none' );
		return array( $this->new_data, array_keys( $added_items ) );
	}

	/**
	 * Get list of new and old items for the podcast.
	 *
	 * @since  7.4.0
	 */
	private function get_items_list() {
		$old_items = $this->old_data->get( 'items' );
		$new_items = $this->new_data->get( 'items' );
		$map       = $this->old_data->get( 'identity_map', 'none' );
		$map       = is_array( $map ) ? $map : array();
		$map       = Episode_Resolver::backfill_identity_map( $old_items, $map );

		// Return data if no old items have been deleted.
		$deleted_items = array_diff_key( $old_items, $new_items );
		$added_items   = array_diff_key( $new_items, $old_items );
		if ( empty( $deleted_items ) || empty( $added_items ) ) {
			$this->new_data->set( 'identity_map', $map, 'none' );
			return array( $new_items, $deleted_items, $added_items );
		}

		$updated_new_items = $new_items;
		$consumed_deleted  = array();
		$consumed_added    = array();

		foreach ( $added_items as $new_key => $new_item ) {
			$old_key = $this->find_matching_deleted_key( $new_item, $deleted_items, $consumed_deleted );
			if ( ! $old_key ) {
				continue;
			}

			$old_item = $deleted_items[ $old_key ];
			$old_uid  = $this->get_item_uid( $old_item );

			if ( $old_uid ) {
				if ( $old_uid !== $new_key ) {
					if ( isset( $updated_new_items[ $old_uid ] ) ) {
						continue;
					}

					unset( $updated_new_items[ $new_key ] );
					$new_item->set(
						array(
							'episode_uid'          => $old_uid,
							'identity_source'      => $this->get_item_field( $old_item, 'identity_source' ),
							'identity_reliability' => $this->get_item_reliability( $old_item ),
						),
						false,
						'none'
					);
					$updated_new_items[ $old_uid ] = $new_item;
				}
			} else {
				Episode_Resolver::add_identity_map_entry( $map, $old_key, $new_key, $updated_new_items );
				if ( ! isset( $map[ $old_key ] ) || $new_key !== $map[ $old_key ] ) {
					continue;
				}
			}

			$consumed_deleted[ $old_key ] = true;
			$consumed_added[ $new_key ]   = true;
		}

		$new_items     = $updated_new_items;
		$deleted_items = array_diff_key( $old_items, $new_items );
		$added_items   = array_diff_key( $new_items, $old_items );

		foreach ( $consumed_deleted as $key => $value ) {
			unset( $deleted_items[ $key ] );
		}

		foreach ( $consumed_added as $key => $value ) {
			unset( $added_items[ $key ] );
		}

		$this->new_data->set( 'identity_map', $map, 'none' );

		return array( $new_items, $deleted_items, $added_items );
	}

	/**
	 * Retain one legacy audio key for each UID ever observed in this feed.
	 *
	 * Existing values are authoritative: they capture the key an earlier
	 * release used before an audio URL changed. A newly parsed UID falls back to
	 * its current audio hash only when no historical key has been retained.
	 *
	 * @since 8.3.0
	 *
	 * @param array $items             Current or retained feed items.
	 * @param array $identity_map      Legacy key to current UID bridges.
	 * @param array $legacy_key_by_uid Previously retained UID to legacy keys.
	 * @return array
	 */
	private function get_legacy_key_by_uid( $items, $identity_map, $legacy_key_by_uid ) {
		$legacy_keys = is_array( $legacy_key_by_uid ) ? $legacy_key_by_uid : array();
		$identity_map = is_array( $identity_map ) ? $identity_map : array();

		foreach ( $identity_map as $legacy_key => $uid ) {
			$legacy_key = sanitize_text_field( (string) $legacy_key );
			$uid        = sanitize_text_field( (string) $uid );
			if ( $legacy_key && $uid && ! isset( $legacy_keys[ $uid ] ) ) {
				$legacy_keys[ $uid ] = $legacy_key;
			}
		}

		foreach ( (array) $items as $item_key => $item ) {
			$uid = $this->get_item_uid( $item );
			$uid = $uid ? $uid : sanitize_text_field( (string) $item_key );
			if ( ! $uid || isset( $legacy_keys[ $uid ] ) ) {
				continue;
			}

			$src = $this->get_item_field( $item, 'src' );
			if ( $src ) {
				$legacy_keys[ $uid ] = Episode_Identity::legacy_audio_key( $src );
			}
		}

		return $legacy_keys;
	}

	/**
	 * Find a deleted old item matching a newly added item.
	 *
	 * @since 8.3.0
	 *
	 * @param ItemData $new_item         Newly fetched item.
	 * @param array    $deleted_items    Deleted old items.
	 * @param array    $consumed_deleted Already matched old keys.
	 * @return string
	 */
	private function find_matching_deleted_key( $new_item, $deleted_items, $consumed_deleted ) {
		$signals = array(
			array( $this, 'has_same_episode_id' ),
			array( $this, 'has_same_legacy_audio_key' ),
			array( $this, 'has_same_duration_fingerprint' ),
			array( $this, 'has_same_date_fingerprint' ),
		);

		foreach ( $signals as $callback ) {
			$matches = array();
			foreach ( $deleted_items as $old_key => $old_item ) {
				if ( isset( $consumed_deleted[ $old_key ] ) ) {
					continue;
				}

				if ( ! $this->can_reconcile_old_item( $old_item ) ) {
					continue;
				}

				if ( call_user_func( $callback, $new_item, $old_item, $old_key ) ) {
					$matches[] = $old_key;
				}
			}

			if ( 1 === count( $matches ) ) {
				return reset( $matches );
			}
		}

		return '';
	}

	/**
	 * Whether an old item may participate in heuristic reconciliation.
	 *
	 * A key without a stored UID is legacy data and needs a bridge to the
	 * parser-generated UID. Once the plugin has stored an identity, only weak
	 * identities may be pinned by heuristics. Publisher-issued strong values are
	 * intentionally never overridden when a fresh parse computes a different
	 * UID.
	 *
	 * @since 8.3.0
	 *
	 * @param ItemData $old_item Old stored item.
	 * @return bool
	 */
	private function can_reconcile_old_item( $old_item ) {
		$old_uid = $this->get_item_uid( $old_item );
		if ( ! $old_uid ) {
			return true;
		}

		return 'weak' === $this->get_item_reliability( $old_item );
	}

	/**
	 * Check if two items have the same stored episode ID.
	 *
	 * @since 8.3.0
	 *
	 * @param ItemData $new_item New item.
	 * @param ItemData $old_item Old item.
	 * @param string   $old_key  Old item storage key.
	 * @return bool
	 */
	private function has_same_episode_id( $new_item, $old_item, $old_key = '' ) {
		$new_id = $this->get_item_field( $new_item, 'episode_id' );
		$old_id = $this->get_item_field( $old_item, 'episode_id' );

		return $new_id && $old_id && $new_id === $old_id;
	}

	/**
	 * Check if two items have the same legacy audio-derived key.
	 *
	 * @since 8.3.0
	 *
	 * @param ItemData $new_item New item.
	 * @param ItemData $old_item Old item.
	 * @param string   $old_key  Old item storage key.
	 * @return bool
	 */
	private function has_same_legacy_audio_key( $new_item, $old_item, $old_key = '' ) {
		$new_src = $this->get_item_field( $new_item, 'src' );
		if ( ! $new_src ) {
			return false;
		}

		$new_legacy_key = Episode_Identity::legacy_audio_key( $new_src );
		if ( $old_key && $old_key === $new_legacy_key ) {
			return true;
		}

		$old_src = $this->get_item_field( $old_item, 'src' );
		if ( ! $old_src ) {
			return false;
		}

		return Episode_Identity::legacy_audio_key( $old_src ) === $new_legacy_key;
	}

	/**
	 * Check if two items have the same title/date/duration fingerprint.
	 *
	 * @since 8.3.0
	 *
	 * @param ItemData $new_item New item.
	 * @param ItemData $old_item Old item.
	 * @param string   $old_key  Old item storage key.
	 * @return bool
	 */
	private function has_same_duration_fingerprint( $new_item, $old_item, $old_key = '' ) {
		$new_fingerprint = $this->get_item_fingerprint( $new_item, true );
		$old_fingerprint = $this->get_item_fingerprint( $old_item, true );

		return $new_fingerprint && $old_fingerprint && $new_fingerprint === $old_fingerprint;
	}

	/**
	 * Check if two items have the same title/date fingerprint.
	 *
	 * @since 8.3.0
	 *
	 * @param ItemData $new_item New item.
	 * @param ItemData $old_item Old item.
	 * @param string   $old_key  Old item storage key.
	 * @return bool
	 */
	private function has_same_date_fingerprint( $new_item, $old_item, $old_key = '' ) {
		$new_fingerprint = $this->get_item_fingerprint( $new_item, false );
		$old_fingerprint = $this->get_item_fingerprint( $old_item, false );

		return $new_fingerprint && $old_fingerprint && $new_fingerprint === $old_fingerprint;
	}

	/**
	 * Get item UID.
	 *
	 * @since 8.3.0
	 *
	 * @param ItemData $item Feed item.
	 * @return string
	 */
	private function get_item_uid( $item ) {
		return $this->get_item_field( $item, 'episode_uid' );
	}

	/**
	 * Get an item's stored identity reliability.
	 *
	 * @since 8.3.0
	 *
	 * @param ItemData $item Feed item.
	 * @return string
	 */
	private function get_item_reliability( $item ) {
		return sanitize_text_field( (string) $this->get_item_field( $item, 'identity_reliability' ) );
	}

	/**
	 * Get item field for internal comparisons.
	 *
	 * @since 8.3.0
	 *
	 * @param ItemData $item  Feed item.
	 * @param string   $field Item field.
	 * @return mixed
	 */
	private function get_item_field( $item, $field ) {
		if ( ! $item instanceof ItemData ) {
			return '';
		}

		return $item->get( $field, 'none' );
	}

	/**
	 * Get an item metadata fingerprint.
	 *
	 * @since 8.3.0
	 *
	 * @param ItemData $item             Feed item.
	 * @param bool     $include_duration Whether duration must be included.
	 * @return string
	 */
	private function get_item_fingerprint( $item, $include_duration ) {
		$title     = $this->get_item_field( $item, 'title' );
		$date      = $this->get_item_field( $item, 'date' );
		$duration  = $this->get_item_field( $item, 'duration' );
		$timestamp = is_array( $date ) && isset( $date['date'] ) ? $date['date'] : 0;

		return Episode_Identity::fingerprint_from_parts( $title, $timestamp, $duration, $include_duration );
	}

}
