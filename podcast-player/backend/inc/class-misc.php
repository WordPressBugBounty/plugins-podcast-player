<?php
/**
 * Podcast player miscellaneous actions.
 *
 * @link       https://www.vedathemes.com
 * @since      1.0.0
 *
 * @package    Podcast_Player
 */

namespace Podcast_Player\Backend\Inc;

use Podcast_Player\Helper\Functions\Getters as Get_Fn;
use Podcast_Player\Helper\Functions\Utility as Utility_Fn;
use Podcast_Player\Helper\Store\StoreManager;
use Podcast_Player\Helper\Store\FeedData;
use Podcast_Player\Helper\Core\Singleton;

/**
 * Display podcast player instance.
 *
 * @package    Podcast_Player
 * @author     vedathemes <contact@vedathemes.com>
 */
class Misc extends Singleton {

	/**
	 * Initiate podcast player data storage.
	 *
	 * @since 6.5.0
	 */
	public function init_storage() {
		$store_manager = StoreManager::get_instance();
		$store_manager->register();
	}

	/**
	 * Add plugin action links.
	 *
	 * Add actions links for better user engagement.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $links List of existing plugin action links.
	 * @return array         List of modified plugin action links.
	 */
	public function action_links( $links ) {
		$links = array_merge(
			array(
				'<a href="' . esc_url( admin_url( 'admin.php?page=pp-options' ) ) . '">' . __( 'Settings', 'podcast-player' ) . '</a>',
			),
			$links
		);

		if ( defined( 'PP_PRO_VERSION' ) ) {
			return $links;
		}

		$links = array_merge(
			array(
				'<a href="' . esc_url( 'https://easypodcastpro.com/podcast-player/' ) . '" style="color: #35b747; font-weight: 700;">' . __( 'Get Pro', 'podcast-player' ) . '</a>',
			),
			$links
		);
		return $links;
	}

	/**
	 * Auto Update Podcast.
	 *
	 * @since 5.8.0
	 *
	 * @param string $feed_key Podcast feed key.
	 */
	public function auto_update_podcast( $feed_key ) {

		// Return if podcast has been deleted from the index.
		$feed_key = Get_Fn::get_feed_url_from_index( $feed_key );
		if ( false === $feed_key ) {
			return;
		}

		// Init feed fetch and update method.
		Get_Fn::get_feed_data( $feed_key );
	}

	/**
	 * Create REST API endpoints to get all pages list.
	 *
	 * @since 1.8.0
	 */
	public function register_routes() {
		register_rest_route(
			'podcastplayer/v1',
			'/fIndex',
			array(
				'methods'             => 'GET',
				'callback'            => function () {
					$feed_index = Get_Fn::get_feed_index();
					if ( $feed_index && is_array( $feed_index ) && ! empty( $feed_index ) ) {
						array_walk(
							$feed_index,
							function ( &$val, $key ) {
								$val = isset( $val['title'] ) ? $val['title'] : '';
							}
						);
						$feed_index = array_filter( $feed_index );
						return array_merge(
							array( '' => esc_html__( 'Select a Podcast', 'podcast-player' ) ),
							$feed_index
						);
					}
					return array();
				},
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}
