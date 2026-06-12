<?php
/**
 * Load front-end resources.
 *
 * @link       https://www.vedathemes.com
 * @since      1.0.0
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/public
 */

namespace Podcast_Player\Frontend\Inc;

use Podcast_Player\Helper\Functions\Getters as Get_Fn;
use Podcast_Player\Frontend\Inc\Instance_Counter;
use Podcast_Player\Helper\Functions\Markup as Markup_Fn;
use Podcast_Player\Helper\Core\Singleton;
use Podcast_Player\Frontend\Inc\Icon_Loader;

/**
 * Load front-end resources.
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/public
 * @author     vedathemes <contact@vedathemes.com>
 */
class Loader extends Singleton {
	/**
	 * Enqueue mediaelement migrate error-fix script.
	 *
	 * Mediaelement migrate WP script forces to use 'mejs-' class prefix for all
	 * mediaelements. Podcast player only work with 'ppjs__' class prefix. Hence,
	 * fixing this issue.
	 *
	 * All javascripts should be loaded in footer, however many times plugins
	 * load mediaelement files in head, which can break podcast player. If
	 * mediaelement-migrate is enqueued in head, then mm-error-fix must also be
	 * loaded in head before mediaelement-migrate script.
	 *
	 * @since    1.0.0
	 */
	public function mm_error_fix() {

		// Return if mediaelement is not required.
		if ( ! $this->has_mediaelement() ) {
			return;
		}

		// Return if mediaelement is not enqueued.
		if ( ! wp_script_is( 'mediaelement-migrate' ) ) {
			return;
		}

		// Mediaelement is loaded in footer.
		if ( $this->is_mediaelement_in_footer() ) {
			return;
		}

		// Enqueue mediaelement error fix script.
		$this->enqueue_mm_error_fix( false );

		// Move mm error fix script to top of the queue.
		$this->move_mm_errorfix_to_top();
	}

	/**
	 * Check if mediaelement is loaded in footer.
	 *
	 * @since    1.0.0
	 */
	public function is_mediaelement_in_footer() {
		$scripts = wp_scripts();
		$queue   = $scripts->queue;
		$in_head = array();
		foreach ( $queue as $handle ) {
			if ( ! $scripts->get_data( $handle, 'group' ) ) {
				array_push( $in_head, $handle );
			}
		}
		$is_mm_in_head = in_array( 'mediaelement-migrate', $in_head, true );
		if ( ! $is_mm_in_head && ! empty( $in_head ) ) {
			$is_mm_in_head = $this->recurse_deps( $in_head, 'mediaelement-migrate' );
		}

		return ! $is_mm_in_head;
	}

	/**
	 * Recursively search the passed dependency tree for $handle.
	 *
	 * @since 4.0.0
	 *
	 * @param string[] $queue  An array of queued _WP_Dependency handles.
	 * @param string   $handle Name of the item. Should be unique.
	 * @return bool Whether the handle is found after recursively searching the dependency tree.
	 */
	public function recurse_deps( $queue, $handle ) {
		$scripts  = wp_scripts();
		$all_deps = array_fill_keys( $queue, true );
		$queues   = array();
		$done     = array();

		while ( $queue ) {
			foreach ( $queue as $queued ) {
				if ( ! isset( $done[ $queued ] ) && isset( $scripts->registered[ $queued ] ) ) {
					$deps = $scripts->registered[ $queued ]->deps;
					if ( $deps ) {
						$all_deps += array_fill_keys( $deps, true );
						array_push( $queues, $deps );
					}
					$done[ $queued ] = true;
				}
			}
			$queue = array_pop( $queues );
		}
		return isset( $all_deps[ $handle ] );
	}

	/**
	 * Enqueue mediaelement migrate error-fix script.
	 *
	 * @since    1.0.0
	 *
	 * @param bool $in_footer whether to load script in footer.
	 */
	public function enqueue_mm_error_fix( $in_footer ) {
		return;
	}

	/**
	 * Enqueue mediaelement migrate error-fix script.
	 *
	 * @since    1.0.0
	 */
	public function move_mm_errorfix_to_top() {
		$scripts = wp_scripts();
		$queue   = $scripts->queue;
		$key     = array_search( 'podcast-player-mmerrorfix', $queue, true );
		if ( false !== $key ) {
			unset( $queue[ $key ] );
			$scripts->queue = array_merge( array( 'podcast-player-mmerrorfix' ), $queue );
		}
	}

	/**
	 * Register podcast player front-end stylesheet.
	 *
	 * @since 8.0.0
	 */
	public function register_styles() {
		wp_register_style(
			'pppublic',
			PODCAST_PLAYER_URL . 'frontend/css/podcast-player-public.css',
			array(),
			PODCAST_PLAYER_VERSION,
			'all'
		);
		wp_style_add_data( 'pppublic', 'rtl', 'replace' );
	}

	/**
	 * Attempt to enqueue podcast player styles in head.
	 *
	 * @since 8.0.0
	 */
	public function maybe_enqueue_styles_early() {
		if ( ! $this->should_enqueue_styles_early() ) {
			return;
		}

		$this->enqueue_styles();
	}

