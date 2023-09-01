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
	 * Handle the pull
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response
	 */
	public static function handle_pull( $request ) {
		$site              = $request['site'];
		$last_processed_id = $request['last_processed_id'];
		$actions           = $request['actions'];
		$signature         = $request['signature'];
		$nonce             = $request['nonce'];
		
		Debugger::log( 'Pull request received' );
		Debugger::log( $site );
		Debugger::log( $last_processed_id );
		Debugger::log( $actions );

		if ( empty( $site ) || 
			empty( $actions ) ||
			empty( $nonce ) ||
			empty( $signature )
		) {
			return new WP_REST_Response( array( 'error' => 'Bad request.' ), 400 );
		}

		$node = Nodes::get_node_by_url( $site );

		if ( ! $node ) {
			Debugger::log( 'Node not found.' );
			return new WP_REST_Response( array( 'error' => 'Bad request. Site not registered in this Hub.' ), 403 );
		}

		$verified         = $node->decrypt_message( $signature, $nonce );
		$verified_message = json_decode( $verified );

		if ( ! $verified || ! is_object( $verified_message ) || (int) $last_processed_id !== (int) $verified_message->last_processed_id ) {
			Debugger::log( 'Signature check failed' );
			return new WP_REST_Response( array( 'error' => 'Invalid Signature.' ), 403 );
		}

		Debugger::log( 'Successfully verified request' );

		$events = Event_Log::get(
			[
				'excluded_node_id' => $node->get_id(),
				'id_greater_than'  => $last_processed_id,
				'action_name_in'   => $actions,
			],
			20
		);

		Debugger::log( count( $events ) . ' events found' );
		
		$response_body = array_map(
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
		
		return new WP_REST_Response( $response_body );
		
	}

}
