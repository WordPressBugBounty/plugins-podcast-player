<?php
/**
 * New-purchase promotion for administrators without Podcast Player Pro.
 *
 * @package Podcast_Player
 */

namespace Podcast_Player\Backend\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pro_Offer {

	const CAMPAIGN_ID  = 'offer20-new-purchases-v2';
	const COUPON_CODE  = 'offer20';
	const PRODUCT_ID   = '19325';
	const DISMISS_KEY  = 'podcast_player_dismissed_pro_offer';
	const END_DATE     = '2026-10-31';

	/**
	 * Detect active Pro functionality in either edition.
	 *
	 * @return bool
	 */
	public static function is_pro_active() {
		return defined( 'PP_PRO_VERSION' ) || function_exists( 'pp_fs' ) || apply_filters( 'podcast_player_is_premium', false );
	}

	/**
	 * Whether the current administrator should see this campaign.
	 *
	 * @return bool
	 */
	public static function should_show() {
		return current_user_can( 'manage_options' )
			// Include the entire last day, using the WordPress site's timezone.
			&& current_time( 'Y-m-d' ) <= self::END_DATE
			&& ! self::is_pro_active()
			&& ! self::is_closed_for_version();
	}

	/**
	 * Dismissals apply only to this campaign and this installed plugin version.
	 *
	 * @return bool
	 */
	private static function is_closed_for_version() {
		$closed = get_user_meta( get_current_user_id(), self::DISMISS_KEY, true );
		return is_array( $closed )
			&& isset( $closed['campaign'], $closed['version'] )
			&& self::CAMPAIGN_ID === $closed['campaign']
			&& PODCAST_PLAYER_VERSION === $closed['version'];
	}

	/**
	 * Hosted checkout with the discount prefilled.
	 *
	 * @return string
	 */
	public static function checkout_url() {
		return add_query_arg( 'coupon', self::COUPON_CODE, 'https://checkout.freemius.com/product/' . self::PRODUCT_ID . '/' );
	}

	/**
	 * Remember this offer's dismissal until the installed plugin version changes.
	 */
	public static function dismiss() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to dismiss this offer.', 'podcast-player' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'pp_dismiss_pro_offer' );
		update_user_meta(
			get_current_user_id(),
			self::DISMISS_KEY,
			array(
				'campaign' => self::CAMPAIGN_ID,
				'version'  => PODCAST_PLAYER_VERSION,
			)
		);
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=pp-options' ) );
		exit;
	}
}
