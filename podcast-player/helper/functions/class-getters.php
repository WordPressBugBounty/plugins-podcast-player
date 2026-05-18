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
use Podcast_Player\Helper\Feed\Get_Feed;
use Podcast_Player\Helper\Functions\Validation as Validation_Fn;
use Podcast_Player\Backend\Admin\Options;
use Podcast_Player\Helper\Functions\Utility as Utility_Fn;
use Podcast_Player\Helper\Store\StoreManager;
use Podcast_Player\Helper\Store\FeedData;
use Podcast_Player\Helper\Store\ItemData;
use Podcast_Player\Helper\Store\StorageRegister;

/**
 * Podcast player utility functions.
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 * @author     vedathemes <contact@vedathemes.com>
 */
class Getters {

	/**
	 * Constructor method.
	 *
	 * @since  3.3.0
	 */
	public function __construct() {}

	/**
	 * Add attributes strings to all HTML A elements in content.
	 *
	 * @since 3.3.0
	 *
	 * @param string $feed_url Podcast feed url.
	 * @param array  $mods     Feed episode filter args.
	 * @param array  $fields   Required episode field keys.
	 */
	public static function get_feed_data( $feed_url, $mods = array(), $fields = array() ) {
		$feed_url = self::get_valid_feed_url( $feed_url );
		if ( is_wp_error( $feed_url ) ) {
			return $feed_url;
		}

		$obj  = new Get_Feed( $feed_url, $mods, $fields );
		$data = $obj->init();
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		// Cron update only if auto import or cron update is enabled.
		$import_data = self::get_feed_import_settings( $feed_url );
		$is_auto     = $import_data['is_auto'];
		$cron_update = Get_Fn::get_plugin_option( 'update_method' );
		if ( $is_auto || 'yes' === $cron_update ) {
			Utility_Fn::schedule_next_auto_update( $feed_url );
		}
		return $data;
	}

	/**
	 * Check and Get valid podcast feed url.
	 *
	 * @since 1.0.0
	 *
	 * @param str $url Url to be checked or fetched.
	 * @return str
	 */
	public static function get_valid_feed_url( $url ) {

		// Check if a valid url has been provided.
		if ( Validation_Fn::is_valid_url( $url ) ) {
			return wp_strip_all_tags( $url );
		}

		// Check if url has been provided in as a custom field.
		$custom_keys = get_post_custom_keys();
		if ( $custom_keys && in_array( $url, $custom_keys, true ) ) {
			$murl = get_post_custom_values( $url );
			$murl = is_array( $murl ) ? $murl[0] : $murl;

			// Check if a valid url has been provided.
			if ( Validation_Fn::is_valid_url( $murl ) ) {
				return wp_strip_all_tags( $murl );
			}
		}

		$url = self::get_feed_url_from_index( $url );
		if ( $url ) {
			return wp_strip_all_tags( $url );
		}

		return new \WP_Error( 'invalid_url', esc_html__( 'Please provide a valid feed url.', 'podcast-player' ) );
	}

	/**
	 * Check and Get valid podcast episode media url.
	 *
	 * @since 4.0.0
	 *
	 * @param str $url Url to be checked or fetched.
	 * @return str
	 */
	public static function get_valid_media_url( $url ) {
		// Check if a valid url has been provided.
		if ( Validation_Fn::is_valid_url( $url ) ) {
			return wp_strip_all_tags( $url );
		}

		// Check if url has been provided in as a custom field.
		$custom_keys = get_post_custom_keys();
		if ( $custom_keys && in_array( $url, $custom_keys, true ) ) {
			$murl = get_post_custom_values( $url );
			$murl = is_array( $murl ) ? $murl[0] : $murl;

			// Check if a valid url has been provided.
			if ( Validation_Fn::is_valid_url( $murl ) ) {
				return wp_strip_all_tags( $murl );
			}
		}

		return false;
	}

