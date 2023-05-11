<?php
/**
 * Newspack Hub Event Log database
 *
 * @package Newspack
 */

namespace Newspack_Hub\Database;

use Newspack_Hub\Debugger;

/**
 * Class to handle the plugin admin pages
 */
class Event_Log {

	/**
	 * The database version
	 *
	 * @var int
	 */
	const DB_VERSION = 1;

	/**
	 * Returns the table name
	 *
	 * @return string
	 */
	public static function get_table_name() {
		self::maybe_update_db();
		global $wpdb;
		return $wpdb->prefix . 'newspack_hub_event_log';
	}

	/**
	 * Returns the current option name
	 *
	 * @return string
	 */
	protected static function get_current_option_name() {
		return 'newspack_db_version_event_log';
	}

	/**
	 * Updates the database if needed
	 *
	 * @return void
	 */
	protected static function maybe_update_db() {
		$db_version = get_option( self::get_current_option_name(), 0 );
		update_option( self::get_current_option_name(), self::DB_VERSION );
		if ( 0 === $db_version ) {
			self::create_db();
		}
	}

	/**
	 * Creates the database
	 *
	 * @return void
	 */
	protected static function create_db() {
		Debugger::log( 'Creating database' );
		global $wpdb;
		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            action_name varchar(100) NOT NULL,
            site varchar(255) NOT NULL,
            email varchar(100) NULL,
            data text NOT NULL,
            timestamp int(11) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
		$wpdb->query( $sql ); //phpcs:ignore
	}

}
