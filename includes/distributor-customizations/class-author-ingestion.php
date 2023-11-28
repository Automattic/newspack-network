<?php
/**
 * Newspack Network author ingestino.
 *
 * @package Newspack
 */

namespace Newspack_Network\Distributor_Customizations;

use Newspack\Data_Events;
use Newspack_Network\Debugger;

/**
 * Class to handle author ingestion.
 *
 * This class is used to handle the authorship data added by Author_Distriburion to the distributed post
 */
class Author_Ingestion {

	/**
	 * Initializes the class
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_insert_post', [ __CLASS__, 'handle_rest_insertion' ], 10, 2 );
	}

	/**
	 * Gets the CoAuthors Plus main object, if present
	 *
	 * @return false|\CoAuthors_Plus
	 */
	public static function get_coauthors_plus() {
		global $coauthors_plus;
		if ( is_null( $coauthors_plus ) || ( ! $coauthors_plus instanceof \CoAuthors_Plus ) ) {
			return false;
		}
		return $coauthors_plus;
	}

	/**
	 * Fired when a post is inserted via the REST API
	 *
	 * Checks if there are newspack network author information and ingests it.
	 *
	 * @param WP_Post         $post     Inserted post object.
	 * @param WP_REST_Request $request  Request object.
	 * @return void
	 */
	public static function handle_rest_insertion( $post, $request ) {

		$distributed_authors = $request->get_param( 'newspack_network_authors' );
		if ( empty( $distributed_authors ) ) {
			return;
		}

		Debugger::log( 'Ingesting authors from distributed post.' );

		update_post_meta( $post->ID, 'newspack_network_authors', $distributed_authors );

		$coauthors_plus = self::get_coauthors_plus();
		$coauthors      = [];

		foreach ( $distributed_authors as $author ) {
			if ( 'wp_user' != $author['type'] ) {
				continue;
			}

			Debugger::log( 'Ingesting author: ' . $author['user_email'] );

			$user = self::get_or_create_user( $author );

			if ( is_wp_error( $user ) ) {
				continue;
			}

			update_user_meta( $user->ID, 'newspack_remote_site', get_post_meta( $post->ID, 'dt_original_site_url', true ) );
			update_user_meta( $user->ID, 'newspack_remote_id', $author['id'] );

			foreach ( Author_Distribution::$watched_meta as $meta_key ) {
				if ( isset( $author[ $meta_key ] ) ) {
					update_user_meta( $user->ID, $meta_key, $author[ $meta_key ] );
				}
			}

			// If CoAuthors Plus is not present, just assign the first author as the post author.
			if ( ! $coauthors_plus ) {
				wp_update_post(
					[
						'ID'          => $post->ID,
						'post_author' => $user->ID,
					]
				);
				break;
			}

			$coauthors[] = $user->user_nicename;
		}

		if ( $coauthors_plus ) {
			$coauthors_plus->add_coauthors( $post->ID, $coauthors );
		}

	}

	/**
	 * Gets or created a user based on the distributed author data.
	 *
	 * @param array $distributed_author The distributed author as received from the remote site.
	 * @return WP_User|WP_Error
	 */
	public static function get_or_create_user( $distributed_author ) {

		$existing_user = get_user_by( 'email', $distributed_author['user_email'] );

		if ( $existing_user ) {
			return $existing_user;
		}

		$insert_array = [
			'user_login'    => $distributed_author['user_email'],
			'user_nicename' => $distributed_author['user_email'],
			'user_pass'     => wp_generate_password(),
			'role'          => 'author',
		];

		foreach ( Author_Distribution::$user_props as $prop ) {
			if ( isset( $distributed_author[ $prop ] ) ) {
				$insert_array[ $prop ] = $distributed_author[ $prop ];
			}
		}

		$user_id = wp_insert_user( $insert_array );

		if ( is_wp_error( $user_id ) ) {
			Debugger::log( 'Error creating user: ' . $user_id->get_error_message() );
			return $user_id;
		}

		return get_user_by( 'id', $user_id );

	}

}
