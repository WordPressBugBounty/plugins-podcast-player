<?php
/**
 * Shortcode API: Display Podcast from feed url class
 *
 * @link       https://www.vedathemes.com
 * @since      1.0.0
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/admin
 */

namespace Podcast_Player\Backend\Inc;

use Podcast_Player\Frontend\Inc\Display;
use Podcast_Player\Helper\Core\Singleton;
use Podcast_Player\Backend\Admin\ShortCodeGen;
use Podcast_Player\Helper\Functions\Utility as Utility_Fn;

/**
 * Class used to display podcast episodes from a feed url.
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/admin
 * @author     vedathemes <contact@vedathemes.com>
 */
class Shortcode extends Singleton {
	/**
	 * Podcast player shortcode function.
	 *
	 * @since 1.0.0
	 *
	 * @param array $atts User defined attributes in shortcode tag.
	 * @param str   $pp_content Shortcode text content.
	 */
	public function render( $atts, $pp_content = null ) {

		$defaults = $this->get_defaults();
		$atts     = shortcode_atts( $defaults, $atts, 'podcastplayer' );
		$img_url  = '';
		$image_id = '';
		if ( $atts['cover_image_url'] ) {
			$dir = wp_upload_dir();
			if ( false !== strpos( $atts['cover_image_url'], $dir['baseurl'] . '/' ) ) {
				$image_id = attachment_url_to_postid( esc_url( $atts['cover_image_url'] ) );
			} else {
				$img_url = $atts['cover_image_url'];
			}
		}

		/**
		 * Podcast player display params from shortcode.
		 *
		 * @since 3.3.0
		 *
		 * @param array $script_data Podcast data to be sent to front-end script.
		 * @param array $args        Podcast display args.
		 */
		$display_args = apply_filters(
			'podcast_player_shcode_display',
			array(
				'url'               => $atts['feed_url'],
				'sortby'            => $atts['sortby'],
				'filterby'          => $atts['filterby'],
				'autoplay'          => $atts['autoplay'],
				'number'            => absint( $atts['number'] ),
				'no-scroll'         => 'true' === $atts['no_scroll'] ? 1 : 0,
				'menu'              => $atts['podcast_menu'],
				'main_menu_items'   => $atts['main_menu_items'],
				'description'       => $pp_content,
				'image'             => $image_id,
				'img_url'           => $img_url,
				'header-default'    => 'true' === $atts['header_default'] ? 1 : 0,
				'list-default'      => 'true' === $atts['list_default'] ? 1 : 0,
				'hide-header'       => 'true' === $atts['hide_header'] ? 1 : 0,
				'hide-title'        => 'true' === $atts['hide_title'] ? 1 : 0,
				'hide-cover-img'    => 'true' === $atts['hide_cover'] ? 1 : 0,
				'hide-description'  => 'true' === $atts['hide_description'] ? 1 : 0,
				'hide-subscribe'    => 'true' === $atts['hide_subscribe'] ? 1 : 0,
				'hide-search'       => 'true' === $atts['hide_search'] ? 1 : 0,
				'hide-author'       => 'true' === $atts['hide_author'] ? 1 : 0,
				'hide-content'      => 'true' === $atts['hide_content'] ? 1 : 0,
				'hide-loadmore'     => 'true' === $atts['hide_loadmore'] ? 1 : 0,
				'hide-download'     => 'true' === $atts['hide_download'] ? 1 : 0,
				'hide-social'       => 'true' === $atts['hide_social'] ? 1 : 0,
				'hide-featured'     => 'true' === $atts['hide_featured'] ? 1 : 0,
				'accent-color'      => $atts['accent_color'],
				'display-style'     => $atts['display_style'],
				'teaser-text'       => $atts['teaser_text'],
				'offset'            => absint( $atts['offset'] ),
				'excerpt-length'    => $atts['excerpt_length'],
				'excerpt-unit'      => $atts['excerpt_unit'],
				'from'              => 'shortcode',
				'apple-sub'         => $atts['apple_sub'],
				'google-sub'        => $atts['google_sub'],
				'spotify-sub'       => $atts['spotify_sub'],
				'breaker-sub'       => $atts['breaker_sub'],
				'castbox-sub'       => $atts['castbox_sub'],
				'castro-sub'        => $atts['castro_sub'],
				'iheart-sub'        => $atts['iheart_sub'],
				'amazon-sub'        => $atts['amazon_sub'],
				'overcast-sub'      => $atts['overcast_sub'],
				'pocketcasts-sub'   => $atts['pocketcasts_sub'],
				'podcastaddict-sub' => $atts['podcastaddict_sub'],
				'podchaser-sub'     => $atts['podchaser_sub'],
				'radiopublic-sub'   => $atts['radiopublic_sub'],
				'soundcloud-sub'    => $atts['soundcloud_sub'],
				'stitcher-sub'      => $atts['stitcher_sub'],
				'tunein-sub'        => $atts['tunein_sub'],
				'youtube-sub'       => $atts['youtube_sub'],
				'bullhorn-sub'      => $atts['bullhorn_sub'],
				'podbean-sub'       => $atts['podbean_sub'],
				'playerfm-sub'      => $atts['playerfm_sub'],
			),
			$atts
		);

		$display = Display::get_instance();
		return $display->init( $display_args, true );
	}

