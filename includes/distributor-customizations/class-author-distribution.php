<?php
/**
 * Newspack Network author distribution.
 *
 * @package Newspack
 */

namespace Newspack_Network\Distributor_Customizations;

use Newspack\Data_Events;
use Newspack_Network\Debugger;
use Newspack_Network\User_Update_Watcher;

/**
 * Class to handle author distribution.
 *
 * Every time a post is distributed, we also send all the information about the author (or authors if CAP is enabled)
 * On the target site, the plugin will create the authors if they don't exist, and override the byline
 */
class Author_Distribution {

	/**
	 * Initializes the class
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'dt_push_post_args', [ __CLASS__, 'add_author_data_to_push' ], 10, 2 );
		add_filter( 'rest_prepare_post', [ __CLASS__, 'add_author_data_to_pull' ], 10, 3 );
	}

	/**
	 * Filters the post data sent on a push to add the author data.
	 *
	 * @param array   $post_body The post data.
	 * @param WP_Post $post The post object.
	 * @return array
	 */
	public static function add_author_data_to_push( $post_body, $post ) {
		$authors = self::get_authors_for_distribution( $post );
		if ( ! empty( $authors ) ) {
			$post_body['newspack_network_authors'] = $authors;
		}
		return $post_body;
	}

	/**
	 * Filters the post data for a REST API response.
	 *
	 * This acts on requests made to pull a post from this site.
	 *
	 * @param WP_REST_Response $response The response object.
	 * @param WP_Post          $post     Post object.
	 * @param WP_REST_Request  $request  Request object.
	 */
	public static function add_author_data_to_pull( $response, $post, $request ) {
		if (
			empty( $request->get_param( 'distributor_request' ) ) ||
			'GET' !== $request->get_method() ||
			'edit' !== $request->get_param( 'context' ) ||
			empty( $request->get_param( 'id' ) )
		) {
			return $response;
		}

		$authors = self::get_authors_for_distribution( $post );

		if ( ! empty( $authors ) ) {
			$data                             = $response->get_data();
			$data['newspack_network_authors'] = $authors;
			$response->set_data( $data );
		}

		return $response;

	}

	/**
	 * Get the authors of a post to be added to the distribution payload.
	 *
	 * @param WP_Post $post The post object.
	 * @return array An array of authors.
	 */
	private static function get_authors_for_distribution( $post ) {
		$author = self::get_wp_user_for_distribution( $post->post_author );

		if ( ! function_exists( 'get_coauthors' ) ) {
			if ( is_wp_error( $author ) ) {
				Debugger::log( 'Error getting author ' . $post->post_author . ' for distribution on post ' . $post->ID . ': ' . $author->get_error_message() );
				return [];
			}
			return [ $author ];
		}

		$co_authors = get_coauthors( $post->ID );
		if ( empty( $co_authors ) ) {
			if ( is_wp_error( $author ) ) {
				Debugger::log( 'Error getting author ' . $post->post_author . ' for distribution on post ' . $post->ID . ': ' . $author->get_error_message() );
				return [];
			}
			return [ $author ];
		}

		$authors = [];

		foreach ( $co_authors as $co_author ) {
			if ( is_a( $co_author, 'WP_User' ) ) {
				// This will never return an error because we are checking for is_a() first.
				$authors[] = self::get_wp_user_for_distribution( $co_author );
				continue;
			}

			$guest_author = self::get_guest_author_for_distribution( $co_author );
			if ( is_wp_error( $guest_author ) ) {
				Debugger::log( 'Error getting guest author for distribution on post ' . $post->ID . ': ' . $guest_author->get_error_message() );
				Debugger::log( $co_author );
				continue;
			}
			$authors[] = $guest_author;
		}

		return $authors;

	}

	/**
	 * Gets the user data of a WP user to be distributed along with the post.
	 *
	 * @param int|WP_Post $user The user ID or object.
	 * @return WP_Error|array
	 */
	private static function get_wp_user_for_distribution( $user ) {
		if ( ! is_a( $user, 'WP_User' ) ) {
			$user = get_user_by( 'ID', $user );
		}

		if ( ! $user ) {
			return new WP_Error( 'Error getting WP User details for distribution. Invalid User:' );
		}

		$author = [
			'type' => 'wp_user',
			'id'   => $user->ID,
		];


		foreach ( User_Update_Watcher::$user_props as $prop ) {
			if ( isset( $user->$prop ) ) {
				$author[ $prop ] = $user->$prop;
			}
		}

		// CoAuthors' guest authors have a 'website' property.
		if ( isset( $user->website ) ) {
			$author['website'] = $user->website;
		}

		foreach ( User_Update_Watcher::$watched_meta as $meta_key ) {
			$author[ $meta_key ] = get_user_meta( $user->ID, $meta_key, true );
		}

		return $author;
	}

	/**
	 * Get the guest author data to be distributed along with the post.
	 *
	 * @param object $guest_author The Guest Author object.
	 * @return WP_Error|array
	 */
	private static function get_guest_author_for_distribution( $guest_author ) {

		global $coauthors_plus;

		if ( ! is_object( $guest_author ) || ! isset( $guest_author->type ) || 'guest-author' !== $guest_author->type ) {
			return new WP_Error( 'Error getting guest author details for distribution. Invalid Guest Author' );
		}

		$author = [
			'type' => 'guest_author',
			'id'   => $guest_author->ID,
		];

		foreach ( User_Update_Watcher::$user_props as $prop ) {
			if ( isset( $guest_author->$prop ) ) {
				$author[ $prop ] = $guest_author->$prop;
			}
		}

		// CoAuthors' guest authors have a 'website' property.
		if ( isset( $guest_author->website ) ) {
			$author['website'] = $guest_author->website;
		}

		// Gets the guest author avatar.
		// We only want to send an actual uploaded avatar, we don't want to send the fallback avatar, like gravatar.
		// If no avatar was set, let it default to the fallback set in the target site.
		$author_avatar = $coauthors_plus->guest_authors->get_guest_author_thumbnail( $guest_author, 80 );
		if ( $author_avatar ) {
			$author['avatar_img_tag'] = $author_avatar;
		}

		foreach ( User_Update_Watcher::$watched_meta as $meta_key ) {
			if ( isset( $guest_author->$meta_key ) ) {
				$author[ $meta_key ] = $guest_author->$meta_key;
			}
		}

		return $author;
	}

}
