<?php
/**
 * Newspack Pull Endpoint.
 *
 * @package Newspack
 */

namespace Newspack_Network\Hub;

use Newspack_Network\Accepted_Actions;
use Newspack_Network\Debugger;
use Newspack_Network\Hub\Stores\Event_Log;
use WP_REST_Response;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Class to handle the Endpoint that Nodes will reach to pull new data from
 */
class Pull_Endpoint {

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
			'/pull',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'handle_pull' ],
					'permission_callback' => '__return_true',
				],
			]
		);
	}

	/**
	 * Get the number of events returned in each pull request.
	 *
	 * @return int
	 */
	public static function get_pull_limit() {
		return defined( 'NEWSPACK_NETWORK_EVENTS_PULL_LIMIT' ) && is_numeric( NEWSPACK_NETWORK_EVENTS_PULL_LIMIT ) ? NEWSPACK_NETWORK_EVENTS_PULL_LIMIT : 20;
	}

	/**
	 * Handle the pull
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response
	 */
	public static function handle_pull( $request ) {
		$request_error = \Newspack_Network\Utils\Requests::get_request_to_hub_errors( $request );
		if ( \is_wp_error( $request_error ) ) {
			return new WP_REST_Response( [ 'error' => $request_error->get_error_message() ], 403 );
		}

		$site              = $request['site'];
		$last_processed_id = $request['last_processed_id'];
		$actions           = $request['actions'];

		Debugger::log( sprintf( 'Pull request received from site %s, with last processed ID %d, for actions: %s.', $site, $last_processed_id, implode( ', ', $actions ) ) );

		if ( empty( $actions ) ) {
			return new WP_REST_Response( array( 'error' => 'Bad request.' ), 400 );
		}

		$node = Nodes::get_node_by_url( $site );
		$query_args = [
			'excluded_node_id' => $node->get_id(),
			'id_greater_than'  => $last_processed_id,
			'action_name_in'   => $actions,
		];

		$events = Event_Log::get(
			$query_args,
			self::get_pull_limit(),
			1,
			'ASC'
		);

		$total_events = Event_Log::get_total_items( $query_args );

		Debugger::log( count( $events ) . ' events found' );

		$events_formatted = array_map(
			function( $event ) {
				return [
					'id'        => $event->get_id(),
					'site'      => $event->get_node_url(),
					'action'    => $event->get_action_name(),
					'data'      => $event->get_data(),
					'timestamp' => $event->get_timestamp(),
				];
			},
			$events
		);
		$highest_returned_id = empty( $events_formatted ) ? 0 : max( array_column( $events_formatted, 'id' ) );
		$response_body = [
			'data'  => $events_formatted,
			'total' => $total_events,
		];
		return new WP_REST_Response( $response_body );
	}
}
