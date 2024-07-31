<?php
/**
 * Newspack Network Data Listeners.
 *
 * @package Newspack
 */

namespace Newspack_Network;

use Newspack\Data_Events;

/**
 * Class to register additional listeners to the Newspack Data Events API
 */
class Data_Listeners {

	/**
	 * Initialize this class and register hooks
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_listeners' ] );
	}

	/**
	 * Register the listeners to the Newspack Data Events API
	 *
	 * @return void
	 */
	public static function register_listeners() {
		if ( ! class_exists( 'Newspack\Data_Events' ) ) {
			return;
		}

		Data_Events::register_listener( 'woocommerce_order_status_changed', 'newspack_node_order_changed', [ __CLASS__, 'item_changed' ] );
		Data_Events::register_listener( 'woocommerce_subscription_status_changed', 'newspack_node_subscription_changed', [ __CLASS__, 'subscription_changed' ] );
		Data_Events::register_listener( 'newspack_network_user_updated', 'network_user_updated', [ __CLASS__, 'user_updated' ] );
		Data_Events::register_listener( 'delete_user', 'network_user_deleted', [ __CLASS__, 'user_deleted' ] );
		Data_Events::register_listener( 'newspack_network_nodes_synced', 'network_nodes_synced', [ __CLASS__, 'nodes_synced' ] );
	}

	/**
	 * Callback for the Data Events API listeners
	 *
	 * @param int    $item_id     The Subscription or Order ID.
	 * @param string $status_from The status before the change.
	 * @param string $status_to   The status after the change.
	 * @param object $item        The Subscription or Order object.
	 * @return array
	 */
	public static function item_changed( $item_id, $status_from, $status_to, $item ) {
		$relationship = 'normal';
		if ( function_exists( 'wcs_order_contains_subscription' ) ) {
			if ( wcs_order_contains_subscription( $item_id, 'renewal' ) ) {
				$relationship = 'renewal';
			} elseif ( wcs_order_contains_subscription( $item_id, 'resubscribe' ) ) {
				$relationship = 'resubscribe';
			} elseif ( wcs_order_contains_subscription( $item_id, 'parent' ) ) {
				$relationship = 'parent';
			}
		}
		$result = [
			'id'                        => $item_id,
			'user_id'                   => $item->get_customer_id(),
			'user_name'                 => '',
			'email'                     => $item->get_billing_email(),
			'status_before'             => $status_from,
			'status_after'              => $status_to,
			'formatted_total'           => wp_strip_all_tags( $item->get_formatted_order_total() ),
			'payment_count'             => method_exists( $item, 'get_payment_count' ) ? $item->get_payment_count() : 1,
			'subscription_relationship' => $relationship,
			'currency'                  => $item->get_currency(),
			'total'                     => wc_format_decimal( $item->get_total(), 2 ),
			'payment_method_title'      => $item->get_payment_method_title(),
			'date_created'              => wc_rest_prepare_date_response( $item->get_date_created() ),
		];
		$user   = $item->get_user();
		if ( $user ) {
			$result['user_name'] = $user->display_name;
		}

		return $result;
	}

	/**
	 * Callback for the Data Events API listeners
	 *
	 * @param int    $item_id     The Subscription ID.
	 * @param string $status_from The status before the change.
	 * @param string $status_to   The status after the change.
	 * @param object $item        The Subscription object.
	 * @return array
	 */
	public static function subscription_changed( $item_id, $status_from, $status_to, $item ) {

		$result = self::item_changed( $item_id, $status_from, $status_to, $item );

		$result['start_date'] = $item->get_date( 'start_date' );
		$result['trial_end_date'] = $item->get_date( 'trial_end_date' );
		$result['next_payment_date'] = $item->get_date( 'next_payment_date' );
		$result['last_payment_date'] = $item->get_date( 'last_payment_date' );
		$result['end_date'] = $item->get_date( 'end_date' );

		return $result;
	}

	/**
	 * Filters the user data for the event being triggered
	 *
	 * @param array $user_data The user data.
	 * @return array
	 */
	public static function user_updated( $user_data ) {
		return $user_data;
	}

	/**
	 * Filters the user data for the event being triggered
	 *
	 * @param int      $id       ID of the user to delete.
	 * @param int|null $reassign ID of the user to reassign posts and links to.
	 *                           Default null, for no reassignment.
	 * @param WP_User  $user     WP_User object of the user to delete.
	 * @return array
	 */
	public static function user_deleted( $id, $reassign, $user ) {
		$should_delete = apply_filters( 'newspack_network_process_user_deleted', true, $user->user_email );
		if ( ! $should_delete ) {
			Debugger::log( 'User deletion with email: ' . $user->user_email . ' was skipped due to filter use.' );
			return;
		}
		// Prevent deletion-related changes triggering a 'network_user_updated' event.
		User_Update_Watcher::$enabled = false;
		return [
			'email' => $user->user_email,
		];
	}

	/**
	 * Filters the nodes data for the event being triggered
	 *
	 * @param array $nodes_data The nodes data.
	 * @return array
	 */
	public static function nodes_synced( $nodes_data ) {
		return [ 'nodes_data' => $nodes_data ];
	}
}
