<?php
/**
 * This files contains the global tweaks that are loaded by default in every site in the Network (both Hub and Nodes).
 *
 * @package newspack-network
 */

/**
 * Send primary category slug to the Node along with post update data.
 *
 * @param WP_Post $post The post object.
 * @param bool    $is_pulling Whether the post is being pulled from the remote site.
 */
function newspack_network_get_primary_category_slug( $post, $is_pulling = false ) {
	if ( $is_pulling ) {
		// When pulling content, the post will be the remote site post (not on the WP instance that executes this code).
		// The category slug has to be read from the data on the post object.
		if ( ! isset( $post->meta['_yoast_wpseo_primary_category'] ) ) {
			return;
		}
		$primary_category_id = reset( $post->meta['_yoast_wpseo_primary_category'] );
		if ( ! $primary_category_id ) {
			return;
		}
		$maybe_primary_categories = array_filter(
			$post->terms['category'],
			function( $category ) use ( $primary_category_id ) {
				return (int) $category['term_id'] === (int) $primary_category_id;
			}
		);
		$maybe_primary_category   = reset( $maybe_primary_categories );
		if ( $maybe_primary_category ) {
			return $maybe_primary_category['slug'];
		}
	} elseif ( class_exists( 'WPSEO_Primary_Term' ) ) {
		// When pushing, the post will be the post on this site.
		// The category exists on the site which executes this code, so it can be retrieved via Yoast.
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
 * @param WP_Post $post The post object.
 */
function newspack_network_fix_primary_category( $post ) {
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

		$slug = newspack_network_get_primary_category_slug( $post );
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

		$slug = newspack_network_get_primary_category_slug( $post, true );
		if ( $slug ) {
			$post_array['meta_input']['yoast_primary_category_slug'] = $slug;
		}

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
		$slug = newspack_network_get_primary_category_slug( $post );
		$post_body['post_data']['distributor_meta']['yoast_primary_category_slug'] = $slug;
		return $post_body;
	},
	10,
	2
);

/**
 * Map Hub primary category to the primary category on the Node.
 */
add_action( 'dt_process_subscription_attributes', 'newspack_network_fix_primary_category' );
add_action( 'dt_process_distributor_attributes', 'newspack_network_fix_primary_category' );
add_action(
	'dt_pull_post',
	function( $new_post_id ) {
		newspack_network_fix_primary_category( get_post( $new_post_id ) );
	}
);
