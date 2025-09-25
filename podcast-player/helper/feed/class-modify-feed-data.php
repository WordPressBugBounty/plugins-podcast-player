<?php
/**
 * Sort & Filter Feed Data for output.
 *
 * @link       https://www.vedathemes.com
 * @since      1.0.0
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 */

namespace Podcast_Player\Helper\Feed;

use Podcast_Player\Helper\Core\Singleton;
use Podcast_Player\Helper\Store\ItemData;

/**
 * Sort & Filter Feed Data for output.
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 * @author     vedathemes <contact@vedathemes.com>
 */
class Modify_Feed_Data extends Singleton {

	/**
	 * Init method.
	 *
	 * @since  3.3.0
	 *
	 * @param array $data        Fetched feed data to modified.
	 * @param array $custom_data Custom data for the feed.
	 * @param array $mods        Modification args.
	 * @param array $fields      Item data fields to retrieve.
	 */
	public function init( $data, $custom_data, $mods, $fields ) {

		// Prepare feed modification data.
		$defaults = $this->get_mod_defaults();
		$mods     = wp_parse_args( $mods, $defaults );

		// Get feed items.
		$feed_items        = $data['items'];
		$custom_data_items = empty( $custom_data['items'] ) ? array() : $custom_data['items'];
		$custom_fields     = array( 'title', 'description', 'author', 'featured', 'featured_id', 'episode', 'season', 'post_id' );

		foreach ( $feed_items as $key => $item ) {
			$custom_item = isset( $custom_data_items[ $key ] ) ? $custom_data_items[ $key ] : false;
			if ( empty( $custom_item ) || ! $custom_item instanceof ItemData  ) {
				continue;
			}

			$props = array_filter( $custom_item->get( $custom_fields, 'none' ) );
			if ( ! empty( $props ) ) {
				$item->set( $props, false, 'none' );
				$feed_items[ $key ] = $item;
			}
		}

		// Compatibility with the older pro version. It should be removed after two updates.
		if ( defined( 'PP_PRO_VERSION' ) && version_compare( PP_PRO_VERSION, '5.6.0', '<' ) ) {
			return $this->legacy_modify_feed_data( $feed_items, $mods, $fields );
		}

		/**
		 * Apply Episodes filters before sorting and getting required number of items.
		 *
		 * @since 4.7.0
		 *
		 * @param array $feed_items All fetched feed items.
		 * @param array $mods       Additional args supplied.
		 */
		$items = apply_filters( 'podcast_player_internal_episode_filters', $feed_items, $mods );

		// Filter feed items where item title contains a specific text ($filterby).
		if ( ! empty( $items ) && ! empty( $mods['filterby'] ) ) {
			$items = $this->filter_data( $items, $mods['filterby'] );
		}

		// Get total available items after applying all filters.
		$total_items = count( $items );
		if ( ! $total_items ) {
			// return array( $total_items, array() );
			$data['total'] = $total_items;
			$data['items'] = array();
			return $data;
		}

		$new_items = array_map(
			function ( $val ) {
				return $val->retrieve( 'echo', array( 'categories', 'season' ) );
			},
			$items
		);

		// We must get all seasons and categories after filter and before trimming required items. So that, we get
		// everything to filter items on the front-end.
		// Get cumulative array of all available seasons.
		$seasons = array_values( array_filter( array_unique( array_column( $new_items, 'season' ) ) ) );

		// Get cumulative array of all available categories.
		$cats = array_column( $new_items, 'categories' );
		$cats = array_unique( call_user_func_array( 'array_merge', $cats ) );

		// Sort filtered items by data or title.
		$items = $this->sort_data( $items, $mods['sortby'] );

		// Move fixed item to top of the list (if available in the list).
		$items = $this->move_fixed_item_to_top( $items, $mods['fixed'] );

		// Get required number of items.
		$items = $this->get_required_items( $items, $mods['start'], $mods['end'] );

		array_walk(
			$items,
			function ( &$val, $key ) use ( $fields ) {
				$val = $val->retrieve( 'echo', $fields );
			}
		);

		$data['total']      = $total_items;
		$data['items']      = $items;
		$data['seasons']    = $seasons;
		$data['categories'] = $cats;

		return $data;
	}

	/**
	 * Compatibility with the older pro version. It should be removed after two updates.
	 *
	 * @since 7.4.0
	 *
	 * @param array $items  All fetched feed items.
	 * @param array $mods   Additional args supplied.
	 * @param array $fields Item data fields to retrieve.
	 */
	private function legacy_modify_feed_data( $items, $mods, $fields ) {
		array_walk(
			$items,
			function ( &$val, $key ) {
				if ( $val instanceof ItemData ) {
					$val = $val->retrieve();
				}
			}
		);

		/**
		 * Perform additional filter or custom operations on fetched data.
		 *
		 * @since 2.8.0
		 *
		 * @param array $feed_items All fetched feed items.
		 * @param array $mods       Additional args supplied.
		 */
		$items = apply_filters( 'podcast_player_episode_filters', $items, $mods );

		// Filter feed items where item title contains a specific text ($filterby).
		if ( ! empty( $items ) && ! empty( $mods['filterby'] ) ) {
			$items = $this->filter_data( $items, $mods['filterby'] );
		}

		// Get total available items after applying all filters.
		$total_items = count( $items );
		if ( ! $total_items ) {
			return array( $total_items, array() );
		}

		// Sort filtered items by data or title.
		$items = $this->sort_data_old( $items, $mods['sortby'] );

		// Move fixed item to top of the list (if available in the list).
		$items = $this->move_fixed_item_to_top( $items, $mods['fixed'] );

		// Get required number of items.
		$items = $this->get_required_items( $items, $mods['start'], $mods['end'] );

		return array( $total_items, $items );
	}

