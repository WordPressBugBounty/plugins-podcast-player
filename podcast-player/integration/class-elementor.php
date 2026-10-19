<?php
/**
 * Optional native Elementor integration.
 *
 * @package Podcast_Player
 */
namespace Podcast_Player\Integration;

use Podcast_Player\Backend\Inc\Widget;
use Podcast_Player\Helper\Functions\Getters;
use Podcast_Player\Helper\Functions\Utility;

class Elementor {
	/** Register hooks without loading any Elementor classes prematurely. */
	public static function init() {
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widget' ) );
		add_action( 'elementor/controls/register', array( __CLASS__, 'register_controls' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'editor_assets' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( __CLASS__, 'preview_icons' ) );
		add_filter( 'podcast_player_feed_items', array( __CLASS__, 'cover_artwork' ), 20, 3 );
		add_action( 'wp_ajax_pp_elementor_options', array( __CLASS__, 'ajax_options' ) );
	}

	public static function register_widget( $manager ) {
		$manager->register( new Elementor_Widget() );
	}

	public static function register_controls( $manager ) {
		$manager->register( new Elementor_Query_Control() );
	}

	public static function editor_assets() {
		wp_enqueue_style( 'pp-elementor-editor', PODCAST_PLAYER_URL . 'integration/elementor-editor.css', array(), PODCAST_PLAYER_VERSION );
		wp_enqueue_script( 'pp-elementor-editor', PODCAST_PLAYER_URL . 'integration/elementor-editor.js', array( 'elementor-editor' ), PODCAST_PLAYER_VERSION, true );
		wp_localize_script( 'pp-elementor-editor', 'ppElementor', array(
			'url'   => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'pp-elementor-options' ),
			'error' => __( 'Unable to load options. Check the source and try again.', 'podcast-player' ),
		) );
	}

	/** The preview can introduce any service icon after the initial footer render. */
	public static function preview_icons() {
		$icons = \Podcast_Player\Frontend\Inc\Icon_Loader::get_instance();
		remove_action( 'wp_footer', array( $icons, 'add_icons' ), 9999 );
		add_action( 'wp_footer', array( $icons, 'add_admin_icons' ), 9999 );
	}

	/** Apply an explicitly selected Elementor cover to the visible episode artwork. */
	public static function cover_artwork( $items, $source, $args ) {
		if ( empty( $args['cover-override'] ) || empty( $args['imgurl'] ) || ! empty( $args['hide-featured'] ) ) {
			return $items;
		}
		foreach ( $items as &$item ) {
			$item['featured'] = esc_url_raw( $args['imgurl'] );
			$item['fset'] = isset( $args['imgset'] ) ? $args['imgset'] : '';
			$item['fratio'] = isset( $args['imgratio'] ) ? (float) $args['imgratio'] : 1;
		}
		unset( $item );
		return $items;
	}

	/** Normalize Elementor media/time values before using the widget sanitizer. */
	public static function settings( $settings ) {
		$widget = new Widget();
		$settings = wp_parse_args( (array) $settings, $widget->get_defaults() );
		// Elementor supplies null for controls hidden by conditions.
		foreach ( $widget->get_defaults() as $key => $default ) {
			if ( ! isset( $settings[ $key ] ) ) { $settings[ $key ] = $default; }
		}
		if ( is_array( $settings['cover_image'] ) ) {
			$settings['cover_image'] = isset( $settings['cover_image']['id'] ) ? absint( $settings['cover_image']['id'] ) : 0;
		}
		if ( ! is_array( $settings['pp_start_time'] ) ) {
			$settings['pp_start_time'] = array_pad( array_slice( array_map( 'absint', explode( ':', (string) $settings['pp_start_time'] ) ), 0, 3 ), 3, 0 );
		}
		foreach ( array( 'pp_terms', 'pp_elist', 'pp_slist', 'pp_catlist' ) as $key ) {
			$settings[ $key ] = array_values( array_filter( array_map( 'sanitize_text_field', (array) $settings[ $key ] ), 'strlen' ) );
		}
		return wp_parse_args( $widget->update( $settings, array() ), $widget->get_defaults() );
	}