	/**
	 * Podcast Player new shortcode function.
	 *
	 * @since 7.9.0
	 *
	 * @param array $atts User defined attributes in shortcode tag.
	 * @param str   $dpt_content Shortcode text content.
	 */
	public function renderPodcast( $atts, $dpt_content = null ) {
		$instance = isset( $atts['instance'] ) ? absint( $atts['instance'] ) : false;
		if ( false === $instance ) {
			return '';
		}

		$shortcodegen = new ShortCodeGen();
		return $shortcodegen->render( $instance, false );
	}

	/**
	 * Podcast player shortcode defaults.
	 *
	 * @since 3.3.0
	 */
	private function get_defaults() {
		return array(
			'feed_url'          => '',
			'sortby'            => 'sort_date_desc',
			'filterby'          => '',
			'autoplay'          => '',
			'number'            => 10,
			'offset'            => 0,
			'podcast_menu'      => '',
			'main_menu_items'   => 0,
			'cover_image_url'   => '',
			'teaser_text'       => '',
			'excerpt_length'    => 25,
			'excerpt_unit'      => '',
			'grid_columns'      => 3,
			'aspect_ratio'      => 'squr',
			'crop_method'       => 'centercrop',
			'no_scroll'         => '',
			'header_default'    => '',
			'list_default'      => '',
			'hide_header'       => '',
			'hide_title'        => '',
			'hide_cover'        => '',
			'hide_description'  => '',
			'hide_subscribe'    => '',
			'hide_search'       => '',
			'hide_author'       => '',
			'hide_content'      => '',
			'hide_loadmore'     => '',
			'hide_download'     => '',
			'hide_social'       => '',
			'hide_featured'     => '',
			'accent_color'      => '',
			'display_style'     => '',
			'fetch_method'      => 'feed',
			'post_type'         => 'post',
			'taxonomy'          => '',
			'terms'             => '',
			'podtitle'          => '',
			'mediasrc'          => '',
			'episodetitle'      => '',
			'episodelink'       => '',
			'audio_msg'         => '',
			'play_freq'         => 0,
			'msg_start'         => 'start',
			'msg_time'          => '',
			'msg_text'          => esc_html__( 'Episode will play after this message.', 'podcast-player' ),
			'font_family'       => '',
			'bgcolor'           => '',
			'txtcolor'          => '',
			'elist'             => '',
			'seasons'           => '',
			'episodes'          => '',
			'categories'        => '',
			'apple_sub'         => '',
			'google_sub'        => '',
			'spotify_sub'       => '',
			'breaker_sub'       => '',
			'castbox_sub'       => '',
			'castro_sub'        => '',
			'iheart_sub'        => '',
			'amazon_sub'        => '',
			'overcast_sub'      => '',
			'pocketcasts_sub'   => '',
			'podcastaddict_sub' => '',
			'podchaser_sub'     => '',
			'radiopublic_sub'   => '',
			'soundcloud_sub'    => '',
			'stitcher_sub'      => '',
			'tunein_sub'        => '',
			'youtube_sub'       => '',
			'bullhorn_sub'      => '',
			'podbean_sub'       => '',
			'playerfm_sub'      => '',
			'feedback'          => '',
			'show_form_time'    => 60,
			'feedback_text'     => esc_html__( 'Are you enjoying this episode?', 'podcast-player' ),
			'positive_text'     => esc_html__( 'Thanks for your feedback.', 'podcast-player' ),
			'positive_url'      => '',
			'negative_text'     => esc_html__( 'Sorry you did not like it. Please share your feedback to help us improve.', 'podcast-player' ),
			'negative_form'     => 'yes',
		);
	}

