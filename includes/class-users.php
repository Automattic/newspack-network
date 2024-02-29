<?php
/**
 * Newspack Users Admin page
 *
 * @package Newspack
 */

namespace Newspack_Network;

/**
 * Class to handle the Users admin page
 */
class Users {

	/**
	 * Runs the initialization.
	 */
	public static function init() {
		add_filter( 'manage_users_columns', [ __CLASS__, 'manage_users_columns' ] );
		add_filter( 'manage_users_custom_column', [ __CLASS__, 'manage_users_custom_column' ], 10, 3 );
		add_filter( 'users_list_table_query_args', [ __CLASS__, 'users_list_table_query_args' ] );
	}

	/**
	 * Add a custom column to the Users table
	 *
	 * @param array $columns The current columns.
	 * @return array
	 */
	public static function manage_users_columns( $columns ) {
		if ( Site_Role::is_hub() ) {
			$columns['newspack_network_activity'] = __( 'Newspack Network Activity', 'newspack-network' );
		}
		$columns['newspack_network_user'] = __( 'Network Original User', 'newspack-network' );
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
		if ( 'newspack_network_user' === $column_name ) {
			$remote_site = get_user_meta( $user_id, \Newspack_Network\Utils\Users::USER_META_REMOTE_SITE, true );
			$remote_id = (int) get_user_meta( $user_id, \Newspack_Network\Utils\Users::USER_META_REMOTE_ID, true );
			if ( $remote_site ) {
				return sprintf(
					'<a href="%swp-admin/user-edit.php?user_id=%d">%s</a>',
					trailingslashit( esc_url( $remote_site ) ),
					$remote_id,
					sprintf( '%s (#%d)', $remote_site, $remote_id )
				);
			}
		}
		if ( 'newspack_network_activity' === $column_name && Site_Role::is_hub() ) {
			$user = get_user_by( 'id', $user_id );
			if ( ! $user ) {
				return $value;
			}

			$last_activity = \Newspack_Network\Hub\Stores\Event_Log::get( [ 'email' => $user->user_email ], 1 );

			if ( empty( $last_activity ) ) {
				return '-';
			}

			$last_activity = $last_activity[0];

			$summary       = $last_activity->get_summary();
			$event_log_url = add_query_arg(
				[
					'page'  => \Newspack_Network\Hub\Admin\Event_Log::PAGE_SLUG,
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

	/**
	 * Handle the filtering of users by multiple roles.
	 * Unfortunatelly, `get_views` and `get_views_links` are not filterable, so "All" will
	 * be displayed as the active filter.
	 *
	 * @param array $args The current query args.
	 */
	public static function users_list_table_query_args( $args ) {
		if ( isset( $_REQUEST['role__in'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['role__in'] = explode( ',', sanitize_text_field( $_REQUEST['role__in'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			unset( $args['role'] );
		}
		return $args;
	}
}
