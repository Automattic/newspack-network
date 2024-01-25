<?php
/**
 * Newspack Hub Newsletter Subscribed Incoming Event class
 *
 * @package Newspack
 */

namespace Newspack_Network\Incoming_Events;

use Newspack_Network\Debugger;
use Newspack_Network\Utils\Users as User_Utils;

/**
 * Class to handle the Newsletter Subscribed Event
 */
class Newsletter_Subscribed extends Abstract_Incoming_Event {

	/**
	 * Processes the event
	 *
	 * @return void
	 */
	public function post_process_in_hub() {
		$this->process_subscription();
	}

	/**
	 * Process event in Node
	 *
	 * @return void
	 */
	public function process_in_node() {
		$this->process_subscription();
	}

	/**
	 * Process process_subscription
	 *
	 * @return void
	 */
	public function process_subscription() {
		$email = $this->get_email();
		Debugger::log( 'Processing newsleteter_subscribed with email: ' . $email );
		if ( ! $email ) {
			return;
		}

		$existing_user = User_Utils::get_or_create_user_by_email( $email, $this->get_site(), $this->data->user_id ?? '' );

		if ( is_wp_error( $existing_user ) ) {
			return;
		}

		$node                               = $this->get_site();
		$network_newsletter_subscriber_data = \Newspack\Reader_Data::get_data( $existing_user->ID, 'network_newsletter_subscriber' );

		if ( $network_newsletter_subscriber_data ) {
			$network_newsletter_subscriber_data = json_decode( $network_newsletter_subscriber_data, true );
		} else {
			$network_newsletter_subscriber_data = [];
		}
		if ( ! isset( $network_newsletter_subscriber_data[ $node ] ) ) {
			$network_newsletter_subscriber_data[ $node ] = [];
		}
		$network_newsletter_subscriber_data[ $node ][ $this->get_timestamp() ] = [
			'subscribed' => $this->get_data()->lists,
		];
		\Newspack\Reader_Data::update_item( $existing_user->ID, 'network_newsletter_subscriber', wp_json_encode( $network_newsletter_subscriber_data ) );
		Debugger::log( 'Updated ' . $email . ' network newsletter subscriber status with for node ' . $node );
	}

}
