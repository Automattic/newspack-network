<?php
/**
 * Newspack Hub plugin initialization.
 *
 * @package Newspack
 */

namespace Newspack_Hub;

/**
 * Class to handle the plugin initialization
 */
class Initializer {

	/**
	 * Runs the initialization.
	 */
	public static function init() {
		Admin::init();
		Nodes::init();
		Webhook::init();
	}

}
