<?php
/**
 * Episode identity helper.
 *
 * @link       https://easypodcastpro.com
 * @since      8.3.0
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 */

namespace Podcast_Player\Helper\Feed;

use Podcast_Player\Helper\Functions\Date_Parser;

/**
 * Generates storage-safe episode identity tokens.
 *
 * @package Podcast_Player
 */
class Episode_Identity {

	/**
	 * Build an identity array from parsed feed item values.
	 *
	 * @since 8.3.0
	 *
	 * @param array $args Episode values.
	 * @return array
	 */
	public static function generate( $args ) {
		$rss_guid  = isset( $args['rss_guid'] ) ? trim( (string) $args['rss_guid'] ) : '';
		$atom_id   = isset( $args['atom_id'] ) ? trim( (string) $args['atom_id'] ) : '';
		$title     = isset( $args['title'] ) ? (string) $args['title'] : '';
		$date      = isset( $args['date'] ) ? (string) $args['date'] : '';
		$duration  = isset( $args['duration'] ) ? $args['duration'] : '';
		$media_url = isset( $args['media_url'] ) ? (string) $args['media_url'] : '';

		if ( $rss_guid ) {
			return self::shape( md5( 'epid:' . $rss_guid ), 'rss_guid', 'strong' );
		}

		if ( $atom_id ) {
			return self::shape( md5( 'epid:' . $atom_id ), 'atom_id', 'strong' );
		}

		$normalized_title = self::normalize_title( $title );
		$timestamp        = self::normalize_date( $date );
		$duration_seconds = self::normalize_duration( $duration );

		if ( $normalized_title && $timestamp && $duration_seconds ) {
			return self::shape(
				md5( 'fp:' . $normalized_title . '|' . $timestamp . '|' . $duration_seconds ),
				'fingerprint_title_date_duration',
				'weak'
			);
		}

		if ( $normalized_title && $timestamp ) {
			return self::shape(
				md5( 'fp:' . $normalized_title . '|' . $timestamp . '|' ),
				'fingerprint_title_date',
				'weak'
			);
		}

		return self::shape( md5( 'last:' . md5( $media_url ) ), 'legacy_audio_url', 'weak' );
	}

	/**
	 * Get legacy audio key for bridge comparisons.
	 *
	 * @since 8.3.0
	 *
	 * @param string $media_url Episode media URL.
	 * @return string
	 */
	public static function legacy_audio_key( $media_url ) {
		return md5( (string) $media_url );
	}

	/**
	 * Disambiguate a colliding episode UID.
	 *
	 * @since 8.3.0
	 *
	 * @param string $episode_uid Episode UID.
	 * @param string $media_url   Episode media URL.
	 * @param int    $index       Collision index.
	 * @return string
	 */
	public static function disambiguate( $episode_uid, $media_url, $index = 1 ) {
		return md5( 'dup:' . (string) $episode_uid . '|' . self::legacy_audio_key( $media_url ) . '|' . absint( $index ) );
	}

	/**
	 * Build a metadata fingerprint from already-normalized item parts.
	 *
	 * @since 8.3.0
	 *
	 * @param string     $title            Episode title.
	 * @param int|string $timestamp        Episode publish timestamp.
	 * @param int|string $duration_seconds Episode duration in seconds.
	 * @param bool       $include_duration Whether duration must be included.
	 * @return string
	 */
	public static function fingerprint_from_parts( $title, $timestamp, $duration_seconds = 0, $include_duration = true ) {
		$normalized_title = self::normalize_title( $title );
		$timestamp        = absint( $timestamp );
		$duration_seconds = self::normalize_duration( $duration_seconds );

		if ( ! $normalized_title || ! $timestamp ) {
			return '';
		}

		if ( $include_duration ) {
			if ( ! $duration_seconds ) {
				return '';
			}

			return md5( 'fp:' . $normalized_title . '|' . $timestamp . '|' . $duration_seconds );
		}

		return md5( 'fp:' . $normalized_title . '|' . $timestamp . '|' );
	}

	/**
	 * Shape identity data.
	 *
	 * @since 8.3.0
	 *
	 * @param string $uid         Episode UID.
	 * @param string $source      Identity source.
	 * @param string $reliability Identity reliability.
	 * @return array
	 */
	private static function shape( $uid, $source, $reliability ) {
		return array(
			'episode_uid'          => $uid,
			'identity_source'      => $source,
			'identity_reliability' => $reliability,
		);
	}

	/**
	 * Normalize title for fingerprinting.
	 *
	 * @since 8.3.0
	 *
	 * @param string $title Episode title.
	 * @return string
	 */
	private static function normalize_title( $title ) {
		$title = wp_strip_all_tags( html_entity_decode( (string) $title, ENT_QUOTES, get_bloginfo( 'charset' ) ) );
		$title = preg_replace( '/\s+/u', ' ', trim( $title ) );
		$title = is_string( $title ) ? $title : '';

		if ( function_exists( 'mb_strtolower' ) ) {
			return mb_strtolower( $title );
		}

		return strtolower( $title );
	}

	/**
	 * Normalize date for fingerprinting.
	 *
	 * @since 8.3.0
	 *
	 * @param string $date Episode date.
	 * @return int
	 */
	private static function normalize_date( $date ) {
		if ( ! $date ) {
			return 0;
		}

		$date_parser = new Date_Parser();
		return absint( $date_parser->parse( $date ) );
	}

	/**
	 * Normalize duration for fingerprinting.
	 *
	 * @since 8.3.0
	 *
	 * @param string|int $duration Episode duration.
	 * @return int
	 */
	private static function normalize_duration( $duration ) {
		if ( ! $duration ) {
			return 0;
		}

		if ( is_numeric( $duration ) ) {
			return absint( $duration );
		}

		$seconds = 0;
		$parts   = array_reverse( explode( ':', sanitize_text_field( (string) $duration ) ) );
		foreach ( $parts as $key => $value ) {
			$seconds += absint( $value ) * pow( 60, $key );
		}

		return absint( $seconds );
	}
}
