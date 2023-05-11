<?php
/**
 * Newspack Hub Webhook.
 *
 * @package Newspack
 */

namespace Newspack_Hub;

/**
 * Class to handle the Webhook
 */
class Webhook {

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
			'newspack-hub/v1',
			'/webhook',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'handle_webhook' ],
					'permission_callback' => '__return_true',
				],
			] 
		);
	}

	/**
	 * Get the registered incoming_events
	 *
	 * @return array Array where the keys are the supported events and the values are the Incoming Events class names
	 */
	public static function get_registered_incoming_events() {
		return [
			'reader_registered' => 'Reader_Registered',
		];
	}

	/**
	 * Handle the webhook
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return void
	 * @TODO: return proper error codes on failure
	 */
	public static function handle_webhook( $request ) {
		$site            = $request['site'];
		$data            = $request['data'];
		$action          = $request['action'];
		$timestamp       = $request['timestamp'];
		$incoming_events = self::get_registered_incoming_events();
		
		Debugger::log( 'Webhook received' );
		Debugger::log( $site );
		Debugger::log( $data );
		Debugger::log( $action );
		Debugger::log( $timestamp );

		if ( empty( $site ) || 
			empty( $data ) || 
			empty( $timestamp ) ||
			empty( $action ) ||
			! array_key_exists( $action, $incoming_events )
		) {
			return;
		}

		$node = Nodes::get_node_by_url( $site );

		if ( ! $node ) {
			Debugger::log( 'Node not found.' );
			return;
		}

		$verified_data = $node->verify_signed_message( $data );
		if ( ! $verified_data ) {
			Debugger::log( 'Signature check failed' );
			return;
		}

		$verified_data = json_decode( $verified_data );

		if ( empty( $verified_data ) ) {
			Debugger::log( 'Invalid data' );
			return;
		}

		Debugger::log( 'Successfully verified data' );
		Debugger::log( $verified_data );

		$incoming_event_class = 'Newspack_Hub\\Incoming_Events\\' . $incoming_events[ $action ];

		$incoming_event = new $incoming_event_class( $node, $verified_data, $timestamp );

		$incoming_event->process();
		
	}
}
