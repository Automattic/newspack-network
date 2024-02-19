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
		Admin::init();

		if ( Site_Role::is_hub() ) {
			Hub\Admin::init();
			Hub\Nodes::init();
			Hub\Webhook::init();
			Hub\Pull_Endpoint::init();
			Hub\Event_Listeners::init();
			Hub\Database\Subscriptions::init();
			Hub\Database\Orders::init();
			Hub\Newspack_Ads_GAM::init();
		}

		// Allow to access node settings before the site has a role, so it can be set via URL.
		if ( Site_Role::is_node() || ! Site_Role::get() ) {
			Node\Settings::init();
		}
		if ( Site_Role::is_node() ) {
			if ( Node\Settings::get_hub_url() ) {
				Node\Webhook::init();
				Node\Pulling::init();
				Rest_Authenticaton::init_node_filters();
			}
		}

		Data_Listeners::init();
		Reader_Roles_Filter::init();
		User_Update_Watcher::init();
		Distributor_Customizations::init();

		register_activation_hook( NEWSPACK_NETWORK_PLUGIN_FILE, [ __CLASS__, 'activation_hook' ] );
	}

	/**
	 * Runs on plugin activation.
	 *
	 * @return void
	 */
	public static function activation_hook() {
		add_role( NEWSPACK_NETWORK_READER_ROLE, __( 'Network Reader', 'newspack-network' ) ); // phpcs:ignore
	}
}
