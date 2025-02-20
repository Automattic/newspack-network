<?php
/**
 * Newspack Network Content Distribution API.
 *
 * @package Newspack
 */

namespace Newspack_Network\Content_Distribution;

use InvalidArgumentException;
use Newspack_Network\Content_Distribution as Content_Distribution_Class;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

/**
 * API Class.
 */
class API {
	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	/**
	 * Register the REST API routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'newspack-network/v1',
			'/content-distribution/distribute/(?P<post_id>\d+)',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'distribute' ],
				'args'                => [
					'urls'             => [
						'type'     => 'array',
						'required' => true,
						'items'    => [
							'type' => 'string',
						],
					],
					'status_on_create' => [
						'type'    => 'string',
						'enum'    => [ 'draft', 'publish' ],
						'default' => 'draft',
					],
				],
				'permission_callback' => function () {
					return current_user_can( Admin::CAPABILITY );
				},
			]
		);

		register_rest_route(
			'newspack-network/v1',
			'/content-distribution/unlink/(?P<post_id>\d+)',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ __CLASS__, 'toggle_unlink' ],
				'args'                => [
					'unlinked' => [
						'required' => true,
						'type'     => 'boolean',
					],
				],
				'permission_callback' => function () {
					return current_user_can( Admin::CAPABILITY );
				},
			]
		);
	}

	/**
	 * Toggle the unlinked status of an incoming post.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response|WP_Error The REST response or error.
	 */
	public static function toggle_unlink( $request ): WP_REST_Response|WP_Error {
		$post_id  = $request->get_param( 'post_id' );
		$unlinked = $request->get_param( 'unlinked' );

		try {
			$incoming_post = new Incoming_Post( $post_id );
			$incoming_post->set_unlinked( $unlinked );
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error( 'newspack_network_content_distribution_error', $e->getMessage(), [ 'status' => 400 ] );
		}

		return rest_ensure_response(
			[
				'post_id'  => $post_id,
				'unlinked' => ! $incoming_post->is_linked(),
				'status'   => 'success',
			]
		);
	}

	/**
	 * Distribute a post to the network.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response|WP_Error The REST response or error.
	 */
	public static function distribute( $request ) {
		$post_id          = $request->get_param( 'post_id' );
		$urls             = $request->get_param( 'urls' );
		$status_on_create = $request->get_param( 'status_on_create' );

		try {
			$outgoing_post = new Outgoing_Post( $post_id );
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error( 'newspack_network_content_distribution_error', $e->getMessage(), [ 'status' => 400 ] );
		}

		$current_distribution = $outgoing_post->get_distribution();

		$new_urls = array_diff( $urls, $current_distribution );

		// If distributing to new destinations, the post must be published.
		if ( ! empty( $new_urls ) && 'publish' !== get_post_status( $post_id ) ) {
			return new WP_Error( 'newspack_network_content_distribution_error', __( 'Post must be published to distribute.', 'newspack-network' ), [ 'status' => 400 ] );
		}

		$distribution = $outgoing_post->set_distribution( $urls );

		if ( is_wp_error( $distribution ) ) {
			return new WP_Error( 'newspack_network_content_distribution_error', $distribution->get_error_message(), [ 'status' => 400 ] );
		}

		Content_Distribution_Class::distribute_post( $outgoing_post, $status_on_create );

		return rest_ensure_response( $distribution );
	}
}
