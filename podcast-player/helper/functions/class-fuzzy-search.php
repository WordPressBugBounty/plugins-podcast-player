<?php
/**
 * Typo-tolerant text matching for podcast episode search.
 *
 * @link       https://easypodcastpro.com
 * @since      8.2.3
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 */

namespace Podcast_Player\Helper\Functions;

/**
 * Fuzzy text matching helpers shared by the feed and imported-post search paths.
 *
 * Pure string routines with no WordPress dependencies, so they remain usable by
 * both the free and premium layers and are unit-testable in isolation.
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 * @author     vedathemes <contact@easypodcastpro.com>
 */
class Fuzzy_Search {

	/**
	 * Loosely check whether a term appears in a given string.
	 *
	 * A literal (sub)string match returns immediately. Otherwise the term and
	 * the string are tokenised and compared word-by-word using the
	 * Damerau–Levenshtein (OSA) edit distance, so typing slips such as
	 * "anual" → "annual" or "podcst" → "podcast" are still matched. Per-token
	 * similarity is length-normalised and aggregated by each query token's
	 * share of the overall query length.
	 *
	 * @since 8.2.3
	 *
	 * @param string $mstring String to search in.
	 * @param string $term    Term to be searched in the string.
	 * @return bool
	 */
	public static function matches( $mstring, $term ) {
		$mstring = self::to_lower( (string) $mstring );
		$term    = self::to_lower( (string) $term );

		// Fast path: literal (sub)string match covers exact and partial hits.
		if ( '' === $term || false !== strpos( $mstring, $term ) ) {
			return true;
		}

		// Master switch: when fuzzy matching is off, only literal matches count.
		if ( ! self::is_fuzzy_enabled() ) {
			return false;
		}

		$thresholds        = self::thresholds();
		$token_threshold   = $thresholds['token'];       // Min per-token similarity.
		$overall_threshold = $thresholds['overall'];     // Min aggregate score.
		$max_distance      = $thresholds['maxDistance']; // Max edit distance / typo.

		/*
		 * Short-query guard: edit distance is meaningless for very short terms
		 * (almost any word is "close"), so they stay literal-match only.
		 */
		if ( self::char_len( str_replace( ' ', '', $term ) ) < $thresholds['minLength'] ) {
			return false;
		}

		// Tokenise query and target on whitespace and punctuation (mb-safe).
		$string_parts = self::tokenize( $mstring );
		$term_parts   = self::tokenize( $term );
		if ( empty( $term_parts ) || empty( $string_parts ) ) {
			return false;
		}

		// Total query length (sans separators) used to weight each token.
		$term_char_len = 0;
		foreach ( $term_parts as $part ) {
			$term_char_len += self::char_len( $part );
		}
		if ( 0 === $term_char_len ) {
			return false;
		}

		// Aggregate length-weighted similarity across query tokens.
		$score = 0;
		foreach ( $term_parts as $part ) {
			$part_len = self::char_len( $part );
			$best     = 0;
			foreach ( $string_parts as $str ) {
				$dist = self::osa_distance( $str, $part, $max_distance );
				if ( $dist > $max_distance ) {
					continue;
				}
				$longest = max( self::char_len( $str ), $part_len );
				$sim     = $longest > 0 ? ( 1 - ( $dist / $longest ) ) : 0;
				if ( $sim > $best ) {
					$best = $sim;
					if ( $best >= 1 ) {
						break; // Perfect token match; cannot improve.
					}
				}
			}
			if ( $best >= $token_threshold ) {
				$score += ( $part_len / $term_char_len ) * $best;
			}
		}

		return $score >= $overall_threshold;
	}

	/**
	 * Whether typo-tolerant (fuzzy) matching is enabled.
	 *
	 * Site owners can disable fuzzy matching — reverting search to literal
	 * (sub)string matching only — via the filter:
	 *
	 *     add_filter( 'pp_search_fuzzy_enabled', '__return_false' );
	 *
	 * @since 8.2.3
	 *
	 * @return bool
	 */
	private static function is_fuzzy_enabled() {
		if ( function_exists( 'apply_filters' ) ) {
			return (bool) apply_filters( 'pp_search_fuzzy_enabled', true );
		}
		return true;
	}

	/**
	 * Resolve the fuzzy-match thresholds.
	 *
	 * Filterable via `pp_search_fuzzy_thresholds`. Missing keys fall back to the
	 * defaults, so a filter may override only the values it cares about:
	 *
	 *     add_filter( 'pp_search_fuzzy_thresholds', function ( $t ) {
	 *         $t['maxDistance'] = 1; // Stricter typo tolerance.
	 *         return $t;
	 *     } );
	 *
	 * - token       float Min per-token similarity (0–1) to count a token match.
	 * - overall     float Min aggregate score (0–1) to accept the whole string.
	 * - maxDistance int   Max edit distance still treated as a typo.
	 * - minLength   int   Min term length (sans spaces) before fuzzy applies.
	 *
	 * @since 8.2.3
	 *
	 * @return array
	 */
	private static function thresholds() {
		$defaults = array(
			'token'       => 0.72,
			'overall'     => 0.70,
			'maxDistance' => 2,
			'minLength'   => 3,
		);
		if ( function_exists( 'apply_filters' ) ) {
			$values = apply_filters( 'pp_search_fuzzy_thresholds', $defaults );
			if ( is_array( $values ) ) {
				return array_merge( $defaults, $values );
			}
		}
		return $defaults;
	}

