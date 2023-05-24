<?php
/**
 * Newspack Hub Woocommerce Subscriptions Store
 *
 * @package Newspack
 */

namespace Newspack_Network\Hub\Stores;

use Newspack_Network\Debugger;
use Newspack_Network\Incoming_Events\Subscription_Changed;
use Newspack_Network\Hub\Database\Subscriptions as Subscriptions_DB;

/**
 * Class to handle Woocommerce Subscriptions Store
 */
class Subscriptions extends Woo_Store {

	/**
	 * Gets the post type slug
	 *
	 * @return string
	 */
	protected static function get_post_type_slug() {
		return Subscriptions_DB::POST_TYPE_SLUG;
	}

	/**
	 * Gets the api endpoint prefix
	 *
	 * @return string
	 */
	protected static function get_api_endpoint_prefix() {
		return 'subscriptions';
	}

	/**
	 * Gets the name of the items class
	 *
	 * @return string
	 */
	protected static function get_item_class() {
		return 'Subscription_Item';
	}

	/**
	 * Persists a Subscription_Changed event by creating or updating a Subscription post.
	 *
	 * @param Subscription_Changed $subscription The Subscription_Changed event.
	 * @return int The local post ID.
	 */
	public static function persist( Subscription_Changed $subscription ) {
		$subscription_id = $subscription->get_id();

		if ( ! $subscription_id ) {
			return;
		}

		Debugger::log( 'Persisting subscription ' . $subscription_id );

		$local_id          = self::get_local_id( $subscription );
		$subscription_data = self::fetch_remote_data( $subscription );

		Debugger::log( 'Local ID: ' . $local_id );

		if ( ! $subscription_data ) {
			return;
		}

		// Data from the event.
		update_post_meta( $local_id, 'payment_count', $subscription->get_payment_count() );
		update_post_meta( $local_id, 'formatted_total', $subscription->get_formatted_total() );
		Debugger::log( 'Updating post status to ' . $subscription->get_status_after() );
		$update_array = [
			'ID'          => $local_id,
			'post_status' => $subscription->get_status_after(),
		];
		$update       = wp_update_post( $update_array );
		Debugger::log( 'Updated post status: ' . $update );

		// Data from the API.
		update_post_meta( $local_id, 'currency', $subscription_data->currency );
		update_post_meta( $local_id, 'total', $subscription_data->total );
		update_post_meta( $local_id, 'payment_method_title', $subscription_data->payment_method_title );
		update_post_meta( $local_id, 'start_date', $subscription_data->start_date_gmt );
		update_post_meta( $local_id, 'trial_end_date', $subscription_data->trial_end_date_gmt );
		update_post_meta( $local_id, 'next_payment_date', $subscription_data->next_payment_date_gmt );
		update_post_meta( $local_id, 'last_payment_date', $subscription_data->last_payment_date_gmt );
		update_post_meta( $local_id, 'end_date', $subscription_data->end_date_gmt );

		delete_post_meta( $local_id, 'line_items' );
		foreach ( $subscription_data->line_items as $line_item ) {
			add_post_meta(
				$local_id, 
				'line_items', 
				[
					'name'       => $line_item->name,
					'product_id' => $line_item->product_id,
				] 
			);
		}

		return $local_id;

	}

}
