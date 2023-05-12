<?php
/**
 * Newspack Hub Accepted Actions.
 *
 * @package Newspack
 */

namespace Newspack_Hub;

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
		'reader_registered' => 'Reader_Registered',
	];
}
