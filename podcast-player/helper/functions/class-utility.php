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
	 * Constructor method.
	 *
	 * @since  3.3.0
	 */
	public function __construct() {}

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
		$fdata        = Get_Fn::get_feed_data( $feed_key, array( 'elist' => $elist ), $req_fields );
        $custom_data  = Get_Fn::get_modified_feed_data( $feed_key );
        $custom_items = $custom_data->get( 'items' );

        // Return error message if feed data is not proper.
		if ( is_wp_error( $fdata ) ) {
			return $fdata;
		}

		$furl  = isset( $fdata['furl'] ) ? $fdata['furl'] : $feed_key;
        $items = $fdata['items'];
        foreach ( $items as $key => $item ) {
            $post_id = isset( $item['post_id'] ) ? absint( $item['post_id'] ) : false;
            $date    = isset( $item['timestamp'] ) ? date( 'Y-m-d H:i:s', $item['timestamp'] ) : date( 'Y-m-d H:i:s', strtotime( $item['date'] ) );
			$post_id = self::check_if_post_exists( $post_id, $item['title'], $date, $post_type );
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
            $new_post_id = wp_insert_post(
				apply_filters(
					'pp_import_post_data',
					array(
						'ID'           => $post_id,
						'post_author'  => $post_author,
						'post_content' => $post_content,
						'post_date'    => $date,
						'post_status'  => $post_status,
						'post_title'   => sanitize_text_field( $item['title'] ),
						'post_type'    => $post_type,
					),
					$furl,
					$item
				)
			);

            // Return error message if the import generate errors.
			if ( is_wp_error( $new_post_id ) ) {
				// return array( $new_post_id, $elist );
				continue;
			}

            // Add post specific information.
			add_post_meta(
				$new_post_id,
				'pp_import_data',
				array(
					'podkey'    => sanitize_text_field( $feed_key ),
					'episode'   => sanitize_text_field( $key ),
					'src'       => esc_url_raw( $item['src'] ),
					'type'      => sanitize_text_field( $item['mediatype'] ),
					'is_manual' => 'manual' === $import_settings['location']
				)
			);

			// Add episode specific information.
			add_post_meta( $new_post_id, 'pp_episode_id', $item['episode_id'], true );

            // Conditionally import and set post featured image.
            if ( $is_get_img ) {
                $img_id = ! empty( $item['featured_id'] ) ? absint( $item['featured_id'] ) : self::upload_image( $item['featured'], $item['title'] );
                if ( $img_id ) {
                    set_post_thumbnail( $new_post_id, $img_id );
                }
            }

			// Assign terms to the post or post type.
			if ( $taxonomy && ! empty( $item['categories'] ) && is_array( $item['categories'] ) ) {
                wp_set_object_terms( $new_post_id, array_map('sanitize_text_field', $item['categories']), $taxonomy );
            }

			if ( ! empty( $ep_taxonomy ) && ! empty( $ep_terms ) ) {
				wp_set_object_terms( $new_post_id, $ep_terms, $ep_taxonomy );
			}

            // Store post id in custom feed data.
            if ( ! isset( $custom_items[ $key ] ) || ! $custom_items[ $key ] instanceof ItemData ) {
                $custom_items[ $key ] = new ItemData();
            }
            $custom_items[ $key ]->set( 'post_id', $new_post_id );
        }

        // Update custom feed data.
        $custom_data->set( 'items', $custom_items );
        $store_manager = StoreManager::get_instance();
        $store_manager->update_data( $custom_data, $feed_key, 'modified_feed_data' );

		// Return all imported episodes.
		return array_filter( array_map(
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
