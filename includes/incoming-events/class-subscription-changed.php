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

	/**
	 * Returns the start_date property
	 *
	 * @return ?string
	 */
	public function get_start_date() {
		return $this->data->start_date ?? null;
	}

	/**
	 * Returns the trial_end_date property
	 *
	 * @return ?string
	 */
	public function get_trial_end_date() {
		return $this->data->trial_end_date ?? null;
	}

	/**
	 * Returns the next_payment_date property
	 *
	 * @return ?string
	 */
	public function get_next_payment_date() {
		return $this->data->next_payment_date ?? null;
	}

	/**
	 * Returns the last_payment_date property
	 *
	 * @return ?string
	 */
	public function get_last_payment_date() {
		return $this->data->last_payment_date ?? null;
	}

	/**
	 * Returns the end_date property
	 *
	 * @return ?string
	 */
	public function get_end_date() {
		return $this->data->end_date ?? null;
	}
}