	/**
	 * Get podcast player render for preview in admin page.
	 *
	 * @since 7.9.0
	 */
	public function get_pp_preview() {
		check_ajax_referer( 'podcast-player-admin-options-ajax-nonce', 'security' );
		Utility_Fn::require_capabilities( 'manage_options', 'pp_render_preview' );
		$shortcodegen = new ShortCodeGen();
		$args = isset( $_POST['data'] ) ? $shortcodegen->escape( wp_unslash( $_POST['data'] ) ) : false;
		if ( false === $args || ! is_array( $args ) ) {
			echo wp_json_encode( array(
				'error' => __( 'Invalid data provided', 'podcast-player' ),
			) );
			wp_die();
		}
		$content = $shortcodegen->render( $args, false );
		// Scripts data.
		// $cdata         = apply_filters( 'podcast_player_script_data', array() );
		// $ppjs_settings = apply_filters( 'podcast_player_mediaelement_settings', array() );
		echo wp_json_encode( array(
			'markup' => $content,
			// 'cdata'  => $cdata,
			// 'mejs'   => $ppjs_settings,
		) );
		wp_die();
	}

	/**
	 * Get podcast player form to generate the shortcode on the admin page.
	 *
	 * @since 7.9.0
	 */
	public function get_shortcode_form() {
		check_ajax_referer( 'podcast-player-admin-options-ajax-nonce', 'security' );
		Utility_Fn::require_capabilities( 'manage_options', 'pp_blank_shortcode_template' );
		$shortcodegen = new ShortCodeGen();
		$instance       = $shortcodegen->get_next_instance_id();
		ob_start();
		$shortcodegen->form( $instance );
		$form = ob_get_clean();
		echo wp_json_encode( array(
			'form'     => $form,
			'instance' => $instance,
		) );
		wp_die();
	}

	/**
	 * Get DPT form to generate the shortcode on the admin page.
	 *
	 * @since 2.6.0
	 */
	public function create_new_shortcode() {
		check_ajax_referer( 'podcast-player-admin-options-ajax-nonce', 'security' );
		Utility_Fn::require_capabilities( 'manage_options', 'pp_create_new_shortcode' );
		$shortcodegen = new ShortCodeGen();
		$args = isset( $_POST['data'] ) ? $shortcodegen->sanitize( wp_unslash( $_POST['data'] ) ) : false;
		$inst = isset( $_POST['instance'] ) ? absint(wp_unslash( $_POST['instance'] )) : false;
		if ( false === $args || false === $inst ) {
			echo wp_json_encode( array(
				'error'     => __( 'Shortcode data not provided correctly.', 'podcast-player' ),
			) );
			wp_die();
		}
		$shortcode_list = $shortcodegen->shortcode_settings;
		if ( isset( $shortcode_list[ $inst ] ) ) {
			$inst = $shortcodegen->get_next_instance_id();
		}
		$shortcode_list[ $inst ] = $args;
		$shortcodegen->shortcode_settings = $shortcode_list;
		$shortcodegen->save();
		echo wp_json_encode( array(
			'success'  => __( 'Shortcode created successfully.', 'podcast-player' ),
			'instance' => $inst,
		) );
		wp_die();
	}

