<?php
/**
 * Contextual admin marketing recommendations.
 *
 * @package Podcast_Player
 * @subpackage Podcast_Player/Backend/Admin
 */

namespace Podcast_Player\Backend\Admin;

use Podcast_Player\Helper\Functions\Getters as Get_Fn;
use Podcast_Player\Helper\Store\FeedData;
use Podcast_Player\Helper\Store\ItemData;
use Podcast_Player\Helper\Store\StoreManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds small, local admin recommendations from existing podcast data.
 */
class Marketing_Context {

	const PRO_URL       = 'https://easypodcastpro.com/podcast-player/';
	const SELFHOST_URL  = 'https://easypodcastpro.com/selfhost-podcasting/';
	const COMPOSER_URL  = 'https://easypodcastpro.com/podcast-composer/';
	const TUBECAST_URL  = 'https://easypodcastpro.com/easy-tubecasting/';
	const MORE_STYLES   = '__pp_get_more_styles';

	/**
	 * Cached feed contexts.
	 *
	 * @var array
	 */
	private static $feed_contexts = array();

	/**
	 * Cached recommendation choices for this request.
	 *
	 * @var array
	 */
	private static $random_choices = array();

	/**
	 * Determine whether the premium feature set is active.
	 */
	public static function is_premium() {
		return defined( 'PP_PRO_VERSION' ) || apply_filters( 'podcast_player_is_premium', false );
	}

	/**
	 * Determine whether row-level Pro Tips should be shown.
	 */
	public static function are_pro_tips_enabled() {
		return 'yes' === Get_Fn::get_plugin_option( 'show_pro_tips' );
	}

	/**
	 * Check whether any podcast is saved.
	 */
	public static function has_podcasts() {
		$feed_index = Get_Fn::get_feed_index();
		return is_array( $feed_index ) && ! empty( $feed_index );
	}

