<?php
/**
 * Newspack User Updated Incoming Event class
 *
 * @package Newspack
 */

namespace Newspack_Network\Incoming_Events;

use Newspack_Network\User_Update_Watcher;
use Newspack_Network\Debugger;
use Newspack_Network\Utils\Users as User_Utils;

/**
 * Class to handle the User Updated Event
 *
 * This event is always sent from the Hub and received by Nodes.
 */
class User_Updated extends Abstract_Incoming_Event {

	/**
	 * Processes the event
	 *
	 * @return void
	 */
	public function post_process_in_hub() {
		$this->maybe_update_user();
	}

	/**
	 * Process event in Node
	 *
	 * @return void
	 */
	public function process_in_node() {
		$this->maybe_update_user();
	}

	/**
	 * Maybe updates a new WP user based on this event
	 *
	 * @return void
	 */
	public function maybe_update_user() {
		$email = $this->get_email();
		Debugger::log( 'Processing user_updated with email: ' . $email );
		if ( ! $email ) {
			return;
		}
		$existing_user = get_user_by( 'email', $email );

		if ( ! $existing_user ) {
			Debugger::log( 'User not found, skipping.' );
			return;
		}

		User_Update_Watcher::$enabled = false;

		$data = $this->get_data();

		// Only the fixed set of profile fields and meta keys that the user sync tracks are
		// applied. The incoming payload is signed by the sending site but not otherwise
		// validated, so an arbitrary key (e.g. user_pass, role, wp_capabilities,
		// _application_passwords) must never reach wp_update_user() / update_user_meta().
		if ( isset( $data->prop ) ) {
			$incoming_props = (array) $data->prop;
			$update_array   = [
				'ID' => $existing_user->ID,
			];
			foreach ( User_Update_Watcher::$user_props as $prop_key ) {
				if ( isset( $incoming_props[ $prop_key ] ) ) {
					$update_array[ $prop_key ] = $incoming_props[ $prop_key ];
				}
			}
			// A background sync event must not change the email address of a staff account:
			// the email is the password-reset target, so a peer site could otherwise take
			// over the account. Reader emails still sync (email is the reader's identity);
			// a staff email change can still be pushed via manual sync.
			if ( isset( $update_array['user_email'] ) && ! \Newspack\Reader_Activation::is_user_reader( $existing_user ) ) {
				Debugger::log( sprintf( 'Ignoring email change for non-reader user %d via network sync.', $existing_user->ID ) );
				unset( $update_array['user_email'] );
			}
			// Only update if at least one allowed prop is present; $update_array always has 'ID'.
			if ( count( $update_array ) > 1 ) {
				Debugger::log( 'Updating user with data: ' . print_r( $update_array, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				wp_update_user( $update_array );
			}
		}

		if ( isset( $data->meta ) ) {
			$incoming_meta = (array) $data->meta;
			foreach ( User_Update_Watcher::get_writable_meta() as $meta_key ) {
				if ( isset( $incoming_meta[ $meta_key ] ) ) {
					Debugger::log( 'Updating user meta: ' . $meta_key );
					update_user_meta( $existing_user->ID, $meta_key, $incoming_meta[ $meta_key ] );
				}
			}

			User_Utils::maybe_sideload_avatar( $existing_user->ID, $data->meta, true );
		}
	}
}
