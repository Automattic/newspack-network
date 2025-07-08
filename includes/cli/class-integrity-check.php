<?php
/**
 * Integrity Check CLI command.
 *
 * @package Newspack
 */

namespace Newspack_Network\CLI;

use Newspack_Network\Site_Role;
use Newspack_Network\Hub\Nodes;
use Newspack_Network\Woocommerce_Memberships\Admin as Memberships_Admin;
use WP_CLI;

/**
 * Integrity Check CLI command class.
 */
class Integrity_Check {

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
			WP_CLI::add_command( 'newspack-network integrity-check', [ __CLASS__, 'integrity_check' ] );
		}
	}

	/**
	 * Performs an integrity check on network membership data.
	 *
	 * ## OPTIONS
	 *
	 * [--verbose]
	 * : Output verbose information during the check.
	 *
	 * [--fix-discrepancies]
	 * : Attempt to fix discrepancies found during the check.
	 *
	 * [--chunk-size=<size>]
	 * : Maximum number of memberships to compare in each chunk (default: 1000).
	 *
	 * [--max=<count>]
	 * : Maximum number of memberships to process (for testing only - do not use in production).
	 *
	 * ## EXAMPLES
	 *
	 *     wp newspack-network integrity-check
	 *     wp newspack-network integrity-check --verbose
	 *     wp newspack-network integrity-check --chunk-size=500
	 *     wp newspack-network integrity-check --max=50 --verbose
	 *
	 * @param array $args The command arguments.
	 * @param array $assoc_args The command options.
	 * @return void
	 */
	public static function integrity_check( $args, $assoc_args ) { // phpcs:ignore Generic.NamingConventions.ConstructorName.OldStyle
		$verbose = isset( $assoc_args['verbose'] ) ? true : false;
		$fix_discrepancies = isset( $assoc_args['fix-discrepancies'] ) ? true : false;
		$chunk_size = isset( $assoc_args['chunk-size'] ) ? intval( $assoc_args['chunk-size'] ) : 1000;
		$max_records = isset( $assoc_args['max'] ) ? intval( $assoc_args['max'] ) : null;

		if ( ! Site_Role::is_hub() ) {
			WP_CLI::error( 'This command can only be run on a Hub site.' );
		}

		if ( $max_records ) {
			WP_CLI::warning( sprintf( 'Using --max=%d for testing. Do not use --max in production as it may produce false positives.', $max_records ) );
		}

		WP_CLI::line( 'Starting integrity check for network membership data...' );
		WP_CLI::line( '' );

		// Step 1: Get hub's membership data and generate hash.
		$hub_data = self::get_hub_membership_data( $max_records );
		$hub_hash = self::generate_hash( $hub_data );

		if ( $verbose ) {
			WP_CLI::line( sprintf( '%d memberships found on the hub', count( $hub_data ) ) );
			WP_CLI::line( sprintf( 'Hub hash: %s', $hub_hash ) );
			WP_CLI::line( '' );
		}

		// Step 2: Get node data and compare hashes.
		$nodes = Nodes::get_all_nodes();
		$discrepancies = [];

		foreach ( $nodes as $node ) {
			$node_hash = self::get_node_hash( $node, $max_records );

			if ( $verbose ) {
				WP_CLI::line( sprintf( 'Node %s hash: %s', $node->get_url(), $node_hash ) );
			}

			if ( $hub_hash !== $node_hash ) {
				$discrepancies[] = $node;
				WP_CLI::warning( sprintf( 'Hash mismatch detected for node: %s', $node->get_url() ) );
			} else {
				WP_CLI::success( sprintf( 'Hash match for node: %s', $node->get_url() ) );
			}
		}

		WP_CLI::line( '' );

		if ( empty( $discrepancies ) ) {
			WP_CLI::success( 'All nodes are in sync with the hub!' );
			return;
		}

		WP_CLI::warning( sprintf( 'Found %d nodes with discrepancies', count( $discrepancies ) ) );

		// Step 3: Collect discrepancies from all nodes into a consolidated table.
		$all_discrepancies = [];
		$node_columns = [ 'email', 'network_id', 'hub_status' ];
		
		foreach ( $discrepancies as $node ) {
			WP_CLI::line( sprintf( 'Analyzing discrepancies for node: %s', $node->get_url() ) );
			
			$node_url = $node->get_url();
			$node_name = str_replace( [ 'https://www.', 'https://', 'http://www.', 'http://' ], '', $node_url );
			$node_columns[] = $node_name;

			$specific_discrepancies = self::find_discrepancies_chunked( $hub_data, $node, $chunk_size, $verbose, $max_records );

			// Process discrepancies for this node.
			foreach ( $specific_discrepancies as $discrepancy ) {
				$key = $discrepancy['email'] . '::' . $discrepancy['network_id'];
				
				if ( ! isset( $all_discrepancies[ $key ] ) ) {
					$all_discrepancies[ $key ] = [
						'email'      => $discrepancy['email'],
						'network_id' => $discrepancy['network_id'],
						'hub_status' => $discrepancy['hub_status'],
					];
				}
				
				$all_discrepancies[ $key ][ $node_name ] = $discrepancy['node_status'];
			}
		}

		// Display consolidated table if there are any discrepancies.
		if ( ! empty( $all_discrepancies ) ) {
			WP_CLI::line( '' );
			WP_CLI::line( sprintf( 'Found %d total discrepancies:', count( $all_discrepancies ) ) );
			WP_CLI::line( '' );

			// Prepare table data with node columns.
			$table_data = [];
			foreach ( $all_discrepancies as $discrepancy ) {
				// Fill in missing node statuses with empty string.
				foreach ( $node_columns as $column ) {
					if ( ! isset( $discrepancy[ $column ] ) && ! in_array( $column, [ 'email', 'network_id', 'hub_status' ] ) ) {
						$discrepancy[ $column ] = '';
					}
				}
				$table_data[] = $discrepancy;
			}

			// Display as table using WP-CLI's table formatter.
			WP_CLI\Utils\format_items( 'table', $table_data, $node_columns );
		}

		if ( $fix_discrepancies ) {
			WP_CLI::line( 'Fix discrepancies functionality would be implemented here.' );
		}
	}

	/**
	 * Get all membership data from the hub
	 *
	 * @param int|null $max_records Maximum number of records to return (for testing).
	 * @return array Array of (email, status) pairs
	 */
	private static function get_hub_membership_data( $max_records = null ) {
		if ( ! class_exists( 'WC_Memberships_User_Membership' ) ) {
			WP_CLI::error( 'WooCommerce Memberships plugin is not active.' );
		}

		global $wpdb;

		// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users
		$query = "
			SELECT DISTINCT
				u.user_email,
				p.post_status as status,
				pm_network.meta_value as network_id
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->users} u ON p.post_author = u.ID
			INNER JOIN {$wpdb->postmeta} pm_network ON p.post_parent = pm_network.post_id AND pm_network.meta_key = %s
			WHERE p.post_type = 'wc_user_membership'
			AND pm_network.meta_value IS NOT NULL
			AND pm_network.meta_value != ''
			ORDER BY LOWER(u.user_email) ASC
		";
		// phpcs:enable WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users

		if ( $max_records ) {
			$query .= $wpdb->prepare( ' LIMIT %d', $max_records );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users,WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $wpdb->prepare( $query, Memberships_Admin::NETWORK_ID_META_KEY ) );

		$membership_data = [];
		foreach ( $results as $result ) {
			$membership_data[] = [
				'email'      => strtolower( $result->user_email ),
				'status'     => $result->status,
				'network_id' => $result->network_id,
			];
		}

		return $membership_data;
	}

	/**
	 * Get membership data from a node via REST API
	 *
	 * @param \Newspack_Network\Node\Node $node The node to query.
	 * @return array Array of (email, status) pairs
	 */
	private static function get_node_membership_data( $node ) {
		$endpoint = sprintf( '%s/wp-json/newspack-network/v1/integrity-check/memberships', $node->get_url() );
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => $node->get_authorization_headers( 'integrity-check' ),
				'timeout' => 60, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			WP_CLI::error( sprintf( 'Failed to get membership data from node: %s', $node->get_url() ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return $data['memberships'] ?? [];
	}

	/**
	 * Get hash from a node via REST API
	 *
	 * @param \Newspack_Network\Node\Node $node The node to query.
	 * @param int|null                    $max_records Maximum number of records to include in hash.
	 * @return string The hash from the node
	 */
	private static function get_node_hash( $node, $max_records = null ) {
		$endpoint = sprintf( '%s/wp-json/newspack-network/v1/integrity-check/hash', $node->get_url() );

		if ( $max_records ) {
			$endpoint = add_query_arg( [ 'max' => $max_records ], $endpoint );
		}

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => $node->get_authorization_headers( 'integrity-check' ),
				'timeout' => 60, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			WP_CLI::error( sprintf( 'Failed to get hash from node: %s', $node->get_url() ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return $data['hash'] ?? '';
	}

	/**
	 * Get chunk hash from a node via REST API
	 *
	 * @param \Newspack_Network\Node\Node $node The node to query.
	 * @param int                         $offset The offset for the chunk.
	 * @param int                         $limit The limit for the chunk.
	 * @return string The chunk hash from the node
	 */
	private static function get_node_chunk_hash( $node, $offset, $limit ) {
		$endpoint = sprintf( '%s/wp-json/newspack-network/v1/integrity-check/chunk-hash', $node->get_url() );
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
		$response = wp_remote_get(
			add_query_arg(
				[
					'offset' => $offset,
					'limit'  => $limit,
				],
				$endpoint
			),
			[
				'headers' => $node->get_authorization_headers( 'integrity-check' ),
				'timeout' => 60, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			WP_CLI::error( sprintf( 'Failed to get chunk hash from node: %s', $node->get_url() ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return $data['hash'] ?? '';
	}

	/**
	 * Get chunk data from a node via REST API
	 *
	 * @param \Newspack_Network\Node\Node $node The node to query.
	 * @param int                         $offset The offset for the chunk.
	 * @param int                         $limit The limit for the chunk.
	 * @return array The chunk data from the node
	 */
	private static function get_node_chunk_data( $node, $offset, $limit ) {
		$endpoint = sprintf( '%s/wp-json/newspack-network/v1/integrity-check/chunk-data', $node->get_url() );
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
		$response = wp_remote_get(
			add_query_arg(
				[
					'offset' => $offset,
					'limit'  => $limit,
				],
				$endpoint
			),
			[
				'headers' => $node->get_authorization_headers( 'integrity-check' ),
				'timeout' => 60, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			WP_CLI::error( sprintf( 'Failed to get chunk data from node: %s', $node->get_url() ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return $data['memberships'] ?? [];
	}

	/**
	 * Generate a hash from membership data
	 *
	 * @param array $data Array of (email, status) pairs.
	 * @return string SHA-256 hash
	 */
	private static function generate_hash( $data ) {
		if ( empty( $data ) ) {
			return '';
		}

		// Create a string representation of the data for hashing.
		$hash_string = '';
		foreach ( $data as $item ) {
			$hash_string .= $item['email'] . ':' . $item['status'] . ':' . $item['network_id'] . "\n";
		}

		return hash( 'sha256', $hash_string );
	}

	/**
	 * Find specific discrepancies between hub and node data using range-based chunked approach
	 *
	 * Uses email address ranges instead of positional offsets to avoid the "shifting problem":
	 * If hub has [A,B,C,D] and node has [B,C,D,E] (A missing), positional chunks would ALL
	 * mismatch due to shifting. Range-based chunks (A-B, C-D) only show mismatch in affected range.
	 *
	 * @param array                       $hub_data Hub membership data.
	 * @param \Newspack_Network\Node\Node $node The node to compare with.
	 * @param int                         $chunk_size Maximum number of memberships per chunk.
	 * @param bool                        $verbose Whether to output verbose information.
	 * @param int|null                    $max_records Maximum number of records to process (for testing).
	 * @return array Array of specific discrepancies
	 */
	private static function find_discrepancies_chunked( $hub_data, $node, $chunk_size, $verbose = false, $max_records = null ) {
		$total_hub_memberships = count( $hub_data );

		// Create email ranges based on actual data distribution.
		$email_ranges = self::create_email_ranges( $hub_data, $chunk_size );
		$num_chunks = count( $email_ranges );
		$all_discrepancies = [];

		if ( $verbose ) {
			WP_CLI::line( sprintf( 'Checking %d memberships in %d range-based chunks (target size: %d)', $total_hub_memberships, $num_chunks, $chunk_size ) );
		}

		foreach ( $email_ranges as $chunk_index => $range ) {
			// Get hub chunk data for this range.
			$hub_chunk = self::filter_data_by_range( $hub_data, $range['start'], $range['end'] );

			// Generate hash for this chunk from hub data.
			$hub_chunk_hash = self::generate_hash( $hub_chunk );

			// Get corresponding chunk hash from node using range.
			$node_chunk_hash = self::get_node_range_hash( $node, $range['start'], $range['end'], $max_records );

			if ( $verbose ) {
				WP_CLI::line(
					sprintf(
						'  Chunk %d (%s to %s): Hub=%s, Node=%s (%d emails)',
						$chunk_index + 1,
						$range['start'],
						$range['end'],
						substr( $hub_chunk_hash, 0, 8 ),
						substr( $node_chunk_hash, 0, 8 ),
						count( $hub_chunk )
					)
				);
			}

			// If chunk hashes match, skip this chunk.
			if ( $hub_chunk_hash === $node_chunk_hash ) {
				if ( $verbose ) {
					WP_CLI::line( sprintf( '    ✓ Chunk %d matches', $chunk_index + 1 ) );
				}
				continue;
			}

			// Chunk hashes don't match - get detailed data for this range.
			if ( $verbose ) {
				WP_CLI::line( sprintf( '    ✗ Chunk %d mismatch - fetching detailed data', $chunk_index + 1 ) );
			}

			$node_chunk_data = self::get_node_range_data( $node, $range['start'], $range['end'], $max_records );
			$chunk_discrepancies = self::compare_chunk_data( $hub_chunk, $node_chunk_data );

			$all_discrepancies = array_merge( $all_discrepancies, $chunk_discrepancies );

			if ( $verbose ) {
				WP_CLI::line( sprintf( '    Found %d discrepancies in chunk %d', count( $chunk_discrepancies ), $chunk_index + 1 ) );
			}
		}

		return $all_discrepancies;
	}

	/**
	 * Compare two chunks of membership data and find discrepancies
	 *
	 * @param array $hub_chunk Hub chunk data.
	 * @param array $node_chunk Node chunk data.
	 * @return array Array of discrepancies
	 */
	private static function compare_chunk_data( $hub_chunk, $node_chunk ) {
		$discrepancies = [];

		// Create lookup arrays for faster comparison using (email, network_id) as key.
		$hub_lookup = [];
		foreach ( $hub_chunk as $item ) {
			$key = $item['email'] . '::' . $item['network_id'];
			$hub_lookup[ $key ] = $item;
		}

		$node_lookup = [];
		foreach ( $node_chunk as $item ) {
			$key = $item['email'] . '::' . $item['network_id'];
			$node_lookup[ $key ] = $item;
		}

		// Find discrepancies within this chunk.
		$all_keys = array_unique( array_merge( array_keys( $hub_lookup ), array_keys( $node_lookup ) ) );
		sort( $all_keys );

		foreach ( $all_keys as $key ) {
			$hub_item = $hub_lookup[ $key ] ?? null;
			$node_item = $node_lookup[ $key ] ?? null;
			
			$hub_status = $hub_item ? $hub_item['status'] : 'NOT_FOUND';
			$node_status = $node_item ? $node_item['status'] : 'NOT_FOUND';
			
			// Extract email and network_id for display.
			$parts = explode( '::', $key );
			$email = $parts[0];
			$network_id = $parts[1];

			if ( $hub_status !== $node_status ) {
				$discrepancies[] = [
					'email'       => $email,
					'network_id'  => $network_id,
					'hub_status'  => $hub_status,
					'node_status' => $node_status,
				];
			}
		}

		return $discrepancies;
	}

	/**
	 * Create email ranges based on actual data distribution
	 *
	 * Creates content-based ranges instead of positional chunks to solve the shifting problem.
	 * Each range covers a specific email address span (e.g., 'a@domain.com' to 'm@domain.com').
	 * When emails are added/removed, only the affected range needs re-checking, not all chunks.
	 *
	 * @param array $hub_data Hub membership data (sorted by email).
	 * @param int   $target_chunk_size Target number of emails per chunk.
	 * @return array Array of ranges with start and end email addresses
	 */
	private static function create_email_ranges( $hub_data, $target_chunk_size ) {
		if ( empty( $hub_data ) ) {
			return [];
		}

		$total_emails = count( $hub_data );
		$num_chunks = max( 1, ceil( $total_emails / $target_chunk_size ) );
		$actual_chunk_size = ceil( $total_emails / $num_chunks );

		$ranges = [];
		for ( $i = 0; $i < $num_chunks; $i++ ) {
			$start_index = $i * $actual_chunk_size;
			$end_index = min( ( $i + 1 ) * $actual_chunk_size - 1, $total_emails - 1 );

			$start_email = $hub_data[ $start_index ]['email'];
			$end_email = $hub_data[ $end_index ]['email'];

			// For the last chunk, extend to ensure we capture everything beyond the last email.
			$end_email_boundary = ( $i === $num_chunks - 1 ) ? 'zzzzz' : $end_email;

			$ranges[] = [
				'start' => $start_email,
				'end'   => $end_email_boundary,
			];
		}

		return $ranges;
	}

	/**
	 * Filter membership data by email range
	 *
	 * @param array  $data Membership data.
	 * @param string $start_email Start email (inclusive).
	 * @param string $end_email End email (inclusive).
	 * @return array Filtered data
	 */
	private static function filter_data_by_range( $data, $start_email, $end_email ) {
		$filtered = [];
		$start_email = strtolower( $start_email );
		$end_email = strtolower( $end_email );
		
		foreach ( $data as $item ) {
			$email = strtolower( $item['email'] );
			if ( $email >= $start_email && $email <= $end_email ) {
				$filtered[] = $item;
			}
		}
		return $filtered;
	}

	/**
	 * Get range hash from a node via REST API
	 *
	 * @param \Newspack_Network\Node\Node $node The node to query.
	 * @param string                      $start_email Start email for the range.
	 * @param string                      $end_email End email for the range.
	 * @param int|null                    $max_records Maximum number of records to include in hash (for testing).
	 * @return string The range hash from the node
	 */
	private static function get_node_range_hash( $node, $start_email, $end_email, $max_records = null ) {
		$endpoint = sprintf( '%s/wp-json/newspack-network/v1/integrity-check/range-hash', $node->get_url() );
		
		$query_args = [
			'start' => strtolower( $start_email ),
			'end'   => strtolower( $end_email ),
		];
		
		if ( $max_records ) {
			$query_args['max'] = $max_records;
		}

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
		$response = wp_remote_get(
			add_query_arg( $query_args, $endpoint ),
			[
				'headers' => $node->get_authorization_headers( 'integrity-check' ),
				'timeout' => 60, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			WP_CLI::error( sprintf( 'Failed to get range hash from node: %s', $node->get_url() ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return $data['hash'] ?? '';
	}

	/**
	 * Get range data from a node via REST API
	 *
	 * @param \Newspack_Network\Node\Node $node The node to query.
	 * @param string                      $start_email Start email for the range.
	 * @param string                      $end_email End email for the range.
	 * @param int|null                    $max_records Maximum number of records to return (for testing).
	 * @return array The range data from the node
	 */
	private static function get_node_range_data( $node, $start_email, $end_email, $max_records = null ) {
		$endpoint = sprintf( '%s/wp-json/newspack-network/v1/integrity-check/range-data', $node->get_url() );
		
		$query_args = [
			'start' => strtolower( $start_email ),
			'end'   => strtolower( $end_email ),
		];
		
		if ( $max_records ) {
			$query_args['max'] = $max_records;
		}

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
		$response = wp_remote_get(
			add_query_arg( $query_args, $endpoint ),
			[
				'headers' => $node->get_authorization_headers( 'integrity-check' ),
				'timeout' => 60, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			WP_CLI::error( sprintf( 'Failed to get range data from node: %s', $node->get_url() ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return $data['memberships'] ?? [];
	}
}