	/**
	 * Get feed url from the feed index.
	 *
	 * @since 3.5.0
	 *
	 * @param string $key Feed unique key.
	 */
	public static function get_feed_url_from_index( $key ) {
		$key = apply_filters( 'podcast_player_resolve_feed_key', $key );
		$feed_index = self::get_feed_index();
		if ( $feed_index && isset( $feed_index[ $key ] ) ) {
			$info = $feed_index[ $key ];
			if ( isset( $info['url'] ) && $info['url'] && Validation_Fn::is_valid_url( $info['url'] ) ) {
				return wp_strip_all_tags( $info['url'] );
			}
		}

		$store_manager = StoreManager::get_instance();
		$index         = $store_manager->get_object_index( $key );
		if ( is_object( $index ) && method_exists( $index, 'get' ) ) {
			$url = $index->get( 'source_url', 'none' );
			if ( $url && Validation_Fn::is_valid_url( $url ) ) {
				return wp_strip_all_tags( $url );
			}

			$urls = $index->get( 'feed_url', 'none' );
			if ( ! empty( $urls ) && is_array( $urls ) ) {
				foreach ( $urls as $url ) {
					if ( Validation_Fn::is_valid_url( $url ) ) {
						return wp_strip_all_tags( $url );
					}
				}
			}
		}

		return false;
	}

	/**
	 * Check if url is video or audio media url.
	 *
	 * @since 3.3.0
	 *
	 * @param string $media Media url to be checked.
	 */
	public static function get_media_type( $media ) {
		$audio_ext  = wp_get_audio_extensions();
		$video_ext  = wp_get_video_extensions();
		$mime_types = wp_get_mime_types();
		$media_type = false;

		// Adding support for aac file extension.
		$audio_ext[] = 'aac';
		$media_url   = $media ? preg_replace( '/\?.*/', '', $media ) : false;
		if ( $media_url ) {
			$type = wp_check_filetype( $media_url, $mime_types );
			if ( in_array( strtolower( $type['ext'] ), $audio_ext, true ) ) {
				$media_type = 'audio';
			} elseif ( in_array( strtolower( $type['ext'] ), $video_ext, true ) ) {
				$media_type = 'video';
			}
		}
		return apply_filters( 'podcast_player_media_type', $media_type, $media );
	}

	/**
	 * Get all available display styles.
	 *
	 * @return array
	 */
	public static function display_styles() {

		/**
		 * Get podcast player display styles.
		 *
		 * @since 3.3.0
		 *
		 * @param array $styles Array of styles available in podcast player.
		 */
		return apply_filters(
			'podcast_player_display_styles',
			array(
				array(
					'style_id' => 'modern',
					'label'    => esc_html__( 'Modern Player', 'podcast-player' ),
					'support'  => array( 'bgcolor' ),
				),
				array(
					'style_id' => '',
					'label'    => esc_html__( 'Default Player', 'podcast-player' ),
					'support'  => array( 'excerpt', 'bgcolor' ),
				),
				array(
					'style_id' => 'legacy',
					'label'    => esc_html__( 'Catalogue (Legacy) Player', 'podcast-player' ),
					'support'  => array( 'bgcolor' ),
				),
			)
		);
	}

	/**
	 * Get elements supported by selected style.
	 *
	 * @return array
	 */
	public static function get_styles() {
		return array_column( self::display_styles(), 'label', 'style_id' );
	}

	/**
	 * Get elements supported by selected style.
	 *
	 * @return array
	 */
	public static function get_style_supported() {
		return array_column( self::display_styles(), 'support', 'style_id' );
	}

	/**
	 * Get plugin options.
	 *
	 * @since 3.3.0
	 *
	 * @param string $key Get option value for an option key.
	 */
	public static function get_plugin_option( $key ) {
		$all_options = get_option( 'pp-common-options' );
		$params      = self::get_plugin_option_fields( $key );

		// Return false if plugin option do not exists.
		if ( ! $params ) {
			return false;
		}

		// Return default value if options are not yet saved.
		if ( false === $all_options ) {
			return $params['default'];
		}

		// Return saved or default plugin option.
		return isset( $all_options[ $key ] ) ? $all_options[ $key ] : $params['default'];
	}

	/**
	 * Get plugin's options fields array.
	 *
	 * @since 3.5.0
	 *
	 * @param string $key Plugin option key.
	 */
	public static function get_plugin_option_fields( $key ) {
		$options = Options::get_instance();
		$fields  = $options->get_setting_fields();
		if ( isset( $fields[ $key ] ) ) {
			return $fields[ $key ];
		}
		return false;
	}

