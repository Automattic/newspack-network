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
class Donation_Subscription_Cancelled extends Abstract_Incoming_Event {

	/**
	 * Process event in Node
	 *
	 * @return void
	 */
	public function process_in_node() {
		$email = $this->get_email();
		Debugger::log( 'Processing donation_new with email: ' . $email );
		if ( ! $email ) {
			return;
		}
		$existing_user = get_user_by( 'email', $email );

		if ( ! $existing_user ) {
			Debugger::log( 'User not found' );
			return;
		}

		$node_id            = $this->get_node_id();
		$network_donor_data = \Newspack\Reader_Data::get_data( $existing_user->ID, 'network_donor' );

		if ( $network_donor_data ) {
			$network_donor_data = json_decode( $network_donor_data, true );
		} else {
			$network_donor_data = [];
		}
		if ( ! isset( $network_donor_data[ $node_id ] ) ) {
			$network_donor_data[ $node_id ] = [];
		}
		$network_donor_data[ $node_id ][ $this->get_timestamp() ] = 'subscription_cancelled';
		\Newspack\Reader_Data::update_item( $existing_user->ID, 'network_donor', wp_json_encode( $network_donor_data ) );
		Debugger::log( 'Updated ' . $email . ' network donor status with "subscription_cancelled" for node ' . $node_id );
	}

}
