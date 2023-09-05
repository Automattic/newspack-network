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
		Debugger::log( 'Processing donation_new with email: ' . $email );
		if ( ! $email ) {
			return;
		}
		$existing_user = get_user_by( 'email', $email );

		if ( ! $existing_user ) {
			// Create user.
			$user_id = wp_insert_user(
				[
					'user_email' => $email,
					'user_login' => $email,
					'user_pass'  => wp_generate_password(),
					'role'       => NEWSPACK_NETWORK_READER_ROLE,
				]
			);
			if ( is_wp_error( $user_id ) ) {
				Debugger::log( 'Error creating user: ' . $user_id->get_error_message() );
				return;
			}
			$existing_user = get_user_by( 'id', $user_id );
		}

		$node_id            = $this->get_node_id();
		$recurrence         = $this->get_data()->recurrence;
		$network_donor_data = \Newspack\Reader_Data::get_data( $existing_user->ID, 'network_donor' );

		if ( $network_donor_data ) {
			$network_donor_data = json_decode( $network_donor_data, true );
		} else {
			$network_donor_data = [];
		}
		if ( ! isset( $network_donor_data[ $node_id ] ) ) {
			$network_donor_data[ $node_id ] = [];
		}
		$network_donor_data[ $node_id ][ $this->get_timestamp() ] = $recurrence;
		\Newspack\Reader_Data::update_item( $existing_user->ID, 'network_donor', wp_json_encode( $network_donor_data ) );
		Debugger::log( 'Updated ' . $email . ' network donor status with "' . $recurrence . '" for node ' . $node_id );
	}

}
