<?php
/**
 * Newspack Network Users methods.
 *
 * @package Newspack
 */

namespace Newspack_Network\Utils;

use Newspack_Network\Debugger;

/**
 * Class to watch the user for updates and trigger events
 */
class Users {

	/**
	 * Gets an existing user or creates a user propagated from another site in the Network
	 *
	 * @param string $email The email of the user to look for. If no user is found, a new one will be created with this email.
	 * @param string $remote_site_url The URL of the remote site. Used only when a new user is created.
	 * @param string $remote_id The ID of the user in the remote site. Used only when a new user is created.
	 * @param array  $insert_array An array of additional fields to be passed to wp_insert_user() when creating a new user. Use this to set the user's role, the default is NEWSPACK_NETWORK_READER_ROLE.
	 * @return WP_User|WP_Error
	 */
	public static function get_or_create_user_by_email( $email, $remote_site_url, $remote_id, $insert_array = [] ) {

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
		update_user_meta( $user_id, 'newspack_remote_id', $remote_id );

		return get_user_by( 'id', $user_id );

	}

	/**
	 * Looks for avatar information and tries to sideload it to the user
	 *
	 * @param int   $user_id The user ID to add the avatar to.
	 * @param array $user_data The user data where we are going to look for the avatar. We are looking for the 'simple_local_avatar' meta key.
	 * @return bool True if the avatar was sideloaded, false otherwise.
	 */
	public static function maybe_sideload_avatar( $user_id, $user_data ) {

		Debugger::log( 'Attempting to sideload user avatar' );

		global $simple_local_avatars;
		if ( ! $simple_local_avatars || ! is_a( $simple_local_avatars, 'Simple_Local_Avatars' ) ) {
			Debugger::log( 'Simple Local Avatars plugin not active, skipping' );
			return false;
		}

		$avatar_meta_key = 'simple_local_avatar';

		if ( ! is_array( $user_data ) ) {
			$user_data = (array) $user_data;
		}

		if ( array_key_exists( $avatar_meta_key, $user_data ) && ! empty( $user_data[ $avatar_meta_key ]['full'] ) ) {
			Debugger::log( 'Updating user avatar' );
			$avatar_url = $user_data[ $avatar_meta_key ]['full'];

			if ( ! function_exists( 'media_sideload_image' ) ) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}

			$avatar_id = media_sideload_image( $avatar_url, 0, null, 'id' );
			if ( $avatar_id && is_int( $avatar_id ) ) {
				Debugger::log( 'Avatar successfully sideloaded with ID: ' . $avatar_id );
				$simple_local_avatars->assign_new_user_avatar( $avatar_id, $user_id );
				return true;
			}
		}

		Debugger::log( 'No avatar found in user data' );
		return false;
	}

}
