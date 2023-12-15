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
