<?php
/**
 * This files contains the global tweaks that are loaded by default in every site in the Network (bot Hub and Nodes)
 *
 * @package newspack-network
 */

/**
 * =========================================
 * ======== Post publication date ==========
 * =========================================
 * Keep the publication date on the new pushed post.
 *
 * This filter is used to filter the arguments sent to the remote server during a push. The below code snippet passes the original published date to the new pushed post and sets the same published date instead of setting it as per the current time.
 */
add_filter(
	'dt_push_post_args',
	function( $post_body, $post ) {
		$post_body['date']     = $post->post_date;
		$post_body['date_gmt'] = $post->post_date_gmt;
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
 * =========================================
 * ===== End of Post publication date ======
 * =========================================
 */

/**
 * =========================================
 * ===== Allow editors to pull content =====
 * =========================================
 */
function newspack_network_filter_distributor_menu_cap() {
	return 'edit_others_posts';
}
add_filter( 'dt_capabilities', 'newspack_network_filter_distributor_menu_cap' );
add_filter( 'dt_pull_capabilities', 'newspack_network_filter_distributor_menu_cap' );
/**
 * =========================================
 * ==== End of editors to pull content =====
 * =========================================
 */

/**
 * =========================================
 * =========== Bug Workaround ==============
 * This is a workaround the bug fixed in https://github.com/10up/distributor/pull/1185
 * Until that fix is released, we need to keep this workaround.
 * =========================================
 */
add_action(
	'init',
	function() {
		wp_cache_delete( 'dt_media::{$post_id}', 'dt::post' );
	}
);