	/**
	 * Get the best Podcast Player Pro action for a specific saved podcast.
	 *
	 * Ecosystem products are intentionally excluded from this method because they
	 * cannot be safely tied to a specific Podcast Player feed.
	 *
	 * @param string $feed_key Stored podcast key.
	 * @return array|false
	 */
	public static function get_next_action_for_feed( $feed_key ) {
		if ( self::is_premium() || ! self::are_pro_tips_enabled() ) {
			return false;
		}

		$context = self::get_feed_context( $feed_key );
		if ( empty( $context['has_episodes'] ) ) {
			return false;
		}

		$episode_count = $context['episode_count'];
		$actions       = array();

		if ( $episode_count < 5 && ! $context['mostly_imported'] ) {
			return self::random_action(
				self::action_variants(
					'import_seo_small',
					array(
						array(
							__( 'Create SEO-friendly episode posts', 'podcast-player' ),
							__( 'Podcast Player Pro can import feed episodes as WordPress posts, pages, or custom post types, with an image, description, and shareable link.', 'podcast-player' ),
						),
						array(
							__( 'Import episodes to help search engines find them', 'podcast-player' ),
							__( 'Pro turns feed-only episodes into WordPress content that people can find in search, open directly, and share.', 'podcast-player' ),
						),
					),
					self::PRO_URL
				),
				$feed_key . ':import-seo-small'
			);
		}

		if ( $context['has_long_descriptions'] ) {
			$actions = array_merge(
				$actions,
				self::action_variants(
					'content_search',
					array(
						array(
							__( 'Find episodes by words inside the content', 'podcast-player' ),
							__( 'With Pro, the podcast search can also check episode content, so a listener searching for a term has a better chance of finding the correct episode.', 'podcast-player' ),
						),
						array(
							__( 'Improve episode search with content matching', 'podcast-player' ),
							__( 'Pro can look for the listener search term inside episode descriptions and content, not only in the episode title.', 'podcast-player' ),
						),
					),
					self::PRO_URL
				)
			);
		}

		if ( $episode_count >= 100 && ! $context['has_taxonomy_depth'] && ! $context['mostly_imported'] ) {
			$actions = array_merge(
				$actions,
				self::action_variants(
					'episode_categorization',
					array(
						array(
							__( 'Organize this large podcast by topic', 'podcast-player' ),
							__( 'Pro can import episodes and organize them with categories, making a large archive easier to browse by topic.', 'podcast-player' ),
						),
						array(
							__( 'Add categories while importing episodes', 'podcast-player' ),
							__( 'Pro helps turn a long episode list into a better organized archive with categories and clearer browsing.', 'podcast-player' ),
						),
					),
					self::PRO_URL
				)
			);
		} elseif ( $episode_count >= 100 || ( $episode_count >= 50 && $context['has_taxonomy_depth'] ) ) {
			$actions = array_merge(
				$actions,
				self::action_variants(
					'episode_filtering',
					array(
						array(
							__( 'Add filters for this large podcast', 'podcast-player' ),
							__( 'Pro adds episode filters so visitors can narrow a large podcast by season, category, topic, or selected episodes.', 'podcast-player' ),
						),
						array(
							__( 'Let visitors filter episodes by season, category, or topic', 'podcast-player' ),
							__( 'Pro makes large archives easier to explore by letting listeners narrow the list instead of scrolling through everything.', 'podcast-player' ),
						),
					),
					self::PRO_URL
				)
			);
		}

		if ( ! $context['mostly_imported'] ) {
			$actions = array_merge(
				$actions,
				self::action_variants(
					'import_archive',
					array(
						array(
							__( 'Create searchable episode posts', 'podcast-player' ),
							__( 'Pro imports episodes as WordPress posts, pages, or custom post types, giving every episode its own searchable entry, image, and link.', 'podcast-player' ),
						),
						array(
							__( 'Turn this podcast into searchable WordPress content', 'podcast-player' ),
							__( 'Pro creates individual posts, pages, or custom post type entries from the feed so visitors can find, read, and share each episode more easily.', 'podcast-player' ),
						),
					),
					self::PRO_URL
				)
			);
		}

		if ( $episode_count >= 5 ) {
			$actions = array_merge(
				$actions,
				self::action_variants(
					'display_templates',
					array(
						array(
							__( 'Try grid and card professional layouts for this podcast', 'podcast-player' ),
							__( 'Pro includes extra display templates, including cleaner grid and card layouts that make episode lists easier to scan.', 'podcast-player' ),
						),
						array(
							__( 'Show this podcast in a cleaner layout', 'podcast-player' ),
							__( 'Pro gives you more layout choices, so this podcast can use a compact, list, grid, or card design depending on the page.', 'podcast-player' ),
						),
					),
					self::PRO_URL
				)
			);
		}

		if ( $context['is_fast_publishing'] && ! $context['mostly_imported'] ) {
			$actions = array_merge(
				$actions,
				self::action_variants(
					'auto_import',
					array(
						array(
							__( 'Auto-import future episodes', 'podcast-player' ),
							__( 'Pro can watch this active feed and automatically create WordPress posts when new episodes are published.', 'podcast-player' ),
						),
						array(
							__( 'Publish new episodes faster', 'podcast-player' ),
							__( 'Pro reduces repeat work by importing new feed episodes into WordPress for you after they appear in the feed.', 'podcast-player' ),
						),
					),
					self::PRO_URL
				)
			);
		}

		if ( $context['has_funding'] ) {
			$actions = array_merge(
				$actions,
				self::action_variants(
					'sticky_player',
					array(
						array(
							__( 'Keep support links visible while visitors listen', 'podcast-player' ),
							__( 'Pro includes a sticky player, so listening controls and support links can stay visible while visitors browse the page.', 'podcast-player' ),
						),
						array(
							__( 'Keep the player visible across the page', 'podcast-player' ),
							__( 'Pro can keep the player on screen while visitors read, which is useful when your podcast includes support or funding links.', 'podcast-player' ),
						),
					),
					self::PRO_URL
				)
			);
		}

		if ( $context['has_transcripts'] ) {
			$actions = array_merge(
				$actions,
				self::action_variants(
					'transcripts',
					array(
						array(
							__( 'Show transcripts in the player', 'podcast-player' ),
							__( 'Pro can show transcript, caption, and chapter links with episodes so visitors can find supporting content while listening.', 'podcast-player' ),
						),
						array(
							__( 'Make transcripts easier to find', 'podcast-player' ),
							__( 'Pro places transcript links near the player, so visitors do not have to search the page for episode resources.', 'podcast-player' ),
						),
					),
					self::PRO_URL
				)
			);
		}

		if ( empty( $actions ) ) {
			return false;
		}

		return self::random_action( $actions, $feed_key );
	}

