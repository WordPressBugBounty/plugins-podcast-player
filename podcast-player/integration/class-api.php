<?php
/**
 * Public integration API for other podcast plugins.
 *
 * @link    https://easypodcastpro.com
 * @since   8.1.1
 *
 * @package Podcast_Player
 */

namespace Podcast_Player\Integration;

use Podcast_Player\Backend\Admin\ShortCodeGen;
use Podcast_Player\Helper\Feed\Fetch_Feed;
use Podcast_Player\Helper\Functions\Getters as Get_Fn;
use Podcast_Player\Helper\Functions\Validation as Validation_Fn;
use Podcast_Player\Helper\Store\FeedData;
use Podcast_Player\Helper\Store\StorageRegister;
use Podcast_Player\Helper\Store\StoreManager;

/**
 * Public integration API.
 *
 * This class intentionally keeps the integration surface small and stable so
 * hosting plugins can hand Podcast Player a canonical feed, defaults, and
 * refresh requests without depending on admin-only implementation details.
 *
 * @since 8.1.1
 */
class Api {

	/**
	 * Check if Podcast Player is available.
	 *
	 * @since 8.1.1
	 *
	 * @return bool
	 */
	public static function is_available() {
		return defined( 'PODCAST_PLAYER_VERSION' );
	}

	/**
	 * Get active Podcast Player version.
	 *
	 * @since 8.1.1
	 *
	 * @return string
	 */
	public static function get_version() {
		return defined( 'PODCAST_PLAYER_VERSION' ) ? PODCAST_PLAYER_VERSION : '';
	}

	/**
	 * Register or update a feed owned by an external plugin.
	 *
	 * @since 8.1.1
	 *
	 * @param string $external_key Stable external key.
	 * @param string $feed_url     Public podcast feed URL.
	 * @param string $title        Podcast title.
	 * @param array  $args         Optional integration metadata.
	 * @return array|\WP_Error
	 */
	public static function register_feed( $external_key, $feed_url, $title = '', $args = array() ) {
		$external_key = self::sanitize_external_key( $external_key );
		$feed_url     = esc_url_raw( $feed_url );

		if ( ! $external_key ) {
			return new \WP_Error( 'missing_key', __( 'A stable feed key is required.', 'podcast-player' ) );
		}

		if ( ! Validation_Fn::is_valid_url( $feed_url ) ) {
			return new \WP_Error( 'invalid_feed_url', __( 'A valid podcast feed URL is required.', 'podcast-player' ) );
		}

		$store = StoreManager::get_instance();
		$store->maybe_add_new_object( $external_key, sanitize_text_field( $title ) );
		$index = $store->get_object_index( $external_key );

		if ( ! $index instanceof StorageRegister ) {
			return new \WP_Error( 'register_failed', __( 'Podcast Player could not create a feed record.', 'podcast-player' ) );
		}

		if ( $feed_url !== $index->get( 'source_url', 'none' ) ) {
			$store->add_podcast_source_url( $external_key, $feed_url );
			$index = $store->get_object_index( $external_key );
		}

		$metadata = array(
			'owner'       => isset( $args['owner'] ) ? sanitize_key( $args['owner'] ) : '',
			'external_id' => isset( $args['external_id'] ) ? sanitize_text_field( $args['external_id'] ) : '',
			'source'      => isset( $args['source'] ) ? sanitize_key( $args['source'] ) : '',
			'privacy'     => isset( $args['privacy'] ) ? sanitize_key( $args['privacy'] ) : 'public',
			'managed'     => isset( $args['managed'] ) ? (bool) $args['managed'] : true,
			'updated_at'  => time(),
		);
		$store->update_data( array_filter( $metadata ), $external_key, 'integration_meta' );

		$result = array(
			'external_key' => $external_key,
			'render_key'   => self::get_render_key( $external_key ),
			'object_id'    => $index->get( 'object_id' ),
			'source_url'   => $feed_url,
			'title'        => $index->get( 'title', 'none' ),
		);

		/**
		 * Fires after an external plugin registers or updates a feed.
		 *
		 * @since 8.1.1
		 *
		 * @param array $result Registration result.
		 * @param array $args   Integration metadata.
		 */
		do_action( 'podcast_player_after_feed_registered', $result, $args );

		return $result;
	}

	/**
	 * Resolve a stable external key to the key that should be passed to display APIs.
	 *
	 * @since 8.1.1
	 *
	 * @param string $external_key Stable external key.
	 * @return string
	 */
	public static function get_render_key( $external_key ) {
		$external_key = self::sanitize_external_key( $external_key );

		return apply_filters( 'podcast_player_integration_render_key', $external_key, $external_key );
	}

