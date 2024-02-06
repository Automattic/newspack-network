<?php
/**
 * This files contains the global tweaks that are loaded by default in every site in the Network (both Hub and Nodes).
 *
 * @package newspack-network
 */

/**
 * Send primary category slug to the Node along with post update data.
 *
 * @param array   $post_body The post data to be sent to the Node.
 * @param WP_Post $post The post object.
 */
function newspack_network_get_primary_category_slug( $post_body, $post ) {
	if ( class_exists( 'WPSEO_Primary_Term' ) ) {
		$primary_term = new WPSEO_Primary_Term( 'category', $post->ID );
		$category_id  = $primary_term->get_primary_term();
		if ( $category_id ) {
			$category = get_term( $category_id );
			return $category->slug;
		}
	}
}

/**
 * Fix primary category on the Node.
 *
 * @param WP_Post         $post The post object.
 * @param WP_REST_Request $request The request data.
 */
function newspack_network_fix_primary_category( $post, $request ) {
	$primary_category_slug = get_post_meta( $post->ID, 'yoast_primary_category_slug', true );
	// Match the category by slug, the IDs might have a clash.
	$hub_primary_category = get_term_by( 'slug', $primary_category_slug, 'category' );
	if ( $hub_primary_category ) {
		update_post_meta( $post->ID, '_yoast_wpseo_primary_category', $hub_primary_category->term_id );
	} elseif ( class_exists( '\Newspack\Logger' ) ) {
		\Newspack\Logger::error( __( 'No matching category found on the Hub site.', 'newspack-network' ) );
	}
}

/**
 * This filter is used to filter the arguments sent to the remote server during a push.
 */
add_filter(
	'dt_push_post_args',
	function( $post_body, $post ) {
		// Pass the original published date to the new pushed post and set the same published date
		// instead of setting it to the current time.
		$post_body['date']     = $post->post_date;
		$post_body['date_gmt'] = $post->post_date_gmt;

		$slug = newspack_network_get_primary_category_slug( $post_body, $post );
		if ( $slug ) {
			$post_body['distributor_meta']['yoast_primary_category_slug'] = $slug;
		}

		return $post_body;
	},
	10,
	2
);
/**
 * Keep the publication date on the new pulled post.
 *
 * This filters the arguments passed into wp_insert_post during a pull.
 */
add_filter(
	'dt_pull_post_args',
	function( $post_array, $remote_id, $post ) {
		$post_array['post_date']     = $post->post_date;
		$post_array['post_date_gmt'] = $post->post_date_gmt;
		return $post_array;
	},
	10,
	3
);

/**
 * Allow editors to pull content.
 */
function newspack_network_filter_distributor_menu_cap() {
	return 'edit_others_posts';
}
add_filter( 'dt_capabilities', 'newspack_network_filter_distributor_menu_cap' );
add_filter( 'dt_pull_capabilities', 'newspack_network_filter_distributor_menu_cap' );

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

/**
 * Send primary category slug to the Node when updating a post.
 */
add_filter(
	'dt_subscription_post_args',
	function( $post_body, $post ) {
		$slug = newspack_network_get_primary_category_slug( $post_body, $post );
		$post_body['post_data']['distributor_meta']['yoast_primary_category_slug'] = $slug;
		return $post_body;
	},
	10,
	2
);

/**
 * Map Hub primary category to the primary category on the Node.
 */
add_action( 'dt_process_subscription_attributes', 'newspack_network_fix_primary_category', 10, 2 );
add_action( 'dt_process_distributor_attributes', 'newspack_network_fix_primary_category', 10, 2 );
