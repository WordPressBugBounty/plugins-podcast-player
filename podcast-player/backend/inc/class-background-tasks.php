<?php
/**
 * Perform background tasks.
 *
 * @link       https://www.vedathemes.com
 * @since      1.0.0
 * @package    Podcast_Player
 */

namespace Podcast_Player\Backend\Inc;

use Podcast_Player\Helper\Functions\Getters as Get_Fn;
use Podcast_Player\Helper\Functions\Utility as Utility_Fn;
use Podcast_Player\Helper\Core\Singleton;
use Podcast_Player\Helper\Store\StoreManager;
use Podcast_Player\Helper\Store\FeedData;
use Podcast_Player\Helper\Store\ItemData;
use Podcast_Player\Helper\Feed\Get_Feed;

/**
 * Perform background tasks.
 *
 * @package    Podcast_Player
 * @author     vedathemes <contact@vedathemes.com>
 */
class Background_Tasks extends Singleton {

    /**
     * There are cases where download data is not saved properly due to some error, server settings,
     * custom codes or conflict with other plugin. These edge cases sometimes cause plugin to
     * download a large number of images. Let's put a hard cap on that to prevent any issues on user's website.
     */
    const MAX_TOTAL_IMAGE_DOWNLOADS = 1000;

    /**
     * Download episode featured images.
     *
     * @since 7.4.0
     *
     * @param array $return Task result.
     * @param array $args   Background task args.
     */
    public function download_images( $return, $args ) {
        global $wpdb;

        if ( 'yes' !== Get_Fn::get_plugin_option( 'img_save' ) ) {
            // Skip task and remove it from the queue.
            return array( true, $args['data'] );
        }

        // GLOBAL SAFETY STOP
        if ( $this->image_download_limit_reached() ) {
            $this->disable_image_downloads();

            return array(
                new \WP_Error(
                    'image-limit-reached',
                    esc_html__( 'Global image download limit reached. Image saving disabled.', 'podcast-player' )
                ),
                false
            );
        }

        $feed_url = isset( $args['identifier'] ) ? $args['identifier'] : '';
        $items    = isset( $args['data'] ) ? $args['data'] : array();
        if ( empty( $feed_url ) || empty( $items ) ) {
            $error = new \WP_Error(
				'no-data-available',
				esc_html__( 'Feed URL or items not found.', 'podcast-player' )
			);
            return ( array( $error, false ) );
        }

        // Process maximum 50 items at a time.
        $items        = array_slice( $items, 0, 50 );
        $hashes       = array();
        $item_hashes  = array();
        foreach ( $items as $key => $item ) {
            if ( empty( $item['featured'] ) ) {
                continue;
            }
            $raw_hash = md5( $item['featured'] );
            $norm_url = Utility_Fn::normalize_media_url( $item['featured'] );
            $norm_hash = md5( $norm_url );

            $item_hashes[ $key ] = array(
                'raw'  => $raw_hash,
                'norm' => $norm_hash,
            );
            $hashes[] = $raw_hash;
            $hashes[] = $norm_hash;
        }
        $hashes    = array_unique( array_filter( $hashes ) );
        $in_clause = implode( ',', array_map( function ( $hash ) use ( $wpdb ) {
            return $wpdb->prepare( '%s', $hash );
        }, $hashes ) );

        if ( empty( $in_clause ) ) {
            // No valid items to process; return early.
            return array( new \WP_Error( 'no-valid-items', esc_html__( 'No valid featured images found.', 'podcast-player' ) ), false );
        }

        $sql           = "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ( 'pp_featured_key', 'pp_featured_key_norm' ) AND meta_value IN ( $in_clause )";
        $results       = $wpdb->get_results( $sql, ARRAY_A );
        $featured_keys = array_column( $results, 'post_id', 'meta_value' );
        $completed     = array();
        $pending       = array();
        foreach ( $items as $key => $item ) {
            $hash_pair = isset( $item_hashes[ $key ] ) ? $item_hashes[ $key ] : array();
            $raw_hash  = isset( $hash_pair['raw'] ) ? $hash_pair['raw'] : '';
            $norm_hash = isset( $hash_pair['norm'] ) ? $hash_pair['norm'] : '';

            $matched_post_id = false;
            if ( $raw_hash && isset( $featured_keys[ $raw_hash ] ) ) {
                $matched_post_id = $featured_keys[ $raw_hash ];
            } elseif ( $norm_hash && isset( $featured_keys[ $norm_hash ] ) ) {
                $matched_post_id = $featured_keys[ $norm_hash ];
            }

            if ( $matched_post_id ) {
                // Backfill normalized hash for future de-dupes if missing.
                if ( $norm_hash ) {
                    add_post_meta( $matched_post_id, 'pp_featured_key_norm', $norm_hash, true );
                }
                $completed[ $key ] = array_merge( $item, array( 'post_id' => $matched_post_id ) );
            } else {
                $pending[ $key ] = $item;
            }
        }

        $pending = array_slice( $pending, 0, 2 );
        return $this->fetch_featured_images( $feed_url, $completed, $pending );
    }

