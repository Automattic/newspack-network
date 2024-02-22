<?php
/**
 * Newspack Hub Membership_Plans Admin pages
 *
 * @package Newspack
 */

namespace Newspack_Network\Hub\Admin;

use Newspack_Network\Admin as Network_Admin;
use Newspack_Network\Debugger;

/**
 * Class to handle Woo Membership_Plans
 */
abstract class Membership_Plans {
	const PAGE_SLUG = 'newspack-network-membership-plans';
	const OPTIONS_CACHE_KEY_PLANS = 'newspack-network-membership-plans';

	/**
	 * Runs the initialization.
	 */
	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
	}

	/**
	 * Adds the submenu page
	 *
	 * @return void
	 */
	public static function add_menu() {
		Network_Admin::add_submenu_page( __( 'Membership Plans', 'newspack-network' ), self::PAGE_SLUG, [ __CLASS__, 'render' ] );
	}

	/**
	 * Renders the page
	 */
	public static function render() {
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'refetch' ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			delete_option( self::OPTIONS_CACHE_KEY_PLANS );
		}
		?>
			<div class="wrap newspack-network-membership-plans">
				<style>.newspack-network-membership-plans .tablenav{display: none;}</style>
				<h2><?php echo esc_html( __( 'Membership Plans from all Nodes', 'newspack-network' ) ); ?></h2>
				<?php
					$table = new Membership_Plans_Table();
					$table->prepare_items();
					$table->display();
				?>
				<h2><?php echo esc_html( __( 'Membership Plans on the Hub (this site)', 'newspack-network' ) ); ?></h2>
				<?php
					$table = new Membership_Plans_Table( true );
					$table->prepare_items();
					$table->display();
				?>
				<?php $plans_cache = self::get_membership_plans_from_cache(); ?>
				<?php if ( $plans_cache && isset( $plans_cache['last_updated'] ) ) : ?>
					<p>
					<?php
					printf(
						/* translators: last fetch date. */
						esc_html__( 'Plans from Nodes were last fetched on %s.', 'newspack-network' ),
						esc_html( gmdate( 'Y-m-d H:i', (int) $plans_cache['last_updated'] ) )
					);
					?>
					</p>
					<form method='post'>
						<input type="hidden" name="action" value="refetch">
						<input name='submit' type='submit' id='submit' class='button-secondary' value='<?php _e( 'Refetch Plans' ); ?>' />
					</form>
				<?php endif; ?>
			</div>
		<?php
	}

	/**
	 * Fetches Woo data from a Node site.
	 *
	 * @param \Newspack_Network\Node\Node $node The node.
	 * @param string                      $collection_endpoint The collection endpoint.
	 * @param string                      $collection_endpoint_id The collection endpoint ID.
	 */
	public static function fetch_collection_from_api( $node, $collection_endpoint, $collection_endpoint_id ) {
		$endpoint = sprintf( '%s/wp-json/%s', $node->get_url(), $collection_endpoint );
		$response = wp_remote_get( // phpcs:ignore
			$endpoint,
			[
				'headers' => $node->get_authorization_headers( 'get-woo-' . $collection_endpoint_id ),
			]
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			Debugger::log( 'API request for node\'s memberships failed' );
			return;
		}
		return json_decode( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Get membership plans from cache.
	 */
	private static function get_membership_plans_from_cache() {
		return get_option( self::OPTIONS_CACHE_KEY_PLANS, false );
	}

	/**
	 * Get membership plans from all nodes.
	 */
	public static function get_membershp_plans_from_nodes() {
		$plans_cache = self::get_membership_plans_from_cache();
		if ( $plans_cache && isset( $plans_cache['plans'] ) ) {
			return $plans_cache['plans'];
		}
		$membership_plans = [];
		$nodes = \Newspack_Network\Hub\Nodes::get_all_nodes();
		foreach ( $nodes as $node ) {
			$node_plans = self::fetch_collection_from_api( $node, 'wc/v2/memberships/plans', 'membership-plans' );
			foreach ( $node_plans as $plan ) {
				$network_pass_id = null;
				foreach ( $plan->meta_data as $meta ) {
					if ( $meta->key === \Newspack_Network\Woocommerce_Memberships\Admin::NETWORK_ID_META_KEY ) {
						$network_pass_id = $meta->value;
					}
				}
				$membership_plans[] = [
					'id'              => $plan->id,
					'node_url'        => $node->get_url(),
					'name'            => $plan->name,
					'network_pass_id' => $network_pass_id,
				];
			}
		}
		$plans_to_save = [
			'plans'        => $membership_plans,
			'last_updated' => time(),
		];
		update_option( self::OPTIONS_CACHE_KEY_PLANS, $plans_to_save );
		return $membership_plans;
	}

	/**
	 * Get local membership plans.
	 */
	public static function get_local_membership_plans() {
		$membership_plans = [];
		foreach ( wc_memberships_get_membership_plans() as $plan ) {
			$membership_plans[] = [
				'id'              => $plan->post->ID,
				'name'            => $plan->post->post_title,
				'network_pass_id' => get_post_meta( $plan->post->ID, \Newspack_Network\Woocommerce_Memberships\Admin::NETWORK_ID_META_KEY, true ),
			];
		}
		return $membership_plans;
	}
}