	/** Editor-only data for the native dependent selectors. */
	public static function ajax_options() {
		check_ajax_referer( 'pp-elementor-options', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit posts.', 'podcast-player' ) ), 403 );
		}
		$raw = isset( $_POST['settings'] ) && is_string( $_POST['settings'] ) ? json_decode( wp_unslash( $_POST['settings'] ), true ) : array();
		if ( ! is_array( $raw ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid settings.', 'podcast-player' ) ), 400 );
		}
		$field = isset( $_POST['field'] ) ? sanitize_key( $_POST['field'] ) : '';
		$allowed = array( 'feed_url', 'pp_taxonomy', 'pp_terms', 'pp_elist', 'pp_slist', 'pp_catlist' );
		if ( ! in_array( $field, $allowed, true ) ) {
			wp_send_json_error( array(), 400 );
		}
		$settings = self::settings( $raw );
		$options = array();
		if ( 'feed_url' === $field ) {
			foreach ( (array) Getters::get_feed_index() as $url => $feed ) {
				$options[ $url ] = isset( $feed['title'] ) ? $feed['title'] : $url;
			}
		} elseif ( ! apply_filters( 'podcast_player_is_premium', false ) ) {
			wp_send_json_error( array(), 403 );
		} elseif ( 'pp_taxonomy' === $field ) {
			foreach ( get_object_taxonomies( $settings['pp_post_type'], 'objects' ) as $name => $taxonomy ) {
				$options[ $name ] = $taxonomy->label;
			}
		} elseif ( 'pp_terms' === $field && $settings['pp_taxonomy'] ) {
			$terms = get_terms( array( 'taxonomy' => $settings['pp_taxonomy'], 'hide_empty' => false ) );
			if ( ! is_wp_error( $terms ) ) {
				$options = wp_list_pluck( $terms, 'name', 'slug' );
			}
		} elseif ( 'pp_elist' === $field && 'post' === $settings['pp_fetch_method'] ) {
			$data = apply_filters( 'podcast_player_posts_elist', array(
				'post-type' => $settings['pp_post_type'], 'taxonomy' => $settings['pp_taxonomy'],
				'terms' => $settings['pp_terms'], 'sortby' => $settings['sortby'],
				'filterby' => $settings['filterby'], 'number' => -1,
			) );
			$options = isset( $data['episodes'] ) ? $data['episodes'] : array();
		} elseif ( 'feed' === $settings['pp_fetch_method'] && $settings['feed_url'] && in_array( $field, array( 'pp_elist', 'pp_slist', 'pp_catlist' ), true ) ) {
			// Seasons/categories always list the whole feed; episode choices respect their filters.
			if ( 'pp_elist' !== $field ) {
				$settings['pp_slist'] = $settings['pp_catlist'] = array();
			}
			$feed = ( new Widget() )->fetch_feed_data( $settings );
			if ( is_wp_error( $feed ) ) {
				wp_send_json_error( array( 'message' => $feed->get_error_message() ) );
			}
			$lists = Utility::multi_array_columns( array( 'items', 'seasons', 'categories' ), $feed );
			if ( 'pp_elist' === $field ) {
				$options = wp_list_pluck( $lists['items'], 'title' );
			} elseif ( 'pp_slist' === $field ) {
				foreach ( $lists['seasons'] as $season ) {
					$options[ '0' . $season ] = sprintf( __( 'Season %s', 'podcast-player' ), $season );
				}
			} else {
				$options = $lists['categories'];
			}
		}
		$results = array();
		foreach ( $options as $id => $label ) {
			if ( '' !== (string) $id ) {
				$results[] = array( 'id' => (string) $id, 'text' => wp_strip_all_tags( $label ) );
			}
		}
		wp_send_json_success( $results );
	}
}
