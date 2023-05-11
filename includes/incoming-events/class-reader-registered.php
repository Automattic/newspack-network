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
		// @TODO create the user.
	}
}
