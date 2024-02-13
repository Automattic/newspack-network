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
class User_Synced extends Abstract_Incoming_Event {

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
		Debugger::log( 'Processing user_synced with email: ' . $email );
		if ( ! $email ) {
			return;
		}

		$user = User_Utils::get_or_create_user_by_email( $email, $this->get_site(), $this->data->user_id ?? '' );

		$user_current_role = array_shift( $user->roles );
		$new_role          = $this->data->role ?? '';

		if ( ! empty( $new_role ) && $user_current_role !== $new_role ) {
			$user->set_role( $new_role );
		}
	}
}
