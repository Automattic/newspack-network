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
class Data_Backfill {
	/**
	 * The final results object.
	 *
	 * @var array
	 */
	private static $results = [];

	/**
	 * WP_CLI progress handler.
	 *
	 * @var class
	 */
	public static $progress = false;

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
	 * Process newspack_network_woo_membership_updated events.
	 *
	 * @param string $start The start date.
	 * @param string $end The end date.
	 * @param bool   $live Whether to run in live mode.
	 * @param bool   $verbose Whether to output verbose information.
	 */
	private static function process_newspack_network_woo_membership_updated( $start, $end, $live, $verbose ) {
		// Get all memberships created or updated between $start and $end.
		$membership_posts_ids = get_posts(
			[
				'post_type'   => 'wc_user_membership',
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
				'date_query'  => [
					'column'    => 'post_modified_gmt',
					'after'     => $start,
					'before'    => $end,
					'inclusive' => true,
				],
			]
		);
		if ( ! $verbose ) {
			self::$progress = \WP_CLI\Utils\make_progress_bar( 'Processing memberships', count( $membership_posts_ids ) );
		}
		foreach ( $membership_posts_ids as $post_id ) {
			$membership = new \WC_Memberships_User_Membership( $post_id );
			$status = $membership->get_status();
			$plan_network_id = get_post_meta( $membership->get_plan()->get_id(), \Newspack_Network\Woocommerce_Memberships\Admin::NETWORK_ID_META_KEY, true );
			if ( ! $plan_network_id ) {
				if ( $verbose ) {
					WP_CLI::line( sprintf( 'Skipping membership #%d with status %s, the plan has no network ID.', $membership->get_id(), $status ) );
				}
				self::increment_results_counter( 'newspack_network_woo_membership_updated', 'skipped' );
				continue;
			}
			$membership_data = [
				'email'           => $membership->get_user()->user_email,
				'user_id'         => $membership->get_user()->ID,
				'plan_network_id' => $plan_network_id,
				'membership_id'   => $membership->get_id(),
				'new_status'      => $status,
			];
			if ( $status === 'active' ) {
				$timestamp = strtotime( $membership->get_start_date() );
			} else {
				$timestamp = strtotime( $membership->get_end_date() );
			}
			if ( $live ) {
				self::process_event_entity( $membership_data, $timestamp, 'newspack_network_woo_membership_updated' );
			}
			if ( $verbose ) {
				WP_CLI::line( '👉 ' . sprintf( 'Membership #%d with status %s on %s, linked with network ID "%s".', $membership->get_id(), $status, gmdate( 'Y-m-d H:i:s', $timestamp ), $plan_network_id ) );
			}
			if ( self::$progress ) {
				self::$progress->tick();
			}
		}
	}

	/**
	 * Increment the results counter.
	 *
	 * @param string $action The action.
	 * @param string $counter The counter.
	 */
	private static function increment_results_counter( $action, $counter ) {
		if ( ! isset( self::$results[ $action ] ) ) {
			self::$results[ $action ] = [
				'processed' => 0,
				'duplicate' => 0,
				'skipped'   => 0,
			];
		}
		self::$results[ $action ][ $counter ]++;
	}



	/**
	 * Backfill data for a specific action.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : The action to backfill. Choose "all" to backfill all actions.
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
		$live = isset( $assoc_args['live'] ) ? true : false;
		$verbose = isset( $assoc_args['verbose'] ) ? true : false;

		WP_CLI::line( '' );

		if ( $action !== 'all' && ! in_array( $action, array_keys( Accepted_Actions::ACTIONS ) ) ) {
			WP_CLI::error( 'Invalid action.' );
		}

		$is_hub = Site_Role::is_hub();
		$is_node = Site_Role::is_node();
		if ( ! $is_hub && ! $is_node ) {
			WP_CLI::error( 'This command can only be run on a Hub or Node site.' );
		}

		if ( $is_hub ) {
			WP_CLI::line( 'Running on a Hub site – will create events for the event log.' );
		} else {
			WP_CLI::line( 'Running on a Node site – will create webhook requests. This may result in duplicate requests if the request queue was cleared already, but the Hub will handle the duplicates.' );
		}
		WP_CLI::line( '' );

		if ( ! method_exists( '\Newspack\Reader_Activation', 'get_reader_roles' ) ) {
			WP_CLI::error( 'Incompatible Newspack plugin version.' );
		}
		if ( $live ) {
			WP_CLI::line( '⚡️ Heads up! Running live, data will be updated.' );
		} else {
			WP_CLI::line( 'Running in dry-run mode, data will not be updated. Use --live flag to run in live mode.' );
		}

		WP_CLI::line( '' );
		if ( $action === 'all' ) {
			WP_CLI::line( sprintf( 'Backfilling data for all supported actions, from %s to %s.', $start, $end ) );
		} else {
			WP_CLI::line( sprintf( 'Backfilling data for action %s, from %s to %s.', $action, $start, $end ) );
		}
		WP_CLI::line( '' );

		if ( 'all' === $action ) {
			$actions = array_keys( Accepted_Actions::ACTIONS );
		} else {
			$actions = [ $action ];
		}

		foreach ( $actions as $action_name ) {
			$class_name = 'Newspack_Network\\Backfillers\\' . Accepted_Actions::ACTIONS[ $action_name ];
			if ( ! class_exists( $class_name ) ) {
				if ( 'all' !== $action ) {
					WP_CLI::error( sprintf( 'Backfilling data for %s is not supported yet.', $action_name ) );
				}
				continue;
			}
			$backfiller = new $class_name( $start, $end, $live, $verbose );
			$backfiller->process_events();

		}

		if ( self::$progress ) {
			self::$progress->finish();
		}
		if ( $live ) {
			WP_CLI::line( '' );
			foreach ( self::$results as $action_key => $result ) {
				WP_CLI::success(
					sprintf(
						'Processed %d %s events, ignored %d as duplicates and skipped %d.',
						$result['processed'],
						$action_key,
						$result['duplicate'],
						$result['skipped']
					)
				);
			}
		}
		WP_CLI::line( '' );
	}
}