	/**
	 * Attempt to enqueue podcast player scripts in head.
	 *
	 * @since 8.0.0
	 */
	public function maybe_enqueue_scripts_early() {
		if ( ! $this->should_enqueue_styles_early() ) {
			return;
		}

		$this->enqueue_public_script();
	}

	/**
	 * Check whether podcast player styles can be enqueued early.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	private function should_enqueue_styles_early() {

		// Never run this in admin pages.
		if ( is_admin() ) {
			return false;
		}

		// Respect existing customizer/Ajax conditions.
		if ( $this->has_podcast_player() ) {
			return true;
		}

		// Check known frontend render markers in singular content.
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof \WP_Post && $this->has_podcast_markup( $post->post_content ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if content includes known podcast player markers.
	 *
	 * @since 8.0.0
	 *
	 * @param string $content Post content.
	 * @return bool
	 */
	private function has_podcast_markup( $content ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return false;
		}

		// Podcast player shortcodes.
		if ( has_shortcode( $content, 'podcastplayer' ) || has_shortcode( $content, 'showpodcastplayer' ) ) {
			return true;
		}

		// Podcast player dynamic block.
		if ( function_exists( 'has_block' ) && has_block( 'podcast-player/podcast-player', $content ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Enqueue podcast player front-end styles and scripts in footer.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_resources() {

		// Return if podcast player is not available on the page.
		if ( ! $this->has_podcast_player() ) {
			return;
		}

		// Enqueue podcast player styles.
		$this->enqueue_styles();

		// Load mm error fix script if not already loaded.
		if ( ! wp_script_is( 'podcast-player-mmerrorfix' ) && $this->has_mediaelement() ) {
			$this->enqueue_mm_error_fix( true );
			$this->move_mm_errorfix_to_top();
		}

		// Enqueue podcast player scripts.
		$this->enqueue_scripts();
	}

	/**
	 * Enqueue front-end styles and scripts in elementor preview screen.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_elementor_resources() {

		// TODO: This is mostly the same function as the enqueue_resources() function. Refactor and unify.
		// Enqueue podcast player styles.
		$this->enqueue_styles();

		// Load mm error fix script if not already loaded.
		if ( ! wp_script_is( 'podcast-player-mmerrorfix' ) && $this->has_mediaelement() ) {
			$this->enqueue_mm_error_fix( true );
			$this->move_mm_errorfix_to_top();
		}

		// Enqueue podcast player scripts.
		$this->enqueue_scripts();
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		if ( ! wp_style_is( 'pppublic', 'registered' ) ) {
			$this->register_styles();
		}
		wp_enqueue_style( 'pppublic' );
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		// Scripts data.
		$cdata         = apply_filters( 'podcast_player_script_data', array() );
		$ppjs_settings = apply_filters( 'podcast_player_mediaelement_settings', array() );
		$has_hls       = $this->has_hls_source( $cdata );

		$this->enqueue_public_script( $has_hls );

		wp_localize_script( 'pppublic', 'podcastPlayerData', $cdata );
		wp_localize_script( 'pppublic', 'ppmejsSettings', $ppjs_settings );
	}

	/**
	 * Enqueue public facing javascripts.
	 *
	 * @since 8.0.0
	 */
	private function enqueue_public_script( $has_hls = false ) {

		$deps = array( 'jquery' );
		if ( $has_hls ) {
			$this->enqueue_hls_script();
			$deps[] = 'podcast-player-hls';
			$this->add_public_script_dependency( 'podcast-player-hls' );
		}

		/**
		 * Register public facing javascripts.
		 */
		wp_enqueue_script(
			'pppublic',
			PODCAST_PLAYER_URL . 'frontend/js/public.build.js',
			$deps,
			PODCAST_PLAYER_VERSION,
			true
		);
	}

	/**
	 * Enqueue HLS playback support script.
	 *
	 * @since 8.2.0
	 */
	private function enqueue_hls_script() {
		wp_enqueue_script(
			'podcast-player-hls',
			PODCAST_PLAYER_URL . 'frontend/js/hls.build.js',
			array(),
			PODCAST_PLAYER_VERSION,
			true
		);
	}

	/**
	 * Add a dependency to the public script if it was registered earlier.
	 *
	 * @since 8.2.0
	 *
	 * @param string $dependency Script dependency handle.
	 */
	private function add_public_script_dependency( $dependency ) {
		$scripts = wp_scripts();
		if ( empty( $scripts->registered['pppublic'] ) ) {
			return;
		}

		if ( ! in_array( $dependency, $scripts->registered['pppublic']->deps, true ) ) {
			$scripts->registered['pppublic']->deps[] = $dependency;
		}
	}

	/**
	 * Check whether current frontend data includes an active HLS source.
	 *
	 * @since 8.2.0
	 *
	 * @param array $data Podcast player script data.
	 * @return bool
	 */
	private function has_hls_source( $data ) {
		foreach ( $data as $instance ) {
			if ( ! is_array( $instance ) ) {
				continue;
			}

			foreach ( $instance as $key => $episode ) {
				if ( 0 !== strpos( (string) $key, 'ppe-' ) || ! is_array( $episode ) ) {
					continue;
				}

				if ( ! empty( $episode['is_hls'] ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Media Element player settings.
	 *
	 * @param array $settings Array of mejs settings.
	 * @since 1.0.0
	 */
	public function mejs_settings( $settings = array() ) {
		$markup = $this->get_js_templates();
		$mejs   = array(
			'pluginPath'  => includes_url( 'js/mediaelement/', 'relative' ),
			'classPrefix' => 'ppjs__',
			'stretching'  => 'responsive',
			'isPremium'   => false,
			'isSticky'    => false,
			'features'    => array( 'current', 'progress', 'duration', 'fullscreen' ),
			'isMeJs'      => $this->has_mediaelement(),
			'hlsSrc'      => esc_url( add_query_arg( 'ver', PODCAST_PLAYER_VERSION, PODCAST_PLAYER_URL . 'frontend/js/hls.build.js' ) ),
			'i18n'        => array(
				'message' => esc_html__( 'Your Message', 'podcast-player' ),
				'email'   => esc_html__( 'Your Email', 'podcast-player' ),
				'name'    => esc_html__( 'Your Name', 'podcast-player' ),
				'send'    => esc_html__( 'Send', 'podcast-player' ),
				'thanks'  => esc_html__( 'Thanks for your feedback', 'podcast-player' ),
			),
		);
		$settings = array_merge( $mejs, $markup, $settings );

		// The custom Podcast Player media layer now handles both audio and video.
		$settings['isMeJs'] = false;

		return $settings;
	}

	/**
	 * Media Element player modern icons.
	 *
	 * @since 1.0.0
	 */
	public function get_js_templates() {
		// TODO: Loading too many templates, can be optimized.
		$templates = array(
			'ppPauseBtn'    => 'pp-pause',
			'ppClose'       => 'pp-x',
			'ppMaxiScrnBtn' => 'pp-maximize',
			'ppMiniScrnBtn' => 'pp-minimize',
			'ppMinMax'      => 'pp-drop-down',
			'ppPlayCircle'  => 'pp-play',
			'ppVidLoading'  => 'pp-refresh',
			'ppArrowUp'     => 'pp-arrow-up',
			'ppThumbsUp'    => 'pp-thumbs-up',
			'ppThumbsDown'  => 'pp-thumbs-down',
		);

		// Create icons markup.
		$templates = array_map(
			function ( $icon ) {
				return Markup_Fn::get_icon( array( 'icon' => $icon ) );
			},
			$templates
		);

		// Add audio control button's markup.
		$controls = '';
		$template = Markup_Fn::locate_template( 'misc/js', 'controls' );
		if ( $template ) {
			ob_start();
			require $template;
			$controls = ob_get_clean();
		}
		$templates['ppAudioControlBtns'] = Markup_Fn::remove_breaks( $controls );

		// Add additional controls button's markup.
		$template = Markup_Fn::locate_template( 'misc/js', 'addcontrols' );
		if ( $template ) {
			ob_start();
			require $template;
			$controls = ob_get_clean();
		}
		$templates['ppAdditionalControls'] = Markup_Fn::remove_breaks( $controls );

		// Add Aux wrapper template.
		$ppaux    = '';
		$template = Markup_Fn::locate_template( 'misc/js', 'auxmodal' );
		if ( $template ) {
			ob_start();
			require $template;
			$ppaux = ob_get_clean();
		}
		$templates['ppAuxModal'] = Markup_Fn::remove_breaks( $ppaux );

		// Add play pause button for video.
		$ppbtn    = '';
		$template = Markup_Fn::locate_template( 'misc/buttons', 'playpause' );
		if ( $template ) {
			ob_start();
			require $template;
			$ppbtn = ob_get_clean();
		}
		$templates['ppPlayPauseBtn'] = Markup_Fn::remove_breaks( $ppbtn );

		// Add video share button.
		$ppvshare = '';
		$template = Markup_Fn::locate_template( 'misc/js', 'vshare' );
		if ( $template ) {
			ob_start();
			require $template;
			$ppvshare = ob_get_clean();
		}
		$templates['ppVideoShare'] = Markup_Fn::remove_breaks( $ppvshare );

		// Screen reader text to close/Minimize the player.
		$templates['ppCloseBtnText'] = sprintf(
			'<span class="ppjs__offscreen">%s</span>',
			esc_html__( 'Minimize or Close the player', 'podcast-player' )
		);

		return $templates;
	}

	/**
	 * Check if podcast player display class is instantiated.
	 *
	 * @since 1.0.0
	 */
	public function has_podcast_player() {
		// Always load scripts on customizer preview screen.
		if ( is_customize_preview() ) {
			return true; // TODO: Returned without applying below filter.
		}

		$pp         = Instance_Counter::get_instance();
		$has_player = $pp->has_podcast_player();

		return apply_filters( 'podcast_player_has_podcast', $has_player );
	}

	/**
	 * Check if mediaelement player is enabled or required.
	 *
	 * MediaElement is no longer used for Podcast Player audio or video playback.
	 *
	 * @since 6.7.0
	 */
	public function has_mediaelement() {
		return false;
	}
}
