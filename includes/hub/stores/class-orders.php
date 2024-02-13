<?php
/**
 * Newspack Hub Woocommerce Orders Store
 *
 * @package Newspack
 */

namespace Newspack_Network\Hub\Stores;

use Newspack_Network\Debugger;
use Newspack_Network\Incoming_Events\Order_Changed;
use Newspack_Network\Hub\Database\Orders as Orders_DB;

/**
 * Class to handle Woocommerce Orders Store
 */
class Orders extends Woo_Store {

	/**
	 * Gets the post type slug
	 *
	 * @return string
	 */
	protected static function get_post_type_slug() {
		return Orders_DB::POST_TYPE_SLUG;
	}

	/**
	 * Gets the api endpoint prefix
	 *
	 * @return string
	 */
	protected static function get_api_endpoint_prefix() {
		return 'orders';
	}

	/**
	 * Gets the name of the items class
	 *
	 * @return string
	 */
	protected static function get_item_class() {
		return 'Order_Item';
	}

	/**
	 * Persists a Order_Changed event by creating or updating a Subscription post.
	 *
	 * @param Order_Changed $order The Order_Changed event.
	 * @return int The local post ID.
	 */
	public static function persist( Order_Changed $order ) {
		$order_id = $order->get_id();

		if ( ! $order_id ) {
			return;
		}

		Debugger::log( 'Persisting order ' . $order_id );

		$local_id   = self::get_local_id( $order );
		$order_data = self::fetch_data_from_api( $order );

		Debugger::log( 'Local ID: ' . $local_id );

		if ( ! $order_data ) {
			return;
		}

		// Data from the event.
		update_post_meta( $local_id, 'payment_count', $order->get_payment_count() );
		update_post_meta( $local_id, 'formatted_total', $order->get_formatted_total() );
		update_post_meta( $local_id, 'subscription_relationship', $order->get_subscription_relationship() );
		Debugger::log( 'Updating post status to ' . $order->get_status_after() );
		$update_array = [
			'ID'          => $local_id,
			'post_status' => $order->get_status_after(),
		];
		$update       = wp_update_post( $update_array );
		Debugger::log( 'Updated post status: ' . $update );

		// Data from the API.
		update_post_meta( $local_id, 'currency', $order_data->currency );
		update_post_meta( $local_id, 'total', $order_data->total );
		update_post_meta( $local_id, 'date_created', $order_data->date_created );

		return $local_id;
	}
}
