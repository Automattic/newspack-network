<?php
/**
 * Newspack Network WooCommerce Subscriptions integration for WooCommerce Memberships.
 *
 * @package Newspack
 */

namespace Newspack_Network\Woocommerce_Memberships;

/**
 * Handles tweaks for WooCommerce Memberships WooCommerce Subscriptions integration.
 */
class Subscriptions_Integration {
	/**
	 * Runs the initialization.
	 *
	 * @return void
	 */
	public static function init() {
		// You'd think this first filter is enough, but it's not. Even if the membership cancellation via linked subscription
		// is prevented, the expiry code is also executed.
		add_filter( 'wc_memberships_cancel_subscription_linked_membership', [ __CLASS__, 'prevent_membership_expiration' ], 10, 2 );
		add_filter( 'wc_memberships_expire_user_membership', [ __CLASS__, 'prevent_membership_expiration' ], 10, 2 );
	}

	/**
	 * Prevent membership expiration if another network site has a synced membership active.
	 *
	 * @param bool                                                      $cancel_or_expire whether to cancel/expire the membership when the subscription is cancelled (default true).
	 * @param \WC_Memberships_Integration_Subscriptions_User_Membership $user_membership the subscription-tied membership.
	 */
	public static function prevent_membership_expiration( $cancel_or_expire, $user_membership ) {
		$user_email = get_userdata( $user_membership->user_id )->user_email;
		$membership_plan_id = get_post_meta( $user_membership->get_plan()->get_id(), Admin::NETWORK_ID_META_KEY, true );

		if ( \Newspack_Network\Site_Role::is_hub() ) {
			$active_subscriptions_ids = \Newspack_Network\Hub\Network_Data_Endpoint::get_active_subscription_ids_from_network(
				$user_email,
				[ $membership_plan_id ]
			);
		} else {
			$params = [
				'site'             => get_bloginfo( 'url' ),
				'plan_network_ids' => [ $membership_plan_id ],
				'email'            => $user_email,
			];
			$response = \Newspack_Network\Utils\Requests::request_to_hub( 'wp-json/newspack-network/v1/network-subscriptions', $params, 'GET' );
			if ( is_wp_error( $response ) ) {
				return $cancel_or_expire;
			}
			$active_subscriptions_ids = json_decode( wp_remote_retrieve_body( $response ) )->active_subscriptions_ids ?? [];
		}
		$can_cancel = empty( $active_subscriptions_ids );
		if ( ! $can_cancel ) {
			$user_membership->add_note(
				__( 'Membership is not cancelled, because there is at least one active subscription linked to the membership plan on the network.', 'newspack-plugin' )
			);
		}
		return $can_cancel ? $cancel_or_expire : false;
	}
}