	/**
	 * Damerau–Levenshtein distance (Optimal String Alignment variant).
	 *
	 * Counts the minimum number of single-character insertions, deletions,
	 * substitutions and adjacent transpositions required to turn one string
	 * into the other. The transposition support (over plain Levenshtein) is
	 * what makes it forgiving of common typing swaps such as "podcst" vs
	 * "podcast" or "teh" vs "the".
	 *
	 * Comparison is case-insensitive and multibyte-safe. Intended to be run on
	 * short tokens (single words), not whole titles or content bodies.
	 *
	 * @since 8.2.3
	 *
	 * @param string $first  First string.
	 * @param string $second Second string.
	 * @param int    $max    Optional. Maximum distance the caller cares about.
	 *                       When >= 0 and the strings differ in length by more
	 *                       than this, the matrix is skipped and `$max + 1` is
	 *                       returned. Use -1 (default) to always compute exactly.
	 * @return int Edit distance between the two strings.
	 */
	public static function osa_distance( $first, $second, $max = -1 ) {
		$a  = self::mb_chars( self::to_lower( (string) $first ) );
		$b  = self::mb_chars( self::to_lower( (string) $second ) );
		$la = count( $a );
		$lb = count( $b );

		// Trivial cases: distance equals the length of the non-empty string.
		if ( 0 === $la ) {
			return $lb;
		}
		if ( 0 === $lb ) {
			return $la;
		}

		// Length-difference guard: distance is at least abs( la - lb ).
		if ( $max >= 0 && abs( $la - $lb ) > $max ) {
			return $max + 1;
		}

		// Initialise the ( la + 1 ) x ( lb + 1 ) distance matrix.
		$d = array();
		for ( $i = 0; $i <= $la; $i++ ) {
			$d[ $i ]      = array_fill( 0, $lb + 1, 0 );
			$d[ $i ][ 0 ] = $i;
		}
		for ( $j = 0; $j <= $lb; $j++ ) {
			$d[ 0 ][ $j ] = $j;
		}

		for ( $i = 1; $i <= $la; $i++ ) {
			for ( $j = 1; $j <= $lb; $j++ ) {
				$cost = ( $a[ $i - 1 ] === $b[ $j - 1 ] ) ? 0 : 1;

				$d[ $i ][ $j ] = min(
					$d[ $i - 1 ][ $j ] + 1,         // Deletion.
					$d[ $i ][ $j - 1 ] + 1,         // Insertion.
					$d[ $i - 1 ][ $j - 1 ] + $cost  // Substitution.
				);

				// Adjacent transposition (the Damerau / OSA step).
				if (
					$i > 1 && $j > 1 &&
					$a[ $i - 1 ] === $b[ $j - 2 ] &&
					$a[ $i - 2 ] === $b[ $j - 1 ]
				) {
					$d[ $i ][ $j ] = min(
						$d[ $i ][ $j ],
						$d[ $i - 2 ][ $j - 2 ] + 1
					);
				}
			}
		}

		return $d[ $la ][ $lb ];
	}

	/**
	 * Lowercase a string, multibyte-aware where possible.
	 *
	 * Uses `mb_strtolower()` when the mbstring extension is available and
	 * degrades to `strtolower()` otherwise so the routine never depends on a
	 * non-guaranteed PHP extension.
	 *
	 * @since 8.2.3
	 *
	 * @param string $str String to lowercase.
	 * @return string
	 */
	private static function to_lower( $str ) {
		if ( function_exists( 'mb_strtolower' ) ) {
			return mb_strtolower( $str );
		}
		return strtolower( $str );
	}

	/**
	 * Split a string into searchable word tokens.
	 *
	 * Splits on whitespace and Unicode punctuation, with a plain-space
	 * fallback when PCRE Unicode support is unavailable.
	 *
	 * @since 8.2.3
	 *
	 * @param string $str String to tokenise.
	 * @return array List of non-empty tokens.
	 */
	private static function tokenize( $str ) {
		$parts = preg_split( '/[\s\p{P}]+/u', $str, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $parts ) ) {
			$parts = array_values( array_filter( explode( ' ', $str ) ) );
		}
		return $parts;
	}

	/**
	 * Count characters in a string, multibyte-aware where possible.
	 *
	 * @since 8.2.3
	 *
	 * @param string $str String to measure.
	 * @return int
	 */
	private static function char_len( $str ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $str );
		}
		return strlen( $str );
	}

	/**
	 * Split a string into an array of (multibyte) characters.
	 *
	 * Wraps `mb_str_split()` where available (PHP 7.4+) and falls back to a
	 * UTF-8 aware `preg_split()` so the plugin's PHP 5.6 floor is respected.
	 *
	 * @since 8.2.3
	 *
	 * @param string $str String to split.
	 * @return array Array of single characters.
	 */
	private static function mb_chars( $str ) {
		if ( '' === $str ) {
			return array();
		}
		if ( function_exists( 'mb_str_split' ) ) {
			return mb_str_split( $str );
		}
		$chars = preg_split( '//u', $str, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $chars ) ? $chars : array();
	}
}
