<?php
/**
 * Newspack Network limiter for WooCommerce Subscriptions.
 *
 * @package Newspack
 */

namespace Newspack_Network\Woocommerce_Subscriptions;

/**
 * Handles limiting for WooCommerce Subscriptions - only one
 */
class Limiter {
	/**
	 * Cache for restriction checking results.
	 *
	 * @var array
	 */
	private static $cache = [];

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
		return self::can_buy_subscription( $subscription_product ) ? $purchasable : false;
	}

	/**
	 * Verify if this subscription can be bought.
	 *
	 * @param \WC_Product_Subscription|\WC_Product_Subscription_Variation $subscription_product The subscription product.
	 */
	public static function can_buy_subscription( $subscription_product ) {
		$cache_key = $subscription_product->get_id();
		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			self::$cache[ $cache_key ] = true;
			return self::$cache[ $cache_key ];
		}

		// Get the membership plan related to the subscription.
		$plans = self::get_plans_from_subscription_product( $subscription_product );
		if ( empty( $plans ) ) {
			self::$cache[ $cache_key ] = true;
			return self::$cache[ $cache_key ];
		}
		$user_email = get_userdata( $user_id )->user_email;
		$params = [
			'email'            => $user_email,
			'plan_network_ids' => array_column( $plans, 'network_pass_id' ),
			'site'             => get_bloginfo( 'url' ),
		];
		$response = \Newspack_Network\Utils\Requests::request_to_hub( 'wp-json/newspack-network/v1/network-subscriptions', $params, 'GET' );
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			self::$cache[ $cache_key ] = true;
			return self::$cache[ $cache_key ];
		}
		$response_data = json_decode( wp_remote_retrieve_body( $response ) );
		if ( isset( $response_data->active_subscriptions_ids ) && count( $response_data->active_subscriptions_ids ) > 0 ) {
			self::$cache[ $cache_key ] = false;
			return self::$cache[ $cache_key ];
		}
		self::$cache[ $cache_key ] = true;
		return self::$cache[ $cache_key ];
	}

	/**
	 * Get the plan related to the subscription product.
	 *
	 * @param WC_Product $product Product data.
	 */
	public static function get_plans_from_subscription_product( $product ) {
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
					'id'              => $plan->get_id(),
					'network_pass_id' => get_post_meta( $plan->post->ID, \Newspack_Network\Woocommerce_Memberships\Admin::NETWORK_ID_META_KEY, true ),
				];
			},
			$plans
		);
	}

	/**
	 * Filters the error message shown when a product can't be added to the cart.
	 *
	 * @param string     $message Message.
	 * @param WC_Product $product_data Product data.
	 *
	 * @return string
	 */
	public static function woocommerce_cart_product_cannot_be_purchased_message( $message, $product_data ) {
		if ( ! self::can_buy_subscription( $product_data ) ) {
			return __( 'You can only purchase one subscription in this network at a time.', 'newspack-network' );
		}
		return $message;
	}
}
