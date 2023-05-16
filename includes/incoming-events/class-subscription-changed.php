<?php
/**
 * Newspack Hub Subscription Changed Incoming Event class
 *
 * @package Newspack
 */

namespace Newspack_Hub\Incoming_Events;

use Newspack_Hub\Node;
use Newspack_Hub\Stores\Subscriptions;

/**
 * Class to handle the Subscription Changed Incoming Event
 */
class Subscription_Changed extends Abstract_Incoming_Event {
	
	/**
	 * Returns the subscription_id property
	 *
	 * @return ?int
	 */
	public function get_id() {
		return $this->data->id ?? null;
	}

	/**
	 * Returns the status_before property
	 *
	 * @return ?string
	 */
	public function get_status_before() {
		return $this->data->status_before ?? null;
	}

	/**
	 * Returns the status_after property
	 *
	 * @return ?string
	 */
	public function get_status_after() {
		return $this->data->status_after ?? null;
	}

	/**
	 * Returns the user_email property
	 *
	 * @return ?string
	 */
	public function get_user_name() {
		return $this->data->user_name ?? null;
	}

	/**
	 * Returns the payment_count property
	 *
	 * @return ?string
	 */
	public function get_payment_count() {
		return $this->data->payment_count ?? null;
	}

	/**
	 * Returns the formatted_total property
	 *
	 * @return ?string
	 */
	public function get_formatted_total() {
		return $this->data->formatted_total ?? null;
	}

	/**
	 * Processes the event
	 *
	 * @return void
	 */
	public function post_process() {
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
