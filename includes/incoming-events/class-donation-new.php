<?php
/**
 * Newspack Hub Canonical Url Updated Incoming Event class
 *
 * @package Newspack
 */

namespace Newspack_Network\Incoming_Events;

use Newspack_Network\Debugger;
use Newspack_Network\Node\Canonical_Url;

/**
 * Class to handle the Canonical Url Updated Event
 *
 * This event is always sent from the Hub and received by Nodes.
 */
class Donation_New extends Abstract_Incoming_Event {   

	/**
	 * Process event in Node
	 *
	 * @return void
	 */
	public function process_in_node() {
		$email = $this->get_email();
		Debugger::log( 'Processing reader_registered with email: ' . $email );
		if ( ! $email ) {
			return;
		}
		$existing_user = get_user_by( 'email', $email );
		
		if ( ! $existing_user ) {
			Debugger::log( 'User not found' );
			return;
		}
		
		Debugger::log( 'User marked as Network donor: ' . $email );
		\Newspack\Reader_Data::update_item( $existing_user->ID, 'network_donor', wp_json_encode( true ) );

	}

}
