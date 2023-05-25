<?php
/**
 * Newspack Hub Woocommerce Generic Woo items store for orders and subscriptions
 *
 * @package Newspack
 */

namespace Newspack_Network\Hub\Stores;

use Newspack_Network\Incoming_Events\Woo_Item_Changed;

/**
 * Class to handle Woocommerce Generic Woo items store for orders and subscriptions
 */
abstract class Woo_Store {


	/**
	 * Gets the post type slug
	 *
	 * @return string
	 */
	abstract protected static function get_post_type_slug();

	/**
	 * Gets the api endpoint prefix
	 *
	 * @return string
	 */
	abstract protected static function get_api_endpoint_prefix();

	/**
	 * Gets the name of the items class
	 *
	 * @return string
	 */
	abstract protected static function get_item_class();

	/**
	 * Gets an item by its ID
	 *
	 * @param int $item_id The item ID.
	 * @return ?Woo_Item The Woo_Item object if the item is found.
	 */
	public static function get_item( $item_id ) {
		$item_class = __NAMESPACE__ . '\\' . static::get_item_class();
		$item       = new $item_class( $item_id );
		if ( $item->get_id() ) {
			return $item;
		}
	}

	/**
	 * Returns the local post ID for a given Woo_Item_Changed event.
	 *
	 * If there's no local post for the given Woo_Item_Changed event, creates one.
	 *
	 * @param Woo_Item_Changed $woo_item The Woo_Item_Changed event.
	 * @return int The local post ID.
	 */
	protected static function get_local_id( Woo_Item_Changed $woo_item ) {
		$woo_item_id = $woo_item->get_id();
		$stored      = get_posts(
			[
				'post_type'      => static::get_post_type_slug(),
				'post_status'    => 'any',
				'meta_key'       => 'remote_id',
				'meta_value'     => $woo_item_id, //phpcs:ignore
				'posts_per_page' => 1,
				'fields'         => 'ids',
			]
		);
		if ( ! empty( $stored ) ) {
			return $stored[0];
		}
		return self::create_subscription( $woo_item );
	}

	/**
	 * Creates a local post for a given Woo_Item_Changed event.
	 *
	 * @param Woo_Item_Changed $woo_item The Woo_Item_Changed event.
	 * @return int The local post ID.
	 */
	protected static function create_subscription( Woo_Item_Changed $woo_item ) {
		$woo_item_id = $woo_item->get_id();
		$user_id     = 0;
		$user        = get_user_by( 'email', $woo_item->get_email() );
		if ( $user instanceof \WP_User ) {
			$user_id = $user->ID;
		}
		$post_arr = [
			'post_type'   => static::get_post_type_slug(),
			'post_status' => $woo_item->get_status_after(),
			'post_title'  => '#' . $woo_item_id,
			'post_author' => $user_id,
		];
		$post_id  = wp_insert_post( $post_arr );

		add_post_meta( $post_id, 'remote_id', $woo_item_id );
		add_post_meta( $post_id, 'node_id', $woo_item->get_node()->get_id() );
		add_post_meta( $post_id, 'user_email', $woo_item->get_email() );
		add_post_meta( $post_id, 'user_name', $woo_item->get_user_name() );

		return $post_id;
	}


	/**
	 * Fetches subscription data from the API.
	 *
	 * @param Woo_Item_Changed $woo_item The Woo_Item_Changed event.
	 * @return object The subscription data.
	 */
	protected static function fetch_remote_data( Woo_Item_Changed $woo_item ) {

		$woo_item_id = $woo_item->get_id();
		
		$endpoint = sprintf( '%s/wp-json/wc/v3/%s/%d', $woo_item->get_node()->get_url(), static::get_api_endpoint_prefix(), $woo_item_id );

		$response = wp_remote_get( // phpcs:ignore
			$endpoint,
			[
				'headers' => [
					'Authorization' => $woo_item->get_node()->get_authorization_header(),
				],
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return;
		}

		$body = wp_remote_retrieve_body( $response );

		return json_decode( $body );

	}
}
