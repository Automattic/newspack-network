<?php
/**
 * Newspack Hub Order Event Log Item
 *
 * @package Newspack
 */

namespace Newspack_Network\Hub\Stores\Event_Log_Items;

use Newspack_Network\Hub\Stores\Abstract_Event_Log_Item;

/**
 * Class to handle the Order Event Log Item
 */
class Order_Changed extends Abstract_Event_Log_Item {

	/**
	 * Gets a summary for this event
	 *
	 * @return string
	 */
	public function get_summary() {
		return sprintf(
			/* translators: 1: Order ID 2: Previous status, 3: New status, 4: site url */
			__( 'Order #%1$d updated its status from %2$s to %3$s on %4$s', 'newspack-network-hub' ),
			$this->get_data()->id,
			$this->get_data()->status_before,
			$this->get_data()->status_after,
			$this->get_node_url()
		);
	}
}