	/**
	 * Get DPT form to generate the shortcode on the admin page.
	 *
	 * @since 7.9.0
	 */
	public function load_shortcode() {
		check_ajax_referer( 'podcast-player-admin-options-ajax-nonce', 'security' );
		Utility_Fn::require_capabilities( 'manage_options', 'pp_load_shortcode' );
		$instance = isset( $_POST['instance'] ) ? absint( wp_unslash( $_POST['instance'] ) ) : false;
		if ( false === $instance ) {
			echo wp_json_encode( array(
				'error' => __( 'Invalid data provided', 'podcast-player' ),
			) );
			wp_die();
		}
		$shortcodegen = new ShortCodeGen();
		$preview = $shortcodegen->render( $instance, false );
		ob_start();
		$shortcodegen->form( $instance );
		$form = ob_get_clean();
		// Scripts data.
		// $cdata         = apply_filters( 'podcast_player_script_data', array() );
		// $ppjs_settings = apply_filters( 'podcast_player_mediaelement_settings', array() );
		echo wp_json_encode( array(
			'form'     => $form,
			'preview'  => $preview,
			'instance' => $instance,
			// 'cdata'    => $cdata,
			// 'mejs'     => $ppjs_settings,
		) );
		wp_die();
	}

	/**
	 * Delete already generated DPT shortcode from the admin page.
	 *
	 * @since 2.6.0
	 */
	public function delete_shortcode() {
		check_ajax_referer( 'podcast-player-admin-options-ajax-nonce', 'security' );
		Utility_Fn::require_capabilities( 'manage_options', 'pp_delete_shortcode' );
		$instance = isset( $_POST['instance'] ) ? absint( wp_unslash( $_POST['instance'] ) ) : false;
		if ( false === $instance ) {
			echo wp_json_encode( array(
				'error' => __( 'Invalid data provided', 'podcast-player' ),
			) );
			wp_die();
		}
		$shortcodegen = new ShortCodeGen();
		$shortcode_list = $shortcodegen->shortcode_settings;
		if ( isset( $shortcode_list[ $instance ] ) ) {
			unset( $shortcode_list[ $instance ] );
			$shortcodegen->shortcode_settings = $shortcode_list;
			$shortcodegen->save();
		}
		echo wp_json_encode( array(
			'success' => true,
		) );
		wp_die();
	}

	/**
	 * Update already generated DPT shortcode from the admin page.
	 *
	 * @since 2.6.0
	 */
	public function update_shortcode() {
		check_ajax_referer( 'podcast-player-admin-options-ajax-nonce', 'security' );
		Utility_Fn::require_capabilities( 'manage_options', 'pp_update_shortcode' );
		$shortcodegen = new ShortCodeGen();
		$args = isset( $_POST['data'] ) ? $shortcodegen->sanitize( wp_unslash( $_POST['data'] ) ) : false;
		$inst = isset( $_POST['instance'] ) ? absint(wp_unslash( $_POST['instance'] )) : false;
		$shortcode_list = $shortcodegen->shortcode_settings;
		if ( false === $args || false === $inst || ! isset( $shortcode_list[ $inst ] ) ) {
			echo wp_json_encode( array(
				'error'     => __( 'Shortcode data not provided correctly.', 'podcast-player' ),
			) );
			wp_die();
		}
		$shortcode_list[ $inst ] = $args;
		$shortcodegen->shortcode_settings = $shortcode_list;
		$shortcodegen->save();
		echo wp_json_encode( array(
			'success' => __( 'Shortcode updated successfully.', 'podcast-player' ),
		) );
		wp_die();
	}
}
