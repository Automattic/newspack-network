<?php
/**
 * Reconcile Memberships CLI command.
 *
 * @package Newspack
 */

namespace Newspack_Network\CLI;

use Newspack_Network\Site_Role;
use Newspack_Network\Hub\Nodes;
use Newspack_Network\Integrity_Check_Utils;
use WP_CLI;

/**
 * Reconcile Memberships CLI command class.
 */
class Reconcile_Memberships {

	/**
	 * Initialize this class and register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_commands' ] );
	}

	/**
	 * Register the WP-CLI commands.
	 *
	 * @return void
	 */
	public static function register_commands() {
		if ( Site_Role::is_hub() && defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'newspack-network reconcile-memberships', [ __CLASS__, 'reconcile' ] );
		}
	}

	/**
	 * Detects and fixes membership discrepancies between the hub and all nodes.
	 *
	 * ## OPTIONS
	 *
	 * [--live]
	 * : Actually dispatch events to fix discrepancies. Without this flag, runs as a dry-run only.
	 *
	 * [--verbose]
	 * : Output detailed information during reconciliation.
	 *
	 * [--node=<url>]
	 * : Only reconcile with a specific node URL.
	 *
	 * ## EXAMPLES
	 *
	 *     wp newspack-network reconcile-memberships
	 *     wp newspack-network reconcile-memberships --live
	 *     wp newspack-network reconcile-memberships --verbose
	 *     wp newspack-network reconcile-memberships --node=https://example.com --live
	 *
	 * @param array $args       The command arguments.
	 * @param array $assoc_args The command options.
	 * @return void
	 */
	public static function reconcile( $args, $assoc_args ) {
		$is_live = isset( $assoc_args['live'] );
		$verbose = isset( $assoc_args['verbose'] );
		$node_filter = isset( $assoc_args['node'] ) ? $assoc_args['node'] : null;

		if ( $is_live ) {
			WP_CLI::warning( 'Running in LIVE mode – events will be dispatched to fix discrepancies.' );
		} else {
			WP_CLI::line( 'Running in DRY-RUN mode. Use --live to dispatch fix events.' );
		}
		WP_CLI::line( '' );

		// Step 1: Get hub membership data.
		$hub_data = Integrity_Check_Utils::get_membership_data();

		if ( $verbose ) {
			WP_CLI::line( sprintf( '%d memberships found on the hub.', count( $hub_data ) ) );
			WP_CLI::line( '' );
		}

		// Build lookup keyed by email::network_id for fast access.
		$hub_lookup = [];
		foreach ( $hub_data as $item ) {
			$key = $item['email'] . '::' . $item['network_id'];
			$hub_lookup[ $key ] = $item;
		}

		// Step 2: Get nodes to process.
		$all_nodes = Nodes::get_all_nodes();

		if ( $node_filter ) {
			$all_nodes = array_filter(
				$all_nodes,
				function( $node ) use ( $node_filter ) {
					return rtrim( $node->get_url(), '/' ) === rtrim( $node_filter, '/' );
				}
			);

			if ( empty( $all_nodes ) ) {
				WP_CLI::error( sprintf( 'No node found with URL: %s', $node_filter ) );
			}
		}

		if ( empty( $all_nodes ) ) {
			WP_CLI::warning( 'No nodes found.' );
			return;
		}

		// Step 3: Process each node.
		$summary_counts = [
			'missing_on_node' => 0,
			'missing_on_hub'  => 0,
			'status_mismatch' => 0,
			'push_to_node'    => 0,
			'skipped'         => 0,
		];

		foreach ( $all_nodes as $node ) {
			WP_CLI::line( sprintf( 'Processing node: %s', $node->get_url() ) );

			$node_memberships = self::get_node_membership_data( $node, $verbose );
			if ( null === $node_memberships ) {
				WP_CLI::warning( sprintf( 'Skipping node %s due to fetch error.', $node->get_url() ) );
				WP_CLI::line( '' );
				continue;
			}

			$node_managed_memberships = self::get_node_managed_memberships( $node, $verbose );
			if ( null === $node_managed_memberships ) {
				WP_CLI::warning( sprintf( 'Could not fetch managed memberships from %s – timestamp comparison will use hub as authoritative.', $node->get_url() ) );
				$node_managed_memberships = [];
			}

			if ( $verbose ) {
				WP_CLI::line( sprintf( '  %d memberships found on node.', count( $node_memberships ) ) );
				WP_CLI::line( sprintf( '  %d managed memberships found on node.', count( $node_managed_memberships ) ) );
			}

			$discrepancies = self::classify_discrepancies( $hub_lookup, $node_memberships, $node_managed_memberships );

			if ( empty( $discrepancies ) ) {
				WP_CLI::success( sprintf( 'Node %s is in sync with the hub.', $node->get_url() ) );
				WP_CLI::line( '' );
				continue;
			}

			WP_CLI::line( sprintf( '  Found %d discrepancies:', count( $discrepancies ) ) );
			WP_CLI::line( '' );

			// Display table of planned actions.
			$table_columns = [ 'email', 'network_id', 'type', 'hub_status', 'node_status', 'action' ];
			WP_CLI\Utils\format_items( 'table', $discrepancies, $table_columns );
			WP_CLI::line( '' );

			// Accumulate summary counts.
			foreach ( $discrepancies as $discrepancy ) {
				$summary_counts[ $discrepancy['type'] ]++;
				if ( 'push_to_node' === $discrepancy['action'] ) {
					$summary_counts['push_to_node']++;
				} else {
					$summary_counts['skipped']++;
				}
			}

			// In live mode, dispatch events for push_to_node actions.
			if ( $is_live ) {
				$dispatched = 0;
				foreach ( $discrepancies as $discrepancy ) {
					if ( 'push_to_node' !== $discrepancy['action'] ) {
						continue;
					}
					$key = $discrepancy['email'] . '::' . $discrepancy['network_id'];
					$hub_item = $hub_lookup[ $key ] ?? null;
					if ( $hub_item ) {
						self::dispatch_to_node( $hub_item );
						$dispatched++;
					}
				}
				WP_CLI::success( sprintf( 'Dispatched %d event(s) for node %s.', $dispatched, $node->get_url() ) );
			}

			WP_CLI::line( '' );
		}

		// Print summary.
		WP_CLI::line( '=== Summary ===' );
		WP_CLI::line( sprintf( 'Missing on node:  %d', $summary_counts['missing_on_node'] ) );
		WP_CLI::line( sprintf( 'Missing on hub:   %d', $summary_counts['missing_on_hub'] ) );
		WP_CLI::line( sprintf( 'Status mismatch:  %d', $summary_counts['status_mismatch'] ) );
		WP_CLI::line( sprintf( 'Actions – push to node: %d', $summary_counts['push_to_node'] ) );
		WP_CLI::line( sprintf( 'Actions – skipped:      %d', $summary_counts['skipped'] ) );

		if ( ! $is_live && 0 < $summary_counts['push_to_node'] ) {
			WP_CLI::line( '' );
			WP_CLI::line( sprintf( 'Run with --live to dispatch %d fix event(s).', $summary_counts['push_to_node'] ) );
		}
	}

	/**
	 * Fetch all membership data from a node via the /integrity-check/memberships endpoint.
	 *
	 * @param \Newspack_Network\Hub\Node $node    The node to query.
	 * @param bool                       $verbose Whether to output verbose information.
	 * @return array|null Array of membership items, or null on error.
	 */
	private static function get_node_membership_data( $node, $verbose = false ) {
		$endpoint = sprintf( '%s/wp-json/newspack-network/v1/integrity-check/memberships', $node->get_url() );
		$endpoint = add_query_arg( [ '_t' => time() ], $endpoint ); // Cache-busting parameter.

		if ( $verbose ) {
			WP_CLI::line( sprintf( '  Fetching memberships from: %s', $endpoint ) );
		}

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => $node->get_authorization_headers( 'integrity-check' ),
				'timeout' => 60, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
			]
		);

		if ( is_wp_error( $response ) ) {
			WP_CLI::warning( sprintf( 'Failed to fetch memberships from node %s: %s', $node->get_url(), $response->get_error_message() ) );
			return null;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			WP_CLI::warning( sprintf( 'Non-200 response (%d) fetching memberships from node %s.', wp_remote_retrieve_response_code( $response ), $node->get_url() ) );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return $data['memberships'] ?? [];
	}

	/**
	 * Fetch managed membership data from a node via the /integrity-check/managed-memberships endpoint.
	 *
	 * Returns items that include post_modified for timestamp comparison.
	 *
	 * @param \Newspack_Network\Hub\Node $node    The node to query.
	 * @param bool                       $verbose Whether to output verbose information.
	 * @return array|null Array keyed by email::network_id, or null on error.
	 */
	private static function get_node_managed_memberships( $node, $verbose = false ) {
		$endpoint = sprintf( '%s/wp-json/newspack-network/v1/integrity-check/managed-memberships', $node->get_url() );
		$endpoint = add_query_arg( [ '_t' => time() ], $endpoint ); // Cache-busting parameter.

		if ( $verbose ) {
			WP_CLI::line( sprintf( '  Fetching managed memberships from: %s', $endpoint ) );
		}

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => $node->get_authorization_headers( 'integrity-check' ),
				'timeout' => 60, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
			]
		);

		if ( is_wp_error( $response ) ) {
			WP_CLI::warning( sprintf( 'Failed to fetch managed memberships from node %s: %s', $node->get_url(), $response->get_error_message() ) );
			return null;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			WP_CLI::warning( sprintf( 'Non-200 response (%d) fetching managed memberships from node %s.', wp_remote_retrieve_response_code( $response ), $node->get_url() ) );
			return null;
		}

		$data        = json_decode( wp_remote_retrieve_body( $response ), true );
		$memberships = $data['memberships'] ?? [];

		// Build lookup keyed by email::network_id.
		$lookup = [];
		foreach ( $memberships as $item ) {
			if ( empty( $item['network_id'] ) ) {
				continue;
			}
			$key            = $item['email'] . '::' . $item['network_id'];
			$lookup[ $key ] = $item;
		}

		return $lookup;
	}

	/**
	 * Classify discrepancies between hub and node data.
	 *
	 * Compares hub and node membership data keyed by email::network_id and returns
	 * a list of discrepancy records describing the type and recommended action.
	 *
	 * Discrepancy types:
	 *   - missing_on_node: Hub has the membership but the node does not → push_to_node.
	 *   - missing_on_hub:  Node has the membership but the hub does not → skip.
	 *   - status_mismatch: Both have it with different statuses.
	 *       Hub timestamp newer (or node timestamp unavailable) → push_to_node.
	 *       Node timestamp newer → skip.
	 *
	 * @param array $hub_lookup           Hub memberships keyed by email::network_id.
	 * @param array $node_memberships     Raw node membership array (email, status, network_id).
	 * @param array $node_managed_lookup  Node managed memberships keyed by email::network_id (includes post_modified).
	 * @return array Array of discrepancy records.
	 */
	private static function classify_discrepancies( $hub_lookup, $node_memberships, $node_managed_lookup ) {
		// Build node lookup keyed by email::network_id.
		$node_lookup = [];
		foreach ( $node_memberships as $item ) {
			$key                 = $item['email'] . '::' . $item['network_id'];
			$node_lookup[ $key ] = $item;
		}

		$all_keys     = array_unique( array_merge( array_keys( $hub_lookup ), array_keys( $node_lookup ) ) );
		$discrepancies = [];

		foreach ( $all_keys as $key ) {
			$hub_item  = $hub_lookup[ $key ] ?? null;
			$node_item = $node_lookup[ $key ] ?? null;

			$parts      = explode( '::', $key, 2 );
			$email      = $parts[0];
			$network_id = $parts[1] ?? '';

			$hub_status  = $hub_item ? $hub_item['status'] : '';
			$node_status = $node_item ? $node_item['status'] : '';

			if ( null === $hub_item ) {
				// Node has it, hub does not.
				$discrepancies[] = [
					'email'       => $email,
					'network_id'  => $network_id,
					'type'        => 'missing_on_hub',
					'hub_status'  => '',
					'node_status' => $node_status,
					'action'      => 'skip',
				];
				continue;
			}

			if ( null === $node_item ) {
				// Hub has it, node does not.
				$discrepancies[] = [
					'email'       => $email,
					'network_id'  => $network_id,
					'type'        => 'missing_on_node',
					'hub_status'  => $hub_status,
					'node_status' => '',
					'action'      => 'push_to_node',
				];
				continue;
			}

			if ( $hub_status === $node_status ) {
				// Statuses match – no discrepancy.
				continue;
			}

			// Status mismatch: compare timestamps to decide direction.
			$hub_modified  = $hub_item['post_modified'] ?? '';
			$node_modified = '';

			if ( isset( $node_managed_lookup[ $key ] ) ) {
				$node_modified = $node_managed_lookup[ $key ]['post_modified'] ?? '';
			}

			// Hub is authoritative when node timestamp is unavailable or hub is newer.
			if ( empty( $node_modified ) || $hub_modified >= $node_modified ) {
				$action = 'push_to_node';
			} else {
				// Node has fresher data – log and skip.
				if ( defined( 'WP_CLI' ) && WP_CLI ) {
					WP_CLI::warning(
						sprintf(
							'Status mismatch for %s (plan %s): hub=%s (%s), node=%s (%s) – node is newer, skipping.',
							$email,
							$network_id,
							$hub_status,
							$hub_modified,
							$node_status,
							$node_modified
						)
					);
				}
				$action = 'skip';
			}

			$discrepancies[] = [
				'email'       => $email,
				'network_id'  => $network_id,
				'type'        => 'status_mismatch',
				'hub_status'  => $hub_status,
				'node_status' => $node_status,
				'action'      => $action,
			];
		}

		return $discrepancies;
	}

	/**
	 * Dispatch a membership_updated event for the given hub membership item.
	 *
	 * Creates an event and persists it to the hub's event log. Nodes will pull
	 * it during their next sync cycle and update their local membership accordingly.
	 *
	 * @param array $hub_item A single hub membership record (email, status, network_id, membership_id).
	 * @return void
	 */
	private static function dispatch_to_node( $hub_item ) {
		$event_data = [
			'email'           => $hub_item['email'],
			'user_id'         => 0,
			'plan_network_id' => $hub_item['network_id'],
			'membership_id'   => $hub_item['membership_id'],
			'new_status'      => str_replace( 'wcm-', '', $hub_item['status'] ),
		];

		$event = new \Newspack_Network\Incoming_Events\Woocommerce_Membership_Updated(
			get_bloginfo( 'url' ),
			$event_data,
			time()
		);

		$event->process_in_hub();
	}
}
