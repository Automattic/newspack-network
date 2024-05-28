<?php
/**
 * Newspack Hub Subscriptions Admin pages
 *
 * @package Newspack
 */

namespace Newspack_Network\Hub\Admin;

use Newspack_Network\Admin as Network_Admin;
use Newspack_Network\Debugger;

/**
 * Class to handle Woo Subscriptions
 */
abstract class Subscriptions {
	const PAGE_SLUG = 'newspack-network-subscriptions';
	const OPTIONS_CACHE_KEY = 'newspack-network-subscriptions';
	const CRON_HOOK_NAME = 'newspack_network_fetch_subscriptions';

	/**
	 * Runs the initialization.
	 */
	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts_and_styles' ] );

		// Fetch subscriptions daily.
		if ( ! wp_next_scheduled( self::CRON_HOOK_NAME ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK_NAME );
		}
		add_action( self::CRON_HOOK_NAME, [ __CLASS__, 'refetch_subscriptions' ] );
	}

	/**
	 * Adds the submenu page
	 *
	 * @return void
	 */
	public static function add_menu() {
		Network_Admin::add_submenu_page( __( 'Subscriptions', 'newspack-network' ), self::PAGE_SLUG, [ __CLASS__, 'render' ] );
	}

	/**
	 * Renders the page
	 */
	public static function render() {
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'refetch' ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			delete_option( self::OPTIONS_CACHE_KEY );
		}
		$table = new Subscriptions_Table();
		$table->prepare_items();

		?>
			<div class="wrap newspack-network-subscriptions">
				<h2><?php echo esc_html( __( 'Subscriptions', 'newspack-network' ) ); ?></h2>
				<?php $local_cache = self::get_subscriptions_from_cache(); ?>
				<?php if ( $local_cache && isset( $local_cache['last_updated'] ) ) : ?>
					<form method='post' class='newspack-network-subscriptions__refetch-status'>
						<span>
							<?php
							printf(
								/* translators: last fetch date. */
								esc_html__( 'Subscriptions were last fetched on %s.', 'newspack-network' ),
								esc_html( gmdate( 'Y-m-d H:i', (int) $local_cache['last_updated'] ) )
							);
							?>
						</span>
						<input type="hidden" name="action" value="refetch">
						<input name='submit' type='submit' id='submit' class='button-link' value='<?php _e( 'Refetch Subscriptions' ); ?>' />
					</form>
				<?php endif; ?>
				<form method="get">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
					<?php
						$table->search_box( __( 'Search', 'newspack-network' ), 'search' );
						$table->display();
					?>
				</form>
			</div>
		<?php
	}

	/**
	 * Fetches Woo data from a Node site.
	 *
	 * @param \Newspack_Network\Node\Node $node The node.
	 * @param string                      $collection_endpoint The collection endpoint.
	 * @param string                      $collection_endpoint_id The collection endpoint ID.
	 * @param array                       $query_args The query args.
	 * @param array                       $results Results.
	 */
	public static function fetch_collection_from_api( $node, $collection_endpoint, $collection_endpoint_id, $query_args = [], $results = [] ) {
		$query_args = array_merge(
			[
				'page'     => 1,
				'per_page' => 100,
			],
			$query_args
		);
		$endpoint = add_query_arg( $query_args, sprintf( '%s/wp-json/%s', $node->get_url(), $collection_endpoint ) );
		$response = wp_remote_get( // phpcs:ignore
			$endpoint,
			[
				'headers' => $node->get_authorization_headers( 'get-woo-' . $collection_endpoint_id ),
				'timeout' => 60, // phpcs:ignore
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			Debugger::log( sprintf( 'API request for %s failed', $collection_endpoint ) );
			return [];
		}

		$new_results = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! is_array( $new_results ) ) {
			return [];
		}
		$results = array_merge( $new_results, $results );

		$total_pages = wp_remote_retrieve_header( $response, 'X-WP-TotalPages' );
		$has_more = $query_args['page'] < $total_pages;
		if ( $has_more ) {
			return self::fetch_collection_from_api(
				$node,
				$collection_endpoint,
				$collection_endpoint_id,
				array_merge( $query_args, [ 'page' => $query_args['page'] + 1 ] ),
				$results
			);
		} else {
			return $results;
		}
	}

	/**
	 * Get subscriptions from cache.
	 */
	private static function get_subscriptions_from_cache() {
		return get_option( self::OPTIONS_CACHE_KEY, false );
	}

	/**
	 * Get a mapping from node id to site URL.
	 */
	private static function get_site_id_to_site_url_mapping() {
		return array_reduce(
			\Newspack_Network\Hub\Nodes::get_all_nodes(),
			function( $acc, $node ) {
				$acc[ $node->get_id() ] = $node->get_url();
				return $acc;
			},
			[
				0 => get_site_url(),
			]
		);
	}

	/**
	 * Filter the subscriptions.
	 *
	 * @param array $subscriptions The subscriptions.
	 * @param array $args The arguments.
	 */
	private static function filter_subscriptions( $subscriptions, $args ) {
		$node_id = null;
		if ( isset( $args['node_id'] ) && is_numeric( $args['node_id'] ) ) {
			$node_id = intval( $args['node_id'] );
		}

		$site_id_mapping = self::get_site_id_to_site_url_mapping();
		$site_url = isset( $site_id_mapping[ $node_id ] ) ? $site_id_mapping[ $node_id ] : null;
		if ( $site_url ) {
			$subscriptions = array_filter(
				$subscriptions,
				function( $subscription ) use ( $site_url ) {
					return $subscription['site_url'] === $site_url;
				}
			);
		}
		return $subscriptions;
	}

	/**
	 * Get subscriptions from all nodes.
	 *
	 * @param array $args The arguments.
	 */
	public static function get_subscriptions_from_network( $args = [] ) {
		$local_cache = self::get_subscriptions_from_cache();
		if ( $local_cache && isset( $local_cache['subscriptions'] ) ) {
			$local_cache['subscriptions'] = self::filter_subscriptions( $local_cache['subscriptions'], $args );
			return $local_cache;
		}

		$subscriptions = self::get_local_subscriptions( $args );

		$nodes = \Newspack_Network\Hub\Nodes::get_all_nodes();
		foreach ( $nodes as $node ) {
			$node_subscriptions = self::fetch_collection_from_api( $node, 'wc/v3/subscriptions', 'subscriptions' );
			foreach ( $node_subscriptions as $index => $subscription ) {
				$subscriptions[] = [
					'id'                => $subscription->id,
					'customer_id'       => $subscription->customer_id,
					'status'            => $subscription->status,
					'email'             => $subscription->billing->email,
					'first_name'        => $subscription->billing->first_name,
					'last_name'         => $subscription->billing->last_name,
					'total'             => $subscription->total,
					'currency'          => $subscription->currency,
					'billing_interval'  => $subscription->billing_interval,
					'billing_period'    => $subscription->billing_period,
					'start_date'        => $subscription->start_date_gmt,
					'end_date'          => $subscription->end_date_gmt,
					'last_payment_date' => $subscription->last_payment_date_gmt,
					'site_url'          => $node->get_url(),
				];
			}
		}
		$subscriptions_data = [
			'subscriptions' => $subscriptions,
			'last_updated'  => time(),
		];
		update_option( self::OPTIONS_CACHE_KEY, $subscriptions_data );
		$subscriptions_data['subscriptions'] = self::filter_subscriptions( $subscriptions_data['subscriptions'], $args );
		return $subscriptions_data;
	}

	/**
	 * Get local subscriptions.
	 *
	 * @param array $args The arguments.
	 */
	public static function get_local_subscriptions( $args ) {
		$subscriptions = [];
		$args = array_merge(
			[
				'subscriptions_per_page' => -1,
			],
			$args
		);
		if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
			return [];
		}
		foreach ( wcs_get_subscriptions( $args ) as $subscription ) {
			$subscriptions[] = [
				'id'                => $subscription->get_id(),
				'customer_id'       => $subscription->get_user_id(),
				'status'            => $subscription->get_status(),
				'email'             => $subscription->get_billing_email(),
				'first_name'        => $subscription->get_billing_first_name(),
				'last_name'         => $subscription->get_billing_last_name(),
				'total'             => $subscription->get_total(),
				'currency'          => $subscription->get_currency(),
				'billing_interval'  => $subscription->get_billing_interval(),
				'billing_period'    => $subscription->get_billing_period(),
				'start_date'        => $subscription->get_date( 'start_date' ),
				'end_date'          => $subscription->get_date( 'end_date' ),
				'last_payment_date' => $subscription->get_date( 'last_order_date_created' ),
				'site_url'          => get_site_url(),
			];
		}
		return $subscriptions;
	}

	/**
	 * Enqueues scripts and styles.
	 *
	 * @param string $hook The current screen.
	 */
	public static function enqueue_scripts_and_styles( $hook ) {
		if ( 'newspack-network_page_newspack-network-subscriptions' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'newspack-network-subscriptions',
			plugins_url( 'css/subscriptions.css', __FILE__ ),
			[],
			filemtime( NEWSPACK_NETWORK_PLUGIN_DIR . '/includes/hub/admin/css/subscriptions.css' )
		);
	}

	/**
	 * Refetches subscriptions.
	 */
	public static function refetch_subscriptions() {
		delete_option( self::OPTIONS_CACHE_KEY );
		self::get_subscriptions_from_network();
	}
}
