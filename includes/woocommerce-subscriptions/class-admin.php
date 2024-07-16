<?php
/**
 * Newspack Network Admin customizations for WooCommerce Subscriptions.
 *
 * @package Newspack
 */

namespace Newspack_Network\Woocommerce_Subscriptions;

/**
 * Handles admin tweaks for WooCommerce Subscriptions.
 *
 * Adds a metabox to the membership plan edit screen to allow the user to add a network id metadata to the plans
 */
class Admin {
	/**
	 * Initializer.
	 */
	public static function init() {
		add_filter( 'woocommerce_rest_prepare_shop_subscription_object', [ __CLASS__, 'adjust_wc_subscription_rest_response' ], 2, 3 );
	}

	/**
	 * The subscription keys that are used in the response.
	 *
	 * @var array
	 */
	const USED_SUBSCRIPTION_KEYS = [
		'id'                    => true,
		'customer_id'           => true,
		'status'                => true,
		'billing'               => [
			'email'      => true,
			'first_name' => true,
			'last_name'  => true,
		],
		'total'                 => true,
		'currency'              => true,
		'billing_interval'      => true,
		'billing_period'        => true,
		'start_date_gmt'        => true,
		'end_date_gmt'          => true,
		'last_payment_date_gmt' => true,
	];

	/**
	 * Filter subscription data from REST API.
	 *
	 * @param \WP_REST_Response $response the response object.
	 * @param WC_Data           $subscription   Object data.
	 * @param \WP_REST_Request  $request the request object.
	 */
	public static function adjust_wc_subscription_rest_response( $response, $subscription, $request ) {
		if ( $request && isset( $request->get_headers()['x_np_network_signature'] ) ) {
			// Filter the response data to only include the keys we want. Some more will be added in
			// WC_REST_Subscriptions_Controller::prepare_object_for_response, but there are no filters there.
			$adjusted_data = [];
			foreach ( array_keys( $response->data ) as $key ) {
				if ( isset( self::USED_SUBSCRIPTION_KEYS[ $key ] ) ) {
					if ( is_array( self::USED_SUBSCRIPTION_KEYS[ $key ] ) ) {
						$adjusted_data[ $key ] = array_filter(
							$response->data[ $key ],
							function( $sub_key ) use ( $key ) {
								return isset( self::USED_SUBSCRIPTION_KEYS[ $key ][ $sub_key ] );
							},
							ARRAY_FILTER_USE_KEY
						);
					} else {
						$adjusted_data[ $key ] = $response->data[ $key ];
					}
				}
			}

			$response->data = $adjusted_data;
		}
		return $response;
	}
}
