<?php
/**
 * Data Backfill scripts.
 *
 * @package Newspack
 */

namespace Newspack_Network;

use WP_CLI;

/**
 * Data Backfill class.
 */
class Synchronize_All {
	/**
	 * Number of events left to fetch.
	 *
	 * @var int
	 */
	private static $events_left = 0;

	/**
	 * Initialize this class and register hooks
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_commands' ] );
	}

	/**
	 * Register the WP-CLI commands
	 *
	 * @return void
	 */
	public static function register_commands() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'newspack-network sync-all', [ __CLASS__, 'sync_all' ] );
		}
	}

	/**
	 * Get events from the Hub.
	 */
	private static function get_events() {
		$response = \Newspack_Network\Node\Pulling::make_request();
		if ( is_wp_error( $response ) ) {
			WP_CLI::error( 'Error fetching events from the Hub: ' . $response->get_error_message() );
		}
		$response = json_decode( $response, true );
		if ( ! isset( $response['data'] ) ) {
			WP_CLI::error( 'Missing data in response.' );
		}

		WP_CLI::line( 'Received ' . count( $response['data'] ) . ' events, processing…' );

		\Newspack_Network\Node\Pulling::process_pulled_data( $response['data'] );

		self::$events_left = $response['more_items_count'];
		WP_CLI::line( 'Events left to fetch: ' . self::$events_left );

		if ( self::$events_left > 0 ) {
			self::get_events();
		}
	}

	/**
	 * Sync all data.
	 */
	public static function sync_all() {
		WP_CLI::line( '' );
		if ( ! Site_Role::is_node() ) {
			WP_CLI::error( 'This command can only be run on a Node site.' );
		}
		self::get_events();
	}
}
