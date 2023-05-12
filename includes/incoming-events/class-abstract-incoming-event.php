<?php
/**
 * Newspack Hub Abstract Connector class
 *
 * @package Newspack
 */

namespace Newspack_Hub\Incoming_Events;

use Newspack_Hub\Debugger;
use Newspack_Hub\Node;
use Newspack_Hub\Stores\Event_Log;

/**
 * Class to handle the plugin admin pages
 */
abstract class Abstract_Incoming_Event {

	/**
	 * The action name. Child classes should set this.
	 *
	 * @var string
	 */
	public $action_name;

	/**
	 * The Node object for this event
	 *
	 * @var Node
	 */
	protected $node;

	/**
	 * The data for this event
	 *
	 * @var array
	 */
	protected $data;

	/**
	 * The timestamp for this event
	 *
	 * @var int
	 */
	protected $timestamp;

	/**
	 * Constructs a new Incoming Event
	 *
	 * @param Node  $node      The Node object for this event.
	 * @param array $data      The data for this event.
	 * @param int   $timestamp The timestamp for this event.
	 */
	public function __construct( Node $node, $data, $timestamp ) {
		$this->node      = $node;
		$this->data      = $data;
		$this->timestamp = $timestamp;
	}

	/**
	 * Processes the event
	 *
	 * @return void
	 */
	public function process() {
		Debugger::log( 'Processing event' );
		Event_Log::persist( $this );
		$this->post_process();
	}

	/**
	 * Child classes should implement this method to do any post-processing
	 *
	 * @return void
	 */
	abstract public function post_process();

	/**
	 * Returns the Node object for this event
	 *
	 * @return Node
	 */
	public function get_node() {
		return $this->node;
	}

	/**
	 * Returns the data for this event
	 *
	 * @return array
	 */ 
	public function get_data() {
		return $this->data;
	}

	/**
	 * Returns the timestamp for this event
	 *
	 * @return int
	 */
	public function get_timestamp() {
		return $this->timestamp;
	}

	/**
	 * Returns the action name for this event
	 *
	 * @return string
	 */
	public function get_action_name() {
		return $this->action_name;
	}

	/**
	 * Returns the Node ID for this event
	 *
	 * @return string
	 */
	public function get_node_id() {
		return $this->node->get_id();
	}

	/**
	 * Returns the site name for this event
	 *
	 * @return string
	 */
	public function get_email() {
		return $this->data->email ?? '';
	}
	
}
