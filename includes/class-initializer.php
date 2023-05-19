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
		Database\Subscriptions::init();
		Database\Orders::init();

		register_activation_hook( NEWSPACK_HUB_PLUGIN_FILE, [ __CLASS__, 'activation_hook' ] );
	}

	/**
	 * Runs on plugin activation.
	 *
	 * @return void
	 */
	public static function activation_hook() {
		add_role( 'network_reader', __( 'Network Reader', 'newspack-hub' ) ); // phpcs:ignore
	}

}