	/**
	 * Get one site-level ecosystem recommendation.
	 *
	 * These recommendations are intentionally not tied to a specific podcast row.
	 * They are independent of Podcast Player Pro status and only check whether
	 * the recommended sibling plugin is already active.
	 *
	 * @return array|false
	 */
	public static function get_site_recommendation() {
		$recommendations = array();
		$summary         = self::get_site_summary();

		if ( self::has_selfhost_migration_signal() && ! self::is_selfhost_active() ) {
			$recommendations[] = self::action(
				'selfhost_migration',
				__( 'Migrate to Selfhost Podcasting', 'podcast-player' ),
				__( 'Migrate your local podcast to a more powerful podcasting alternative. Test the move safely before you switch.', 'podcast-player' ),
				self::SELFHOST_URL,
				__( 'Migrate to Selfhost Podcasting', 'podcast-player' )
			);
		}

		if ( $summary['podcast_count'] > 0 && self::has_membership_signal() && ! self::is_selfhost_active() ) {
			$recommendations[] = self::action(
				'selfhost_private_access',
				__( 'Offer private podcast access', 'podcast-player' ),
				__( 'Use Selfhost Podcasting to create private podcast feeds for members or customers.', 'podcast-player' ),
				self::SELFHOST_URL,
				__( 'Create private podcast feeds', 'podcast-player' )
			);
		}

		if ( ( $summary['podcast_count'] > 1 || $summary['max_episode_count'] >= 250 ) && ! self::is_composer_active() ) {
			$recommendations[] = self::action(
				'podcast_composer',
				__( 'Merge or split podcasts', 'podcast-player' ),
				__( 'Merge multiple shows, split a large podcast, or curate episodes from different podcasts.', 'podcast-player' ),
				self::COMPOSER_URL,
				__( 'Merge or split podcasts with Podcast Composer', 'podcast-player' )
			);
		}

		if ( $summary['has_video'] && ! self::is_tubecasting_active() ) {
			$recommendations[] = self::action(
				'easy_tubecasting',
				__( 'Build a YouTube video archive', 'podcast-player' ),
				__( 'Use Easy TubeCasting to show channels, playlists, and video podcasts.', 'podcast-player' ),
				self::TUBECAST_URL,
				__( 'Build a YouTube video archive', 'podcast-player' )
			);
		}

		if ( empty( $recommendations ) ) {
			return false;
		}

		return self::random_action( $recommendations, 'site-recommendation' );
	}

