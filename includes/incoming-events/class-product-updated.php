<?php
/**
 * Newspack Hub Product Updated Incoming Event class
 *
 * @package Newspack
 */

namespace Newspack_Network\Incoming_Events;

use Newspack_Network\Debugger;

/**
 * Class to handle the Product Updated Incoming Event
 */
class Product_Updated extends Abstract_Incoming_Event {

	const OPTION_NAME = 'newspack_network_products';

	/**
	 * Processes the event in Hub.
	 *
	 * @return void
	 */
	public function post_process_in_hub() {
		$this->update_option();
	}

	/**
	 * Process event in Node.
	 *
	 * @return void
	 */
	public function process_in_node() {
		$this->update_option();
	}

	/**
	 * Updates the option with the product data.
	 *
	 * @return void
	 */
	public function update_option() {
		Debugger::log( 'Processing product_updated' );

		$current_value = get_option( self::OPTION_NAME, [] );

		if ( ! is_array( $current_value ) ) {
			$current_value = [];
		}

		if ( ! isset( $current_value[ $this->get_site() ] ) ) {
			$current_value[ $this->get_site() ] = [];
		}

		$current_value[ $this->get_site() ][ $this->get_id() ] = [
			'id'         => $this->get_id(),
			'name'       => $this->get_name(),
			'slug'       => $this->get_slug(),
			'network_id' => $this->get_network_id(),
		];

		update_option( self::OPTION_NAME, $current_value );
	}

	/**
	 * Returns the id property.
	 *
	 * @return ?int
	 */
	public function get_id() {
		return $this->data->id ?? null;
	}

	/**
	 * Returns the name property.
	 *
	 * @return ?string
	 */
	public function get_name() {
		return $this->data->name ?? null;
	}

	/**
	 * Returns the slug property.
	 *
	 * @return ?string
	 */
	public function get_slug() {
		return $this->data->slug ?? null;
	}

	/**
	 * Returns the network_id property.
	 *
	 * @return ?string
	 */
	public function get_network_id() {
		return $this->data->network_id ?? null;
	}
}
