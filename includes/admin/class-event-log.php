<?php
/**
 * Newspack Hub Event Log Admin page
 *
 * @package Newspack
 */

namespace Newspack_Hub\Admin;

use Newspack_Hub\Admin;

/**
 * Class to handle the Event log admin page
 */
class Event_Log {

	const PAGE_SLUG = 'newspack-hub-event-log';

	/**
	 * Runs the initialization.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
	}
	
	/**
	 * Adds the admin page
	 *
	 * @return void
	 */
	public static function add_admin_menu() {
		Admin::add_submenu_page( __( 'Event Log', 'newspack-network-hub' ), self::PAGE_SLUG, [ __CLASS__, 'render_page' ] );
	}

	/**
	 * Renders the admin page
	 *
	 * @return void
	 */
	public static function render_page() {

		$table = new Event_Log_List_Table();

		echo '<div class="wrap"><h2>', esc_html( __( 'Event Log', 'newspack-network-hub' ) ), '</h2>';
		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '" />';
		
		$table->prepare_items();

		$table->search_box( 'search', 'search_id' );
		
		$table->display();
		
		echo '</div></form>';
	}
}