	/**
	 * Get podcast feed index.
	 *
	 * @return array
	 */
	public static function get_feed_index() {
		$store_manager = StoreManager::get_instance();
		$all_feeds     = $store_manager->get_object_index();
		array_walk(
			$all_feeds,
			function ( &$value, $key ) {
				$urls   = $value->get( 'feed_url', 'none' );
				$source = $value->get( 'source_url', 'none' );
				$url    = $source && Validation_Fn::is_valid_url( $source ) ? $source : '';
				if ( ! $url && ! empty( $urls ) && is_array( $urls ) ) {
					foreach ( $urls as $candidate ) {
						if ( Validation_Fn::is_valid_url( $candidate ) ) {
							$url = $candidate;
							break;
						}
					}
				}
				$value  = array(
					'url'        => $url,
					'title'      => $value->get( 'title' ),
					'source_url' => $source,
				);
			}
		);
		return apply_filters( 'podcast_player_feed_index', $all_feeds );
	}

	/**
	 * Get fields available for podcast-specific player defaults.
	 *
	 * This storage is intentionally available in the core plugin so companion
	 * integrations can keep one managed player profile even when Pro is not active.
	 *
	 * @since 8.1.1
	 *
	 * @return array
	 */
	public static function get_podcast_default_fields() {
		$menus = wp_get_nav_menus();
		$menus = wp_list_pluck( $menus, 'name', 'term_id' );
		$menus = array( '' => esc_html__( 'None', 'podcast-player' ) ) + $menus;

		$fields = array(
			'number'           => array( 'type' => 'number' ),
			'offset'           => array( 'type' => 'number' ),
			'sortby'           => array(
				'type'    => 'select',
				'choices' => array(
					'sort_title_desc' => esc_html__( 'Title Descending', 'podcast-player' ),
					'sort_title_asc'  => esc_html__( 'Title Ascending', 'podcast-player' ),
					'sort_date_desc'  => esc_html__( 'Date Descending', 'podcast-player' ),
					'sort_date_asc'   => esc_html__( 'Date Ascending', 'podcast-player' ),
					'no_sort'         => esc_html__( 'Do Not Sort', 'podcast-player' ),
					'reverse_sort'    => esc_html__( 'Reverse Sort', 'podcast-player' ),
				),
			),
			'filterby'         => array( 'type' => 'text' ),
			'display-style'    => array(
				'type'    => 'select',
				'choices' => self::get_styles(),
			),
			'menu'             => array(
				'type'    => 'select',
				'choices' => $menus,
			),
			'main_menu_items'  => array( 'type' => 'number' ),
			'image'            => array( 'type' => 'image' ),
			'img_url'          => array( 'type' => 'url' ),
			'description'      => array( 'type' => 'textarea' ),
			'teaser-text'      => array(
				'type'    => 'select',
				'choices' => array(
					''     => esc_html__( 'Show Excerpt', 'podcast-player' ),
					'full' => esc_html__( 'Show Full Content', 'podcast-player' ),
					'none' => esc_html__( 'Hide Teaser Text', 'podcast-player' ),
				),
			),
			'excerpt-length'   => array( 'type' => 'number' ),
			'excerpt-unit'     => array(
				'type'    => 'select',
				'choices' => array(
					''     => esc_html__( 'Number of words', 'podcast-player' ),
					'char' => esc_html__( 'Number of characters', 'podcast-player' ),
				),
			),
			'header-default'   => array( 'type' => 'checkbox' ),
			'list-default'     => array( 'type' => 'checkbox' ),
			'no-scroll'        => array( 'type' => 'checkbox' ),
			'hide-header'      => array( 'type' => 'checkbox' ),
			'hide-title'       => array( 'type' => 'checkbox' ),
			'hide-cover-img'   => array( 'type' => 'checkbox' ),
			'hide-description' => array( 'type' => 'checkbox' ),
			'hide-subscribe'   => array( 'type' => 'checkbox' ),
			'hide-search'      => array( 'type' => 'checkbox' ),
			'hide-author'      => array( 'type' => 'checkbox' ),
			'hide-content'     => array( 'type' => 'checkbox' ),
			'hide-loadmore'    => array( 'type' => 'checkbox' ),
			'hide-download'    => array( 'type' => 'checkbox' ),
			'hide-social'      => array( 'type' => 'checkbox' ),
			'hide-featured'    => array( 'type' => 'checkbox' ),
			'accent-color'     => array( 'type' => 'color' ),
			'bgcolor'          => array( 'type' => 'color' ),
			'txtcolor'         => array(
				'type'    => 'select',
				'choices' => array(
					''      => esc_html__( 'Dark Text', 'podcast-player' ),
					'ltext' => esc_html__( 'Light Text', 'podcast-player' ),
				),
			),
			'font-family'      => array( 'type' => 'text' ),
			'aspect-ratio'     => array(
				'type'    => 'select',
				'choices' => array(
					''       => esc_html__( 'No Cropping', 'podcast-player' ),
					'land1'  => esc_html__( 'Landscape (4:3)', 'podcast-player' ),
					'land2'  => esc_html__( 'Landscape (3:2)', 'podcast-player' ),
					'port1'  => esc_html__( 'Portrait (3:4)', 'podcast-player' ),
					'port2'  => esc_html__( 'Portrait (2:3)', 'podcast-player' ),
					'wdscrn' => esc_html__( 'Widescreen (16:9)', 'podcast-player' ),
					'squr'   => esc_html__( 'Square (1:1)', 'podcast-player' ),
				),
			),
			'crop-method'      => array(
				'type'    => 'select',
				'choices' => array(
					'topleftcrop'      => esc_html__( 'Top Left Cropping', 'podcast-player' ),
					'topcentercrop'    => esc_html__( 'Top Center Cropping', 'podcast-player' ),
					'centercrop'       => esc_html__( 'Center Cropping', 'podcast-player' ),
					'bottomleftcrop'   => esc_html__( 'Bottom Left Cropping', 'podcast-player' ),
					'bottomcentercrop' => esc_html__( 'Bottom Center Cropping', 'podcast-player' ),
				),
			),
			'grid-columns'     => array( 'type' => 'number' ),
			'feedback'         => array( 'type' => 'checkbox' ),
			'show-form-time'   => array( 'type' => 'number' ),
			'feedback-text'    => array( 'type' => 'text' ),
			'positive-text'    => array( 'type' => 'text' ),
			'positive-url'     => array( 'type' => 'url' ),
			'negative-text'    => array( 'type' => 'text' ),
			'negative-form'    => array( 'type' => 'checkbox' ),
		);

		foreach ( self::get_services_list() as $key => $label ) {
			$fields[ $key . '-sub' ] = array(
				'label' => $label,
				'type'  => 'url',
			);
		}

		return apply_filters( 'podcast_player_default_fields', $fields );
	}

