<?php
/**
 * Podcast player premium.
 *
 * @link       https://www.vedathemes.com
 * @since      4.5.0
 *
 * @package    Podcast_Player
 */

namespace Podcast_Player\Frontend\Inc;

use Podcast_Player\Helper\Functions\Getters as Get_Fn;
use Podcast_Player\Helper\Functions\Markup as Markup_Fn;
use Podcast_Player\Helper\Core\Singleton;

/**
 * Podcast player premium.
 *
 * @package    Podcast_Player
 * @author     vedathemes <contact@vedathemes.com>
 */
class General extends Singleton {
	/**
	 * Resolve opt-in dynamic tokens in display arguments.
	 *
	 * @since 8.1.1
	 *
	 * @param array $args Podcast display args.
	 * @return array
	 */
	public function resolve_dynamic_display_args( $args ) {
		if ( ! is_array( $args ) || empty( $args ) ) {
			return $args;
		}

		foreach ( $args as $key => $value ) {
			$args[ $key ] = $this->resolve_dynamic_display_arg_value( $value );
		}

		return $args;
	}

	/**
	 * Resolve a dynamic token in a display arg value.
	 *
	 * @since 8.1.1
	 *
	 * @param mixed $value Display arg value.
	 * @return mixed
	 */
	private function resolve_dynamic_display_arg_value( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $this->resolve_dynamic_display_arg_value( $item );
			}
			return $value;
		}

		if ( ! is_string( $value ) ) {
			return $value;
		}

		switch ( trim( $value ) ) {
			case 'current_post_id':
			case '{current_post_id}':
				return $this->get_current_post_id();

			case 'current_post_audiosrc':
			case '{current_post_audiosrc}':
				return $this->get_current_post_audiosrc();
		}

		return $value;
	}

	/**
	 * Get the current post ID.
	 *
	 * @since 8.1.1
	 *
	 * @return int|false
	 */
	private function get_current_post_id() {
		$post_id = get_the_ID();

		if ( ! $post_id ) {
			$post_id = get_queried_object_id();
		}

		return $post_id ? absint( $post_id ) : false;
	}

	/**
	 * Get the current imported episode audio URL.
	 *
	 * @since 8.1.1
	 *
	 * @return string|false
	 */
	private function get_current_post_audiosrc() {
		$post_id = $this->get_current_post_id();
		if ( ! $post_id ) {
			return false;
		}

		$import = get_post_meta( $post_id, 'pp_import_data', true );
		if ( ! is_array( $import ) || empty( $import['src'] ) ) {
			return false;
		}

		return esc_url_raw( $import['src'] );
	}

	/**
	 * Apply podcast-specific player defaults.
	 *
	 * External integrations can provide lower-priority defaults. Saved Podcast
	 * Player defaults are merged above them so site-owner choices always win.
	 *
	 * @since 8.1.1
	 *
	 * @param array $args          Podcast display args.
	 * @param array $base_defaults Plugin display defaults.
	 * @return array
	 */
	public function podcast_defaults( $args, $base_defaults ) {
		if ( empty( $args['url'] ) || ( isset( $args['fetch-method'] ) && 'feed' !== $args['fetch-method'] ) ) {
			return $args;
		}

		$external_defaults = apply_filters( 'podcast_player_external_defaults', array(), $args['url'], $args );
		$external_defaults = is_array( $external_defaults ) ? $external_defaults : array();
		$defaults          = array_merge( $external_defaults, Get_Fn::get_podcast_defaults( $args['url'] ) );
		if ( empty( $defaults ) ) {
			return $args;
		}

		foreach ( $defaults as $key => $value ) {
			if ( $this->should_apply_podcast_default( $key, $value, $args, $base_defaults ) ) {
				$args[ $key ] = $value;
			}
		}

		return $args;
	}

	/**
	 * Check if a podcast-specific default should fill the current value.
	 *
	 * @since 8.1.1
	 *
	 * @param string $key           Display arg key.
	 * @param mixed  $default_value Saved podcast default value.
	 * @param array  $args          Current display args.
	 * @param array  $base_defaults Plugin display defaults.
	 */
	private function should_apply_podcast_default( $key, $default_value, $args, $base_defaults ) {
		if ( '' === $default_value || null === $default_value || false === $default_value ) {
			return false;
		}

		if ( ! empty( $args['_provided'] ) && is_array( $args['_provided'] ) && in_array( $key, $args['_provided'], true ) ) {
			return false;
		}

		if ( ! array_key_exists( $key, $args ) ) {
			return true;
		}

		$current = $args[ $key ];
		if ( $this->is_empty_default_value( $current ) ) {
			return true;
		}

		if ( array_key_exists( $key, $base_defaults ) && $this->default_values_match( $current, $base_defaults[ $key ] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if a value is effectively empty for default inheritance.
	 *
	 * @since 8.1.1
	 *
	 * @param mixed $value Value to inspect.
	 * @return bool
	 */
	private function is_empty_default_value( $value ) {
		if ( '' === $value || null === $value || false === $value ) {
			return true;
		}

		if ( is_array( $value ) ) {
			$filtered = array_filter(
				$value,
				function ( $item ) {
					return '' !== $item && null !== $item && false !== $item;
				}
			);
			return empty( $filtered );
		}

		return false;
	}

	/**
	 * Compare a current value against the plugin base default.
	 *
	 * @since 8.1.1
	 *
	 * @param mixed $current Current value.
	 * @param mixed $default Base default.
	 * @return bool
	 */
	private function default_values_match( $current, $default ) {
		if ( is_bool( $current ) || is_bool( $default ) ) {
			return (bool) $current === (bool) $default;
		}

		if ( is_numeric( $current ) && is_numeric( $default ) ) {
			return (string) $current === (string) $default;
		}

		return $current === $default;
	}

	/**
	 * Prevent exposing podcast feed data.
	 *
	 * @param array $data Podcast episodes data.
	 *
	 * @since 4.5.0
	 */
	public function data_protect( $data ) {
		if ( 'yes' !== Get_Fn::get_plugin_option( 'hide_data' ) ) {
			return $data;
		}
		return array( array(), 0 );
	}

	/**
	 * Prevent exposing episode audio URL.
	 *
	 * @param string $url Podcast episode audio URL.
	 *
	 * @since 4.5.0
	 */
	public function mask_audio_url( $url ) {
		if ( 'yes' !== Get_Fn::get_plugin_option( 'hide_data' ) ) {
			return $url;
		}
		return md5( esc_url( $url ) );
	}

	/**
	 * Create properly formatted subscribe menu.
	 *
	 * @param  string  $item_output The menu item output.
	 * @param  WP_Post $item        Menu item object.
	 * @param  int     $depth       Depth of the menu.
	 * @param  array   $args        wp_nav_menu() arguments.
	 * @return string  $item_output The menu item output with social icon.
	 *
	 * @since 4.5.0
	 */
	public function subscribe_menu( $item_output, $item, $depth, $args ) {

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

		// Change SVG icon inside social links menu if there is supported URL.
		if ( 'pod-menu' === $args->menu_class ) {
			$has_sub = false;
			foreach ( $sub_icons as $attr => $value ) {
				if ( false !== strpos( $item_output, $attr ) ) {
					$has_sub     = true;
					$item_output = str_replace( $args->link_before, '<span class="ppjs__offscreen">', $item_output );
					$item_output = str_replace( $args->link_after, '</span>' . $this->get_podcast_template( 'subscribe', $value ), $item_output );
					break;
				}
			}
		}
		return $item_output;
	}

	/**
	 * Get podcast player template parts.
	 *
	 * @since  5.4.0
	 *
	 * @param string $path Template relative path.
	 * @param string $name Template file name without .php suffix.
	 */
	public function get_podcast_template( $path, $name ) {
		$markup   = '';
		$template = Markup_Fn::locate_template( $path, $name );
		if ( $template ) {
			ob_start();
			require $template;
			$markup .= ob_get_clean();
		}

		$markup = Markup_Fn::remove_breaks( $markup );
		// TODO: Can the above code be generalized and moved to markup or utility?

		if ( $markup ) {
			$markup = sprintf( '<span class="subscribe-item %1$s-sub">%2$s</span>', $name, $markup );
		}

		return $markup;
	}
}
