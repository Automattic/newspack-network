<?php
/**
 * Newspack User Synced Incoming Event class
 *
 * @package Newspack
 */

namespace Newspack_Network\Incoming_Events;

use Newspack_Network\User_Manual_Sync;
use Newspack_Network\Debugger;
use Newspack_Network\Utils\Users as User_Utils;

/**
 * Class to handle the User Updated Event
 *
 * This event is always sent from the Hub and received by Nodes.
 */
class User_Manually_Synced extends Abstract_Incoming_Event {

	/**
	 * Processes the event
	 *
	 * @return void
	 */
	public function post_process_in_hub() {
		$this->maybe_sync_user();
	}

	/**
	 * Process event in Node
	 *
	 * @return void
	 */
	public function process_in_node() {
		$this->maybe_sync_user();
	}

	/**
	 * Maybe updates a new WP user based on this event
	 *
	 * @return void
	 */
	public function maybe_sync_user() {
		$email = $this->get_email();
		Debugger::log( 'Processing user_manually_synced with email: ' . $email );
		if ( ! $email ) {
			return;
		}

		$user = User_Utils::get_or_create_user_by_email( $email, $this->get_site(), $this->data->user_id ?? '' );

		if ( is_wp_error( $user ) ) {
			Debugger::log( 'Error creating user: ' . $user->get_error_message() );
			return;
		}

		// Get data passed for user.
		$data = $this->get_data();

		// Update user role if changed.
		$user_current_role = $user->roles;
		$new_role          = $data->role ?? '';

		if ( ! empty( $new_role ) && $user_current_role !== $new_role ) {
			$user->set_role( $new_role );
		}

		// Loop through user props and update.
		if ( isset( $data->prop ) ) {
			$update_array = [
				'ID' => $user->ID,
			];
			foreach ( $data->prop as $prop_key => $prop_value ) {
				$update_array[ $prop_key ] = $prop_value;
			}
			Debugger::log( 'Manually syncing user with data: ' . print_r( $update_array, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			wp_update_user( $update_array );
		}

		// Loop through user meta and update.
		if ( isset( $data->meta ) ) {
			foreach ( $data->meta as $meta_key => $meta_value ) {
				Debugger::log( 'Manually syncing user meta: ' . $meta_key );
				update_user_meta( $user->ID, $meta_key, $meta_value );
			}
		}

		User_Utils::maybe_sideload_avatar( $user->ID, $data->meta, true );
	}
}
