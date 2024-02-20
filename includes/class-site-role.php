<?php
/**
 * Newspack Network Site Role handling.
 *
 * @package Newspack
 */

namespace Newspack_Network;

/**
 * Class to handle the Site Role option
 */
class Site_Role {

	/**
	 * The option name where the role is stored
	 *
	 * @var string
	 */
	const OPTION_NAME = 'newspack_network_site_role';

	/**
	 * Gets the site role
	 *
	 * @return ?string
	 */
	public static function get() {
		return get_option( self::OPTION_NAME );
	}

	/**
	 * Checks if the site role is "hub"
	 *
	 * @return boolean
	 */
	public static function is_hub() {
		return 'hub' === self::get();
	}

	/**
	 * Checks if the site role is "node"
	 *
	 * @return boolean
	 */
	public static function is_node() {
		return 'node' === self::get();
	}

	/**
	 * Validates a value to be used as the site role
	 *
	 * @param string $role The new value to be used as the site role.
	 * @return string|bool
	 */
	public static function sanitize( $role ) {
		return in_array( $role, [ 'hub', 'node' ], true ) ? $role : false;
	}
}