	/**
	 * Get saved defaults for a podcast.
	 *
	 * @since 8.1.1
	 *
	 * @param string $podcast Podcast feed URL or key.
	 * @return array
	 */
	public static function get_podcast_defaults( $podcast ) {
		if ( ! $podcast ) {
			return array();
		}

		$store_manager = StoreManager::get_instance();
		$defaults      = $store_manager->get_data( $podcast, 'podcast_defaults' );

		return is_array( $defaults ) ? self::sanitize_podcast_defaults( $defaults ) : array();
	}

	/**
	 * Save defaults for a podcast.
	 *
	 * @since 8.1.1
	 *
	 * @param string $podcast  Podcast feed URL or key.
	 * @param array  $defaults Raw defaults.
	 * @return array
	 */
	public static function save_podcast_defaults( $podcast, $defaults ) {
		if ( ! $podcast ) {
			return array();
		}

		$defaults      = self::sanitize_podcast_defaults( $defaults );
		$store_manager = StoreManager::get_instance();

		if ( empty( $defaults ) ) {
			$store_manager->delete_data( $podcast, 'podcast_defaults' );
			return array();
		}

		$store_manager->update_data( $defaults, $podcast, 'podcast_defaults' );
		return $defaults;
	}

	/**
	 * Delete defaults for a podcast.
	 *
	 * @since 8.1.1
	 *
	 * @param string $podcast Podcast feed URL or key.
	 * @return bool
	 */
	public static function delete_podcast_defaults( $podcast ) {
		if ( ! $podcast ) {
			return false;
		}

		$store_manager = StoreManager::get_instance();
		return $store_manager->delete_data( $podcast, 'podcast_defaults' );
	}