	/**
	 * Find a Podcast Player feed record by source or alias URL.
	 *
	 * @since 8.1.1
	 *
	 * @param string $url Feed URL.
	 * @return array|false
	 */
	public static function find_feed_by_url( $url ) {
		$url = esc_url_raw( $url );

		if ( ! Validation_Fn::is_valid_url( $url ) ) {
			return false;
		}

		$store = StoreManager::get_instance();
		foreach ( $store->get_object_index() as $key => $index ) {
			if ( ! $index instanceof StorageRegister ) {
				continue;
			}

			if ( self::urls_match( $url, $index->get( 'source_url', 'none' ) ) ) {
				return self::format_index_result( $key, $index );
			}

			$urls = $index->get( 'feed_url', 'none' );
			if ( ! is_array( $urls ) ) {
				continue;
			}

			foreach ( $urls as $alias ) {
				if ( self::urls_match( $url, $alias ) ) {
					return self::format_index_result( $key, $index );
				}
			}
		}

		return false;
	}

	/**
	 * Refresh a feed cache.
	 *
	 * @since 8.1.1
	 *
	 * @param string $key  Feed URL, register key, or external key.
	 * @param string $mode soft|hard.
	 * @return bool|\WP_Error
	 */
	public static function refresh_feed( $key, $mode = 'soft' ) {
		$key = Validation_Fn::is_valid_url( $key ) ? esc_url_raw( $key ) : self::sanitize_external_key( $key );

		if ( ! $key ) {
			return new \WP_Error( 'missing_key', __( 'A feed key is required.', 'podcast-player' ) );
		}

		$store = StoreManager::get_instance();
		if ( 'hard' === $mode ) {
			$store->delete_data( $key, array( 'feed_data', 'last_checked' ) );
		} else {
			$store->delete_data( $key, 'last_checked' );
		}

		$data = Get_Fn::get_feed_data( $key );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		/**
		 * Fires after a feed refresh is requested through the integration API.
		 *
		 * @since 8.1.1
		 *
		 * @param string $key  Feed key.
		 * @param string $mode Refresh mode.
		 */
		do_action( 'podcast_player_after_feed_refreshed', $key, $mode );

		return true;
	}

	/**
	 * Save Podcast Player defaults for a feed.
	 *
	 * @since 8.1.1
	 *
	 * @param string $key      Feed URL, register key, or external key.
	 * @param array  $defaults Display defaults.
	 * @return array
	 */
	public static function save_defaults( $key, array $defaults ) {
		return Get_Fn::save_podcast_defaults( $key, $defaults );
	}

	/**
	 * Get Podcast Player defaults for a feed.
	 *
	 * @since 8.2.0
	 *
	 * @param string $key Feed URL, register key, or external key.
	 * @return array
	 */
	public static function get_defaults( $key ) {
		return Get_Fn::get_podcast_defaults( $key );
	}

	/**
	 * Delete Podcast Player defaults for a feed.
	 *
	 * @since 8.1.1
	 *
	 * @param string $key Feed URL, register key, or external key.
	 * @return bool
	 */
	public static function delete_defaults( $key ) {
		return Get_Fn::delete_podcast_defaults( $key );
	}

	/**
	 * Create or update a managed saved shortcode.
	 *
	 * @since 8.1.1
	 *
	 * @param string $external_id Stable owner ID.
	 * @param array  $settings    Shortcode generator settings.
	 * @return int|\WP_Error
	 */
	public static function create_or_update_shortcode( $external_id, array $settings ) {
		$external_id = self::sanitize_external_key( $external_id );

		if ( ! $external_id ) {
			return new \WP_Error( 'missing_id', __( 'A managed shortcode ID is required.', 'podcast-player' ) );
		}

		$managed   = get_option( 'pp_managed_shortcodes', array() );
		$managed   = is_array( $managed ) ? $managed : array();
		$instance  = isset( $managed[ $external_id ] ) ? absint( $managed[ $external_id ] ) : 0;
		$generator = new ShortCodeGen();
		$old       = $instance && isset( $generator->shortcode_settings[ $instance ] ) ? $generator->shortcode_settings[ $instance ] : array();

		if ( ! $instance ) {
			$instance = empty( $generator->shortcode_settings ) ? 1 : max( array_map( 'absint', array_keys( $generator->shortcode_settings ) ) ) + 1;
		}

		$settings                           = $generator->sanitize( $settings, $old );
		$settings['_managed_by']           = 'integration';
		$settings['_external_id']          = $external_id;
		$generator->shortcode_settings[ $instance ] = $settings;
		$generator->save();

		$managed[ $external_id ] = $instance;
		update_option( 'pp_managed_shortcodes', $managed, false );

		/**
		 * Fires after a managed shortcode is saved.
		 *
		 * @since 8.1.1
		 *
		 * @param int    $instance    Shortcode instance ID.
		 * @param string $external_id Managed shortcode external ID.
		 * @param array  $settings    Saved settings.
		 */
		do_action( 'podcast_player_managed_shortcode_saved', $instance, $external_id, $settings );
		do_action( 'podcast_player_managed_shortcode_updated', $instance, $external_id, $settings );

		return $instance;
	}

	/**
	 * Delete a managed saved shortcode.
	 *
	 * @since 8.1.1
	 *
	 * @param string $external_id Stable owner ID.
	 * @return bool
	 */
	public static function delete_managed_shortcode( $external_id ) {
		$external_id = self::sanitize_external_key( $external_id );
		$managed     = get_option( 'pp_managed_shortcodes', array() );

		if ( ! $external_id || ! is_array( $managed ) || empty( $managed[ $external_id ] ) ) {
			return false;
		}

		$instance  = absint( $managed[ $external_id ] );
		$generator = new ShortCodeGen();
		unset( $generator->shortcode_settings[ $instance ], $managed[ $external_id ] );
		$generator->save();
		update_option( 'pp_managed_shortcodes', $managed, false );

		return true;
	}

