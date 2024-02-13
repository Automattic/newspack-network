<?php
/**
 * Newspack Network Distributor Customizations.
 *
 * @package Newspack
 */

namespace Newspack_Network;

/**
 * Class to initialize the customizations we do to the Distributor plugin
 */
class Distributor_Customizations {

	/**
	 * Initializes the customizations.
	 *
	 * @return void
	 */
	public static function init() {
		Distributor_Customizations\Base::init();
		Distributor_Customizations\Canonical_Url::init();
		Distributor_Customizations\Author_Distribution::init();
		Distributor_Customizations\Author_Ingestion::init();
		Distributor_Customizations\Authorship_Filters::init();
	}
}
