<?php
/**
 * Escape data and prepare to send to frontend.
 *
 * @link       https://www.vedathemes.com
 * @since      1.0.0
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 */

namespace Podcast_Player\Helper\Feed;

use Podcast_Player\Helper\Functions\Getters as Get_Fn;
use Podcast_Player\Helper\Core\Singleton;

/**
 * Escape data and prepare to send to frontend.
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 * @author     vedathemes <contact@vedathemes.com>
 */
class Prepare_Front_New extends Singleton {

	/**
	 * Constructor method.
	 *
	 * @since  3.3.0
	 *
	 * @param array $data Fetched feed data to be sent to frontend.
	 */
	public function init( $data ) {
		$items = $data['items'];
		array_walk(
			$items,
			function ( &$item, $key ) {
				$item = $this->resolve_media_source( $item );
				if ( isset( $item['date'] ) ) {
					$item['timestamp'] = $this->timestamp( $item['date'] );
					$item['date']      = $this->date( $item['date'] );
				}
			}
		);
		$data['items'] = $items;
		unset( $data['legacy_key_by_uid'] );
		return $data;
	}

	/**
	 * Resolve the active media source for frontend playback.
	 *
	 * @since 8.2.0
	 *
	 * @param array $item Feed item data.
	 * @return array
	 */
	private function resolve_media_source( $item ) {
		if ( empty( $item['media_sources'] ) || ! is_array( $item['media_sources'] ) ) {
			$item['is_hls']      = Get_Fn::is_hls_source( isset( $item['src'] ) ? $item['src'] : '' ) ? 1 : 0;
			$item['source_mime'] = '';
			return $item;
		}

		$primary = $this->get_primary_source( $item );
		$source  = $primary;

		if ( ! empty( $primary ) && 'hls' === $primary['format'] ) {
			$source = $primary;
		} elseif ( 'yes' === Get_Fn::get_plugin_option( 'prefer_hls_streams' ) ) {
			$hls = $this->get_preferred_hls_source( $item['media_sources'] );
			if ( ! empty( $hls ) ) {
				$source = $hls;
			}
		}

		if ( empty( $source['url'] ) ) {
			unset( $item['media_sources'] );
			return $item;
		}

		$item['src']           = $source['url'];
		$item['mediatype']     = ! empty( $source['media_type'] ) ? $source['media_type'] : Get_Fn::get_media_type( $source['url'], isset( $source['type'] ) ? $source['type'] : '' );
		$item['source_mime']   = isset( $source['type'] ) ? $source['type'] : '';
		$item['source_format'] = isset( $source['format'] ) ? $source['format'] : '';
		$item['is_hls']        = isset( $source['format'] ) && 'hls' === $source['format'] ? 1 : 0;
		unset( $item['media_sources'] );

		return $item;
	}

	/**
	 * Get primary enclosure source from item sources.
	 *
	 * @since 8.2.0
	 *
	 * @param array $item Feed item data.
	 * @return array
	 */
	private function get_primary_source( $item ) {
		foreach ( $item['media_sources'] as $source ) {
			if ( isset( $source['rel'] ) && 'enclosure' === $source['rel'] ) {
				return $source;
			}
		}

		foreach ( $item['media_sources'] as $source ) {
			if ( ! empty( $source['url'] ) && isset( $item['src'] ) && $source['url'] === $item['src'] ) {
				return $source;
			}
		}

		return reset( $item['media_sources'] );
	}

	/**
	 * Get preferred HLS source from available media sources.
	 *
	 * @since 8.2.0
	 *
	 * @param array $sources Available media sources.
	 * @return array
	 */
	private function get_preferred_hls_source( $sources ) {
		$hls_sources = array_values(
			array_filter(
				$sources,
				function ( $source ) {
					return isset( $source['format'] ) && 'hls' === $source['format'];
				}
			)
		);

		if ( empty( $hls_sources ) ) {
			return array();
		}

		$locale = function_exists( 'get_locale' ) ? strtolower( str_replace( '_', '-', get_locale() ) ) : '';
		usort(
			$hls_sources,
			function ( $a, $b ) use ( $locale ) {
				$a_default = ! empty( $a['default'] ) ? 1 : 0;
				$b_default = ! empty( $b['default'] ) ? 1 : 0;
				if ( $a_default !== $b_default ) {
					return $b_default - $a_default;
				}

				$a_lang = ! empty( $a['lang'] ) && $locale && 0 === strpos( $locale, strtolower( $a['lang'] ) ) ? 1 : 0;
				$b_lang = ! empty( $b['lang'] ) && $locale && 0 === strpos( $locale, strtolower( $b['lang'] ) ) ? 1 : 0;
				if ( $a_lang !== $b_lang ) {
					return $b_lang - $a_lang;
				}

				$a_quality = max( absint( isset( $a['height'] ) ? $a['height'] : 0 ), absint( isset( $a['bitrate'] ) ? $a['bitrate'] : 0 ) );
				$b_quality = max( absint( isset( $b['height'] ) ? $b['height'] : 0 ), absint( isset( $b['bitrate'] ) ? $b['bitrate'] : 0 ) );
				return $b_quality - $a_quality;
			}
		);

		return reset( $hls_sources );
	}

	/**
	 * Escape feed item date.
	 *
	 * @since  3.3.0
	 *
	 * @param string|Array $val Feed item date.
	 */
	public function date( $val ) {
		$timezone = Get_Fn::get_plugin_option( 'timezone' );
		$date     = is_array( $val ) ? $val['date'] : $val;
		$date     = $date ? $date : 0;
		$offset   = is_array( $val ) ? $val['offset'] : 0;

		if ( 'local' === $timezone ) {
			return date_i18n( get_option( 'date_format' ), $date + 60 * 60 * get_option( 'gmt_offset' ) );
		} elseif ( 'feed' === $timezone ) {
			return date_i18n( get_option( 'date_format' ), $date + $offset );
		} else {
			return date_i18n( get_option( 'date_format' ), $date );
		}
	}

	/**
	 * Escape feed item date.
	 *
	 * @since  6.7.0
	 *
	 * @param string|Array $val Feed item date.
	 */
	public function timestamp( $val ) {
		$timezone = Get_Fn::get_plugin_option( 'timezone' );
		$date     = is_array( $val ) ? $val['date'] : $val;
		$date     = $date ? $date : 0;
		$offset   = is_array( $val ) ? $val['offset'] : 0;

		if ( 'local' === $timezone ) {
			return $date + 60 * 60 * get_option( 'gmt_offset' );
		} elseif ( 'feed' === $timezone ) {
			return $date + $offset;
		} else {
			return $date;
		}
	}
}
