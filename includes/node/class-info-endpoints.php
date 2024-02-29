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
					'permission_callback' => '__return_true',
				],
			]
		);
	}

	/**
	 * Handles the info request.
	 */
	public static function handle_info_request() {
		return rest_ensure_response(
			[
				'sync_users_count' => \Newspack_Network\Utils\Users::get_synchronized_users_count(),
			]
		);
	}
}