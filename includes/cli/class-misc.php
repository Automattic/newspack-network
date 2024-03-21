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
	 * @when after_wp_load
	 */
	public static function fix_roles( array $args, array $assoc_args ) {
		WP_CLI::line( '' );

		$live = isset( $assoc_args['live'] ) ? true : false;
		if ( $live ) {
			WP_CLI::line( 'Live mode – users will be updated.' );
		} else {
			WP_CLI::line( 'Dry run – users will not be updated. Use --live flag to run in live mode.' );
		}

		$users_without_role = get_users(
			[
				'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					[
						'key'     => 'wp_capabilities',
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => 'wp_capabilities',
						'value' => 'a:0:{}',
						'compare' => '=',
					],
				],
			]
		);
		WP_CLI::line( 'Found ' . count( $users_without_role ) . ' users without role.' );
		WP_CLI::line( '' );

		foreach ( $users_without_role as $user ) {
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
}
