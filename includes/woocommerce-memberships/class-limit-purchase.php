<?php
/**
 * Newspack Network limiter for WooCommerce Subscriptions.
 *
 * @package Newspack
 */

namespace Newspack_Network\Woocommerce_Memberships;

/**
 * Handles limiting WooCommerce Subscriptions purchases
 *
 * If the subscription is tied to a membership, we check whether the user already has an active subscription that gives access to the same membership in another site in the network.
 */
class Limit_Purchase {

	/**
	 * Initializer.
	 */
	public static function init() {
		add_filter( 'woocommerce_subscription_is_purchasable', [ __CLASS__, 'restrict_network_subscriptions' ], 10, 2 );
		add_filter( 'woocommerce_cart_product_cannot_be_purchased_message', [ __CLASS__, 'woocommerce_cart_product_cannot_be_purchased_message' ], 10, 2 );
	}

	/**
	 * Restricts subscription purchasing from a network-synchronized plan to one.
	 *
	 * @param bool                                                        $purchasable Whether the subscription product is purchasable.
	 * @param \WC_Product_Subscription|\WC_Product_Subscription_Variation $subscription_product The subscription product.
	 * @return bool
	 */
	public static function restrict_network_subscriptions( $purchasable, $subscription_product ) {
		return self::get_network_equivalent_subscription_for_current_user( $subscription_product ) ? false : $purchasable;
	}

	/**
	 * Given a product, check if the current user has an active subscription in another site that gives access to the same membership.
	 *
	 * @param \WC_Product $product Product data.
	 */
	private static function get_network_equivalent_subscription_for_current_user( \WC_Product $product ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		// Get the membership plan related to the subscription.
		$plans = self::get_plans_from_subscription_product( $product );
		if ( empty( $plans ) ) {
			return;
		}

		// Check if the user has an active subscription in another site that gives access to the same membership.
		foreach ( $plans as $plan ) {
			$user_subscription = Subscriptions_Integration::user_has_active_subscription_in_network( $user_id, $plan['network_id'] );
			if ( $user_subscription ) {
				return $user_subscription;
			}
		}
	}

	/**
	 * Get the plan related to the subscription product.
	 *
	 * @param \WC_Product $product Product data.
	 */
	public static function get_plans_from_subscription_product( \WC_Product $product ) {
		$membership_plans = [];
		if ( ! function_exists( 'wc_memberships_get_membership_plans' ) ) {
			return [];
		}
		$plans = array_filter(
			wc_memberships_get_membership_plans(),
			function( $plan ) use ( $product ) {
				return in_array( $product->get_id(), $plan->get_product_ids() );
			}
		);
		return array_map(
			function( $plan ) {
				return [
					'id'         => $plan->get_id(),
					'network_id' => get_post_meta( $plan->post->ID, Admin::NETWORK_ID_META_KEY, true ),
				];
			},
			$plans
		);
	}

	/**
	 * Filters the error message shown when a product can't be added to the cart.
	 *
	 * @param string      $message Message.
	 * @param \WC_Product $product_data Product data.
	 *
	 * @return string
	 */
	public static function woocommerce_cart_product_cannot_be_purchased_message( $message, \WC_Product $product_data ) {
		$network_subscription = self::get_network_equivalent_subscription_for_current_user( $product_data );
		if ( $network_subscription ) {
			$message = sprintf(
				/* translators: %s: Site URL */
				__( "You can't buy this subscription because you already have it active on %s", 'newspack-network' ),
				$network_subscription['site']
			);
		}
		return $message;
	}
}