	/**
	 * Get product cards ordered by the strongest local workflow signal.
	 *
	 * @return array[]
	 */
	public static function get_product_recommendations() {
		$summary = self::get_site_summary();

		$products = array(
			self::product(
				'selfhost',
				__( 'Selfhost Podcasting', 'podcast-player' ),
				__( 'Host and publish podcasts from your WordPress dashboard.', 'podcast-player' ),
				__( 'Create clean Apple and Spotify-ready podcast feeds inside WordPress. Use it for feed ownership, safer migration, private feeds, and direct publishing.', 'podcast-player' ),
				self::SELFHOST_URL,
				__( 'Create a Podcast', 'podcast-player' ),
				30
			),
			self::product(
				'composer',
				__( 'Podcast Composer', 'podcast-player' ),
				__( 'Merge, split, rearrange, and curate podcast feeds.', 'podcast-player' ),
				__( 'Build new output feeds from existing shows. Use it to merge multiple shows, split a large archive, or create a hand-picked feed.', 'podcast-player' ),
				self::COMPOSER_URL,
				__( 'Learn More', 'podcast-player' ),
				40
			),
			self::product(
				'tubecasting',
				__( 'Easy TubeCasting', 'podcast-player' ),
				__( 'Display YouTube videos, playlists, channels, and video podcasts.', 'podcast-player' ),
				__( 'Turn YouTube channels, playlists, and video podcasts into a clean WordPress video experience with a responsive player and playlist.', 'podcast-player' ),
				self::TUBECAST_URL,
				__( 'Learn More', 'podcast-player' ),
				50
			),
		);

		foreach ( $products as &$product ) {
			if ( 'selfhost' === $product['id'] && ! self::is_selfhost_active() && self::has_selfhost_migration_signal() ) {
				$product['priority'] = 10;
				$product['reason']   = __( 'Recommended for safely moving a local podcast when you are ready to switch.', 'podcast-player' );
			} elseif ( 'selfhost' === $product['id'] && ! self::is_selfhost_active() && $summary['podcast_count'] > 0 && self::has_membership_signal() ) {
				$product['priority'] = 15;
				$product['reason']   = __( 'Recommended for creating private podcast feeds for members or customers.', 'podcast-player' );
			} elseif ( 'composer' === $product['id'] && ! self::is_composer_active() && ( $summary['podcast_count'] > 1 || $summary['max_episode_count'] >= 250 ) ) {
				$product['priority'] = 20;
				$product['reason']   = __( 'Recommended for multiple shows or very large podcast archives.', 'podcast-player' );
			} elseif ( 'tubecasting' === $product['id'] && ! self::is_tubecasting_active() && $summary['has_video'] ) {
				$product['priority'] = 25;
				$product['reason']   = __( 'Recommended because this site has video podcast signals.', 'podcast-player' );
			}
		}
		unset( $product );

		usort(
			$products,
			function ( $a, $b ) {
				return $a['priority'] <=> $b['priority'];
			}
		);

		return $products;
	}

	/**
	 * Get summary context for one feed.
	 *
	 * @param string $feed_key Stored podcast key.
	 * @return array
	 */
	public static function get_feed_context( $feed_key ) {
		if ( isset( self::$feed_contexts[ $feed_key ] ) ) {
			return self::$feed_contexts[ $feed_key ];
		}

		$defaults = array(
			'episode_count'         => 0,
			'has_episodes'          => false,
			'has_long_descriptions' => false,
			'has_taxonomy_depth'    => false,
			'has_transcripts'       => false,
			'has_video'             => false,
			'has_funding'           => false,
			'is_fast_publishing'    => false,
			'mostly_imported'       => false,
		);

		$store_manager = StoreManager::get_instance();
		$feed_data     = $store_manager->get_data( $feed_key );
		if ( ! $feed_data instanceof FeedData ) {
			self::$feed_contexts[ $feed_key ] = $defaults;
			return $defaults;
		}

		$items         = (array) $feed_data->get( 'items', 'none' );
		$episode_count = absint( $feed_data->get( 'total', 'none' ) );
		if ( ! $episode_count ) {
			$episode_count = count( $items );
		}

		$modified_data  = Get_Fn::get_modified_feed_data( $feed_key );
		$modified_items = $modified_data instanceof FeedData ? (array) $modified_data->get( 'items', 'none' ) : array();

		$description_chars = 0;
		$description_items = 0;
		$long_items        = 0;
		$imported_items    = 0;
		$has_transcripts   = false;
		$has_video         = false;

		foreach ( $items as $episode_key => $item ) {
			$description = wp_strip_all_tags( (string) self::item_value( $item, 'description' ) );
			$chars       = strlen( $description );
			if ( $chars ) {
				$description_chars += $chars;
				$description_items++;
				if ( $chars >= 800 || str_word_count( $description ) >= 120 ) {
					$long_items++;
				}
			}

			$post_id = absint( self::item_value( $item, 'post_id' ) );
			if ( isset( $modified_items[ $episode_key ] ) ) {
				$post_id = max( $post_id, absint( self::item_value( $modified_items[ $episode_key ], 'post_id' ) ) );
			}
			if ( $post_id ) {
				$imported_items++;
			}

			if ( self::item_value( $item, 'transcript' ) || self::item_value( $item, 'captions' ) || self::item_value( $item, 'chapters' ) ) {
				$has_transcripts = true;
			}

			$media_type = strtolower( (string) self::item_value( $item, 'mediatype' ) );
			$src        = strtolower( (string) self::item_value( $item, 'src' ) );
			if ( 'video' === $media_type || false !== strpos( $src, 'youtube.com' ) || false !== strpos( $src, 'youtu.be' ) ) {
				$has_video = true;
			}
		}

		$avg_description = $description_items ? $description_chars / $description_items : 0;
		$long_ratio      = $episode_count ? $long_items / $episode_count : 0;
		$import_ratio    = $episode_count ? $imported_items / $episode_count : 0;
		$categories      = (array) $feed_data->get( 'categories', 'none' );
		$seasons         = (array) $feed_data->get( 'seasons', 'none' );
		$funding         = (array) $feed_data->get( 'funding', 'none' );
		$is_active       = (bool) $feed_data->get( 'is_active', 'none' );
		$release_cycle   = absint( $feed_data->get( 'release_cycle', 'none' ) );
		$last_released   = absint( $feed_data->get( 'last_released', 'none' ) );
		$day_seconds     = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
		$recent_window   = $release_cycle ? max( $release_cycle * 3 * $day_seconds, 45 * $day_seconds ) : 45 * $day_seconds;

		self::$feed_contexts[ $feed_key ] = array(
			'episode_count'         => $episode_count,
			'has_episodes'          => $episode_count > 0,
			'has_long_descriptions' => $avg_description >= 500 || $long_ratio >= 0.3 || $long_items >= 10,
			'has_taxonomy_depth'    => count( array_filter( $categories ) ) > 1 || count( array_filter( $seasons ) ) > 1,
			'has_transcripts'       => $has_transcripts,
			'has_video'             => $has_video,
			'has_funding'           => ! empty( array_filter( $funding ) ),
			'is_fast_publishing'    => $is_active && $last_released && $release_cycle > 0 && $release_cycle <= 10 && ( time() - $last_released ) <= $recent_window,
			'mostly_imported'       => $episode_count > 0 && $import_ratio >= 0.75,
		);

		return self::$feed_contexts[ $feed_key ];
	}