    /**
     * Fetch featured images.
     *
     * @since 7.4.0
     *
     * @param string $feed_url  Feed URL.
     * @param array  $completed Completed items.
     * @param array  $pending   Pending items.
     */
    private function fetch_featured_images( $feed_url, $completed, $pending ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
        foreach ( $pending as $key => $item ) {

            // Hard Stop if download limit has reached.
            if ( $this->image_download_limit_reached() ) {
                $this->disable_image_downloads();
                return array(
                    new \WP_Error(
                        'image-limit-reached',
                        esc_html__( 'Global image limit reached. Downloads stopped.', 'podcast-player' )
                    ),
                    false
                );
            }

            $image_url = isset( $item['featured'] ) ? $item['featured'] : '';
            $title     = isset( $item['title'] ) ? $item['title'] : '';
            if ( empty( $image_url ) ) {
                $completed[ $key ] = array_merge( $item, array( 'post_id' => false ) );
            } else {
                // 1. Download the REAL URL (unchanged!)
                $tmp = download_url( $image_url );

                if ( is_wp_error( $tmp ) ) {
                    return ( array( $tmp, false ) );
                }

                // 2. Force a correct "filename" for WP regardless of the remote URL
                // Try using MIME type to get correct extension
                $headers  = wp_remote_head( $image_url );
                $mime     = wp_remote_retrieve_header( $headers, 'content-type' );
                $ext      = Get_Fn::get_extension_from_mime( $mime );
                $filename = 'podcast-episode-image-' . md5( $image_url ) . '.' . $ext;

                // 3. Build array as if it was a file upload
                $file = [
                    'name'     => $filename,
                    'tmp_name' => $tmp,
                ];

                // 4. Let WP handle the upload
                $attachment_id = media_handle_sideload( $file, 0, $title );

                if ( ! is_wp_error( $attachment_id ) ) {

                    // Count successful downloads.
                    $this->increment_image_download_count();

                    add_post_meta( $attachment_id, 'pp_featured_key', md5( $image_url ), true );
                    $normalized_url = Utility_Fn::normalize_media_url( $image_url );
                    add_post_meta( $attachment_id, 'pp_featured_key_norm', md5( $normalized_url ), true );

                    // Let's do post_meta verification to see if data is getting saved correctly.
                    $stored = get_post_meta( $attachment_id, 'pp_featured_key', true );
                    if ( $stored !== md5( $image_url ) ) {
                        $this->disable_image_downloads();
                        return array(
                            new \WP_Error(
                                'meta-write-failed',
                                esc_html__( 'Failed to persist image meta. Downloads stopped.', 'podcast-player' )
                            ),
                            false
                        );
                    }

                    $completed[ $key ] = array_merge( $item, array( 'post_id' => $attachment_id ) );
                } else {
                    wp_delete_file( $tmp );
					return ( array( $attachment_id, false ) );
				}
            }
        }
        $this->update_episode_featured_id( $feed_url, $completed );
        return array( true, $completed );
    }

