<?php
/**
 * Newspack Hub Accepted Actions.
 *
 * @package Newspack
 */

namespace Newspack_Network;

/**
 * This class holds the actions this Hub will accept from other sites.
 *
 * The class names will be used to instantiate the appropriate classes for each action type.
 */
class Accepted_Actions {

	/**
	 * Get the accepted actions
	 *
	 * @var array Array where the keys are the supported events and the values are the Incoming Events class names
	 */
	const ACTIONS = [
		'reader_registered'                  => 'Reader_Registered',
		'newspack_node_order_changed'        => 'Order_Changed',
		'newspack_node_subscription_changed' => 'Subscription_Changed',
		'canonical_url_updated'              => 'Canonical_Url_Updated',
	];
}
