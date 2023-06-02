<?php
/**
 * Newspack Node Pulling mechanism.
 *
 * @package Newspack
 */

namespace Newspack_Network\Node;

use Newspack_Network\Accepted_Actions;
use Newspack_Network\Crypto;
use Newspack_Network\Debugger;

/**
 * Class to pull data from the Hub and process it
 */
class Pulling {

	/**
	 * The interval in seconds between pulls
	 *
	 * @var int
	 */
	const PULL_INTERVAL = 60 * 5; // 5 minutes

	/**
	 * The option name that stores the ID of the last processed event
	 *
	 * @var string
	 */
	const LAST_PROCESSED_EVENT_OPTION_NAME = 'newspack_node_last_processed_action';

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_cron_events' ] );
		add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_schedule' ] ); // phpcs:ignore
	}

	/**
	 * Adds a custom cron schedule
	 *
	 * @param array $schedules The Cron schedules.
	 * @return array
	 */
	public static function add_cron_schedule( $schedules ) { 
		// translators: %d is the number of seconds.
		$display                                     = sprintf( __( 'Newspack Network Pull Interval: %d seconds', 'newspack-network' ), self::PULL_INTERVAL );
		$schedules['newspack_network_pull_interval'] = array(
			'interval' => self::PULL_INTERVAL,
			'display'  => esc_html( $display ),
		);
		return $schedules;
	}

	/**
	 * Register webhook cron events.
	 */
	public static function register_cron_events() {
		$hook = 'newspack_network_pull_from_hub';
		add_action( $hook, [ __CLASS__, 'pull' ] );
		if ( ! wp_next_scheduled( $hook ) ) {
			wp_schedule_event( time(), 'newspack_network_pull_interval', $hook );
		}
	}

	/**
	 * Gets the ID of the last processed event
	 *
	 * @return int
	 */
	public static function get_last_processed_id() {
		return get_option( self::LAST_PROCESSED_EVENT_OPTION_NAME, 0 );
	}

	/**
	 * Sets the ID of the last processed event
	 *
	 * @param int $id The event ID.
	 * @return void
	 */
	public static function set_last_processed_id( $id ) {
		update_option( self::LAST_PROCESSED_EVENT_OPTION_NAME, $id );
	}

	/**
	 * Gets the request parameters for the pull request
	 *
	 * @return array
	 */
	public static function get_request_params() {
		$params = [
			'last_processed_id' => self::get_last_processed_id(),
			'actions'           => Accepted_Actions::ACTIONS_THAT_NODES_PULL,
			'site'              => get_bloginfo( 'url' ),
		];
		return self::sign_params( $params );
	}

	/**
	 * Signs the request parameters with the Node's private key
	 *
	 * @param array $params The request parameters.
	 * @return array The params array with an additional signature key.
	 */
	public static function sign_params( $params ) {
		$message             = wp_json_encode( $params );
		$private_key         = Settings::get_private_key();
		$signature           = Crypto::sign_message( $message, $private_key );
		$params['signature'] = $signature;
		return $params;
	}

	/**
	 * Makes a request to the Hub to pull data
	 *
	 * @return array|\WP_Error
	 */
	public static function make_request() {
		$url      = trailingslashit( Settings::get_hub_url() ) . 'wp-json/newspack-hub/v1/pull';
		$params   = self::get_request_params();
		$response = wp_remote_post(
			$url,
			[
				'body' => $params,
			] 
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return new \WP_Error( 'newspack-network-node-pulling-error', __( 'Error pulling data from the Hub', 'newspack-network-node' ) );
		}
		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Pulls data from the Hub and processes it
	 *
	 * @return void
	 */
	public static function pull() {
		Debugger::log( 'Pulling data' );
		$response = self::make_request();
		Debugger::log( 'Pulled data response:' );
		Debugger::log( $response );
		if ( is_wp_error( $response ) ) {
			Debugger::log( 'Error pulling data' );
			Debugger::log( $response->get_error_message() );
			return;
		}
		$response = json_decode( $response, true );
		if ( ! is_array( $response ) ) {
			return;
		}

		foreach ( $response as $event ) {
			$action    = $event['action'] ?? false;
			$site      = $event['site'] ?? false;
			$data      = $event['data'] ?? false;
			$timestamp = $event['timestamp'] ?? false;
			$id        = $event['id'] ?? false;

			if ( ! $action || ! $id || ! $data || ! $timestamp ) {
				continue;
			}

			$incoming_event_class = 'Newspack_Network\\Incoming_Events\\' . Accepted_Actions::ACTIONS[ $action ];

			$incoming_event = new $incoming_event_class( $site, $data, $timestamp );

			if ( ! method_exists( $incoming_event, 'process_in_node' ) ) {
				continue;
			}

			$incoming_event->process_in_node();
			
			self::set_last_processed_id( $id );
		}
	}

	
}
