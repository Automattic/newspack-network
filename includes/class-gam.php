<?php
/**
 * Newspack Ads GAM Integration.
 *
 * Implements support for custom targeting key-val pairs for each site in the
 * network. The ad slots are then targeted to the site's URL.
 *
 * @package Newspack
 */

namespace Newspack_Network;

use Newspack_Ads\Providers\GAM\Api as GAM_API;
use Newspack_Ads\Providers\GAM_Model;

/**
 * Integration class for Newspack Ads' GAM support.
 */
final class GAM {
	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'newspack_ads_setup_gam', [ __CLASS__, 'create_targeting_keys' ] );
		add_action( 'save_post_' . Hub\Nodes::POST_TYPE_SLUG, [ __CLASS__, 'create_targeting_keys' ] );
		add_filter( 'newspack_ads_ad_targeting', [ __CLASS__, 'add_targeting' ], 10, 2 );
	}

	/**
	 * Create targeting keys.
	 */
	public static function create_targeting_keys() {
		if ( ! Site_Role::is_hub() ) {
			return;
		}
		if ( ! class_exists( 'Newspack_Ads\Providers\GAM_Model' ) ) {
			return;
		}
		$api = GAM_Model::get_api();
		if ( ! $api || is_wp_error( $api ) ) {
			Debugger::log( 'Error adding GAM targeting keys: GAM API is not available.' );
			return;
		}
		$nodes     = Hub\Nodes::get_all_nodes();
		$node_urls = array_map(
			function( $node ) {
				return $node->get_url();
			},
			$nodes
		);
		$urls      = array_merge( [ \get_site_url() ], $node_urls );
		$api->targeting_keys->create_targeting_key( 'site', $urls, 'PREDEFINED', 'CUSTOM_DIMENSION' );
		Debugger::log( 'Updated GAM targeting keys.' );
	}

	/**
	 * Add targeting.
	 *
	 * @param array $targeting Targeting.
	 * @param array $ad_unit   Ad unit.
	 *
	 * @return array
	 */
	public static function add_targeting( $targeting, $ad_unit ) {
		$targeting['site'] = \get_site_url();
		return $targeting;
	}
}
