<?php
/**
 * Newspack Nodes Synced Incoming Event class
 *
 * @package Newspack
 */

namespace Newspack_Network\Incoming_Events;

use Newspack_Network\Debugger;

/**
 * Class to handle the Nodes Synced Event
 *
 * This event is always sent from the Hub and received by Nodes.
 */
class Nodes_Synced extends Abstract_Incoming_Event {
	/**
	 * Process event in Node
	 *
	 * @return void
	 */
	public function process_in_node() {
		Debugger::log( 'Processing nodes_synced event.' );
		// Get data passed for node urls.
		$data = $this->get_data();
		// If the data is empty, return early.
		if ( empty( $data ) ) {
			Debugger::log( 'No data passed for nodes_synced event.' );
			return;
		}

		foreach ( $data as $key => $node ) {
			if ( $data['url'] === get_site_url() ) {
				unset( $data[ $key ] );
			}
		}

		$updated = update_option( 'newspack_hub_nodes_synced', $data );

		if ( $updated ) {
			Debugger::log( 'Nodes synced event processed.' );
		} else {
			Debugger::log( 'Error processing nodes_synced event.' );
		}
	}
}
