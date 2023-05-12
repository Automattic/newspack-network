<?php
/**
 * Newspack Hub Reader Registered Incoming Event class
 *
 * @package Newspack
 */

namespace Newspack_Hub\Incoming_Events;

use Newspack_Hub\Node;
use Newspack_Hub\Stores\Event_Log;

/**
 * Class to handle the Registered Incoming Event
 */
class Reader_Registered extends Abstract_Incoming_Event {
	
	/**
	 * The action name.
	 *
	 * @var string
	 */
	public $action_name = 'reader_registered';
	
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
		$existing_user = get_user_by( 'email', $email );

		if ( $existing_user ) {
			return;
		}

		$user_id = wp_insert_user(
			[
				'user_email' => $email,
				'user_login' => $email,
				'user_pass'  => wp_generate_password(),
				'role'       => 'network_reader',
			]
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		add_user_meta( $user_id, 'newspack_hub_node_id', $this->get_node_id() );
		
	}
}
