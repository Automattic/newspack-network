<?php
/**
 * Newspack Network authorship filters.
 *
 * @package Newspack
 */

namespace Newspack_Network\Distributor_Customizations;

use Newspack\Data_Events;
use Newspack_Network\Debugger;
use Newspack_Network\User_Update_Watcher;
use Newspack_Network\Utils\Users as User_Utils;

/**
 * Class to handle authorshipt filters
 *
 * This class is responsible for filtering the authorship data that is publicly displayed in the site using the distributed authors and including CAP's guest authors
 */
class Authorship_Filters {

	/**
	 * Initializes the class
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'get_coauthors', [ __CLASS__, 'filter_coauthors' ], 10, 2 );
	}

	/**
	 * Filters the coauthors of a post to include the distributed authors and CAP's guest authors
	 *
	 * @param array $coauthors Array of coauthors.
	 * @param int   $post_id   Post ID.
	 * @return array
	 */
	public static function filter_coauthors( $coauthors, $post_id ) {
		if ( is_admin() ) {
			return $coauthors;
		}

		$distributed_authors = get_post_meta( $post_id, 'newspack_network_authors', true );

		// If anything, at any point, goes wrong with the distributed authors, we return the original coauthors.
		if ( ! $distributed_authors ) {
			return $coauthors;
		}

		$filtered_coauthors = [];

		foreach ( $distributed_authors as $distributed_author ) {
			if ( ! isset( $distributed_author['type'] ) ) {
				return $coauthors;
			}

			if ( 'guest_author' === $distributed_author['type'] ) {
				$filtered_coauthors[] = (object) $distributed_author;
			} elseif ( 'wp_user' === $distributed_author['type'] ) {
				$user = get_user_by( 'email', $distributed_author['user_email'] );
				if ( is_a( $user, 'WP_User' ) ) {
					return $coauthors;
				}
				$filtered_coauthors[] = $user;
			}
		}

		return $filtered_coauthors;
	}

}
