<?php
/**
 * Misc CLI scripts.
 *
 * @package Newspack
 */

namespace Newspack_Network;

use WP_CLI;

/**
 * Misc CLI class.
 */
class Misc {
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
			WP_CLI::add_command( 'newspack-network fix-roles', [ __CLASS__, 'fix_roles' ] );
			WP_CLI::add_command( 'newspack-network get-user-memberships', [ __CLASS__, 'get_user_memberships' ] );
			WP_CLI::add_command( 'newspack-network fix-membership-discrepancies', [ __CLASS__, 'fix_membership_discrepancies' ] );
			WP_CLI::add_command( 'newspack-network deduplicate-users', [ __CLASS__, 'deduplicate_users' ] );
			WP_CLI::add_command( 'newspack-network deduplicate-subscriptions', [ __CLASS__, 'deduplicate_subscriptions' ] );
		}
	}

	/**
	 * Assign 'Subscriber' role to users without any role set..
	 *
	 * @param array $args Indexed array of args.
	 * @param array $assoc_args Associative array of args.
	 * @return void
	 *
	 * ## OPTIONS
	 *
	 * [--live]
	 * : Run the command in live mode, updating the users.
	 *
	 * [--file]
	 * : Read users from a CSV file, instead of by querying the DB.
	 *
	 * @when after_wp_load
	 */
	public static function fix_roles( array $args, array $assoc_args ) {
		WP_CLI::line( '' );

		$live = isset( $assoc_args['live'] ) ? true : false;
		$csv_file = isset( $assoc_args['file'] ) ? $assoc_args['file'] : false;

		if ( $live ) {
			WP_CLI::line( 'Live mode – users will be updated.' );
		} else {
			WP_CLI::line( 'Dry run – users will not be updated. Use --live flag to run in live mode.' );
		}

		if ( $csv_file ) {
			WP_CLI::line( 'Read users from CSV: ' . $csv_file );
			$users_to_update = [];
			if ( file_exists( $csv_file ) ) {
				WP_CLI::line( 'File found.' );
				$csv = array_map( 'str_getcsv', file( $csv_file ) );
				array_walk(
					$csv,
					function( &$a ) use ( $csv ) {
						$a = array_combine( $csv[0], $a );
					}
				);
				array_shift( $csv ); // Remove column header.
				$users_to_update = $csv;
			}
		} else {
			$users_to_update = get_users(
				[
					'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'relation' => 'OR',
						[
							'key'     => 'wp_capabilities',
							'compare' => 'NOT EXISTS',
						],
						[
							'key'     => 'wp_capabilities',
							'value'   => 'a:0:{}',
							'compare' => '=',
						],
					],
				]
			);
		}
		WP_CLI::line( 'Got ' . count( $users_to_update ) . ' users to update.' );
		WP_CLI::line( '' );

		foreach ( $users_to_update as $user ) {
			// If read from CSV, find the user by email.
			if ( is_array( $user ) ) {
				$user_email = $user['user_email'];
				$user = get_user_by( 'email', $user_email );
				if ( $user === false ) {
					WP_CLI::warning( 'User not found by email: ' . $user_email );
					continue;
				}
			}
			$user_id = $user->ID;
			if ( $live ) {
				$user->set_role( 'subscriber' );
				WP_CLI::line( "👉 Assigned Subscriber role to user $user->user_email (#$user_id)." );
			} else {
				WP_CLI::line( "👉 In live mode, would assign Subscriber role to user $user->user_email (#$user_id)." );
			}
		}

		WP_CLI::line( '' );
	}

	/**
	 * Get user memberships.
	 *
	 * @param array $args Indexed array of args.
	 * @param array $assoc_args Associative array of args.
	 * @return void
	 *
	 * ## EXAMPLES
	 *
	 *     wp newspack-network get-user-memberships member-name@example.com
	 */
	public static function get_user_memberships( array $args, array $assoc_args ) {
		WP_CLI::line( '' );

		$email_address = isset( $args[0] ) ? $args[0] : false;
		if ( ! $email_address ) {
			WP_CLI::error( 'Please provide an email address.' );
		}

		$memberships = \wc_memberships_get_user_memberships( get_user_by( 'email', $email_address ) );
		foreach ( $memberships as $membership ) {
			$plan_name = $membership->get_plan()->get_name();
			$plan_network_id = get_post_meta( $membership->get_plan()->get_id(), \Newspack_Network\Woocommerce_Memberships\Admin::NETWORK_ID_META_KEY, true );
			if ( $plan_network_id ) {
				$plan_name .= ' (Network ID: ' . $plan_network_id . ')';
			}

			WP_CLI::line( '➡ Membership ID: ' . $membership->get_id() . ', status: ' . $membership->get_status() . ', plan: ' . $plan_name );
		}

		WP_CLI::line( '' );
	}

	/**
	 * Fix membership discrepancies.
	 *
	 * @param array $args Indexed array of args.
	 * @param array $assoc_args Associative array of args.
	 * @return void
	 *
	 * ## OPTIONS
	 *
	 * [--live]
	 * : Run the command in live mode, updating the users.
	 *
	 * ## EXAMPLES
	 *
	 *     wp newspack-network fix-membership-discrepancies
	 */
	public static function fix_membership_discrepancies( array $args, array $assoc_args ) {
		WP_CLI::line( '' );

		$live = isset( $assoc_args['live'] ) ? true : false;
		if ( $live ) {
			WP_CLI::line( 'Live mode – memberships will be updated or created.' );
		} else {
			WP_CLI::line( 'Dry run – memberships will not be updated. Use --live flag to run in live mode.' );
		}

		$membership_plans_from_network_data = \Newspack_Network\Hub\Admin\Membership_Plans::get_membership_plans_from_network();
		if ( ! isset( $membership_plans_from_network_data['discrepancies_emails'] ) ) {
			WP_CLI::error( 'Missing discrepancies emails in network memberships data.' );
		}
		$by_origin_site = [];
		foreach ( $membership_plans_from_network_data['discrepancies_emails'] as $email_address ) {
			$user = get_user_by( 'email', $email_address );
			if ( ! $user ) {
				WP_CLI::warning( 'User not found by email: ' . $email_address );
				continue;
			}
			$origin_site = get_user_meta( $user->ID, \Newspack_Network\Utils\Users::USER_META_REMOTE_SITE, true );
			if ( ! $origin_site ) {
				WP_CLI::warning( 'Missing origin site for user with email: ' . $email_address );
				continue;
			}
			$origin_site = preg_replace( '/newspackstaging.com/', 'newspackqa.blog', $origin_site );
			if ( isset( $by_origin_site[ $origin_site ] ) ) {
				$by_origin_site[ $origin_site ][] = $email_address;
			} else {
				$by_origin_site[ $origin_site ] = [ $email_address ];
			}
		}

		WP_CLI::line( '' );
		foreach ( $by_origin_site as $site_url => $user_emails ) {
			WP_CLI::line( sprintf( 'Found %d users originating from site %s', count( $user_emails ), $site_url ) );

			foreach ( $user_emails as $user_email ) {
				WP_CLI::line( sprintf( 'Fixing %s…', $user_email ) );

				$found_memberships = \Newspack_Network\Hub\Admin\Membership_Plans::fetch_collection_from_api(
					\Newspack_Network\Hub\Nodes::get_node_by_url( $site_url ),
					'wc/v2/memberships/members',
					'memberships',
					[
						'customer' => urlencode( $user_email ),
					]
				);

				if ( ! $found_memberships ) {
					WP_CLI::warning( 'Could not retrieve any user memberships.' );
					continue;
				}
				WP_CLI::line( sprintf( 'Found %d membership(s)', count( $found_memberships ) ) );
				foreach ( $found_memberships as $membership ) {
					WP_CLI::line( sprintf( 'Found membership %d with status %s.', $membership->id, $membership->status ) );
					$membership_data = [
						'email'           => $user_email,
						'user_id'         => $membership->customer_id,
						'plan_network_id' => $membership->plan_network_id,
						'membership_id'   => $membership->id,
						'new_status'      => $membership->status,
					];
					$timestamp = false;
					switch ( $membership->status ) {
						case 'paused':
							$timestamp = strtotime( $membership->paused_date );
							break;
						case 'cancelled':
							$timestamp = strtotime( $membership->cancelled_date );
							break;
						case 'expired':
							$timestamp = strtotime( $membership->end_date );
							break;
					}

					if ( ! $timestamp ) {
						$timestamp = strtotime( $membership->start_date );
					}

					$event = new \Newspack_Network\Incoming_Events\Woocommerce_Membership_Updated( $site_url, $membership_data, $timestamp );

					if ( $live ) {
						if ( Site_Role::is_hub() ) {
							$event->process_in_hub();
							if ( $event->is_persisted ) {
								WP_CLI::success( 'Processed event.' );
							} else {
								WP_CLI::warning( 'Event not persisted, possibly a duplicate.' );
							}
							Data_Backfill::increment_results_counter( $event->get_action_name(), $event->is_persisted ? 'processed' : 'duplicate' );
						} else {
							\Newspack\Data_Events\Webhooks::handle_dispatch( $event->get_action_name(), $event->get_timestamp(), $event->get_data() );
							WP_CLI::success( 'Dispatched webhook.' );
						}
					} else {
						WP_CLI::line( 'Would processing the event in live mode.' );
					}
				}
			}
			WP_CLI::line( '' );
		}

		WP_CLI::line( '' );
	}

	/**
	 * Deduplicate users.
	 *
	 * @param array $args Indexed array of args.
	 * @param array $assoc_args Associative array of args.
	 * @return void
	 *
	 * ## OPTIONS
	 *
	 * [--live]
	 * : Run the command in live mode, updating the users.
	 *
	 * ## EXAMPLES
	 *
	 *     wp newspack-network deduplicate-users
	 */
	public static function deduplicate_users( array $args, array $assoc_args ) {
		WP_CLI::line( '' );

		$live = isset( $assoc_args['live'] ) ? true : false;
		if ( $live ) {
			WP_CLI::line( 'Live mode – users will be deleted.' );
		} else {
			WP_CLI::line( 'Dry run – users will not be deleted. Use --live flag to run in live mode.' );
		}

		global $wpdb;
		$duplicate_users_result = $wpdb->get_results(
			'SELECT user_email, COUNT(user_email) as count FROM wp_users GROUP BY user_email HAVING count > 1'
		);
		WP_CLI::line( sprintf( 'Found %d duplicated user(s)', count( $duplicate_users_result ) ) );
		WP_CLI::line( '' );
		foreach ( $duplicate_users_result as $key => $result ) {
			WP_CLI::line( 'Email address: ' . $result->user_email );
			$user_ids_results = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT id FROM wp_users WHERE user_email = %s',
					$result->user_email
				)
			);
			$users = [];
			foreach ( $user_ids_results as $key => $result ) {
				$users[] = get_user_by( 'id', $result->id );
			}
			$by_user = [];
			foreach ( $users as $user ) {
				$memberships = \wc_memberships_get_user_memberships( $user );
				$by_user[ $user->ID ] = [
					'memberships' => $memberships,
					'user'        => $user,
				];
				if ( count( $memberships ) === 0 ) {
					WP_CLI::line( sprintf( 'User %d has no memberships', $user->ID ) );
				} else {
					$statuses = array_map(
						function( $membership ) {
							return $membership->status;
						},
						$memberships
					);
					$plan_ids = array_map(
						function( $membership ) {
							return $membership->plan_id;
						},
						$memberships
					);
					WP_CLI::line( sprintf( 'User %d has %d membership(s) (plans: %s) (statuses: %s)', $user->ID, count( $memberships ), implode( ', ', $plan_ids ), implode( ', ', $statuses ) ) );
				}
			}

			// Get the user with the least memberships.
			$min_memberships = PHP_INT_MAX;
			$min_user_id = null;
			foreach ( $by_user as $user_id => $user_data ) {
				$memberships = $user_data['memberships'];
				if ( count( $memberships ) < $min_memberships ) {
					$min_memberships = count( $memberships );
					$min_user_id = $user_id;
				}
			}
			if ( $live ) {
				WP_CLI::line( 'Removing user #' . $min_user_id );
				$result = wp_delete_user( $min_user_id );
				if ( $result ) {
					WP_CLI::success( 'Deleted user ' . $min_user_id );
				} else {
					WP_CLI::warning( 'Failed to delete user ' . $min_user_id );
				}
			} else {
				WP_CLI::line( 'Would remove user #' . $min_user_id );
			}

			WP_CLI::line( '' );
		}
	}

	/**
	 * Deduplicate subscriptions.
	 *
	 * @param array $args Indexed array of args.
	 * @param array $assoc_args Associative array of args.
	 * @return void
	 *
	 * ## OPTIONS
	 *
	 * [--live]
	 * : Run the command in live mode, updating the subscriptions.
	 *
	 * ## EXAMPLES
	 *
	 *     wp newspack-network deduplicate-subscriptions
	 */
	public static function deduplicate_subscriptions( array $args, array $assoc_args ) {
		WP_CLI::line( '' );

		$live = isset( $assoc_args['live'] ) ? true : false;
		if ( $live ) {
			WP_CLI::line( 'Live mode – subscriptions will be deleted.' );
		} else {
			WP_CLI::line( 'Dry run – subscriptions will not be deleted. Use --live flag to run in live mode.' );
		}
		WP_CLI::line( '' );

		$extra_subscriptions_count = 0;

		global $wpdb;
		$subscriptions_per_user_results = $wpdb->get_results(
			"SELECT customer_id, COUNT(customer_id) as count FROM wp_wc_orders WHERE type = 'shop_subscription' AND status = 'wc-active' GROUP BY customer_id HAVING count > 1"
		);
		WP_CLI::line( sprintf( 'Found %d user(s) with multiple active subscriptions', count( $subscriptions_per_user_results ) ) );

		foreach ( $subscriptions_per_user_results as $result ) {
			$user = get_user_by( 'id', $result->customer_id );
			if ( $user ) {
				// Compare to user's memberships.
				$active_memberships = \wc_memberships_get_user_memberships( $user->ID, [ 'status' => [ 'active' ] ] );
				WP_CLI::line( sprintf( 'User %s (#%d) has %d active subscriptions and %d active memberships.', $user->user_email, $result->customer_id, $result->count, count( $active_memberships ) ) );
				$memberships_subscriptions_delta = $result->count - count( $active_memberships );
				if ( $memberships_subscriptions_delta > 0 ) {
					WP_CLI::warning( sprintf( 'Active subscriptions (%d) do not match active memberships (%d) for user #%d.', $result->count, count( $active_memberships ), $result->customer_id ) );
					$extra_subscriptions_count += $memberships_subscriptions_delta;
				}
			} else {
				WP_CLI::line( sprintf( '%d active subscriptions are assigned to user #%d (no user found)', $result->count, $result->customer_id ) );
			}
		}

		// TODO: The deduplication.

		if ( $extra_subscriptions_count > 0 ) {
			WP_CLI::line( '' );
			WP_CLI::warning( sprintf( 'There are %d extra active subscriptions.', $extra_subscriptions_count ) );
		}

		WP_CLI::line( '' );
	}
}
