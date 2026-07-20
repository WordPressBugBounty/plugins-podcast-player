<?php
/**
 * Episode identity resolver.
 *
 * @link       https://easypodcastpro.com
 * @since      8.3.0
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 */

namespace Podcast_Player\Helper\Feed;

use Podcast_Player\Helper\Store\FeedData;
use Podcast_Player\Helper\Store\ItemData;

/**
 * Resolves saved episode references to current episode UIDs.
 *
 * @package Podcast_Player
 */
class Episode_Resolver {

	/**
	 * Current feed items keyed by episode UID where possible.
	 *
	 * @since 8.3.0
	 * @access private
	 * @var array
	 */
	private $items = array();

	/**
	 * Legacy key to episode UID map.
	 *
	 * @since 8.3.0
	 * @access private
	 * @var array
	 */
	private $identity_map = array();

	/**
	 * Durable UID to original legacy-key map used for compatibility aliases.
	 *
	 * @since 8.3.0
	 * @access private
	 * @var array
	 */
	private $legacy_key_by_uid = array();

	/**
	 * Runtime aliases built from the current item set.
	 *
	 * @since 8.3.0
	 * @access private
	 * @var array
	 */
	private $runtime_aliases = array();

	/**
	 * Constructor.
	 *
	 * @since 8.3.0
	 *
	 * @param array $items        Feed items.
	 * @param array $identity_map      Legacy key to episode UID map.
	 * @param array $legacy_key_by_uid UID to durable legacy key map.
	 */
	public function __construct( $items = array(), $identity_map = array(), $legacy_key_by_uid = array() ) {
		$this->items             = is_array( $items ) ? $items : array();
		$this->identity_map      = is_array( $identity_map ) ? $identity_map : array();
		$this->legacy_key_by_uid = is_array( $legacy_key_by_uid ) ? $legacy_key_by_uid : array();

		$this->build_runtime_aliases();
	}

	/**
	 * Create resolver from feed data.
	 *
	 * @since 8.3.0
	 *
	 * @param FeedData|array $feed_data Feed data object or retrieved array.
	 * @return self
	 */
	public static function from_feed_data( $feed_data ) {
		if ( $feed_data instanceof FeedData ) {
			return new self(
				$feed_data->get( 'items', 'none' ),
				$feed_data->get( 'identity_map', 'none' ),
				$feed_data->get( 'legacy_key_by_uid', 'none' )
			);
		}

		if ( is_array( $feed_data ) ) {
			$items = isset( $feed_data['items'] ) && is_array( $feed_data['items'] ) ? $feed_data['items'] : array();
			$map   = isset( $feed_data['identity_map'] ) && is_array( $feed_data['identity_map'] ) ? $feed_data['identity_map'] : array();
			$legacy_key_by_uid = isset( $feed_data['legacy_key_by_uid'] ) && is_array( $feed_data['legacy_key_by_uid'] ) ? $feed_data['legacy_key_by_uid'] : array();
			return new self( $items, $map, $legacy_key_by_uid );
		}

		return new self();
	}

	/**
	 * Resolve a saved episode value to the current episode UID when possible.
	 *
	 * Unknown values are returned unchanged so legacy fallback paths can still
	 * decide what to do with them.
	 *
	 * @since 8.3.0
	 *
	 * @param string $value Saved episode value.
	 * @return string
	 */
	public function resolve_episode_uid( $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		if ( isset( $this->items[ $value ] ) ) {
			$uid = $this->get_item_uid( $this->items[ $value ] );
			return $uid ? $uid : $value;
		}

		if ( isset( $this->identity_map[ $value ] ) ) {
			return sanitize_text_field( (string) $this->identity_map[ $value ] );
		}

		if ( isset( $this->runtime_aliases[ $value ] ) ) {
			return $this->runtime_aliases[ $value ];
		}

		return $value;
	}

	/**
	 * Resolve saved episode values to current episode UIDs when possible.
	 *
	 * @since 8.3.0
	 *
	 * @param array $values Saved episode values.
	 * @return array
	 */
	public function resolve_episode_uids( $values ) {
		$resolved = array();
		foreach ( (array) $values as $value ) {
			$uid = $this->resolve_episode_uid( $value );
			if ( '' !== $uid ) {
				$resolved[] = $uid;
			}
		}

		return array_values( array_unique( $resolved ) );
	}

	/**
	 * Resolve a saved episode value to the current item object when possible.
	 *
	 * @since 8.3.0
	 *
	 * @param string $value Saved episode value.
	 * @return ItemData|false
	 */
	public function resolve_item( $value ) {
		$uid = $this->resolve_episode_uid( $value );

		if ( isset( $this->items[ $uid ] ) && $this->items[ $uid ] instanceof ItemData ) {
			return $this->items[ $uid ];
		}

		return false;
	}

	/**
	 * Rekey item-like arrays through the identity map.
	 *
	 * @since 8.3.0
	 *
	 * @param array $items Item array keyed by episode references.
	 * @return array
	 */
	public function rekey_items_by_uid( $items ) {
		if ( ! is_array( $items ) ) {
			return array();
		}

		$rekeyed = array();
		foreach ( $items as $key => $item ) {
			$uid = $this->resolve_episode_uid( $key );
			if ( isset( $rekeyed[ $uid ] ) ) {
				if ( $uid === $key ) {
					$rekeyed[ $uid ] = $item;
				}
				continue;
			}
			$rekeyed[ $uid ] = $item;
		}

		return $rekeyed;
	}