	/**
	 * Sanitize podcast-specific defaults.
	 *
	 * @since 8.1.1
	 *
	 * @param array $defaults Raw defaults.
	 * @return array
	 */
	public static function sanitize_podcast_defaults( $defaults ) {
		if ( ! is_array( $defaults ) ) {
			return array();
		}

		$fields    = self::get_podcast_default_fields();
		$sanitized = array();

		foreach ( $fields as $key => $field ) {
			if ( ! array_key_exists( $key, $defaults ) ) {
				continue;
			}

			$type  = isset( $field['type'] ) ? $field['type'] : 'text';
			$value = $defaults[ $key ];

			switch ( $type ) {
				case 'checkbox':
					$value = ( true === $value || 'true' === $value || '1' === (string) $value || 'yes' === $value ) ? 'yes' : '';
					break;
				case 'number':
					$value = '' === $value || null === $value ? '' : absint( $value );
					break;
				case 'image':
					$value = absint( $value );
					if ( $value && false === wp_get_attachment_image_src( $value, 'large' ) ) {
						$value = '';
					}
					break;
				case 'url':
					$value = esc_url_raw( $value );
					break;
				case 'color':
					$value = sanitize_hex_color( $value );
					break;
				case 'select':
					$value   = sanitize_text_field( $value );
					$choices = isset( $field['choices'] ) && is_array( $field['choices'] ) ? $field['choices'] : array();
					if ( ! array_key_exists( $value, $choices ) ) {
						$value = '';
					}
					break;
				case 'textarea':
					$value = sanitize_textarea_field( $value );
					break;
				default:
					$value = sanitize_text_field( $value );
					break;
			}

			if ( '' === $value || null === $value || false === $value ) {
				continue;
			}

			$sanitized[ $key ] = $value;
		}

		return $sanitized;
	}

	/**
	 * Get image src and srcset.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Image attachment ID.
	 * @param str $size Required Image size.
	 * @return array
	 */
	public static function get_image_src_set( $id, $size ) {
		$image  = wp_get_attachment_image_src( $id, $size );
		$src    = '';
		$srcset = '';
		$ratio  = 1;
		if ( $image ) {
			list( $url, $width, $height ) = $image;
			// Get image src.
			$src = $url;

			// Get Image ratio.
			if ( $width && $height ) {
				$ratio = $height / $width;
			}

			// Get image srcset.
			$image_meta = wp_get_attachment_metadata( $id );
			if ( is_array( $image_meta ) ) {
				$size_array = array( absint( $width ), absint( $height ) );
				$srcset     = wp_calculate_image_srcset( $size_array, $src, $image_meta, $id );
				$srcset     = $srcset ? $srcset : '';
			}
		}
		return array(
			'src'    => $src,
			'srcset' => $srcset,
			'ratio'  => $ratio,
		);
	}

	/**
	 * Get unique key of the given url.
	 *
	 * @since 3.3.0
	 *
	 * @param string $url Url for which unique key to be generated.
	 */
	public static function get_url_key( $url ) {
		$url = wp_strip_all_tags( $url );
		if ( ! Validation_Fn::is_valid_url( $url ) ) {
			return '';
		}
		return md5( $url );
	}

	/**
	 * Get podcast service from the link.
	 *
	 * @param string $link Podcast Subcription Link.
	 *
	 * @since 5.6.0
	 */
	public static function get_podcast_service( $link ) {

		/**
		 * Filter subscription links markup.
		 *
		 * @since 5.4.0
		 *
		 * @param array $sub_links_markup Array of subscription links markup.
		 */
		$sub_icons = apply_filters(
			'pp_subscription_links_markup',
			array(
				'podcasts.apple.com'  => 'apple',
				'deezer.com'          => 'deezer',
				'breaker.audio'       => 'breaker',
				'castbox.fm'          => 'castbox',
				'castro.fm'           => 'castro',
				'podcasts.google.com' => 'google',
				'iheart.com'          => 'iheart',
				'overcast.fm'         => 'overcast',
				'pocketcasts.com'     => 'pocketcasts',
				'pca.st'              => 'pocketcasts',
				'podcastaddict.com'   => 'podcastaddict',
				'podchaser.com'       => 'podchaser',
				'radiopublic.com'     => 'radiopublic',
				'soundcloud.com'      => 'soundcloud',
				'spotify.com'         => 'spotify',
				'stitcher.com'        => 'pandora',
				'pandora.com'         => 'pandora',
				'tunein.com'          => 'tunein',
				'youtube.com'         => 'youtube',
				'bullhorn.fm'         => 'bullhorn',
				'podbean.com'         => 'podbean',
				'player.fm'           => 'playerfm',
				'music.amazon'        => 'amazon',
			)
		);

		$service = false;
		foreach ( $sub_icons as $attr => $value ) {
			if ( false !== strpos( $link, $attr ) ) {
				$service = $value;
				break;
			}
		}

		return $service;
	}

