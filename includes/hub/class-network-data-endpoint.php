<?php
/**
 * Newspack Network_Data Endpoint.
 *
 * @package Newspack
 */

namespace Newspack_Network\Hub;

use Newspack_Network\Debugger;
use WP_REST_Response;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Class to handle the Endpoint that Nodes will reach to pull new data from
 */
class Network_Data_Endpoint {
	/**
	 * Runs the initialization.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public static function register_routes() {
		register_rest_route(
			'newspack-network/v1',
			'/network-subscriptions',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'api_get_network_subscriptions' ],
					'permission_callback' => '__return_true', // Auth will be handled by \Newspack_Network\Utils\Requests::get_request_to_hub_errors.
				],
			]
		);
	}

	/**
	 * Get active subscription IDs from the network.
	 *
	 * @param string $email Email of the user.
	 * @param string $plan_network_ids Network ID of the plan.
	 * @param string $site Site URL.
	 */
	public static function get_active_subscription_ids_from_network( $email, $plan_network_ids, $site = false ) {
		$active_subscriptions_ids = [];
		foreach ( Nodes::get_all_nodes() as $node ) {
			if ( $site && $site === $node->get_url() ) {
				// Skip the node which is making the request.
				continue;
			}
			$node_subscription_ids = $node->get_subscriptions_with_network_plans( $email, $plan_network_ids );
			if ( is_array( $node_subscription_ids ) ) {
				$active_subscriptions_ids = array_merge( $active_subscriptions_ids, $node_subscription_ids );
			}
		}
		// Also look on the Hub itself, unless the $site was provided.
		if ( $site !== false ) {
			$active_subscriptions_ids = array_merge(
				$active_subscriptions_ids,
				\Newspack_Network\Utils\Users::get_users_active_subscriptions_tied_to_network_ids( $email, $plan_network_ids )
			);
		}
		return $active_subscriptions_ids;
	}

	/**
	 * Handle the request for active subscriptions tied to a network plan.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response
	 */
	public static function api_get_network_subscriptions( $request ) {
		$request_error = \Newspack_Network\Utils\Requests::get_request_to_hub_errors( $request );
		if ( \is_wp_error( $request_error ) ) {
			return new WP_REST_Response( [ 'error' => $request_error->get_error_message() ], 403 );
		}
		if ( ! isset( $request['plan_network_ids'] ) || empty( $request['plan_network_ids'] ) ) {
			return new WP_REST_Response( [ 'error' => __( 'Missing plan_network_ids', 'newspack-network' ) ], 400 );
		}
		return new WP_REST_Response(
			[
				'active_subscriptions_ids' => self::get_active_subscription_ids_from_network( $request['email'], $request['plan_network_ids'], $request['site'] ),
			]
		);
	}
}