	/**
	 * Get aggregate site podcast context.
	 *
	 * @return array
	 */
	private static function get_site_summary() {
		$feed_index        = Get_Fn::get_feed_index();
		$podcast_count     = is_array( $feed_index ) ? count( $feed_index ) : 0;
		$max_episode_count = 0;
		$has_video         = false;

		if ( is_array( $feed_index ) ) {
			foreach ( array_keys( $feed_index ) as $feed_key ) {
				$context           = self::get_feed_context( $feed_key );
				$max_episode_count = max( $max_episode_count, $context['episode_count'] );
				$has_video         = $has_video || $context['has_video'];
			}
		}

		return array(
			'podcast_count'     => $podcast_count,
			'max_episode_count' => $max_episode_count,
			'has_video'         => $has_video,
		);
	}

	/**
	 * Build an ecosystem product card.
	 *
	 * @param string $id          Product ID.
	 * @param string $title       Product title.
	 * @param string $subtitle    Product subtitle.
	 * @param string $description Product description.
	 * @param string $url         Product URL.
	 * @param string $cta         CTA text.
	 * @param int    $priority    Sort priority.
	 * @return array
	 */
	private static function product( $id, $title, $subtitle, $description, $url, $cta, $priority ) {
		return array(
			'id'          => sanitize_key( $id ),
			'title'       => $title,
			'subtitle'    => $subtitle,
			'description' => $description,
			'url'         => esc_url_raw( $url ),
			'cta'         => $cta,
			'priority'    => absint( $priority ),
			'reason'      => '',
		);
	}

	/**
	 * Safely read an ItemData or array field.
	 *
	 * @param mixed  $item Episode item.
	 * @param string $key  Field key.
	 * @return mixed
	 */
	private static function item_value( $item, $key ) {
		if ( $item instanceof ItemData ) {
			return $item->get( $key, 'none' );
		}

		if ( is_array( $item ) && array_key_exists( $key, $item ) ) {
			return $item[ $key ];
		}

		return null;
	}

