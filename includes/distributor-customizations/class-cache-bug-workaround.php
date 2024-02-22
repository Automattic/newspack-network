<?php
/**
 * Newspack Distributor Bug Workaround
 *
 * @package Newspack
 */

namespace Newspack_Network\Distributor_Customizations;

/**
 * Class to workaround a bug in the Distributor plugin that affects site running with memcached.
 *
 * This is a workaround the bug fixed in https://github.com/10up/distributor/pull/1185
 * Until that fix is released, we need to keep this workaround.
 */
class Cache_Bug_Workaround {

	/**
	 * Initialize hooks
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'workaround' ] );
	}

	/**
	 * Deletes the buggy cache key
	 */
	public static function workaround() {
		wp_cache_delete( 'dt_media::{$post_id}', 'dt::post' );
	}
}
