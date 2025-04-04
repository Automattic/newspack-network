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

		// Gather the network-synchronized membership plans.
		$membership_plans = \Newspack_Network\Hub\Admin\Membership_Plans::get_local_membership_plans();
		$nodes = \Newspack_Network\Hub\Nodes::get_all_nodes();
		foreach ( $nodes as $node ) {
			$node_plans = \Newspack_Network\Hub\Admin\Membership_Plans::fetch_collection_from_api( $node, 'wc/v2/memberships/plans', 'membership-plans' );
			if ( $node_plans === null ) {
				continue;
			}
			foreach ( $node_plans as $plan ) {
				$network_pass_id = null;
				foreach ( $plan->meta_data as $meta ) {
					if ( $meta->key === \Newspack_Network\Woocommerce_Memberships\Admin::NETWORK_ID_META_KEY ) {
						$network_pass_id = $meta->value;
					}
				}
				$membership_plans[] = [
					'id'                         => $plan->id,
					'site_url'                   => $node->get_url(),
					'name'                       => $plan->name,
					'network_pass_id'            => $network_pass_id,
					'active_memberships_count'   => $plan->active_memberships_count,
					'active_subscriptions_count' => $plan->active_subscriptions_count,
				];
			}
		}

		$membership_plans = array_filter(
			$membership_plans,
			function ( $plan ) {
				return ! empty( $plan['network_pass_id'] );
			}
		);

		// TODO: in batches, look up the status of each membership

		WP_CLI::success( 'Audit complete.' );
		WP_CLI::line( '' );
	}
}