	/**
	 * Build a recommendation action.
	 *
	 * @param string $id          Action ID.
	 * @param string $label       Link label.
	 * @param string $description Supporting description.
	 * @param string $url         Destination URL.
	 * @param string $link_label  Optional CTA label.
	 * @return array
	 */
	private static function action( $id, $label, $description, $url, $link_label = '' ) {
		return array(
			'id'          => sanitize_key( $id ),
			'label'       => $label,
			'description' => $description,
			'url'         => esc_url_raw( $url ),
			'link_label'  => $link_label ? $link_label : $label,
		);
	}

	/**
	 * Build several messages for one recommendation type.
	 *
	 * @param string $id       Action ID base.
	 * @param array  $messages Label and description pairs.
	 * @param string $url      Destination URL.
	 * @return array
	 */
	private static function action_variants( $id, $messages, $url ) {
		$actions = array();
		foreach ( $messages as $index => $message ) {
			$label       = isset( $message[0] ) ? $message[0] : '';
			$description = isset( $message[1] ) ? $message[1] : '';
			if ( ! $label ) {
				continue;
			}
			$actions[] = self::action( $id . '_' . absint( $index ), $label, $description, $url );
		}

		return $actions;
	}

	/**
	 * Randomly choose one eligible action for this admin page load.
	 *
	 * @param array  $actions Recommendation actions.
	 * @param string $seed    Stable seed.
	 * @return array|false
	 */
	private static function random_action( $actions, $seed ) {
		$actions = array_values( array_filter( $actions ) );
		if ( empty( $actions ) ) {
			return false;
		}
		if ( 1 === count( $actions ) ) {
			return $actions[0];
		}

		$key = 'pp_marketing_random_' . md5( (string) $seed );
		if ( isset( self::$random_choices[ $key ] ) ) {
			$index = self::$random_choices[ $key ] % count( $actions );
			return $actions[ $index ];
		}

		$index                       = function_exists( 'wp_rand' ) ? wp_rand( 0, count( $actions ) - 1 ) : mt_rand( 0, count( $actions ) - 1 );
		self::$random_choices[ $key ] = $index;
		return $actions[ $index ];
	}

	/**
	 * Check whether a plugin is active without requiring admin-only helpers.
	 *
	 * @param string $plugin_file Plugin basename.
	 * @return bool
	 */
	private static function is_plugin_active( $plugin_file ) {
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( $plugin_file ) ) {
			return true;
		}

		$active_plugins = (array) get_option( 'active_plugins', array() );
		if ( in_array( $plugin_file, $active_plugins, true ) ) {
			return true;
		}

		if ( is_multisite() ) {
			$network_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
			return isset( $network_plugins[ $plugin_file ] );
		}

		return false;
	}

	/**
	 * Check for a local podcast migration signal.
	 */
	private static function has_selfhost_migration_signal() {
		return self::is_plugin_active( 'powerpress/powerpress.php' ) || self::is_plugin_active( 'seriously-simple-podcasting/seriously-simple-podcasting.php' );
	}

	/**
	 * Check for a membership or customer access signal.
	 */
	private static function has_membership_signal() {
		return self::is_plugin_active( 'woocommerce/woocommerce.php' )
			|| self::is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' )
			|| self::is_plugin_active( 'memberpress/memberpress.php' )
			|| self::is_plugin_active( 'paid-memberships-pro/paid-memberships-pro.php' );
	}

	/**
	 * Check for Selfhost Podcasting.
	 */
	private static function is_selfhost_active() {
		return defined( 'SH_PODCASTING_VERSION' ) || self::is_plugin_active( 'selfhost-podcasting/selfhost-podcasting.php' );
	}

	/**
	 * Check for Podcast Composer.
	 */
	private static function is_composer_active() {
		return defined( 'PODCAST_COMPOSER_VERSION' ) || self::is_plugin_active( 'podcast-composer/podcast-composer.php' );
	}

	/**
	 * Check for Easy TubeCasting.
	 */
	private static function is_tubecasting_active() {
		return defined( 'EASY_TUBECASTING_VERSION' ) || self::is_plugin_active( 'easy-tubecasting/easy-tubecasting.php' );
	}
}
