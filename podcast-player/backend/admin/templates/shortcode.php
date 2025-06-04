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
        <h3><?php printf( 'Shortcode Generator', 'display-post-types' ); ?></h3>
        <div class="pp-shortcode-action">
            <button id="pp-shortcode-generator-btn" class="button button-primary">Create New Shortcode</button>
            <?php if ( ! empty( $shortcodegen->shortcode_settings ) ) : ?>
                <span class="pp-separator">or</span>
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
                        <span>Create a </span>
                        <span style="color: #333;">New Shortcode</span>
                        <span> or </span>
                        <span style="color: #333;">Edit an Existing</span>
                        <span> Shortcode using the menu above.</span>
                    </div>
                </div>
                <div class="pp-shortcode-preview-overlap"></div>
            </div>
        </div>
    </div>
</div>