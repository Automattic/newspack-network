<?php
/**
 * Newspack ESP Metadata Settings Updated Log Item
 *
 * @package Newspack
 */

namespace Newspack_Network\Hub\Stores\Event_Log_Items;

use Newspack_Network\Hub\Stores\Abstract_Event_Log_Item;

/**
 * Class to handle the ESP Metadata Settings Updated Log Item
 */
class Esp_Metadata_Settings_Updated extends Abstract_Event_Log_Item {

	/**
	 * Gets a summary for this event
	 *
	 * @return string
	 */
	public function get_summary() {
		return __( 'ESP Metadata settings updated', 'newspack-network' );
	}
}
