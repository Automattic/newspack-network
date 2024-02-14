<?php
/**
 * Newspack Hub Subscription Changed Incoming Event class
 *
 * @package Newspack
 */

namespace Newspack_Network\Incoming_Events;

use Newspack_Network\Hub\Stores\Subscriptions;

/**
 * Class to handle the Subscription Changed Incoming Event
 */
class Subscription_Changed extends Woo_Item_Changed {

	/**
	 * Processes the event
	 *
	 * @return void
	 */
	public function always_process_in_hub() {
		$email = $this->get_email();
		if ( ! $email ) {
			return;
		}

		$subscription_id = $this->get_id();

		if ( ! $subscription_id ) {
			return;
		}

		Subscriptions::persist( $this );

	}


}