	/**
	 * Get podcast service list.
	 *
	 * @since 5.6.0
	 */
	public static function get_services_list() {

		/**
		 * Filter podcast subscription services.
		 *
		 * @since 5.4.0
		 *
		 * @param array $services Array of supported subscription services.
		 */
		return apply_filters(
			'pp_subscription_services',
			array(
				'apple'         => esc_html__( 'Apple', 'podcast-player' ),
				'google'        => esc_html__( 'Google', 'podcast-player' ),
				'spotify'       => esc_html__( 'Spotify', 'podcast-player' ),
				'amazon'        => esc_html__( 'Amazon Music', 'podcast-player' ),
				'breaker'       => esc_html__( 'Breaker', 'podcast-player' ),
				'castbox'       => esc_html__( 'Castbox', 'podcast-player' ),
				'castro'        => esc_html__( 'Castro', 'podcast-player' ),
				'iheart'        => esc_html__( 'iHeart Radio', 'podcast-player' ),
				'overcast'      => esc_html__( 'Overcast', 'podcast-player' ),
				'pocketcasts'   => esc_html__( 'Pocket Casts', 'podcast-player' ),
				'podcastaddict' => esc_html__( 'Podcast Addict', 'podcast-player' ),
				'podchaser'     => esc_html__( 'Podchaser', 'podcast-player' ),
				'radiopublic'   => esc_html__( 'Radio Public', 'podcast-player' ),
				'soundcloud'    => esc_html__( 'Soundcloud', 'podcast-player' ),
				'stitcher'      => esc_html__( 'Pandora', 'podcast-player' ),
				'tunein'        => esc_html__( 'Tune In', 'podcast-player' ),
				'youtube'       => esc_html__( 'YouTube', 'podcast-player' ),
				'bullhorn'      => esc_html__( 'BullHorn', 'podcast-player' ),
				'podbean'       => esc_html__( 'Podbean', 'podcast-player' ),
				'playerfm'      => esc_html__( 'PlayerFM', 'podcast-player' ),
			)
		);
	}

	/**
	 * Get podcast import information (if any).
	 *
	 * @since 6.5.0
	 *
	 * @param string $feed_key Podcast Feed Key.
	 */
	public static function get_feed_import_settings( $feed_key ) {
		$store_manager = StoreManager::get_instance();
		$import_data   = $store_manager->get_data( $feed_key, 'feed_import' );
		if ( ! $import_data ) {
			// Run older method to fetch import settings from options table.
			if ( Validation_Fn::is_valid_url( $feed_key ) ) {
				$feed_key = md5( $feed_key );
			}
			$podcasts = get_option( 'pp_feed_index' );
			if ( is_array( $podcasts ) && ! empty( $podcasts[ $feed_key ] ) ) {
				$pod_data = $podcasts[ $feed_key ];
				if ( is_array( $pod_data ) && ! empty( $pod_data['import'] ) ) {
					$import_data = $pod_data['import'];
					$store_manager->update_data( $import_data, $feed_key, 'feed_import' );
				}
			}
		}
		$import_data = $import_data ? $import_data : array();
		$is_auto     = isset( $import_data['is_auto'] ) ? $import_data['is_auto'] : false;
		$import_data['is_auto'] = apply_filters( 'podcast_player_auto_import', $is_auto, $feed_key );
		return $import_data;
	}

	/**
	 * Get External link data.
	 *
	 * @since 7.3.0
	 * 
	 * @param string $url External link url.
	 */
	public static function get_external_link_data( $url ) {
		$content = wp_safe_remote_request(
			$url,
			array(
				'timeout' => 30,
				'sslverify' => false,
			)
		);

		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$content  = wp_remote_retrieve_body( $content );
		return json_decode( $content, true );
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

	/**
	 * Get extension from mime type.
	 *
	 * @since 7.9.12
	 *
	 * @param string $mime Mime Type.
	 */
	public static function get_extension_from_mime( $mime ) {

		if ( ! $mime ) {
			return 'jpg'; // fallback
		}

		$mime_types = wp_get_mime_types(); 

		foreach ( $mime_types as $exts => $type ) {
			if ( strtolower( $type ) === strtolower( $mime ) ) {
				$list = explode( '|', $exts );
				return $list[0];
			}
		}

		if ( strpos( $mime, 'image/' ) === 0 ) {
			return str_replace( 'image/', '', $mime );
		}

		return 'jpg';
	}
}
