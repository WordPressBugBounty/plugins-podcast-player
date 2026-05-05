<?php
/**
 * Podcast player utility functions.
 *
 * @link       https://www.vedathemes.com
 * @since      3.3.0
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 */

namespace Podcast_Player\Helper\Functions;

use Podcast_Player\Helper\Functions\Getters as Get_Fn;
use Podcast_Player\Helper\Functions\Validation as Validation_Fn;
use Podcast_Player\Helper\Store\StoreManager;
use Podcast_Player\Helper\Store\FeedData;
use Podcast_Player\Helper\Store\ItemData;

/**
 * Podcast player utility functions.
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 * @author     vedathemes <contact@vedathemes.com>
 */
class Utility {

	/**
	 * Active import attempt for the current request.
	 *
	 * Used by the shutdown handler to mark scoped import failures.
	 *
	 * @since 7.9.16
	 * @access private
	 * @var array
	 */
	private static $current_import_attempt = array();

	/**
	 * Prevent registering multiple shutdown handlers in the same request.
	 *
	 * @since 7.9.16
	 * @access private
	 * @var bool
	 */
	private static $import_shutdown_registered = false;

	/**
	 * Request ID shared by all episode import attempts in this request.
	 *
	 * @since 7.9.16
	 * @access private
	 * @var string
	 */
	private static $import_request_id = '';

	/**
	 * Constructor method.
	 *
	 * @since  3.3.0
	 */
	public function __construct() {}

	/**
	 * Register a scoped shutdown handler for fatal import failures.
	 *
	 * @since 7.9.16
	 */
	private static function register_import_shutdown_handler() {
		if ( self::$import_shutdown_registered ) {
			return;
		}

		self::$import_shutdown_registered = true;
		register_shutdown_function( array( __CLASS__, 'handle_import_shutdown' ) );
	}

	/**
	 * Handle fatal errors only when an episode import operation is active.
	 *
	 * @since 7.9.16
	 */
	public static function handle_import_shutdown() {
		if ( empty( self::$current_import_attempt ) ) {
			return;
		}

		$error = error_get_last();
		if ( empty( $error['type'] ) || ! in_array( $error['type'], self::fatal_error_types(), true ) ) {
			return;
		}

		$error_file = isset( $error['file'] ) ? self::normalize_path( $error['file'] ) : '';
		$plugin_dir = defined( 'PODCAST_PLAYER_DIR' ) ? self::normalize_path( PODCAST_PLAYER_DIR ) : '';
		$phase      = isset( self::$current_import_attempt['phase'] ) ? self::$current_import_attempt['phase'] : '';

		$direct_plugin_error = $plugin_dir && $error_file && 0 === strpos( $error_file, $plugin_dir );
		$during_import_phase = in_array( $phase, self::risky_import_phases(), true );
		if ( ! $direct_plugin_error && ! $during_import_phase ) {
			return;
		}

		self::mark_current_import_attempt_failed(
			array(
				'reason'  => $direct_plugin_error ? 'plugin_fatal' : 'fatal_during_import',
				'message' => isset( $error['message'] ) ? sanitize_text_field( $error['message'] ) : '',
				'file'    => $error_file,
				'line'    => isset( $error['line'] ) ? absint( $error['line'] ) : 0,
				'phase'   => sanitize_text_field( $phase ),
			)
		);
	}

