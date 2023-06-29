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
		Admin\Subscriptions::init();
		Admin\Orders::init();
		Admin\Users::init();
		Distributor_Settings::init();
	}

}
