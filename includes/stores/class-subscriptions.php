<?php
/**
 * Newspack Hub Woocommerce Subscriptions Store
 *
 * @package Newspack
 */

namespace Newspack_Hub\Stores;

use Newspack_Hub\Accepted_Actions;
use Newspack_Hub\Debugger;
use Newspack_Hub\Database\Subscriptions as Subscriptions_DB;
use Newspack_Hub\Incoming_Events\Subscription_Changed;

/**
 * Class to handle Woocommerce Subscriptions Store
 */
class Subscriptions {

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
		$subscription_data = self::fetch_subscription_data( $subscription );

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

	/**
	 * Returns the local post ID for a given Subscription_Changed event.
	 *
	 * If there's no local post for the given Subscription_Changed event, creates one.
	 *
	 * @param Subscription_Changed $subscription The Subscription_Changed event.
	 * @return int The local post ID.
	 */
	protected static function get_local_id( Subscription_Changed $subscription ) {
		$subscription_id = $subscription->get_id();
		$stored          = get_posts(
			[
				'post_type'      => Subscriptions_DB::POST_TYPE_SLUG,
				'post_status'    => 'any',
				'meta_key'       => 'remote_id',
				'meta_value'     => $subscription_id, //phpcs:ignore
				'posts_per_page' => 1,
				'fields'         => 'ids',
			]
		);
		if ( ! empty( $stored ) ) {
			return $stored[0];
		}
		return self::create_subscription( $subscription );
	}

	/**
	 * Creates a local post for a given Subscription_Changed event.
	 *
	 * @param Subscription_Changed $subscription The Subscription_Changed event.
	 * @return int The local post ID.
	 */
	protected static function create_subscription( Subscription_Changed $subscription ) {
		$subscription_id = $subscription->get_id();
		$user_id         = 0;
		$user            = get_user_by( 'email', $subscription->get_email() );
		if ( $user instanceof \WP_User ) {
			$user_id = $user->ID;
		}
		$post_arr = [
			'post_type'   => Subscriptions_DB::POST_TYPE_SLUG,
			'post_status' => $subscription->get_status_after(),
			'post_title'  => '#' . $subscription_id,
			'post_author' => $user_id,
		];
		$post_id  = wp_insert_post( $post_arr );

		add_post_meta( $post_id, 'remote_id', $subscription_id );
		add_post_meta( $post_id, 'node_id', $subscription->get_node_id() );
		add_post_meta( $post_id, 'user_email', $subscription->get_email() );
		add_post_meta( $post_id, 'user_name', $subscription->get_user_name() );

		return $post_id;
	}


	/**
	 * Fetches subscription data from the API.
	 *
	 * @param Subscription_Changed $subscription The Subscription_Changed event.
	 * @return object The subscription data.
	 */
	protected static function fetch_subscription_data( Subscription_Changed $subscription ) {

		$subscription_id = $subscription->get_id();
		
		$endpoint = sprintf( '%s/wp-json/wc/v3/subscriptions/%d', $subscription->get_node()->get_url(), $subscription_id );

		$response = wp_remote_get( // phpcs:ignore
			$endpoint,
			[
				'headers' => [
					'Authorization' => $subscription->get_node()->get_authorization_header(),
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
