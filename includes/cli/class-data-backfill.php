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
	 * Find existing webhook requests for a given action and data.
	 *
	 * @param string $action The action name.
	 * @param int    $timestamp The timestamp.
	 * @param array  $data The data.
	 */
	private static function find_webhook_requests( $action, $timestamp, $data ) {
		return get_posts(
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
						'value'   => wp_json_encode( $data ),
						'compare' => '=',
					],
				],
			]
		);
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
				'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'     => \Newspack_Network\Utils\Users::USER_META_REMOTE_SITE,
						'compare' => 'NOT EXISTS',
					],
				],
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
				self::process_event_entity( $user_data, strtotime( $user->user_registered ), 'reader_registered' );
			}
			if ( $verbose ) {
				WP_CLI::line( '👉 ' . sprintf( 'User %s (#%d) registered on %s.', $user->user_email, $user->ID, $user->user_registered ) );
			}
			if ( self::$progress ) {
				self::$progress->tick();
			}
		}
	}

	/**
	 * Process donation_new events.
	 *
	 * @param string $start The start date.
	 * @param string $end The end date.
	 * @param bool   $live Whether to run in live mode.
	 * @param bool   $verbose Whether to output verbose information.
	 */
	private static function process_donation_new( $start, $end, $live, $verbose ) {
		$orders = wc_get_orders(
			[
				'status'       => 'completed',
				'date_created' => $start . '...' . $end,
				'limit'        => -1,
			]
		);
		if ( ! $verbose ) {
			self::$progress = \WP_CLI\Utils\make_progress_bar( 'Processing orders', count( $orders ) );
		}
		foreach ( $orders as $order ) {
			$order_id = $order->get_id();
			$order_data = \Newspack\Data_Events\Utils::get_order_data( $order_id );

			$timestamp = strtotime( $order->get_date_completed() );
			if ( $live ) {
				self::process_event_entity( $order_data, $timestamp, 'donation_new' );
			}
			if ( $verbose ) {
				WP_CLI::line( '👉 ' . sprintf( 'Order #%d completed on %s.', $order_id, gmdate( 'Y-m-d H:i:s', $timestamp ) ) );
			}
			if ( self::$progress ) {
				self::$progress->tick();
			}
		}
	}

	/**
	 * Process donation_subscription_cancelled events.
	 *
	 * @param string $start The start date.
	 * @param string $end The end date.
	 * @param bool   $live Whether to run in live mode.
	 * @param bool   $verbose Whether to output verbose information.
	 */
	private static function process_donation_subscription_cancelled( $start, $end, $live, $verbose ) {
		$subscriptions = wcs_get_subscriptions(
			[
				'subscription_status'    => 'cancelled',
				'subscriptions_per_page' => -1,
				'meta_query'             => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					[
						'key'     => wcs_get_date_meta_key( 'cancelled' ),
						'compare' => '>=',
						'value'   => $start,
					],
					[
						'key'     => wcs_get_date_meta_key( 'cancelled' ),
						'compare' => '<=',
						'value'   => $end,
					],
				],
			]
		);
		if ( ! $verbose ) {
			self::$progress = \WP_CLI\Utils\make_progress_bar( 'Processing subscriptions', count( $subscriptions ) );
		}
		foreach ( $subscriptions as $subscription ) {
			$subscription_data = \Newspack\Data_Events\Utils::get_subscription_data( $subscription );
			$timestamp = strtotime( $subscription->get_date( 'cancelled' ) );
			if ( $live ) {
				self::process_event_entity( $subscription_data, $timestamp, 'donation_subscription_cancelled' );
			}
			if ( $verbose ) {
				WP_CLI::line( '👉 ' . sprintf( 'Subscription #%d cancelled on %s.', $subscription->get_id(), gmdate( 'Y-m-d H:i:s', $timestamp ) ) );
			}
			if ( self::$progress ) {
				self::$progress->tick();
			}
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
	 * Process a WooCommerce entity.
	 *
	 * @param array  $data The data.
	 * @param int    $timestamp The ti;mestamp.
	 * @param string $action The action.
	 */
	private static function process_event_entity( $data, $timestamp, $action ) {
		if ( Site_Role::is_hub() ) {
			$event = new \Newspack_Network\Incoming_Events\Abstract_Incoming_Event(
				get_bloginfo( 'url' ),
				$data,
				$timestamp,
				$action
			);
			$event->process_in_hub();
			self::increment_results_counter( $action, $event->is_persisted ? 'processed' : 'duplicate' );
		} else {
			$requests = self::find_webhook_requests( $action, $timestamp, $data );
			if ( count( $requests ) > 0 ) {
				self::increment_results_counter( $action, 'duplicate' );
				return;
			}
			\Newspack\Data_Events\Webhooks::handle_dispatch( $action, $timestamp, $data );
			self::increment_results_counter( $action, 'processed' );
		}
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
		$live = isset( $assoc_args['live'] ) ? $assoc_args['live'] : null;
		$verbose = isset( $assoc_args['verbose'] ) ? $assoc_args['verbose'] : null;

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

		switch ( $action ) {
			case 'reader_registered':
				self::process_reader_registered( $start, $end, $live, $verbose );
				break;
			case 'donation_new':
				self::process_donation_new( $start, $end, $live, $verbose );
				break;
			case 'donation_subscription_cancelled':
				self::process_donation_subscription_cancelled( $start, $end, $live, $verbose );
				break;
			case 'newspack_network_woo_membership_updated':
				self::process_newspack_network_woo_membership_updated( $start, $end, $live, $verbose );
				break;
			case 'all':
				self::process_reader_registered( $start, $end, $live, $verbose );
				self::process_donation_new( $start, $end, $live, $verbose );
				self::process_donation_subscription_cancelled( $start, $end, $live, $verbose );
				self::process_newspack_network_woo_membership_updated( $start, $end, $live, $verbose );
				break;
			default:
				WP_CLI::error( 'Backfilling data for this action is not supported yet.' );
				break;
		}

		if ( self::$progress ) {
			self::$progress->finish();
		}
		if ( $live ) {
			WP_CLI::line( '' );
			foreach ( [ 'reader_registered', 'donation_new', 'donation_subscription_cancelled', 'newspack_network_woo_membership_updated' ] as $action_key ) {
				if ( isset( self::$results[ $action_key ] ) ) {
					WP_CLI::success(
						sprintf(
							'Processed %d %s events, ignored %d as duplicates and skipped %d.',
							self::$results[ $action_key ]['processed'],
							$action_key,
							self::$results[ $action_key ]['duplicate'],
							self::$results[ $action_key ]['skipped']
						)
					);
				}
			}
		}
		WP_CLI::line( '' );
	}
}
