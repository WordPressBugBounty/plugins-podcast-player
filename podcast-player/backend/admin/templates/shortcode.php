<?php
/**
 * Podcast player options Shortcode page
 *
 * @package Podcast Player
 * @since 7.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Podcast_Player\Backend\Admin\ShortCodeGen;

$shortcodegen = new ShortCodeGen();
?>

<div class="pp-shortcode-wrapper">
    <?php if ( defined( 'PP_PRO_VERSION' ) && version_compare( PP_PRO_VERSION, '5.8.0', '<' ) ) : ?>
    <div class="pp-older-pro-notice">
        <?php esc_html_e( 'You\'re using an older version of Podcast Player Pro. Please update to fully use Shortcode Generator.', 'podcast-player' ); ?>
    </div>
    <?php endif; ?>
    <div class="pp-shortcode-header">
        <div>
            <h3><?php esc_html_e( 'Shortcode Generator', 'podcast-player' ); ?></h3>
            <p class="pp-notes"><?php esc_html_e( 'Create reusable podcast player shortcodes once, then place them on any page, post, widget, or builder area without rebuilding the same setup.', 'podcast-player' ); ?></p>
        </div>
        <div class="pp-shortcode-action">
            <button id="pp-shortcode-generator-btn" class="button button-primary"><?php esc_html_e( 'Create New Shortcode', 'podcast-player' ); ?></button>
            <?php if ( ! empty( $shortcodegen->shortcode_settings ) ) : ?>
                <span class="pp-separator"><?php esc_html_e( 'or', 'podcast-player' ); ?></span>
                <?php echo $shortcodegen->dropdown(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="pp-shortcode-generator">
        <div class="pp-shortcode-result"></div>
        <div class="pp-shortcode-workspace">
            <div id="pp-shortcode-form" class="pp-shortcode-form"></div>
            <div class="pp-shortcode-preview-wrapper">
                <div id="pp-shortcode-preview" class="pp-shortcode-preview">
                    <div style="padding: 20px; font-size: 20px; color: #aaa;">
                        <span><?php esc_html_e( 'Create a new shortcode or choose an existing one to edit. Your player preview will appear here.', 'podcast-player' ); ?></span>
                    </div>
                </div>
                <div class="pp-shortcode-preview-overlap"></div>
            </div>
        </div>
    </div>
</div>
<div id="pp-shortcode-action-modal" class="pp-shortcode-action-modal podcast-player-hidden">
    <div class="pp-shortcode-action-wrapper">
        <h3><?php esc_html_e( 'Confirm Deletion', 'podcast-player' ); ?></h3>
        <p><?php esc_html_e( 'Are you sure you want to delete this shortcode?', 'podcast-player' ); ?></p>
        <button id="pp-shortcode-deletion-btn" class="button button-primary"><?php esc_html_e( 'Delete Shortcode', 'podcast-player' ); ?></button>
        <button id="pp-shortcode-deletion-cancel" class="button button-secondary"><?php esc_html_e( 'Cancel', 'podcast-player' ); ?></button>
    </div>
</div>
