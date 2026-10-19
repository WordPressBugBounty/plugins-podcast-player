<?php
/**
 * Native Elementor widget, using the WordPress widget's settings contract.
 *
 * @package Podcast_Player
 */
namespace Podcast_Player\Integration;

use Elementor\Controls_Manager as Controls;
use Podcast_Player\Backend\Inc\Widget;
use Podcast_Player\Frontend\Inc\Display;
use Podcast_Player\Helper\Functions\Getters;
use Podcast_Player\Helper\Functions\Validation;

class Elementor_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'podcast-player'; }
	public function get_title() { return __( 'Podcast Player', 'podcast-player' ); }
	public function get_icon() { return 'pp-elementor-icon'; }
	public function get_categories() { return array( 'general' ); }
	public function get_style_depends() { return array( 'pppublic' ); }
	public function get_keywords() { return array( 'podcast', 'audio', 'video', 'feed', 'player' ); }

	protected function register_controls() {
		$widget = new Widget();
		$defaults = $widget->get_defaults();
		$sections = $widget->get_widget_options( $defaults );
		$labels = array(
			'pp_taxonomy' => __( 'Taxonomy', 'podcast-player' ),
			'pp_terms' => __( 'Terms', 'podcast-player' ),
			'pp_elist' => __( 'Episodes', 'podcast-player' ),
			'pp_slist' => __( 'Seasons', 'podcast-player' ),
			'pp_catlist' => __( 'Categories', 'podcast-player' ),
		);
		foreach ( $sections as $section => $group ) {
			$this->start_controls_section( 'pp_section_' . $section, array(
				'label' => $group['title'],
				'tab' => 'style' === $section ? Controls::TAB_STYLE : Controls::TAB_CONTENT,
				'condition' => $this->section_condition( $section ),
			) );
			foreach ( $group['items'] as $key => $item ) {
				if ( isset( $item['display_callback'] ) && ! call_user_func( $item['display_callback'] ) ) {
					continue;
				}
				$type = $item['type'];
				$control = array(
					'label' => isset( $item['label'] ) ? $item['label'] : ( isset( $labels[ $key ] ) ? $labels[ $key ] : ( isset( $item['input_attrs']['placeholder'] ) ? $item['input_attrs']['placeholder'] : $key ) ),
					'description' => isset( $item['desc'] ) ? $item['desc'] : '',
					'default' => isset( $defaults[ $key ] ) ? $defaults[ $key ] : '',
					'condition' => $this->control_condition( $key ),
					'label_block' => 'checkbox' !== $type && 'color' !== $type,
				);
				$types = array( 'text' => Controls::TEXT, 'url' => Controls::TEXT, 'furl' => Controls::TEXT, 'number' => Controls::NUMBER, 'textarea' => Controls::TEXTAREA, 'color' => Controls::COLOR, 'checkbox' => Controls::SWITCHER, 'select' => Controls::SELECT, 'image_upload' => Controls::MEDIA, 'mmss' => Controls::TEXT );
				$control['type'] = isset( $types[ $type ] ) ? $types[ $type ] : 'pp-query';
				if ( isset( $item['choices'] ) ) { $control['options'] = $item['choices']; }
				if ( isset( $item['input_attrs'] ) ) {
					foreach ( array( 'min', 'max', 'step', 'placeholder' ) as $attribute ) {
						if ( isset( $item['input_attrs'][ $attribute ] ) ) { $control[ $attribute ] = $item['input_attrs'][ $attribute ]; }
					}
				}
				if ( 'checkbox' === $type ) { $control['return_value'] = 'yes'; }
				if ( 'url' === $type ) { $control['input_type'] = 'url'; }
				if ( 'image_upload' === $type ) { $control['default'] = array( 'id' => '', 'url' => '' ); }
				if ( 'image_upload' === $type ) { $control['description'] = __( 'Use this cover for the podcast and player artwork. Leave empty to use artwork from the source.', 'podcast-player' ); }
				if ( 'mmss' === $type ) {
					$control['default'] = '00:00:00';
					$control['placeholder'] = 'hh:mm:ss';
				}
				if ( 'pp-query' === $control['type'] ) {
					$control['pp_query_field'] = $key;
					$control['multiple'] = 'taxonomy' !== $type;
					$control['options'] = array();
					$control['default'] = $control['multiple'] ? array() : '';
					$control['label_block'] = true;
					$control['select2options'] = array(
						'placeholder' => $control['multiple'] ? __( 'Select one or more…', 'podcast-player' ) : __( 'Select a taxonomy…', 'podcast-player' ),
						'closeOnSelect' => ! $control['multiple'],
					);
					$placeholders = array(
						'pp_slist' => __( 'Select seasons (multiple)…', 'podcast-player' ),
						'pp_catlist' => __( 'Select categories (multiple)…', 'podcast-player' ),
						'pp_elist' => __( 'Select episodes (multiple)…', 'podcast-player' ),
					);
					if ( isset( $placeholders[ $key ] ) ) {
						$control['select2options']['placeholder'] = $placeholders[ $key ];
					}
					$control['description'] = $control['multiple']
						? __( 'Select one or more options. Leave empty to include all.', 'podcast-player' )
						: __( 'Choose a taxonomy to filter its terms.', 'podcast-player' );
				}
				if ( 'feed_url' === $key ) {
					$this->add_control( 'pp_saved_feed', array(
						'label' => __( 'Saved Podcast', 'podcast-player' ), 'type' => 'pp-query',
						'pp_query_field' => 'feed_url', 'options' => array(), 'default' => '',
						'label_block' => true,
						'select2options' => array( 'placeholder' => __( 'Select a podcast…', 'podcast-player' ) ),
						'description' => __( 'Choose a saved podcast, or enter a feed URL below.', 'podcast-player' ),
						'condition' => $this->control_condition( 'feed_url' ),
					) );
				}
				if ( preg_match( '/^pp_.+_sub$/', $key ) ) {
					unset( $control['condition'] );
					$control['conditions'] = array( 'relation' => 'or', 'terms' => array(
						array( 'name' => 'podcast_menu', 'operator' => '==', 'value' => '' ),
						array( 'name' => 'main_menu_items', 'operator' => '==', 'value' => 0 ),
					) );
				}
				$this->add_control( $key, $control );
			}
			$this->end_controls_section();
		}
	}

	private function section_condition( $section ) {
		if ( ! apply_filters( 'podcast_player_is_premium', false ) ) { return array(); }
		if ( in_array( $section, array( 'show', 'filter' ), true ) ) { return array( 'pp_fetch_method!' => 'link' ); }
		if ( in_array( $section, array( 'lshow', 'linfo' ), true ) ) { return array( 'pp_fetch_method' => 'link' ); }
		if ( 'feedback' === $section ) { return array( 'pp_fetch_method' => 'feed' ); }
		return array();
	}

	/** Native conditions mirror the existing widget's visibility rules. */
	private function control_condition( $key ) {
		$condition = array();
		$pro = apply_filters( 'podcast_player_is_premium', false );
		if ( $pro ) {
			if ( in_array( $key, array( 'feed_url', 'pp_slist', 'pp_catlist', 'pp_hide_content' ), true ) ) { $condition['pp_fetch_method'] = 'feed'; }
			if ( in_array( $key, array( 'pp_post_type', 'pp_taxonomy', 'pp_terms', 'pp_podtitle' ), true ) ) { $condition['pp_fetch_method'] = 'post'; }
			if ( in_array( $key, array( 'pp_audiosrc', 'pp_audiotitle', 'pp_audiolink', 'pp_ahide_download', 'pp_ahide_social' ), true ) ) { $condition['pp_fetch_method'] = 'link'; }
			if ( in_array( $key, array( 'number', 'offset', 'pp_teaser_text', 'pp_excerpt_length', 'pp_excerpt_unit', 'pp_txtcolor', 'pp_elist', 'pp_edisplay' ), true ) ) { $condition['pp_fetch_method!'] = 'link'; }
		}
		$features = array( 'pp_teaser_text' => 'excerpt', 'pp_excerpt_length' => 'excerpt', 'pp_excerpt_unit' => 'excerpt', 'pp_aspect_ratio' => 'thumbnail', 'pp_crop_method' => 'thumbnail', 'pp_grid_columns' => 'grid', 'pp_bgcolor' => 'bgcolor', 'pp_txtcolor' => 'txtcolor' );
		if ( isset( $features[ $key ] ) ) {
			$styles = array();
			foreach ( array_keys( Getters::get_styles() ) as $style ) {
				if ( Validation::is_style_support( $style, $features[ $key ] ) ) { $styles[] = $style; }
			}
			$condition['pp_display_style'] = $styles;
		}
		if ( in_array( $key, array( 'pp_excerpt_length', 'pp_excerpt_unit' ), true ) ) { $condition['pp_teaser_text'] = ''; }
		if ( 'pp_crop_method' === $key ) { $condition['pp_aspect_ratio!'] = ''; }
		if ( 'pp_terms' === $key ) { $condition['pp_taxonomy!'] = ''; }
		if ( 'pp_edisplay' === $key ) { $condition['pp_elist!'] = array(); }
		if ( 'pp_start_time' === $key ) { $condition['pp_start_when'] = 'custom'; }
		if ( in_array( $key, array( 'pp_header_default', 'pp_list_default' ), true ) ) { $condition['pp_display_style'] = array( '', 'legacy', 'modern' ); }
		if ( in_array( $key, array( 'pp_hide_cover', 'pp_hide_title', 'pp_hide_description', 'pp_hide_subscribe' ), true ) ) { $condition['pp_hide_header!'] = 'yes'; }
		if ( 'pp_no_scroll' === $key ) { $condition['pp_display_style'] = array( '', 'modern' ); }
		if ( in_array( $key, array( 'pp_show_form_time', 'pp_feedback_text', 'pp_positive_text', 'pp_positive_url', 'pp_negative_text', 'pp_negative_form' ), true ) ) { $condition['pp_collect_feedback'] = 'yes'; }
		if ( 'main_menu_items' === $key ) { $condition['podcast_menu!'] = ''; }
		return $condition;
	}

	protected function render() {
		$raw = $this->get_settings_for_display();
		$settings = Elementor::settings( $raw );
		if ( ! $settings['feed_url'] && 'feed' === $settings['pp_fetch_method'] ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div class="elementor-alert elementor-alert-info">' . esc_html__( 'Choose a podcast or enter its feed URL to display the player.', 'podcast-player' ) . '</div>';
			}
			return;
		}
		if ( $settings['title'] ) { echo '<h3 class="podcast-player-widget-title">' . esc_html( $settings['title'] ) . '</h3>'; }
		$args = ( new Widget() )->get_display_args( $settings );
		if ( ! empty( $raw['cover_image']['url'] ) && empty( $settings['cover_image'] ) ) { $args['img_url'] = esc_url_raw( $raw['cover_image']['url'] ); }
		$args['cover-override'] = ! empty( $settings['cover_image'] ) || ! empty( $args['img_url'] );
		echo Display::get_instance()->init( $args, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
