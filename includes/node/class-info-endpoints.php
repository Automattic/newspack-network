<?php
/**
 * Newspack Network Node site info handler.
 *
 * @package Newspack
 */

namespace Newspack_Network\Node;

use Newspack_Network\Accepted_Actions;
use Newspack_Network\Crypto;

/**
 * Class that register the webhook endpoint that will send events to the Hub
 */
class Info_Endpoints {
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
			'/info',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'handle_info_request' ],
					'permission_callback' => [ __CLASS__, 'permission_callback' ],
				],
			]
		);
		register_rest_route(
			'newspack-network/v1',
			'/subscriptions',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'handle_subscriptions_request' ],
					'permission_callback' => [ __CLASS__, 'permission_callback' ],
				],
			]
		);
	}

	/**
	 * The permission callback.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public static function permission_callback( $request ) {
		$route = $request->get_route();
		$request_slug = substr( $route, strrpos( $route, '/' ) + 1 );
		return \Newspack_Network\Rest_Authenticaton::verify_signature( $request, $request_slug, Settings::get_secret_key() );
	}

	/**
	 * Handles the info request.
	 */
	public static function handle_info_request() {
		return rest_ensure_response(
			[
				'sync_users_count'      => \Newspack_Network\Utils\Users::get_synchronized_users_count(),
				'sync_users_emails'     => \Newspack_Network\Utils\Users::get_synchronized_users_emails(),
				'not_sync_users_count'  => \Newspack_Network\Utils\Users::get_not_synchronized_users_count(),
				'not_sync_users_emails' => \Newspack_Network\Utils\Users::get_not_synchronized_users_emails(),
				'no_role_users_emails'  => \Newspack_Network\Utils\Users::get_no_role_users_emails(),
			]
		);
	}

	/**
	 * Handles the subscriptions request.
	 * Will return the active subscription IDs for the given email, when matching a membership by plan network ID.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public static function handle_subscriptions_request( $request ) {
		return rest_ensure_response(
			\Newspack_Network\Utils\Users::get_users_active_subscriptions_tied_to_network_ids( $request['email'], $request['plan_network_ids'] )
		);
	}
}