	/**
	 * Move an existing Podcast Player feed record to a new canonical source URL.
	 *
	 * @since 8.1.1
	 *
	 * @param string $existing_key   Existing feed key or URL.
	 * @param string $new_source_url New canonical feed URL.
	 * @param array  $args           Validation options.
	 * @return bool|\WP_Error
	 */
	public static function migrate_source( $existing_key, $new_source_url, array $args = array() ) {
		$existing_key   = $existing_key ? sanitize_text_field( $existing_key ) : '';
		$new_source_url = esc_url_raw( $new_source_url );

		if ( ! $existing_key || ! Validation_Fn::is_valid_url( $new_source_url ) ) {
			return new \WP_Error( 'invalid_migration_source', __( 'A valid existing feed key and new feed URL are required.', 'podcast-player' ) );
		}

		$args = wp_parse_args(
			$args,
			array(
				'validate'       => true,
				'allow_mismatch' => false,
			)
		);

		$store    = StoreManager::get_instance();
		$index    = $store->get_object_index( $existing_key );
		$old_data = $store->get_data( $existing_key );

		if ( ! $index instanceof StorageRegister ) {
			return new \WP_Error( 'missing_existing_feed', __( 'The existing Podcast Player feed could not be found.', 'podcast-player' ) );
		}

		if ( $args['validate'] ) {
			if ( ! $old_data ) {
				return new \WP_Error( 'missing_existing_feed_data', __( 'The existing Podcast Player feed data could not be found.', 'podcast-player' ) );
			}

			$fetcher  = new Fetch_Feed( $new_source_url );
			$new_data = $fetcher->get_feed_data();

			if ( is_wp_error( $new_data ) ) {
				return $new_data;
			}

			if ( ! $args['allow_mismatch'] && $old_data instanceof FeedData && $new_data instanceof FeedData ) {
				$old_title = trim( strtolower( (string) $old_data->get( 'title' ) ) );
				$new_title = trim( strtolower( (string) $new_data->get( 'title' ) ) );
				$old_total = absint( $old_data->get( 'total' ) );
				$new_total = absint( $new_data->get( 'total' ) );

				if ( $old_title && $new_title && $old_title !== $new_title && abs( $old_total - $new_total ) > 1 ) {
					return new \WP_Error( 'feed_mismatch', __( 'The new feed does not look like the same podcast.', 'podcast-player' ) );
				}
			}
		}

		$allow = apply_filters( 'podcast_player_before_source_migration', true, $existing_key, $new_source_url, $old_data, $args );
		if ( is_wp_error( $allow ) || ! $allow ) {
			return is_wp_error( $allow ) ? $allow : new \WP_Error( 'migration_blocked', __( 'The source migration was blocked.', 'podcast-player' ) );
		}

		$updated = $store->add_podcast_source_url( $existing_key, $new_source_url );
		if ( ! $updated ) {
			return new \WP_Error( 'source_update_failed', __( 'Podcast Player could not update the feed source.', 'podcast-player' ) );
		}

		do_action( 'podcast_player_after_source_migration', $existing_key, $new_source_url, $args );

		return true;
	}

	/**
	 * Get Podcast Player feed index.
	 *
	 * @since 8.1.1
	 *
	 * @return array
	 */
	public static function get_feed_index() {
		return Get_Fn::get_feed_index();
	}

	/**
	 * Format a storage index result for integrations.
	 *
	 * @since 8.1.1
	 *
	 * @param string          $key   Register array key.
	 * @param StorageRegister $index Register object.
	 * @return array
	 */
	private static function format_index_result( $key, StorageRegister $index ) {
		return array(
			'key'        => $key,
			'render_key' => $key,
			'object_id'  => $index->get( 'object_id', 'none' ),
			'title'      => $index->get( 'title', 'none' ),
			'source_url' => $index->get( 'source_url', 'none' ),
		);
	}

	/**
	 * Sanitize integration keys without requiring them to be URLs.
	 *
	 * @since 8.1.1
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	private static function sanitize_external_key( $key ) {
		$key = strtolower( trim( (string) $key ) );
		$key = preg_replace( '/[^a-z0-9_.:-]/', '-', $key );
		$key = preg_replace( '/-+/', '-', $key );

		return trim( $key, '-' );
	}

	/**
	 * Compare two URLs with a permissive trailing slash fallback.
	 *
	 * @since 8.1.1
	 *
	 * @param string $left  First URL.
	 * @param string $right Second URL.
	 * @return bool
	 */
	private static function urls_match( $left, $right ) {
		$left  = esc_url_raw( $left );
		$right = esc_url_raw( $right );

		if ( ! $left || ! $right ) {
			return false;
		}

		return $left === $right || untrailingslashit( $left ) === untrailingslashit( $right );
	}
}
