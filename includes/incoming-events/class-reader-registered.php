<?php
/**
 * Newspack Hub Reader Registered Incoming Event class
 *
 * @package Newspack
 */

namespace Newspack_Network\Incoming_Events;

use Newspack_Network\Debugger;
use Newspack_Network\Hub\Node;
use Newspack_Network\Hub\Stores\Event_Log;

/**
 * Class to handle the Registered Incoming Event
 */
class Reader_Registered extends Abstract_Incoming_Event {
	
	/**
	 * Processes the event
	 *
	 * @return void
	 */
	public function post_process_in_hub() {
		$this->maybe_create_user();
	}

	/**
	 * Process event in Node
	 *
	 * @return void
	 */
	public function process_in_node() {
		$this->maybe_create_user();
	}

	/**
	 * Maybe creates a new WP user based on this event
	 *
	 * @return void
	 */
	public function maybe_create_user() {
		$email = $this->get_email();
		Debugger::log( 'Processing reader_registered with email: ' . $email );
		if ( ! $email ) {
			return;
		}
		$existing_user = get_user_by( 'email', $email );

		if ( $existing_user ) {
			Debugger::log( 'User already exists' );
			return;
		}

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
			return $user_id;
		}

		Debugger::log( 'User created with ID: ' . $user_id );

		add_user_meta( $user_id, 'newspack_remote_site', $this->get_site() );
		add_user_meta( $user_id, 'newspack_remote_id', $this->data->user_id ?? '' );
	}

}
