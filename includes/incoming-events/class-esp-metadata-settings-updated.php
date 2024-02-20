<?php
/**
 * Newspack ESP Metadta Settings Updated Incoming Event class
 *
 * @package Newspack
 */

namespace Newspack_Network\Incoming_Events;

use Newspack_Network\Esp_Metadata_Sync;

/**
 * Class to handle the ESP Metadta Settings Updated Event
 *
 * This event is always sent from the Hub and received by Nodes.
 */
class Esp_Metadata_Settings_Updated extends Abstract_Incoming_Event {

	/**
	 * Process event in Node
	 *
	 * @return void
	 */
	public function process_in_node() {
		update_option( Esp_Metadata_Sync::OPTION_NAME, $this->get_data()->value );
	}
}
