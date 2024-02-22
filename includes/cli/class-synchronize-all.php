<?php
/**
 * Sync all scripts.
 *
 * @package Newspack
 */

namespace Newspack_Network;

use WP_CLI;
use Newspack_Network\Node\Pulling;

/**
 * Sync all class.
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
			WP_CLI::add_command( 'newspack-network sync-check', [ __CLASS__, 'sync_check' ] );
		}
	}

	/**
	 * Process events received from the Hub.
	 */
	private static function process_events() {

		$response = self::make_request();
		WP_CLI::line( 'Received ' . count( $response['data'] ) . ' events, processing…' );

		Pulling::process_pulled_data( $response['data'] );

		self::$events_left = (int) $response['total'] - \Newspack_Network\Hub\Pull_Endpoint::get_pull_limit();
		if ( 0 > self::$events_left ) {
			self::$events_left = 0;
		}
		WP_CLI::line( 'Events left to fetch: ' . self::$events_left );

		if ( self::$events_left > 0 ) {
			self::process_events();
		}
		WP_CLI::success( 'Sync complete.' );
	}

	/**
	 * Makes a request to pull events from the Hub
	 *
	 * @return array
	 */
	private static function make_request() {
		$response = Pulling::make_request();
		if ( is_wp_error( $response ) ) {
			WP_CLI::error( 'Error fetching events from the Hub: ' . $response->get_error_message() );
		}
		$response = json_decode( $response, true );
		if ( ! isset( $response['data'] ) ) {
			WP_CLI::error( 'Missing data in response.' );
		}
		return $response;
	}

	/**
	 * Syncs all data, pulling all events from the Hub.
	 */
	public static function sync_all() {
		WP_CLI::line( '' );
		if ( ! Site_Role::is_node() ) {
			WP_CLI::error( 'This command can only be run on a Node site.' );
		}
		WP_CLI::line( 'Syncing all data from the Hub can take a long time and will write data to the site.' );
		WP_CLI::line( 'Consider running wp newspack-network sync-check first to check the size of the queue.' );
		WP_CLI::confirm( 'Are you sure you want to sync all data?' );
		self::process_events();
	}

	/**
	 * Checks the current state of the sync
	 */
	public static function sync_check() {
		WP_CLI::line( '' );
		if ( ! Site_Role::is_node() ) {
			WP_CLI::error( 'This command can only be run on a Node site.' );
		}
		WP_CLI::line( 'Checking the sync queue' );

		$response = self::make_request();
		$events_on_the_hub = (int) $response['total'];
		$last_processed_id = Pulling::get_last_processed_id();

		WP_CLI::line( 'Last processed event ID: ' . $last_processed_id );
		if ( $events_on_the_hub === 0 ) {
			WP_CLI::success( 'Sync is up to date. Nothing to pull.' );
		} else {
			WP_CLI::line( 'Events left to sync: ' . ( $events_on_the_hub ) );
		}
	}
}
