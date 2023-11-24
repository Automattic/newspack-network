<?php
/**
 * Newspack Author Updated Incoming Event class
 *
 * @package Newspack
 */

namespace Newspack_Network\Incoming_Events;

use Newspack_Network\Author_Update_Watcher;
use Newspack_Network\Debugger;

/**
 * Class to handle the Canonical Url Updated Event
 *
 * This event is always sent from the Hub and received by Nodes.
 */
class Author_Updated extends Abstract_Incoming_Event {

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
		Debugger::log( 'Processing author_updated with email: ' . $email );
		if ( ! $email ) {
			return;
		}
		$existing_user = get_user_by( 'email', $email );

		if ( ! $existing_user ) {
			Debugger::log( 'User not found, skipping.' );
			return;
		}

		Author_Update_Watcher::$processing_author_updated_event = true;

		$data = $this->get_data();

		if ( isset( $data->prop ) ) {
			$update_array = [
				'ID' => $existing_user->ID,
			];
			foreach ( $data->prop as $prop_key => $prop_value ) {
				$update_array[ $prop_key ] = $prop_value;
			}
			Debugger::log( 'Updating user with data: ' . print_r( $update_array, true ) );
			wp_update_user( $update_array );
		}

		if ( isset( $data->meta ) ) {
			foreach ( $data->meta as $meta_key => $meta_value ) {
				Debugger::log( 'Updating user meta: ' . $meta_key );
				update_user_meta( $existing_user->ID, $meta_key, $meta_value );
			}
		}

	}

}
