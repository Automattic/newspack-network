<?php
/**
 * Newspack Hub Users Admin page
 *
 * @package Newspack
 */

namespace Newspack_Network\Hub\Admin;

use Newspack_Network\Hub\Stores\Event_Log as Event_Log_Store;

/**
 * Class to handle the Users admin page by adding an additional column to the Users table
 */
class Users {

	/**
	 * Runs the initialization.
	 */
	public static function init() {
		add_filter( 'manage_users_columns', [ __CLASS__, 'manage_users_columns' ] );
		add_filter( 'manage_users_custom_column', [ __CLASS__, 'manage_users_custom_column' ], 10, 3 );
	}

	/**
	 * Add a custom column to the Users table
	 *
	 * @param array $columns The current columns.
	 * @return array
	 */
	public static function manage_users_columns( $columns ) {
		$columns['newspack_network_activity'] = __( 'Newspack Network Activity', 'newspack-network' );
		return $columns;
	}

	/**
	 * Add content to the custom column
	 *
	 * @param string $value The current column value.
	 * @param string $column_name The current column name.
	 * @param int    $user_id The current user ID.
	 * @return string
	 */
	public static function manage_users_custom_column( $value, $column_name, $user_id ) {
		if ( 'newspack_network_activity' === $column_name ) {
			$user = get_user_by( 'id', $user_id );
			if ( ! $user ) {
				return $value;
			}

			$last_activity = Event_Log_Store::get( [ 'email' => $user->user_email ], 1 );

			if ( empty( $last_activity ) ) {
				return '-';
			}

			$last_activity = $last_activity[0];

			$summary       = $last_activity->get_summary();
			$event_log_url = add_query_arg(
				[
					'page'  => Event_Log::PAGE_SLUG,
					'email' => $user->user_email,
				],
				admin_url( 'admin.php' )
			);
			return sprintf(
				'%s: <code>%s</code><br><a href="%s">%s</a>',
				__( 'Last Activity', 'newspack-network' ),
				$summary,
				$event_log_url,
				__( 'View all', 'newspack-network' )
			);

		}
		return $value;
	}

}
