<?php
/**
 * Newspack Hub plugin initialization.
 *
 * @package Newspack
 */

namespace Newspack_Network;

/**
 * Class to handle the plugin initialization
 */
class Initializer {

	/**
	 * Runs the initialization.
	 */
	public static function init() {
		Hub\Admin::init();
		Hub\Nodes::init();
		Hub\Webhook::init();
		Hub\Database\Subscriptions::init();
		Hub\Database\Orders::init();

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
