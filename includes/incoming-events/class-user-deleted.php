<?php
/**
 * Newspack Hub User Deleted Incoming Event class
 *
 * @package Newspack
 */

namespace Newspack_Network\Incoming_Events;

use Newspack_Network\Debugger;
use Newspack_Network\Utils\Users as User_Utils;

/**
 * Class to handle the user deletion.
 */
class User_Deleted extends Abstract_Incoming_Event {
	/**
	 * Process event in Node
	 *
	 * @return void
	 */
	public function process_in_node() {
		$this->process_user_deleted();
	}

	/**
	 * Process user deleted
	 *
	 * @return void
	 */
	public function process_user_deleted() {
		$email = $this->get_email();
		Debugger::log( 'Processing user deletion with email: ' . $email );
		if ( ! $email ) {
			return;
		}
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			Debugger::log( sprintf( 'User to be deleted not found by email: %s, skipping.', $email ) );
			return;
		}

		// Ensure this is a network reader.
		$userdata = get_userdata( $user->ID );
		if ( [ NEWSPACK_NETWORK_READER_ROLE ] !== $userdata->roles ) {
			Debugger::log( sprintf( 'User %s is not only or not a network reader, skipping deletion.', $email ) );
			return;
		}
		// Delete the user.
		$result = wp_delete_user( $user->ID );
		if ( $result ) {
			Debugger::log( sprintf( 'User %s deleted.', $email ) );
		} else {
			Debugger::log( sprintf( 'User %s could not be deleted.', $email ) );
		}
	}
}
