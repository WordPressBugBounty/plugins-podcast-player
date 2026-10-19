<?php
/** Native selector populated from the current player's source. @package Podcast_Player */
namespace Podcast_Player\Integration;

class Elementor_Query_Control extends \Elementor\Control_Select2 {
	public function get_type() {
		return 'pp-query';
	}
}
