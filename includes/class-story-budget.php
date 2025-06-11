<?php
/**
 * Story Budget Integration Class.
 *
 * @package Newspack
 */

namespace Newspack_Network;

use Newspack_Network\Hub\Node;
use Newspack_Network\Hub\Nodes;
use Newspack_Network\Node\Settings as Node_Settings;
use Newspack_Network\Content_Distribution;
use Newspack_Network\Content_Distribution\Outgoing_Post;

/**
 * Story Budget Integration Class.
 */
class Story_Budget {
	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ], 9 );
		add_filter( 'rest_allowed_cors_headers', [ __CLASS__, 'add_cors_headers' ] );
		add_filter( 'newspack_story_budget_fields', [ __CLASS__, 'add_fields' ] );
		add_filter( 'newspack_story_budget_story_metadata', [ __CLASS__, 'add_story_remote_metadata' ], 10, 2 );
		add_filter( 'newspack_story_budget_fields_props', [ __CLASS__, 'add_fields_props' ], 10, 2 );
	}

	/**
	 * Enqueue assets.
	 */
	public static function enqueue_assets() {
		wp_enqueue_script(
			'newspack-story-budget-network',
			plugins_url( '../dist/story-budget.js', __FILE__ ),
			[ 'wp-data-controls', 'wp-core-data', 'wp-components', 'wp-hooks' ],
			filemtime( __DIR__ . '/dist/story-budget.js' ),
			true
		);

		wp_localize_script(
			'newspack-story-budget-network',
			'newspackStoryBudgetNetwork',
			[
				'sites' => array_map(
					function( $site ) {
						return [
							'url'  => $site['value'],
							'name' => $site['label'],
						];
					},
					self::get_sites_list()
				),
			]
		);
	}

	/**
	 * Add CORS headers to the REST API.
	 *
	 * @param array $headers The headers.
	 *
	 * @return array The headers.
	 */
	public static function add_cors_headers( $headers ) {
		$headers[] = 'X-Network-Site-Url';
		return $headers;
	}

	/**
	 * Add fields to the Newspack Story Budget.
	 *
	 * @param array $fields The fields to add.
	 * @return array The fields to add.
	 */
	public static function add_fields( $fields ) {
		$sites_list = self::get_sites_list();
		if ( empty( $sites_list ) ) {
			return $fields;
		}

		$fields[] = [
			'description'        => __( 'The websites this story will be published on.', 'newspack-story-budget' ),
			'is_editable'        => true,
			'is_sortable'        => false,
			'is_multiple'        => true,
			'is_filterable'      => 'always',
			'name'               => __( 'Sites', 'newspack-story-budget' ),
			'show_in_table'      => true,
			'slug'               => 'network_sites',
			'type'               => 'text',
			'options'            => $sites_list,
			'default_order'      => 18,
			'get_value_callback' => [ __CLASS__, 'get_field_value' ],
		];

		return $fields;
	}

	/**
	 * Get the value of the field. If the post has been distributed, read the value from the distributed post, otherwise read it from the post meta.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array The value of the field.
	 */
	public static function get_field_value( $post_id ) {
		// If the post has been distributed, read the value from the distributed post.
		$distributed_post = Content_Distribution::get_distributed_post( $post_id );
		if ( $distributed_post ) {
			return $distributed_post->get_distribution();
		}

		$field = \Newspack_Story_Budget\Fields::get_field( 'network_sites' );
		return \get_post_meta( $post_id, $field->get_post_meta_name(), false );
	}

	/**
	 * Get the sites for the Newspack Story Budget.
	 *
	 * @return array The sites.
	 */
	private static function get_sites_list() {
		$sites = [];

		if ( Site_Role::is_hub() ) {
			$nodes = Nodes::get_all_nodes();
			foreach ( $nodes as $node ) {
				$sites[] = [
					'label' => $node->get_name(),
					'value' => $node->get_url(),
				];
			}
		}

		if ( Site_Role::is_node() ) {
			$hub_url = Node_Settings::get_hub_url();
			$sites[] = [
				// The hub name is not stored in the node, so we'll use a pretty URL as label.
				'label' => preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( $hub_url ) ),
				'value' => $hub_url,
			];
			$nodes_data = get_option( Node::HUB_NODES_SYNCED_OPTION, [] );
			foreach ( $nodes_data as $node_data ) {
				$sites[] = [
					'label' => $node_data['title'],
					'value' => $node_data['url'],
				];
			}
		}

		return $sites;
	}

	/**
	 * Add metadata to stories when accessing from a different node.
	 *
	 * @param array $metadata The story metadata.
	 * @param int   $story_id The story ID.
	 *
	 * @return array The story metadata.
	 */
	public static function add_story_remote_metadata( $metadata, $story_id ) {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return $metadata;
		}

		$request_site_url = filter_input( INPUT_SERVER, 'HTTP_X_NETWORK_SITE_URL', FILTER_VALIDATE_URL );

		// Only modify the metadata if this is a remote request.
		if ( ! $request_site_url ) {
			return $metadata;
		}

		$metadata['can_pull']  = true;
		$metadata['is_pulled'] = false;

		$outgoing_post = Content_Distribution::get_distributed_post( $story_id );
		if ( ! $outgoing_post ) {
			return $metadata;
		}

		$metadata['is_pulled'] = in_array( $request_site_url, $outgoing_post->get_distribution(), true );

		return $metadata;
	}

	/**
	 * Add fields props to the Newspack Story Budget.
	 *
	 * @param array $fields_props The fields props to add.
	 * @param int   $story_id     The story ID.
	 *
	 * @return array The fields props to add.
	 */
	public static function add_fields_props( $fields_props, $story_id ) {
		$outgoing_post = Content_Distribution::get_distributed_post( $story_id );
		if ( ! $outgoing_post ) {
			return $fields_props;
		}

		$distribution = $outgoing_post->get_distribution();

		$options = self::get_sites_list();

		foreach ( array_keys( $options ) as $option_key ) {
			if ( in_array( $options[ $option_key ]['value'], $distribution, true ) ) {
				$options[ $option_key ]['user_can_apply'] = false;
			}
		}

		$fields_props['network_sites'] = [
			'options' => $options,
		];

		return $fields_props;
	}
}
