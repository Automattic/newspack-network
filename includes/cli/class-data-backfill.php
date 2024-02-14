<?php
/**
 * Data Backfill scripts.
 *
 * @package Newspack
 */

namespace Newspack_Network;

use Automattic\Jetpack\VideoPress\Data;
use WP_CLI;

/**
 * Data Backfill class.
 */
class Data_Backfill {
	/**
	 * The final results object.
	 *
	 * @var array
	 */
	private static $results = [
		'reader_registered' => [
			'processed' => 0,
			'duplicate' => 0,
		],
	];

	/**
	 * WP_CLI progress handler.
	 *
	 * @var class
	 */
	private static $progress = false;

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
			WP_CLI::add_command( 'newspack-network data-backfill', [ __CLASS__, 'data_backfill' ] );
		}
	}

	/**
	 * Process reader_registered events.
	 *
	 * @param string $start The start date.
	 * @param string $end The end date.
	 * @param bool   $live Whether to run in live mode.
	 * @param bool   $verbose Whether to output verbose information.
	 */
	private static function process_reader_registered( $start, $end, $live, $verbose ) {
		$is_hub = Site_Role::is_hub();
		$is_node = Site_Role::is_node();
		if ( ! $is_hub && ! $is_node ) {
			WP_CLI::error( 'This command can only be run on a Hub or Node site.' );
		}

		if ( $is_hub ) {
			WP_CLI::line( 'Running on a Hub site – will create events for the event log.' );
		} else {
			WP_CLI::line( 'Running on a Node site – will create webhook requests. This may result in duplicate requests if the request queue was cleared already.' );
		}
		WP_CLI::line( '' );

		$action = 'reader_registered';
		// Get all users registered between $start and $end.
		$users = get_users(
			[
				'role__in'   => \Newspack\Reader_Activation::get_reader_roles(),
				'date_query' => [
					'after'     => $start,
					'before'    => $end,
					'inclusive' => true,
				],
				'fields'     => [ 'id', 'user_email', 'user_registered' ],
				'number'     => -1,
			]
		);
		if ( ! $verbose ) {
			self::$progress = \WP_CLI\Utils\make_progress_bar( 'Processing users', count( $users ) );
		}

		foreach ( $users as $user ) {
			$registration_method = get_user_meta( $user->ID, \Newspack\Reader_Activation::REGISTRATION_METHOD, true );
			$user_data = [
				'user_id'  => $user->ID,
				'email'    => $user->user_email,
				'metadata' => [
					// 'current_page_url' is not saved, can't be backfilled.
					'registration_method' => empty( $registration_method ) ? 'backfill-script' : $registration_method,
				],
			];
			if ( $live ) {
				$timestamp = strtotime( $user->user_registered );
				if ( $is_hub ) {
					// Check against duplicate events.
					$maybe_event = \Newspack_Network\Hub\Stores\Event_Log::get(
						[
							'action_name' => $action,
							'email'       => $user->user_email,
						]
					);
					if ( ! empty( $maybe_event ) ) {
						self::$results['reader_registered']['duplicate']++;
						continue;
					}
					// Add a user registration to the event log, with the timestamp of the registration.
					$event = new \Newspack_Network\Incoming_Events\Abstract_Incoming_Event(
						get_bloginfo( 'url' ),
						$user_data,
						$timestamp,
						$action
					);
					$event->process_in_hub();
					self::$results['reader_registered']['processed']++;
				} else {
					$requests = get_posts(
						[
							'post_type'   => \Newspack\Data_Events\Webhooks::REQUEST_POST_TYPE,
							'post_title'  => $action,
							'post_status' => 'any',
							'meta_query'  => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
								'relation' => 'AND',
								[
									'key'     => 'timestamp',
									'value'   => $timestamp,
									'compare' => '=',
								],
								[
									'key'     => 'action_name',
									'value'   => $action,
									'compare' => '=',
								],
								[
									'key'     => 'data',
									'value'   => wp_json_encode( $user_data ),
									'compare' => '=',
								],
							],
						]
					);
					if ( count( $requests ) > 0 ) {
						self::$results['reader_registered']['duplicate']++;
						continue;
					}
					\Newspack\Data_Events\Webhooks::handle_dispatch( $action, $timestamp, $user_data );
					self::$results['reader_registered']['processed']++;
				}
			} elseif ( $verbose ) {
				WP_CLI::line( sprintf( 'User %s (#%d) registered on %s.', $user->user_email, $user->ID, $user->user_registered ) );
			}
			if ( self::$progress ) {
				self::$progress->tick();
			}
		}
	}

	/**
	 * Backfill data for a specific action.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : The action to backfill.
	 *
	 * [--start=<start>]
	 * : The start date for the backfill.
	 *
	 * [--end=<end>]
	 * : The end date for the backfill.
	 *
	 * [--live]
	 * : Run the backfill in live mode, which will process the events.
	 *
	 * [--verbose]
	 * : Output more verbose information.
	 *
	 * ## EXAMPLES
	 *
	 *     wp newspack-network data-backfill reader_registered --start=2020-01-01 --end=2020-12-31 --live
	 *
	 * @param array $args The command arguments.
	 * @param array $assoc_args The command options.
	 * @return void
	 */
	public static function data_backfill( $args, $assoc_args ) { // phpcs:ignore Generic.NamingConventions.ConstructorName.OldStyle
		$action = $args[0];
		$start = isset( $assoc_args['start'] ) ? $assoc_args['start'] : null;
		$end = isset( $assoc_args['end'] ) ? $assoc_args['end'] : null;
		$live = isset( $assoc_args['live'] ) ? $assoc_args['live'] : null;
		$verbose = isset( $assoc_args['verbose'] ) ? $assoc_args['verbose'] : null;

		WP_CLI::line( '' );

		if ( ! in_array( $action, array_keys( Accepted_Actions::ACTIONS ) ) ) {
			WP_CLI::error( 'Invalid action.' );
		}

		if ( ! method_exists( '\Newspack\Reader_Activation', 'get_reader_roles' ) ) {
			WP_CLI::error( 'Incompatible Newspack plugin version.' );
		}
		if ( $live ) {
			WP_CLI::line( '⚡️ Heads up! Running live, data will be updated.' );
		} else {
			WP_CLI::line( 'Running in dry-run mode, data will not be updated. Use --live flag to run in live mode.' );
		}

		WP_CLI::line( '' );
		WP_CLI::line( sprintf( 'Backfilling data for action %s, from %s to %s.', $action, $start, $end ) );
		WP_CLI::line( '' );

		switch ( $action ) {
			case 'reader_registered':
				self::process_reader_registered( $start, $end, $live, $verbose );
				break;
			default:
				WP_CLI::error( 'Backfilling data for this action is not supported yet.' );
				break;
		}

		if ( self::$progress ) {
			self::$progress->finish();
		}
		WP_CLI::line( '' );
		WP_CLI::success(
			sprintf(
				'Processed %d reader_registered events, skipped %d as duplicates.',
				self::$results['reader_registered']['processed'],
				self::$results['reader_registered']['duplicate']
			)
		);
		WP_CLI::line( '' );
	}
}
