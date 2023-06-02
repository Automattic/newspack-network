<?php
/**
 * Newspack Hub Abstract Connector class
 *
 * @package Newspack
 */

namespace Newspack_Network\Incoming_Events;

use Newspack_Network\Accepted_Actions;
use Newspack_Network\Debugger;
use Newspack_Network\Hub\Nodes;
use Newspack_Network\Hub\Node;
use Newspack_Network\Hub\Stores\Event_Log;

/**
 * Class to handle the plugin admin pages
 */
abstract class Abstract_Incoming_Event {

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
	 * @param string       $site      The origin site URL.
	 * @param array|object $data      The data for this event.
	 * @param int          $timestamp The timestamp for this event.
	 */
	public function __construct( $site, $data, $timestamp ) {
		$this->site      = $site;
		$this->data      = (object) $data;
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
	 * Returns the site for this event
	 *
	 * @return string
	 */
	public function get_site() {
		return $this->site;
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
		$class      = new \ReflectionClass( $this );
		$class_name = $class->getShortName();
		return array_search( $class_name, Accepted_Actions::ACTIONS );
	}

	/**
	 * Returns the site name for this event
	 *
	 * @return string
	 */
	public function get_email() {
		return $this->data->email ?? '';
	}

	/**
	 * Get this event's Node object. Will only work on the Hub
	 *
	 * @return ?Node
	 */
	public function get_node() {
		return Nodes::get_node_by_url( $this->site );
	}

}
