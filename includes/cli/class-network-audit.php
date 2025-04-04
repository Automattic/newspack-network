<?php
/**
 * Network Audit scripts.
 *
 * @package Newspack
 */

namespace Newspack_Network;

use WP_CLI;
use Newspack_Network\Node\Pulling;

/**
 * Network Audit class.
 */
class Network_Audit {
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
			WP_CLI::add_command( 'newspack-network network-audit', [ __CLASS__, 'network_audit' ] );
		}
	}

	/**
	 * Syncs all data, pulling all events from the Hub.
	 *
	 * @param array $args Indexed array of args.
	 * @param array $assoc_args Associative array of args.
	 * @return void
	 *
	 * ## EXAMPLES
	 *
	 *     wp newspack-network network-audit
	 *
	 * @when after_wp_load
	 */
	public static function network_audit( array $args, array $assoc_args ) {
		WP_CLI::line( '' );
		if ( ! Site_Role::is_hub() ) {
			WP_CLI::error( 'This command can only be run on the Hub site.' );
		}
		WP_CLI::success( 'Audit complete.' );
		WP_CLI::line( '' );
	}
}