	/**
	 * Fatal error types that can leave an import half-finished.
	 *
	 * @since 7.9.16
	 */
	private static function fatal_error_types() {
		return array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR );
	}

	/**
	 * Import phases where a third-party fatal still affects the import result.
	 *
	 * @since 7.9.16
	 */
	private static function risky_import_phases() {
		return array(
			'fetching_feed_data',
			'resolving_existing_post',
			'creating_post',
			'saving_import_mapping',
			'saving_post_meta',
			'saving_featured_image',
			'saving_terms',
			'saving_modified_feed_data',
		);
	}

	/**
	 * Normalize a file path without assuming all WP helpers are loaded.
	 *
	 * @since 7.9.16
	 *
	 * @param string $path File path.
	 */
	private static function normalize_path( $path ) {
		if ( function_exists( 'wp_normalize_path' ) ) {
			return wp_normalize_path( $path );
		}
		return str_replace( '\\', '/', (string) $path );
	}

	/**
	 * Get a stable request ID for import attempts.
	 *
	 * @since 7.9.16
	 */
	private static function get_import_request_id() {
		if ( ! self::$import_request_id ) {
			self::$import_request_id = uniqid( 'pp-import-', true );
		}
		return self::$import_request_id;
	}

	/**
	 * Get the storage post ID for a feed.
	 *
	 * @since 7.9.16
	 *
	 * @param string $feed_key Podcast feed key.
	 */
	private static function get_feed_storage_post_id( $feed_key ) {
		$store_manager = StoreManager::get_instance();
		$index         = $store_manager->get_object_index( $feed_key );
		if ( ! $index || ! is_object( $index ) || ! method_exists( $index, 'get' ) ) {
			return 0;
		}
		return absint( $index->get( 'object_id' ) );
	}

	/**
	 * Build the per-episode import state meta key.
	 *
	 * @since 7.9.16
	 *
	 * @param string $episode_key Feed episode key.
	 */
	private static function get_episode_import_state_key( $episode_key ) {
		return 'pp_import_episode_' . md5( (string) $episode_key );
	}

	/**
	 * Read per-episode import state.
	 *
	 * @since 7.9.16
	 *
	 * @param string $feed_key    Podcast feed key.
	 * @param string $episode_key Feed episode key.
	 */
	private static function get_episode_import_state( $feed_key, $episode_key ) {
		$object_id = self::get_feed_storage_post_id( $feed_key );
		if ( ! $object_id ) {
			return array();
		}

		$state = get_post_meta( $object_id, self::get_episode_import_state_key( $episode_key ), true );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Persist per-episode import state.
	 *
	 * This intentionally stores only small scalar identifiers, never episode descriptions.
	 *
	 * @since 7.9.16
	 *
	 * @param string $feed_key    Podcast feed key.
	 * @param string $episode_key Feed episode key.
	 * @param array  $state       State data.
	 */
	private static function update_episode_import_state( $feed_key, $episode_key, $state ) {
		$object_id = self::get_feed_storage_post_id( $feed_key );
		if ( ! $object_id ) {
			return false;
		}

		return update_post_meta( $object_id, self::get_episode_import_state_key( $episode_key ), $state );
	}

	/**
	 * Start or refresh an import attempt for an episode.
	 *
	 * @since 7.9.16
	 *
	 * @param string $feed_key    Podcast feed key.
	 * @param string $episode_key Feed episode key.
	 * @param array  $item        Optional feed item data.
	 * @param string $phase       Current import phase.
	 */
	private static function start_episode_import_attempt( $feed_key, $episode_key, $item = array(), $phase = 'starting' ) {
		$existing   = self::get_episode_import_state( $feed_key, $episode_key );
		$request_id = self::get_import_request_id();
		$attempts   = isset( $existing['attempts'] ) ? absint( $existing['attempts'] ) : 0;
		if ( empty( $existing['request_id'] ) || $request_id !== $existing['request_id'] ) {
			++$attempts;
		}

		$state = array(
			'feed_key'    => sanitize_text_field( $feed_key ),
			'episode_key' => sanitize_text_field( $episode_key ),
			'episode_id'  => isset( $item['episode_id'] ) ? sanitize_text_field( $item['episode_id'] ) : ( isset( $existing['episode_id'] ) ? sanitize_text_field( $existing['episode_id'] ) : '' ),
			'src_hash'    => isset( $item['src'] ) ? md5( esc_url_raw( $item['src'] ) ) : ( isset( $existing['src_hash'] ) ? sanitize_text_field( $existing['src_hash'] ) : '' ),
			'post_id'     => isset( $existing['post_id'] ) ? absint( $existing['post_id'] ) : 0,
			'status'      => 'processing',
			'phase'       => sanitize_text_field( $phase ),
			'attempts'    => $attempts,
			'request_id'  => $request_id,
			'started_at'  => isset( $existing['started_at'] ) && $request_id === ( isset( $existing['request_id'] ) ? $existing['request_id'] : '' ) ? absint( $existing['started_at'] ) : time(),
			'updated_at'  => time(),
			'last_error'  => array(),
		);

		self::update_episode_import_state( $feed_key, $episode_key, $state );
		self::set_current_import_attempt( $feed_key, array( $episode_key ), $phase );
		return $state;
	}

	/**
	 * Mark multiple episodes as processing for work that happens before item data is available.
	 *
	 * @since 7.9.16
	 *
	 * @param string $feed_key Podcast feed key.
	 * @param array  $elist    Feed episode keys.
	 * @param string $phase    Current import phase.
	 */
	private static function start_batch_import_attempts( $feed_key, $elist, $phase ) {
		foreach ( (array) $elist as $episode_key ) {
			self::start_episode_import_attempt( $feed_key, $episode_key, array(), $phase );
		}
		self::set_current_import_attempt( $feed_key, $elist, $phase );
	}

	/**
	 * Set the active import attempt context for fatal error handling.
	 *
	 * @since 7.9.16
	 *
	 * @param string $feed_key Podcast feed key.
	 * @param array  $elist    Feed episode keys.
	 * @param string $phase    Current import phase.
	 */
	private static function set_current_import_attempt( $feed_key, $elist, $phase ) {
		self::$current_import_attempt = array(
			'feed_key'     => sanitize_text_field( $feed_key ),
			'episode_keys' => array_values( array_map( 'sanitize_text_field', (array) $elist ) ),
			'phase'        => sanitize_text_field( $phase ),
		);
	}

	/**
	 * Update the current import phase.
	 *
	 * @since 7.9.16
	 *
	 * @param string $feed_key    Podcast feed key.
	 * @param string $episode_key Feed episode key.
	 * @param string $phase       Current import phase.
	 */
	private static function set_episode_import_phase( $feed_key, $episode_key, $phase ) {
		$state = self::get_episode_import_state( $feed_key, $episode_key );
		if ( $state ) {
			$state['phase']      = sanitize_text_field( $phase );
			$state['updated_at'] = time();
			self::update_episode_import_state( $feed_key, $episode_key, $state );
		}
		self::set_current_import_attempt( $feed_key, array( $episode_key ), $phase );
	}

	/**
	 * Clear the active import attempt context.
	 *
	 * @since 7.9.16
	 */
	private static function clear_current_import_attempt() {
		self::$current_import_attempt = array();
	}

	/**
	 * Mark the active import attempt as failed.
	 *
	 * @since 7.9.16
	 *
	 * @param array $error Error details.
	 */
	private static function mark_current_import_attempt_failed( $error ) {
		$current = self::$current_import_attempt;
		if ( empty( $current['feed_key'] ) || empty( $current['episode_keys'] ) ) {
			return;
		}

		foreach ( $current['episode_keys'] as $episode_key ) {
			self::mark_episode_import_failed( $current['feed_key'], $episode_key, $error );
		}
	}

	/**
	 * Mark an episode import as failed.
	 *
	 * @since 7.9.16
	 *
	 * @param string $feed_key    Podcast feed key.
	 * @param string $episode_key Feed episode key.
	 * @param array  $error       Error details.
	 */
	private static function mark_episode_import_failed( $feed_key, $episode_key, $error ) {
		$state = self::get_episode_import_state( $feed_key, $episode_key );
		if ( empty( $state ) ) {
			$state = array(
				'feed_key'    => sanitize_text_field( $feed_key ),
				'episode_key' => sanitize_text_field( $episode_key ),
				'attempts'    => 1,
			);
		}

		$state['status']     = 'failed';
		$state['phase']      = isset( $error['phase'] ) ? sanitize_text_field( $error['phase'] ) : ( isset( $state['phase'] ) ? sanitize_text_field( $state['phase'] ) : '' );
		$state['updated_at'] = time();
		$state['last_error'] = array(
			'reason'  => isset( $error['reason'] ) ? sanitize_text_field( $error['reason'] ) : 'import_failed',
			'message' => isset( $error['message'] ) ? sanitize_text_field( $error['message'] ) : '',
			'file'    => isset( $error['file'] ) ? sanitize_text_field( $error['file'] ) : '',
			'line'    => isset( $error['line'] ) ? absint( $error['line'] ) : 0,
		);

		self::update_episode_import_state( $feed_key, $episode_key, $state );
	}

	/**
	 * Mark an episode import as successfully mapped to a post.
	 *
	 * @since 7.9.16
	 *
	 * @param string $feed_key    Podcast feed key.
	 * @param string $episode_key Feed episode key.
	 * @param int    $post_id     Imported post ID.
	 * @param array  $item        Feed item data.
	 */
	private static function mark_episode_imported( $feed_key, $episode_key, $post_id, $item = array() ) {
		$state = self::get_episode_import_state( $feed_key, $episode_key );
		if ( empty( $state ) ) {
			$state = array(
				'feed_key'    => sanitize_text_field( $feed_key ),
				'episode_key' => sanitize_text_field( $episode_key ),
				'attempts'    => 1,
			);
		}

		$state['episode_id'] = isset( $item['episode_id'] ) ? sanitize_text_field( $item['episode_id'] ) : ( isset( $state['episode_id'] ) ? sanitize_text_field( $state['episode_id'] ) : '' );
		$state['src_hash']   = isset( $item['src'] ) ? md5( esc_url_raw( $item['src'] ) ) : ( isset( $state['src_hash'] ) ? sanitize_text_field( $state['src_hash'] ) : '' );
		$state['post_id']    = absint( $post_id );
		$state['status']     = 'imported';
		$state['phase']      = 'complete';
		$state['updated_at'] = time();
		$state['last_error'] = array();

		self::update_episode_import_state( $feed_key, $episode_key, $state );
	}

	/**
	 * Get a valid imported post ID from the minimal import state.
	 *
	 * @since 7.9.16
	 *
	 * @param string $feed_key    Podcast feed key.
	 * @param string $episode_key Feed episode key.
	 */
	private static function get_imported_post_id_from_state( $feed_key, $episode_key ) {
		$state   = self::get_episode_import_state( $feed_key, $episode_key );
		$post_id = ! empty( $state['post_id'] ) ? absint( $state['post_id'] ) : 0;
		if ( $post_id && false !== get_post_status( $post_id ) ) {
			return $post_id;
		}
		return 0;
	}

	/**
	 * Skip repeated failed auto-imports while still allowing manual retries.
	 *
	 * @since 7.9.16
	 *
	 * @param string $feed_key        Podcast feed key.
	 * @param string $episode_key     Feed episode key.
	 * @param array  $import_settings Import settings.
	 */
	private static function should_skip_auto_import_retry( $feed_key, $episode_key, $import_settings ) {
		$source = isset( $import_settings['import_source'] ) ? sanitize_text_field( $import_settings['import_source'] ) : '';
		if ( 'background' !== $source ) {
			return false;
		}

		$state = self::get_episode_import_state( $feed_key, $episode_key );
		if ( ! empty( $state['post_id'] ) && false !== get_post_status( absint( $state['post_id'] ) ) ) {
			return false;
		}
		$attempts = ! empty( $state['attempts'] ) ? absint( $state['attempts'] ) : 0;
		if ( ! empty( $state['status'] ) && 'failed' === $state['status'] && 3 <= $attempts ) {
			return true;
		}

		$updated_at = ! empty( $state['updated_at'] ) ? absint( $state['updated_at'] ) : 0;
		return ! empty( $state['status'] ) && 'processing' === $state['status'] && 3 <= $attempts && $updated_at && $updated_at < ( time() - 15 * MINUTE_IN_SECONDS );
	}

	/**
	 * Require a capability for admin-ajax actions.
	 *
	 * Call this after nonce verification in admin-ajax handlers.
	 *
	 * @since 7.9.1
	 *
	 * @param string $cap     Capability required to proceed.
	 * @param string $context Optional context string for filters/logs.
	 */
	public static function require_capabilities( $cap = 'manage_options', $context = '' ) {
		$cap = apply_filters( 'podcast_player_ajax_capability', $cap, $context );

		if ( ! wp_doing_ajax() ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Invalid request context.', 'podcast-player' ) ),
				400
			);
		}

		if ( ! is_user_logged_in() || ! current_user_can( $cap ) ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Unauthorized', 'podcast-player' ) ),
				403
			);
		}
	}

	/**
	 * Convert hex color code to equivalent RGB code.
	 *
	 * @since 3.3.0
	 *
	 * @param string  $hex_color Hexadecimal color value.
	 * @param boolean $as_string Return as string or associative array.
	 * @param string  $sep       String to separate RGB values.
	 * @return string
	 */
	public static function hex_to_rgb( $hex_color, $as_string, $sep = ',' ) {
		$hex_color = preg_replace( '/[^0-9A-Fa-f]/', '', $hex_color );
		$rgb_array = array();
		if ( 6 === strlen( $hex_color ) ) {
			$color_val          = hexdec( $hex_color );
			$rgb_array['red']   = 0xFF & ( $color_val >> 0x10 );
			$rgb_array['green'] = 0xFF & ( $color_val >> 0x8 );
			$rgb_array['blue']  = 0xFF & $color_val;
		} elseif ( 3 === strlen( $hex_color ) ) {
			$rgb_array['red']   = hexdec( str_repeat( substr( $hex_color, 0, 1 ), 2 ) );
			$rgb_array['green'] = hexdec( str_repeat( substr( $hex_color, 1, 1 ), 2 ) );
			$rgb_array['blue']  = hexdec( str_repeat( substr( $hex_color, 2, 1 ), 2 ) );
		} else {
			return false; // Invalid hex color code.
		}
		return $as_string ? implode( $sep, $rgb_array ) : $rgb_array;
	}

	/**
	 * Adjust a HEX color for visibility.
	 * - Lightens medium/dark colors.
	 * - Darkens colors that are already very light.
	 *
	 * @param string $hex_color Base hex color (e.g. "#3498db" or "f5f5f5").
	 * @param float  $percent   Adjustment amount (0–100). Default 20%.
	 *
	 * @return string Adjusted HEX color (lighter or darker, depending on brightness).
	 */
	public static function adjust_hex_color_for_visibility( $hex_color, $percent = 20 ) {
		// Clean input
		$hex_color = preg_replace( '/[^0-9A-Fa-f]/', '', $hex_color );

		// Expand shorthand form (#abc → #aabbcc)
		if ( strlen( $hex_color ) === 3 ) {
			$hex_color = str_repeat( substr( $hex_color, 0, 1 ), 2 )
					. str_repeat( substr( $hex_color, 1, 1 ), 2 )
					. str_repeat( substr( $hex_color, 2, 1 ), 2 );
		}

		if ( strlen( $hex_color ) !== 6 ) {
			return false; // Invalid color
		}

		// Convert to RGB
		$r = hexdec( substr( $hex_color, 0, 2 ) );
		$g = hexdec( substr( $hex_color, 2, 2 ) );
		$b = hexdec( substr( $hex_color, 4, 2 ) );

		// Calculate perceived brightness (0–255)
		$brightness = ( $r * 0.299 ) + ( $g * 0.587 ) + ( $b * 0.114 );

		if ( $brightness > 200 ) {
			// Too light → darken instead
			$r = max( 0, round( $r - $r * ( $percent / 100 ) ) );
			$g = max( 0, round( $g - $g * ( $percent / 100 ) ) );
			$b = max( 0, round( $b - $b * ( $percent / 100 ) ) );
		} else {
			// Normal → lighten toward white
			$r = min( 255, round( $r + ( 255 - $r ) * ( $percent / 100 ) ) );
			$g = min( 255, round( $g + ( 255 - $g ) * ( $percent / 100 ) ) );
			$b = min( 255, round( $b + ( 255 - $b ) * ( $percent / 100 ) ) );
		}

		// Return as hex
		return sprintf( "#%02x%02x%02x", $r, $g, $b );
	}

	/**
	 * Normalize a media URL for stable hashing.
	 *
	 * Strips query and fragment to reduce cache-busting noise.
	 *
	 * @since 7.9.15
	 *
	 * @param string $url Media URL.
	 * @return string Normalized URL or original if parsing fails.
	 */
	public static function normalize_media_url( $url ) {
		$url   = trim( (string) $url );
		$parts = wp_parse_url( $url );

		if ( empty( $parts ) || empty( $parts['host'] ) || empty( $parts['path'] ) ) {
			return $url;
		}

		$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] : 'https';
		return $scheme . '://' . $parts['host'] . $parts['path'];
	}

	/**
	 * Calculate color contrast.
	 *
	 * The returned value should be bigger than 5 for best readability.
	 *
	 * @link https://www.splitbrain.org/blog/2008-09/18-calculating_color_contrast_with_php
	 *
	 * @since 1.5
	 *
	 * @param int $r1 First color R value.
	 * @param int $g1 First color G value.
	 * @param int $b1 First color B value.
	 * @param int $r2 First color R value.
	 * @param int $g2 First color G value.
	 * @param int $b2 First color B value.
	 * @return float
	 */
	public static function lumdiff( $r1, $g1, $b1, $r2, $g2, $b2 ) {
		$l1 = 0.2126 * pow( $r1 / 255, 2.2 ) + 0.7152 * pow( $g1 / 255, 2.2 ) + 0.0722 * pow( $b1 / 255, 2.2 );
		$l2 = 0.2126 * pow( $r2 / 255, 2.2 ) + 0.7152 * pow( $g2 / 255, 2.2 ) + 0.0722 * pow( $b2 / 255, 2.2 );

		if ( $l1 > $l2 ) {
			return ( $l1 + 0.05 ) / ( $l2 + 0.05 );
		} else {
			return ( $l2 + 0.05 ) / ( $l1 + 0.05 );
		}
	}

	/**
	 * Get multiple columns from an array.
	 *
	 * @since 3.3.0
	 *
	 * @param array $keys     Array keys to be fetched.
	 * @param array $get_from Array from which data needs to be fetched.
	 */
	public static function multi_array_columns( $keys, $get_from ) {
		$keys = array_flip( $keys );
		array_walk(
			$keys,
			function ( &$val, $key ) use ( $get_from ) {
				if ( isset( $get_from[ $key ] ) ) {
					$val = $get_from[ $key ];
				} else {
					$val = array();
				}
			}
		);
		return $keys;
	}

	/**
	 * Upload image to wp upload directory.
	 *
	 * @since 5.1.0
	 *
	 * @param string $url   Image URL.
	 * @param string $title Podcast episode title.
	 */
	public static function upload_image( $url = '', $title = '' ) {
		$url = esc_url_raw( $url );
		$title = sanitize_text_field( $title );
		if ( ! $url ) {
			return false;
		}

		global $wpdb;

		$fid = md5( $url );
		$sql = $wpdb->prepare(
			"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'pp_featured_key' AND meta_value = %s",
			$fid
		);
		$post_id = (int) $wpdb->get_var( $sql );
		if ( $post_id ) {
			return $post_id;
		}

		// Load WordPress media libs
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// 1. Download the REAL URL (unchanged!)
		$tmp = download_url( $url );

		if ( is_wp_error( $tmp ) ) {
			return false;
		}

		// 2. Force a correct "filename" for WP regardless of the remote URL
		// Try using MIME type to get correct extension
		$headers  = wp_remote_head( $url );
		$mime     = wp_remote_retrieve_header( $headers, 'content-type' );
		$ext      = Get_Fn::get_extension_from_mime( $mime );
		$filename = 'podcast-episode-image-' . md5( $url ) . '.' . $ext;

		// 3. Build array as if it was a file upload
		$file = [
			'name'     => $filename,
			'tmp_name' => $tmp,
		];

		// 4. Let WP handle the upload
		$attachment_id = media_handle_sideload( $file, 0, $title );

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp );
			return false;
		}

		add_post_meta( $attachment_id, 'pp_featured_key', $fid, true );

		return $attachment_id;
	}

	/**
	 * New Import function for podcast episodes.
	 *
	 * @since 7.4.0
	 *
	 * @param string $feed_key        Podcast feed key.
	 * @param array  $elist           IDs of episodes to be imported.
	 * @param array  $import_settings Import settings
	 */
	public static function import_episodes( $feed_key, $elist, $import_settings = array() ) {
		if ( empty( $import_settings ) ) {
			$import_settings = Get_Fn::get_feed_import_settings( $feed_key );
			if ( ! $import_settings || ! is_array( $import_settings ) ) {
				$import_settings = array();
			}
		}
        $post_author = isset( $import_settings['author'] ) ? intval( $import_settings['author'] ) : 0;
        $post_status = isset( $import_settings['post_status'] ) ? sanitize_text_field( $import_settings['post_status'] ) : 'draft';
        $post_type   = isset( $import_settings['post_type'] ) ? sanitize_text_field( $import_settings['post_type'] ) : 'post';
        $is_get_img  = isset( $import_settings['is_get_img'] ) ? (bool) $import_settings['is_get_img'] : false;
        $taxonomy    = isset( $import_settings['taxonomy'] ) ? sanitize_text_field( $import_settings['taxonomy'] ) : '';
		$ep_taxonomy = isset( $import_settings['ep_taxonomy'] ) ? sanitize_text_field( $import_settings['ep_taxonomy'] ) : '';
		$ep_terms    = isset( $import_settings['ep_terms'] ) ? $import_settings['ep_terms'] : array();
		$elist       = array_values( array_filter( (array) $elist ) );
		self::register_import_shutdown_handler();

        // Get items data to be imported as WP posts.
        $req_fields = apply_filters( 'podcast_player_import_episode_fields', array(
            'title',
            'description',
            'date',
            'timestamp',
            'src',
            'featured',
            'featured_id',
            'mediatype',
            'categories',
			'episode_id',
            'post_id'
		), $feed_key );

        // Get required episodes data from the feed.
		self::start_batch_import_attempts( $feed_key, $elist, 'fetching_feed_data' );
		$fdata        = Get_Fn::get_feed_data( $feed_key, array( 'elist' => $elist ), $req_fields );
        $custom_data  = Get_Fn::get_modified_feed_data( $feed_key );
        $custom_items = $custom_data->get( 'items' );

        // Return error message if feed data is not proper.
		if ( is_wp_error( $fdata ) ) {
			self::mark_current_import_attempt_failed(
				array(
					'reason'  => 'feed_fetch_error',
					'message' => $fdata->get_error_message(),
					'phase'   => 'fetching_feed_data',
				)
			);
			self::clear_current_import_attempt();
			return $fdata;
		}
		self::clear_current_import_attempt();

		$furl  = isset( $fdata['furl'] ) ? $fdata['furl'] : $feed_key;
        $items = $fdata['items'];
		$completed_items = array();
        foreach ( $items as $key => $item ) {
			if ( self::should_skip_auto_import_retry( $feed_key, $key, $import_settings ) ) {
				$completed_items[ $key ] = array( 'skipped' => true );
				continue;
			}

			self::start_episode_import_attempt( $feed_key, $key, $item, 'resolving_existing_post' );
			try {
				$post_id        = isset( $item['post_id'] ) ? absint( $item['post_id'] ) : false;
				$state_post_id  = self::get_imported_post_id_from_state( $feed_key, $key );
				$post_id        = $post_id ? $post_id : $state_post_id;
				$date           = isset( $item['timestamp'] ) ? date( 'Y-m-d H:i:s', $item['timestamp'] ) : date( 'Y-m-d H:i:s', strtotime( $item['date'] ) );
				$post_id        = self::check_if_post_exists( $post_id, $item['title'], $date, $post_type );
				if ( $post_id ) {
					if ( ! isset( $custom_items[ $key ] ) || ! $custom_items[ $key ] instanceof ItemData ) {
						$custom_items[ $key ] = new ItemData();
					}
					$custom_items[ $key ]->set( 'post_id', $post_id );
				} else {
					$post_id = 0; // Default for new posts.
				}

				if ( isset( $import_settings['location'] ) && 'manual' === $import_settings['location'] ) {
					$hide_download = isset( $import_settings['hide_download'] ) && $import_settings['hide_download'] ? 'true' : 'false';
					$hide_social = isset( $import_settings['hide_social'] ) && $import_settings['hide_social'] ? 'true' : 'false';
					$editor_block = sprintf(
						'<!-- wp:podcast-player/podcast-player {"feedURL":"%1$s", "elist":["%2$s"], "podcastMenu":"%3$s", "accentColor": "%4$s", "displayStyle": "%5$s", "hideDownload": %6$s, "hideSocial": %7$s, "hideTitle": true, "hideContent": true, "bgColor": "%8$s", "from": "import", "audioSrc": "%9$s" } /-->',
						sanitize_text_field( $feed_key ),
						sanitize_text_field( $key ),
						isset( $import_settings['menu'] ) ? (int) $import_settings['menu'] : '',
						isset( $import_settings['accent'] ) ? sanitize_hex_color( $import_settings['accent'] ) : '',
						isset( $import_settings['style'] ) ? sanitize_text_field( $import_settings['style'] ) : '',
						$hide_download,
						$hide_social,
						isset( $import_settings['bgcolor'] ) ? sanitize_hex_color( $import_settings['bgcolor'] ) : '',
						isset( $item['src'] ) ? esc_url_raw( $item['src'] ) : ''
					);

					$post_content = $editor_block . wp_kses_post( $item['description'] );
				} else {
					$post_content = wp_kses_post( $item['description'] );
				}

				// Importing the post.
				self::set_episode_import_phase( $feed_key, $key, 'creating_post' );
				$existing_status = $post_id ? get_post_status( $post_id ) : false;
				$new_post_id = wp_insert_post(
					apply_filters(
						'pp_import_post_data',
						array(
							'ID'           => $post_id,
							'post_author'  => $post_author,
							'post_content' => $post_content,
							'post_date'    => $date,
							'post_status'  => $existing_status ? $existing_status : $post_status,
							'post_title'   => sanitize_text_field( $item['title'] ),
							'post_type'    => $post_type,
						),
						$furl,
						$item
					)
				);

				// Return error message if the import generate errors.
				if ( is_wp_error( $new_post_id ) ) {
					self::mark_episode_import_failed(
						$feed_key,
						$key,
						array(
							'reason'  => 'post_insert_error',
							'message' => $new_post_id->get_error_message(),
							'phase'   => 'creating_post',
						)
					);
					self::clear_current_import_attempt();
					continue;
				}

				self::set_episode_import_phase( $feed_key, $key, 'saving_import_mapping' );
				self::mark_episode_imported( $feed_key, $key, $new_post_id, $item );
				$completed_items[ $key ] = array( 'post_id' => $new_post_id );

				// Add post specific information.
				self::set_episode_import_phase( $feed_key, $key, 'saving_post_meta' );
				add_post_meta(
					$new_post_id,
					'pp_import_data',
					array(
						'podkey'    => sanitize_text_field( $feed_key ),
						'episode'   => sanitize_text_field( $key ),
						'src'       => esc_url_raw( $item['src'] ),
						'type'      => sanitize_text_field( $item['mediatype'] ),
						'is_manual' => isset( $import_settings['location'] ) && 'manual' === $import_settings['location']
					)
				);

				// Add episode specific information.
				add_post_meta( $new_post_id, 'pp_episode_id', $item['episode_id'], true );

				// Conditionally import and set post featured image.
				if ( $is_get_img ) {
					self::set_episode_import_phase( $feed_key, $key, 'saving_featured_image' );
					$img_id = ! empty( $item['featured_id'] ) ? absint( $item['featured_id'] ) : self::upload_image( $item['featured'], $item['title'] );
					if ( $img_id ) {
						set_post_thumbnail( $new_post_id, $img_id );
					}
				}

				// Assign terms to the post or post type.
				if ( $taxonomy && ! empty( $item['categories'] ) && is_array( $item['categories'] ) ) {
					self::set_episode_import_phase( $feed_key, $key, 'saving_terms' );
					wp_set_object_terms( $new_post_id, array_map('sanitize_text_field', $item['categories']), $taxonomy );
				}

				if ( ! empty( $ep_taxonomy ) && ! empty( $ep_terms ) ) {
					self::set_episode_import_phase( $feed_key, $key, 'saving_terms' );
					wp_set_object_terms( $new_post_id, $ep_terms, $ep_taxonomy );
				}

				// Store post id in custom feed data.
				if ( ! isset( $custom_items[ $key ] ) || ! $custom_items[ $key ] instanceof ItemData ) {
					$custom_items[ $key ] = new ItemData();
				}
				$custom_items[ $key ]->set( 'post_id', $new_post_id );
				self::mark_episode_imported( $feed_key, $key, $new_post_id, $item );
				self::clear_current_import_attempt();
			} catch ( \Exception $e ) {
				self::mark_episode_import_failed(
					$feed_key,
					$key,
					array(
						'reason'  => 'import_exception',
						'message' => $e->getMessage(),
						'file'    => $e->getFile(),
						'line'    => $e->getLine(),
					)
				);
				self::clear_current_import_attempt();
				continue;
			}
        }

        // Update custom feed data.
		self::set_current_import_attempt( $feed_key, array_keys( $items ), 'saving_modified_feed_data' );
        $custom_data->set( 'items', $custom_items );
        $store_manager = StoreManager::get_instance();
        $store_manager->update_data( $custom_data, $feed_key, 'modified_feed_data' );
		self::clear_current_import_attempt();

		// Return all imported episodes.
		return $completed_items + array_filter( array_map(
			function ( $item ) {
				if ( ! empty( $item->get( 'post_id' ) ) ) {
					return array( 'post_id' => $item->get( 'post_id' ) );
				}
				return false;
			},
			$custom_items
		) );
	}

	/**
     * Check if post exists.
     *
     * @since 7.4.0
     *
     * @param int    $post_id   Post ID.
     * @param string $title     Post title.
     * @param string $date      Post date.
     * @param string $post_type Post type.
     */
    public static function check_if_post_exists( $post_id, $title, $date, $post_type ) {

        // Return if episode post id is available.
        if ( $post_id && false !== get_post_status( $post_id ) ) {
            return $post_id;
        }

        // Query to check if a post with the same title, date, and post type exists
        $args = array(
            'post_type'   => $post_type,
            'post_status' => 'any', // Include all statuses if needed
            'title'       => sanitize_text_field( $title ),
            'date_query'  => array(
                array(
                    'year'  => date( 'Y', strtotime( $date ) ),
                    'month' => date( 'm', strtotime( $date ) ),
                    'day'   => date( 'd', strtotime( $date ) ),
                ),
            ),
            'fields'      => 'ids', // Only get post IDs
        );

        $query = new \WP_Query($args);
        if( $query->have_posts() ) {
			$post_ids = $query->posts;
			$post_id  = $post_ids[0];
			return $post_id;
		}

		return false;
    }

	/**
	 * Schedule next auto update for the podcast.
	 *
	 * @since 5.8.0
	 *
	 * @param string $feed Podcast feed URL or feed key.
	 */
	public static function schedule_next_auto_update( $feed ) {
		// If valid feed URL is provided, let's convert it to feed key.
		if ( Validation_Fn::is_valid_url( $feed ) ) {
			$feed = md5( $feed );
		}

		// Remove all scheduled updates for the feed.
		wp_clear_scheduled_hook( 'pp_auto_update_podcast', array( $feed ) );

		// Auto update time interval. Have at least 10 minutes time interval.
		$cache_time = absint( Get_Fn::get_plugin_option( 'refresh_interval' ) );
		$cache_time = max( $cache_time, 10 ) * 60;
		$time       = apply_filters( 'podcast_player_auto_update_time_interval', $cache_time, $feed );

		// Short circuit filter.
		$is_update = apply_filters( 'podcast_player_auto_update', $feed );
		if ( $is_update ) {
			wp_schedule_single_event( time() + $time, 'pp_auto_update_podcast', array( $feed ) );
		}
	}

	/**
	 * Move podcast custom data from options table to the post table.
	 *
	 * @since 6.6.0
	 *
	 * @param string $feed Podcast feed URL or feed key.
	 */
	public static function move_custom_data( $feed ) {
		$ckey        = 'pp_feed_data_custom_' . $feed;
		$custom_data = get_option( $ckey );
		if ( ! $custom_data || ! is_array( $custom_data ) ) {
			return false;
		}

		$store_manager = StoreManager::get_instance();
		$is_updated    = $store_manager->update_data( $custom_data, $feed, 'custom_feed_data' );
		if ( $is_updated ) {
			delete_option( $ckey );
			delete_option( 'pp_feed_data_' . $feed );
		}
		return $custom_data;
	}

	/**
	 * Get modified custom feed data.
	 *
	 * @since 7.4.0
	 *
	 * @param string $feed Podcast feed URL or feed key.
	 */
	public static function get_modified_feed_data( $feed ) {
		$store_manager = StoreManager::get_instance();
		$feed_data     = $store_manager->get_data( $feed, 'modified_feed_data' );
		if ( $feed_data && $feed_data instanceof FeedData ) {
			return $feed_data;
		}

		$feed_data = $store_manager->get_data( $feed, 'custom_feed_data' );
		if ( ! $feed_data ) {
			return new FeedData();
		}

		if ( $feed_data instanceof FeedData ) {
			return $feed_data;
		}

		// Compatibility with old data, if any.
		if ( is_array( $feed_data ) ) {
			// We assume earlier versions only save item data in the custom feed data array.
			$items = array_map(
				function ( $item ) {
					$item_data = new ItemData();
					$item_data->set( $item, false, 'none' );
					return $item_data;
				},
				$feed_data
			);
	
			$feed_data = new FeedData();
			$feed_data->set( 'items', $items );
			return $feed_data;
		}

		return new FeedData();
	}
}
