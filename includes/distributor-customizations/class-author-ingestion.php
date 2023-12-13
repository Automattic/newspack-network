<?php
/**
 * Newspack Network author ingestion.
 *
 * @package Newspack
 */

namespace Newspack_Network\Distributor_Customizations;

use Newspack\Data_Events;
use Newspack_Network\Debugger;
use Newspack_Network\User_Update_Watcher;
use Newspack_Network\Utils\Users as User_Utils;

/**
 * Class to handle author ingestion.
 *
 * This class is used to handle the authorship data added by Author_Distribution to the distributed post
 */
class Author_Ingestion {

	/**
	 * Array of authors from pulled posts. See self::capture_authorship.
	 *
	 * @var array
	 */
	private static $pulled_posts = [];

	/**
	 * Initializes the class
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_insert_post', [ __CLASS__, 'handle_rest_insertion' ], 10, 2 );
		add_filter( 'dt_item_mapping', [ __CLASS__, 'capture_authorship' ], 10, 2 );
		add_action( 'dt_pull_post', [ __CLASS__, 'handle_pull' ] );
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

		self::ingest_authors_for_post( $post->ID, $distributed_authors );
	}

	/**
	 * Ingest authors for a post distributed to this site
	 *
	 * @param int   $post_id The post ID.
	 * @param array $distributed_authors The distributed authors array.
	 * @return void
	 */
	public static function ingest_authors_for_post( $post_id, $distributed_authors ) {

		Debugger::log( 'Ingesting authors from distributed post.' );

		update_post_meta( $post_id, 'newspack_network_authors', $distributed_authors );

		$coauthors_plus = self::get_coauthors_plus();
		$coauthors      = [];

		foreach ( $distributed_authors as $author ) {
			// We only ingest WP Users. Guest authors are only stored in the newspack_network_authors post meta.
			if ( empty( $author['type'] ) || 'wp_user' != $author['type'] ) {
				continue;
			}

			Debugger::log( 'Ingesting author: ' . $author['user_email'] );

			$insert_array = [];

			foreach ( User_Update_Watcher::$user_props as $prop ) {
				if ( isset( $author[ $prop ] ) ) {
					$insert_array[ $prop ] = $author[ $prop ];
				}
			}

			$user = User_Utils::get_or_create_user_by_email( $author['user_email'], get_post_meta( $post_id, 'dt_original_site_url', true ), $author['id'], $insert_array );

			if ( is_wp_error( $user ) ) {
				continue;
			}

			foreach ( User_Update_Watcher::$watched_meta as $meta_key ) {
				if ( isset( $author[ $meta_key ] ) ) {
					update_user_meta( $user->ID, $meta_key, $author[ $meta_key ] );
				}
			}

			// If CoAuthors Plus is not present, just assign the first author as the post author.
			if ( ! $coauthors_plus ) {
				wp_update_post(
					[
						'ID'          => $post_id,
						'post_author' => $user->ID,
					]
				);
				break;
			}

			$coauthors[] = $user->user_nicename;
		}

		if ( $coauthors_plus ) {
			$coauthors_plus->add_coauthors( $post_id, $coauthors );
		}

	}

	/**
	 * Captures and stores the authorship data for a post
	 *
	 * Distributor discards the additional data we send in the REST request, so we need to capture it here
	 * for later use in self::add_author_data_to_pull
	 *
	 * @param WP_Post $post The post object being pulled.
	 * @param array   $post_array The post array received from the REST api.
	 * @return WP_Post
	 */
	public static function capture_authorship( $post, $post_array ) {
		Debugger::log( 'Trying to capture authorship for post ' . $post->ID );
		if ( empty( $post_array['newspack_network_authors'] ) ) {
			return $post;
		}
		Debugger::log( 'Capturing authorship for post ' . $post->ID );
		self::$pulled_posts[ $post->ID ] = $post_array['newspack_network_authors'];
		return $post;
	}

	/**
	 * Triggered when a post is pulled from a remote site.
	 *
	 * @param int $post_id The pulled post ID.
	 * @return void
	 */
	public static function handle_pull( $post_id ) {
		$remote_id = get_post_meta( $post_id, 'dt_original_post_id', true );
		if ( ! $remote_id || empty( self::$pulled_posts[ $remote_id ] ) ) {
			return;
		}

		$distributed_authors = self::$pulled_posts[ $remote_id ];

		self::ingest_authors_for_post( $post_id, $distributed_authors );

	}

}
