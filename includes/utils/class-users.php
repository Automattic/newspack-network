<?php
/**
 * Newspack Network Users methods.
 *
 * @package Newspack
 */

namespace Newspack_Network\Utils;

/**
 * Class to watch the user for updates and trigger events
 */
class Users {

	/**
	 * Gets an existing user or creates a user propagated from another site in the Network
	 *
	 * @param string $email The email of the user to look for. If no user is found, a new one will be created with this email.
	 * @param string $remote_site_url The URL of the remote site. Used only when a new user is created.
	 * @param string $remote_site_id The ID of the remote site. Used only when a new user is created.
	 * @param array  $insert_array An array of additional fields to be passed to wp_insert_user() when creating a new user. Use this to set the user's role, the default is NEWSPACK_NETWORK_READER_ROLE.
	 * @return WP_User|WP_Error
	 */
	public static function get_or_create_user_by_email( $email, $remote_site_url, $remote_site_id, $insert_array = [] ) {

		$existing_user = get_user_by( 'email', $email );

		if ( $existing_user ) {
			return $existing_user;
		}

		$user_array = [
			'user_login'    => $email,
			'user_email'    => $email,
			'user_nicename' => $email,
			'user_pass'     => wp_generate_password(),
			'role'          => NEWSPACK_NETWORK_READER_ROLE,
		];

		$user_array = array_merge( $user_array, $insert_array );

		$user_id = wp_insert_user( $user_array );

		if ( is_wp_error( $user_id ) ) {
			Debugger::log( 'Error creating user: ' . $user_id->get_error_message() );
			return $user_id;
		}

		update_user_meta( $user_id, 'newspack_remote_site', $remote_site_url );
		update_user_meta( $user_id, 'newspack_remote_id', $remote_site_id );

		return get_user_by( 'id', $user_id );

	}

}