	/**
	 * Sort all filtered items.
	 * 
	 * To be removed after above compatibiity is removed.
	 *
	 * @since  3.3.0
	 *
	 * @param array  $items  Filtered feed items.
	 * @param string $sortby Episodes to be sorted by.
	 */
	private function sort_data_old( $items, $sortby ) {
		$allowed_sort_order = array(
			'sort_title_desc',
			'sort_title_asc',
			'sort_date_asc',
			'sort_date_desc',
		);

		if ( in_array( $sortby, $allowed_sort_order, true ) ) {
			uasort( $items, function( $a, $b ) use ( $sortby ) {
				if ( 'sort_title_desc' === $sortby ) {
					return $a['title'] <= $b['title'] ? 1 : -1;
				} else if ( 'sort_title_asc' === $sortby ) {
					return $a['title'] > $b['title'] ? 1 : -1;
				} else if ( 'sort_date_desc' === $sortby ) {
					return $a['date'] <= $b['date'] ? 1 : -1;
				} else if ( 'sort_date_asc' === $sortby ) {
					return $a['date'] > $a['date'] ? 1 : -1;
				}
			});
		} elseif ( 'reverse_sort' === $sortby ) {
			$items = array_reverse( $items );
		}

		return $items;
	}

	/**
	 * Mod defaults.
	 *
	 * @since  3.3.0
	 */
	private function get_mod_defaults() {
		return array(
			'start'    => 0,
			'end'      => 0,
			'filterby' => '',
			'sortby'   => 'none',
			'fixed'    => false,
		);
	}

	/**
	 * Filter feed items if title contains a specific text.
	 *
	 * @since  3.3.0
	 *
	 * @param array  $items    Primary filtered feed items.
	 * @param string $filterby Episodes to be filtered by.
	 */
	private function filter_data( $items, $filterby ) {
		$filterby = trim( convert_chars( wptexturize( strtolower( $filterby ) ) ) );

		// Check if item title contains a specific ($filterby) text.
		return array_filter(
			$items,
			function ( $item ) use ( $filterby ) {
				if ( $item instanceof ItemData ) {
					$item_title = strtolower( $item->get( 'title' ) );
				} else {
					$item_title = strtolower( $item['title'] );
				}
				return false !== strpos( $item_title, $filterby );
			}
		);
	}

	/**
	 * Sort all filtered items.
	 *
	 * @since  3.3.0
	 *
	 * @param array  $items  Filtered feed items.
	 * @param string $sortby Episodes to be sorted by.
	 */
	private function sort_data( $items, $sortby ) {
		$allowed_sort_order = array(
			'sort_title_desc',
			'sort_title_asc',
			'sort_date_asc',
			'sort_date_desc',
		);
		if ( in_array( $sortby, $allowed_sort_order, true ) ) {
			uasort( $items, array( $this, $sortby ) );
		} elseif ( 'reverse_sort' === $sortby ) {
			$items = array_reverse( $items );
		}

		return $items;
	}

	/**
	 * Check and move fixed item on top of the list.
	 *
	 * @since  3.3.0
	 *
	 * @param array  $items     Filtered and sorted feed items.
	 * @param string $fixed_key Fixed item key.
	 */
	private function move_fixed_item_to_top( $items, $fixed_key ) {

		// Check and move the fixed item to top of the list.
		if ( $fixed_key && isset( $items[ $fixed_key ] ) ) {
			$items = array( $fixed_key => $items[ $fixed_key ] ) + $items;
		}
		return $items;
	}

	/**
	 * Get required items from the array.
	 *
	 * @since  3.3.0
	 *
	 * @param array $items Filtered and sorted feed items.
	 * @param int   $start Start collecting items.
	 * @param int   $end   Stop collecting items.
	 */
	private function get_required_items( $items, $start, $end ) {

		// Slice the data as desired.
		if ( 0 === $end ) {
			return array_slice( $items, $start );
		} else {
			return array_slice( $items, $start, $end );
		}
	}

	/**
	 * Sorting callback for items title descending.
	 *
	 * @since 3.3.0
	 *
	 * @param array $a Feed item.
	 * @param array $b Feed item.
	 * @return boolean
	 */
	private function sort_title_desc( $a, $b ) {
		return $a->get( 'title' ) <= $b->get( 'title' ) ? 1 : -1;
	}

	/**
	 * Sorting callback for items title ascending.
	 *
	 * @since 3.3.0
	 *
	 * @param array $a Feed item.
	 * @param array $b Feed item.
	 * @return boolean
	 */
	private function sort_title_asc( $a, $b ) {
		return $a->get( 'title' ) > $b->get( 'title' ) ? 1 : -1;
	}

	/**
	 * Sorting callback for items date ascending.
	 *
	 * @since 3.3.0
	 *
	 * @param array $a Feed item.
	 * @param array $b Feed item.
	 * @return boolean
	 */
	private function sort_date_asc( $a, $b ) {
		return $a->get( 'date' )['date'] > $b->get( 'date' )['date'] ? 1 : -1;
	}

	/**
	 * Sorting callback for items date descending.
	 *
	 * @since 3.3.0
	 *
	 * @param array $a Feed item.
	 * @param array $b Feed item.
	 * @return boolean
	 */
	private function sort_date_desc( $a, $b ) {
		return $a->get( 'date' )['date'] <= $b->get( 'date' )['date'] ? 1 : -1;
	}
}