    /**
     * Update episode featured ID.
     *
     * @since 7.4.0
     *
     * @param string $feed_url Feed URL.
     * @param array  $episodes Episodes for which featured image is downloaded.
     */
    private function update_episode_featured_id( $feed_url, $episodes ) {
        if ( empty( $episodes ) ) {
            return;
        }
        $store_manager = StoreManager::get_instance();
        $custom_data   = Get_Fn::get_modified_feed_data( $feed_url );

        if ( ! $custom_data || ! $custom_data instanceof FeedData ) {
            $custom_data = new FeedData();
        }

        if ( isset( $episodes['cover_image'] ) ) {
            $cover_id = $episodes['cover_image']['post_id'];
            if ( $cover_id ) {
                $custom_data->set( 'cover_id', $cover_id );
            }
            unset( $episodes['cover_image'] );
        }

        if ( ! empty( $episodes ) ) {
            $items = $custom_data->get( 'items' );
            foreach ( $episodes as $key => $episode ) {
                $featured_id = $episode['post_id'];
                if ( ! $featured_id ) {
                    continue;
                }
                if ( ! isset( $items[ $key ] ) || ! $items[ $key ] instanceof ItemData ) {
                    $items[ $key ] = new ItemData();
                }
                $items[ $key ]->set( 'featured_id', $featured_id );
            }
            $custom_data->set( 'items', $items );
        }

        $store_manager->update_data( $custom_data, $feed_url, 'modified_feed_data' );
    }

    /**
     * Import podcast episodes as WordPress posts or custom post type.
     *
     * @since 7.4.0
     *
     * @param array $return Task result.
     * @param array $args   Background task args.
     */
    public function import_episodes( $return, $args ) {
        $feed_url = isset( $args['identifier'] ) ? $args['identifier'] : '';
        $elist    = isset( $args['data'] ) ? $args['data'] : array();
        if ( empty( $feed_url ) || empty( $elist ) ) {
            $error = new \WP_Error(
				'no-data-available',
				esc_html__( 'Feed URL or episode list not found.', 'podcast-player' )
			);
            return ( array( $error, false ) );
        }

        $import_settings = Get_Fn::get_feed_import_settings( $feed_url );

        if ( ! $import_settings['is_auto'] ) {
            $error = new \WP_Error(
				'auto-update-disabled',
				esc_html__( 'Auto update has been disabled.', 'podcast-player' )
			);
            return ( array( $error, false ) );
		}

        $imported_episodes = Utility_Fn::import_episodes( $feed_url, $elist, $import_settings );
        if ( is_wp_error( $imported_episodes ) ) {
            return array( $imported_episodes, false );
        }

        $completed = array_intersect( array_keys( $imported_episodes ), $elist );
        return array( true, $completed );
    }


    /**
     * Update podcast feed data.
     *
     * @since 7.4.4
     *
     * @param array $return Task result.
     * @param array $args   Background task args.
     */
    public function update_podcast_data( $return, $args ) {
        $feed_url = isset( $args['identifier'] ) ? $args['identifier'] : '';
        $feed_url = Get_Fn::get_valid_feed_url( $feed_url );
		
        // Skip task and remove it from the queue if feed URL is not valid.
        if ( empty( $feed_url ) || is_wp_error( $feed_url ) ) {
            return ( array( true, false ) );
		}

        // Skip task and remove it from the queue if podcast data is not found.
        $podcast_data = isset( $args['data'] ) ? $args['data'] : array();
        if ( empty( $podcast_data ) ) {
            return ( array( true, false ) );
        }

        $get_feed = new Get_Feed( $feed_url );
        $data     = $get_feed->fetch_podcast_data( $podcast_data );

        // Remove task from the queue if error in fetching podcast data.
        if ( is_wp_error( $data ) ) {
            return array( true, false );
        }
        return array( true, $data );
    }

    /**
     * Get how many image download operations have been performed successfully.
     *
     * @since 7.9.14
     */
    private function get_total_image_download_count() {
        return (int) get_option( 'pp_total_image_downloads', 0 );
    }

    /**
     * Increase image download operations by one.
     *
     * @since 7.9.14
     */
    private function increment_image_download_count() {
        $count = $this->get_total_image_download_count();
        update_option( 'pp_total_image_downloads', $count + 1, false );
    }

    /**
     * Check if image download limit has been reached.
     *
     * @since 7.9.14
     */
    private function image_download_limit_reached() {
        return $this->get_total_image_download_count() >= self::MAX_TOTAL_IMAGE_DOWNLOADS;
    }

    /**
     * Disable image download options and reset the counter.
     *
     * @since 7.9.14
     */
    private function disable_image_downloads() {
        $options = get_option( 'pp-common-options', array() );
        if ( ! isset( $options['img_save'] ) || 'yes' !== $options['img_save'] ) {
            return; // already disabled
        }
        $options['img_save'] = 'no';
        update_option( 'pp-common-options', $options );
        delete_option( 'pp_total_image_downloads' );
    }
}
