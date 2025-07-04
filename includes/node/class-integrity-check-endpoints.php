<?php
/**
 * Newspack Network Node integrity check endpoints.
 *
 * @package Newspack
 */

namespace Newspack_Network\Node;

use Newspack_Network\Woocommerce_Memberships\Admin as Memberships_Admin;

/**
 * Class that registers the integrity check endpoints for nodes
 */
class Integrity_Check_Endpoints {
	/**
	 * Runs the initialization.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	/**
	 * Register the routes for the integrity check endpoints.
	 */
	public static function register_routes() {
		register_rest_route(
			'newspack-network/v1',
			'/integrity-check/hash',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'handle_hash_request' ],
					'permission_callback' => function( $request ) {
						return \Newspack_Network\Rest_Authenticaton::verify_signature( $request, 'integrity-check', Settings::get_secret_key() );
					},
				],
			]
		);

		register_rest_route(
			'newspack-network/v1',
			'/integrity-check/memberships',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'handle_memberships_request' ],
					'permission_callback' => function( $request ) {
						return \Newspack_Network\Rest_Authenticaton::verify_signature( $request, 'integrity-check', Settings::get_secret_key() );
					},
				],
			]
		);

		register_rest_route(
			'newspack-network/v1',
			'/integrity-check/chunk-hash',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'handle_chunk_hash_request' ],
					'permission_callback' => function( $request ) {
						return \Newspack_Network\Rest_Authenticaton::verify_signature( $request, 'integrity-check', Settings::get_secret_key() );
					},
					'args'                => [
						'offset' => [
							'required' => true,
							'type'     => 'integer',
							'minimum'  => 0,
						],
						'limit'  => [
							'required' => true,
							'type'     => 'integer',
							'minimum'  => 1,
							'maximum'  => 5000,
						],
					],
				],
			]
		);

		register_rest_route(
			'newspack-network/v1',
			'/integrity-check/chunk-data',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'handle_chunk_data_request' ],
					'permission_callback' => function( $request ) {
						return \Newspack_Network\Rest_Authenticaton::verify_signature( $request, 'integrity-check', Settings::get_secret_key() );
					},
					'args'                => [
						'offset' => [
							'required' => true,
							'type'     => 'integer',
							'minimum'  => 0,
						],
						'limit'  => [
							'required' => true,
							'type'     => 'integer',
							'minimum'  => 1,
							'maximum'  => 5000,
						],
					],
				],
			]
		);

		register_rest_route(
			'newspack-network/v1',
			'/integrity-check/range-hash',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'handle_range_hash_request' ],
					'permission_callback' => function( $request ) {
						return \Newspack_Network\Rest_Authenticaton::verify_signature( $request, 'integrity-check', Settings::get_secret_key() );
					},
					'args'                => [
						'start' => [
							'required' => true,
							'type'     => 'string',
						],
						'end'   => [
							'required' => true,
							'type'     => 'string',
						],
					],
				],
			]
		);

		register_rest_route(
			'newspack-network/v1',
			'/integrity-check/range-data',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'handle_range_data_request' ],
					'permission_callback' => function( $request ) {
						return \Newspack_Network\Rest_Authenticaton::verify_signature( $request, 'integrity-check', Settings::get_secret_key() );
					},
					'args'                => [
						'start' => [
							'required' => true,
							'type'     => 'string',
						],
						'end'   => [
							'required' => true,
							'type'     => 'string',
						],
					],
				],
			]
		);
	}

	/**
	 * Handles the hash request.
	 *
	 * Returns hash for memberships within a specific email range, enabling range-based
	 * chunking that avoids the shifting problem of positional chunks.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 */
	public static function handle_hash_request( $request ) {
		$max_records = $request->get_param( 'max' );
		$membership_data = self::get_node_membership_data( $max_records );
		$hash = self::generate_hash( $membership_data );

		return rest_ensure_response(
			[
				'hash'  => $hash,
				'count' => count( $membership_data ),
			]
		);
	}

	/**
	 * Handles the memberships request.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 */
	public static function handle_memberships_request( $request ) {
		$membership_data = self::get_node_membership_data();

		return rest_ensure_response(
			[
				'memberships' => $membership_data,
				'count'       => count( $membership_data ),
			]
		);
	}

	/**
	 * Handles the chunk hash request.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 */
	public static function handle_chunk_hash_request( $request ) {
		$offset = intval( $request->get_param( 'offset' ) );
		$limit = intval( $request->get_param( 'limit' ) );

		$chunk_data = self::get_node_membership_data_chunk( $offset, $limit );
		$hash = self::generate_hash( $chunk_data );

		return rest_ensure_response(
			[
				'hash'   => $hash,
				'offset' => $offset,
				'limit'  => $limit,
				'count'  => count( $chunk_data ),
			]
		);
	}

	/**
	 * Handles the chunk data request.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 */
	public static function handle_chunk_data_request( $request ) {
		$offset = intval( $request->get_param( 'offset' ) );
		$limit = intval( $request->get_param( 'limit' ) );

		$chunk_data = self::get_node_membership_data_chunk( $offset, $limit );

		return rest_ensure_response(
			[
				'memberships' => $chunk_data,
				'offset'      => $offset,
				'limit'       => $limit,
				'count'       => count( $chunk_data ),
			]
		);
	}

	/**
	 * Handles the range hash request.
	 *
	 * Returns hash for memberships within a specific email range, enabling range-based
	 * chunking that avoids the shifting problem of positional chunks.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 */
	public static function handle_range_hash_request( $request ) {
		$start_email = $request->get_param( 'start' );
		$end_email = $request->get_param( 'end' );

		$range_data = self::get_node_membership_data_range( $start_email, $end_email );
		$hash = self::generate_hash( $range_data );

		return rest_ensure_response(
			[
				'hash'  => $hash,
				'start' => $start_email,
				'end'   => $end_email,
				'count' => count( $range_data ),
			]
		);
	}

	/**
	 * Handles the range data request.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 */
	public static function handle_range_data_request( $request ) {
		$start_email = $request->get_param( 'start' );
		$end_email = $request->get_param( 'end' );

		$range_data = self::get_node_membership_data_range( $start_email, $end_email );

		return rest_ensure_response(
			[
				'memberships' => $range_data,
				'start'       => $start_email,
				'end'         => $end_email,
				'count'       => count( $range_data ),
			]
		);
	}

	/**
	 * Get all membership data from the node
	 *
	 * @param int|null $max_records Maximum number of records to return (for testing).
	 * @return array Array of (email, status) pairs
	 */
	private static function get_node_membership_data( $max_records = null ) {
		if ( ! class_exists( 'WC_Memberships_User_Membership' ) ) {
			return [];
		}

		global $wpdb;

		// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users
		$query = "
			SELECT DISTINCT
				u.user_email,
				p.post_status as status
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->users} u ON p.post_author = u.ID
			INNER JOIN {$wpdb->postmeta} pm_network ON p.post_parent = pm_network.post_id AND pm_network.meta_key = %s
			WHERE p.post_type = 'wc_user_membership'
			AND pm_network.meta_value IS NOT NULL
			AND pm_network.meta_value != ''
			ORDER BY u.user_email ASC
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
				'email'  => $result->user_email,
				'status' => $result->status,
			];
		}

		return $membership_data;
	}

	/**
	 * Get a chunk of membership data from the node
	 *
	 * @param int $offset The offset to start from.
	 * @param int $limit The number of records to return.
	 * @return array Array of (email, status) pairs
	 */
	private static function get_node_membership_data_chunk( $offset, $limit ) {
		if ( ! class_exists( 'WC_Memberships_User_Membership' ) ) {
			return [];
		}

		global $wpdb;

		// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users
		$query = "
			SELECT DISTINCT
				u.user_email,
				p.post_status as status
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->users} u ON p.post_author = u.ID
			INNER JOIN {$wpdb->postmeta} pm_network ON p.post_parent = pm_network.post_id AND pm_network.meta_key = %s
			WHERE p.post_type = 'wc_user_membership'
			AND pm_network.meta_value IS NOT NULL
			AND pm_network.meta_value != ''
			ORDER BY u.user_email ASC
			LIMIT %d, %d
		";
		// phpcs:enable WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users,WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $wpdb->prepare( $query, Memberships_Admin::NETWORK_ID_META_KEY, $offset, $limit ) );

		$membership_data = [];
		foreach ( $results as $result ) {
			$membership_data[] = [
				'email'  => $result->user_email,
				'status' => $result->status,
			];
		}

		return $membership_data;
	}

	/**
	 * Get membership data from the node within an email range
	 *
	 * Filters memberships by email address range rather than positional offset.
	 * This enables range-based chunking that's resilient to data shifts when
	 * memberships are added/removed from the beginning or middle of the dataset.
	 *
	 * @param string $start_email The start email (inclusive).
	 * @param string $end_email The end email (inclusive).
	 * @return array Array of (email, status) pairs
	 */
	private static function get_node_membership_data_range( $start_email, $end_email ) {
		if ( ! class_exists( 'WC_Memberships_User_Membership' ) ) {
			return [];
		}

		global $wpdb;

		// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users
		$query = "
			SELECT DISTINCT
				u.user_email,
				p.post_status as status
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->users} u ON p.post_author = u.ID
			INNER JOIN {$wpdb->postmeta} pm_network ON p.post_parent = pm_network.post_id AND pm_network.meta_key = %s
			WHERE p.post_type = 'wc_user_membership'
			AND pm_network.meta_value IS NOT NULL
			AND pm_network.meta_value != ''
			AND u.user_email >= %s
			AND u.user_email <= %s
			ORDER BY u.user_email ASC
		";
		// phpcs:enable WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users,WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $wpdb->prepare( $query, Memberships_Admin::NETWORK_ID_META_KEY, $start_email, $end_email ) );

		$membership_data = [];
		foreach ( $results as $result ) {
			$membership_data[] = [
				'email'  => $result->user_email,
				'status' => $result->status,
			];
		}

		return $membership_data;
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
			$hash_string .= $item['email'] . ':' . $item['status'] . "\n";
		}

		return hash( 'sha256', $hash_string );
	}
}
