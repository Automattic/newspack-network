<?php
/**
 * Newspack Hub Event Log Store
 *
 * @package Newspack
 */

namespace Newspack_Hub\Stores;

use Newspack_Hub\Debugger;
use Newspack_Hub\Incoming_Events\Abstract_Incoming_Event;
use Newspack_Hub\Database\Event_Log as Database;

/**
 * Class to handle Event Log Store
 */
class Event_Log {
	
	/**
	 * Persists an event to the database
	 *
	 * @param Abstract_Incoming_Event $event The Incoming Event to be persisted.
	 * @return int|false The ID of the inserted row, or false on failure.
	 */
	public static function persist( Abstract_Incoming_Event $event ) {
		global $wpdb;
		Debugger::log( 'Persisting Event' );
		$insert = $wpdb->insert( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			Database::get_table_name(),
			[
				'node_id'     => $event->get_node_id(),
				'action_name' => $event->get_action_name(),
				'email'       => $event->get_email(),
				'data'        => wp_json_encode( $event->get_data() ),
				'timestamp'   => $event->get_timestamp(),
			]
		);
		Debugger::log( $insert );
		if ( ! $insert ) {
			return false;
		}
		return $wpdb->insert_id;
	}
}
