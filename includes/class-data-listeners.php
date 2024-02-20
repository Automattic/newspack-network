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

		Data_Events::register_listener( 'woocommerce_subscription_status_changed', 'newspack_node_subscription_changed', [ __CLASS__, 'item_changed' ] );
		Data_Events::register_listener( 'woocommerce_order_status_changed', 'newspack_node_order_changed', [ __CLASS__, 'item_changed' ] );
		Data_Events::register_listener( 'newspack_network_user_updated', 'network_user_updated', [ __CLASS__, 'user_updated' ] );
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
		];
		$user   = $item->get_user();
		if ( $user ) {
			$result['user_name'] = $user->display_name;
		}
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
	 * Filters the nodes data for the event being triggered
	 *
	 * @param array $nodes_data The nodes data.
	 * @return array
	 */
	public static function nodes_synced( $nodes_data ) {
		return [ 'nodes_data' => $nodes_data ];
	}
}