	/**
	 * Rekey custom feed item data through the identity map.
	 *
	 * @since 8.3.0
	 *
	 * @param array $custom_items Custom item data keyed by episode references.
	 * @return array
	 */
	public function rekey_custom_items_by_uid( $custom_items ) {
		return $this->rekey_items_by_uid( $custom_items );
	}

	/**
	 * Get current map.
	 *
	 * @since 8.3.0
	 *
	 * @return array
	 */
	public function get_identity_map() {
		return $this->identity_map;
	}

	/**
	 * Backfill bridge entries from item keys that are not their episode UID.
	 *
	 * This is intended for legacy capture only. It does not add every future
	 * audio-derived key when items are already stored under episode UIDs.
	 *
	 * @since 8.3.0
	 *
	 * @param array $items        Feed items.
	 * @param array $identity_map Existing identity map.
	 * @return array
	 */
	public static function backfill_identity_map( $items, $identity_map = array() ) {
		$map = is_array( $identity_map ) ? $identity_map : array();
		if ( ! is_array( $items ) ) {
			return $map;
		}

		foreach ( $items as $key => $item ) {
			if ( ! $item instanceof ItemData ) {
				continue;
			}

			$uid = $item->get( 'episode_uid', 'none' );
			self::add_identity_map_entry( $map, $key, $uid, $items );
		}

		return $map;
	}

	/**
	 * Add a legacy bridge entry without shadowing current item keys.
	 *
	 * @since 8.3.0
	 *
	 * @param array  $map         Identity map.
	 * @param string $legacy      Legacy item key.
	 * @param string $episode_uid Episode UID.
	 * @param array  $items       Current items.
	 */
	public static function add_identity_map_entry( &$map, $legacy, $episode_uid, $items = array() ) {
		$legacy      = sanitize_text_field( (string) $legacy );
		$episode_uid = sanitize_text_field( (string) $episode_uid );

		if ( ! $legacy || ! $episode_uid || $legacy === $episode_uid ) {
			return;
		}

		if ( isset( $map[ $legacy ] ) ) {
			return;
		}

		if ( isset( $items[ $legacy ] ) ) {
			$current_uid = self::get_item_uid_from_value( $items[ $legacy ] );
			if ( $current_uid !== $episode_uid ) {
				return;
			}
		}

		$map[ $legacy ] = $episode_uid;
	}

	/**
	 * Build runtime aliases from the current item set.
	 *
	 * @since 8.3.0
	 */
	private function build_runtime_aliases() {
		foreach ( $this->items as $key => $item ) {
			$uid = $this->get_item_uid( $item );
			if ( ! $uid ) {
				continue;
			}

			if ( $key !== $uid ) {
				$this->runtime_aliases[ $key ] = $uid;
			}

			$src = $this->get_item_field( $item, 'src' );
			if ( $src ) {
				$legacy_audio_key = Episode_Identity::legacy_audio_key( $src );
				if ( $legacy_audio_key && ! isset( $this->items[ $legacy_audio_key ] ) ) {
					$this->runtime_aliases[ $legacy_audio_key ] = $uid;
				}
			}
		}

		// Retained aliases cover the original audio hash after a publisher moves
		// an enclosure URL. Explicit identity_map entries and live item/audio
		// aliases always win, so a historical value cannot shadow active data.
		foreach ( $this->legacy_key_by_uid as $uid => $legacy_key ) {
			$uid        = sanitize_text_field( (string) $uid );
			$legacy_key = sanitize_text_field( (string) $legacy_key );
			if ( ! $uid || ! $legacy_key || ! isset( $this->items[ $uid ] ) ) {
				continue;
			}

			if ( isset( $this->items[ $legacy_key ] ) || isset( $this->identity_map[ $legacy_key ] ) || isset( $this->runtime_aliases[ $legacy_key ] ) ) {
				continue;
			}

			$this->runtime_aliases[ $legacy_key ] = $uid;
		}
	}

	/**
	 * Get item episode UID.
	 *
	 * @since 8.3.0
	 *
	 * @param ItemData $item Feed item.
	 * @return string
	 */
	private function get_item_uid( $item ) {
		return self::get_item_uid_from_value( $item );
	}

	/**
	 * Get item episode UID from an object or retrieved array.
	 *
	 * @since 8.3.0
	 *
	 * @param ItemData|array $item Feed item.
	 * @return string
	 */
	private static function get_item_uid_from_value( $item ) {
		if ( $item instanceof ItemData ) {
			return sanitize_text_field( (string) $item->get( 'episode_uid', 'none' ) );
		}

		if ( is_array( $item ) && isset( $item['episode_uid'] ) ) {
			return sanitize_text_field( (string) $item['episode_uid'] );
		}

		return '';
	}

	/**
	 * Get an item field from an object or retrieved array.
	 *
	 * @since 8.3.0
	 *
	 * @param ItemData|array $item  Feed item.
	 * @param string         $field Item field.
	 * @return mixed
	 */
	private function get_item_field( $item, $field ) {
		if ( $item instanceof ItemData ) {
			return $item->get( $field, 'none' );
		}

		if ( is_array( $item ) && isset( $item[ $field ] ) ) {
			return $item[ $field ];
		}

		return '';
	}
}
