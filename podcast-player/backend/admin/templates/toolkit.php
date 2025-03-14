<?php
/**
 * Podcast player toolkit page
 *
 * @package Podcast Player
 * @since 3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Podcast_Player\Helper\Functions\Getters as Get_Fn;

$feed_index = Get_Fn::get_feed_index();
?>

<div class="pp-toolkit-page">
	<?php require PODCAST_PLAYER_DIR . '/backend/admin/templates/toolkit/feed-update.php'; ?>
	<?php // require PODCAST_PLAYER_DIR . '/backend/admin/templates/toolkit/feed-review.php'; ?>
</div>
