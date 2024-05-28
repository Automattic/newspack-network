<?php
/**
 * Newspack Hub plugin administration screen handling.
 *
 * @package Newspack
 */

namespace Newspack_Network\Hub;

/**
 * Class to handle the plugin admin pages
 */
class Admin {

	/**
	 * Runs the initialization.
	 */
	public static function init() {
		Admin\Event_Log::init();
		Admin\Membership_Plans::init();
		if ( defined( 'NEWSPACK_NETWORK_EXPERIMENTAL_SUBSCRIPTIONS' ) && NEWSPACK_NETWORK_EXPERIMENTAL_SUBSCRIPTIONS ) {
			Admin\Subscriptions::init();
		}
		Admin\Nodes_List::init();
		Distributor_Settings::init();
	}
}
