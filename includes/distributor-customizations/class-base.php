<?php
/**
 * This files contains the global tweaks that are loaded by default in every site in the Network (both Hub and Nodes).
 *
 * @package newspack-network
 */

namespace Newspack_Network\Distributor_Customizations;

/**
 * General Distributor customizations.
 */
class Base {
	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_filter( 'dt_capabilities', [ __CLASS__, 'filter_distributor_menu_cap' ] );
		add_filter( 'dt_pull_capabilities', [ __CLASS__, 'filter_distributor_menu_cap' ] );

		add_filter( 'dt_push_post_args', [ __CLASS__, 'filter_push_post_args' ], 10, 2 );
		add_filter( 'dt_pull_post_args', [ __CLASS__, 'filter_pull_post_args' ], 10, 3 );
		add_filter( 'dt_subscription_post_args', [ __CLASS__, 'filter_subscription_post_args' ], 10, 2 );
		add_action( 'dt_process_subscription_attributes', [ __CLASS__, 'process_attributes' ], 10, 2 );
		add_action( 'dt_process_distributor_attributes', [ __CLASS__, 'process_attributes' ], 10, 2 );

		/**
		 * This is a workaround the bug fixed in https://github.com/10up/distributor/pull/1185
		 * Until that fix is released, we need to keep this workaround.
		 */
		add_action(
			'init',
			function() {
				wp_cache_delete( 'dt_media::{$post_id}', 'dt::post' );
			}
		);
	}

	/**
	 * Send primary category slug to the Node along with post update data.
	 *
	 * @param array   $post_body The post data to be sent to the Node.
	 * @param WP_Post $post The post object.
	 */
	private static function get_primary_category_slug( $post_body, $post ) {
		if ( class_exists( 'WPSEO_Primary_Term' ) ) {
			$primary_term = new \WPSEO_Primary_Term( 'category', $post->ID );
			$category_id  = $primary_term->get_primary_term();
			if ( $category_id ) {
				$category = get_term( $category_id );
				return $category->slug;
			}
		}
	}

	/**
	 * Process distributed post attributes after the distribution has completed.
	 *
	 * @param WP_Post         $post The post object.
	 * @param WP_REST_Request $request The request data.
	 */
	public static function process_attributes( $post, $request ) {
		// Fix the primary category.
		$primary_category_id   = get_post_meta( $post->ID, '_yoast_wpseo_primary_category', true );
		$primary_category_slug = get_post_meta( $post->ID, 'yoast_primary_category_slug', true );
		$hub_primary_category  = get_term( $primary_category_id );
		if ( ! $hub_primary_category ) {
			// Attempt to find a matching category on the Hub site by slug.
			$hub_primary_category = get_term_by( 'slug', $primary_category_slug, 'category' );
		}
		if ( $hub_primary_category ) {
			update_post_meta( $post->ID, '_yoast_wpseo_primary_category', $hub_primary_category->term_id );
		} elseif ( class_exists( '\Newspack\Logger' ) ) {
			\Newspack\Logger::error( __( 'No matching category found on the Hub site.', 'newspack-network' ) );
		}

		// Synchronize the post status.
		$hub_post_status     = get_post_meta( $post->ID, 'post_status', true );
		$current_post_status = get_post_status( $post->ID );
		if ( $hub_post_status && $hub_post_status !== $current_post_status ) {
			wp_update_post(
				[
					'ID'          => $post->ID,
					'post_status' => $hub_post_status,
				]
			);
		}
	}

	/**
	 * Filter the arguments sent to the remote server during a push.
	 *
	 * @param array   $post_body The post data to be sent to the Node.
	 * @param WP_Post $post The post object.
	 */
	public static function filter_push_post_args( $post_body, $post ) {
		// Pass the original published date to the new pushed post and set the same published date
		// instead of setting it to the current time.
		$post_body['date']     = $post->post_date;
		$post_body['date_gmt'] = $post->post_date_gmt;

		$slug = self::get_primary_category_slug( $post_body, $post );
		if ( $slug ) {
			$post_body['distributor_meta']['yoast_primary_category_slug'] = $slug;
		}

		return $post_body;
	}

	/**
	 * Keep the publication date on the new pulled post.
	 *
	 * @param array   $post_array The post data to be sent to the Node.
	 * @param int     $remote_id The remote post ID.
	 * @param WP_Post $post The post object.
	 */
	public static function filter_pull_post_args( $post_array, $remote_id, $post ) {
		$post_array['post_date']     = $post->post_date;
		$post_array['post_date_gmt'] = $post->post_date_gmt;
		return $post_array;
	}

	/**
	 * Send primary category slug to the Node when updating a post.
	 *
	 * @param array   $post_body The post data to be sent to the Node.
	 * @param WP_Post $post The post object.
	 */
	public static function filter_subscription_post_args( $post_body, $post ) {
		$slug = self::get_primary_category_slug( $post_body, $post );
		$post_body['post_data']['distributor_meta']['yoast_primary_category_slug'] = $slug;
		// Attaching the post status only on updates (so not in filter_push_post_args).
		// By default, only published posts are distributable, so there's no need to attach the post status on new posts.
		$post_body['post_data']['distributor_meta']['post_status'] = $post->post_status;
		return $post_body;
	}

	/**
	 * Allow editors to pull content.
	 */
	public static function filter_distributor_menu_cap() {
		return 'edit_others_posts';
	}
}
