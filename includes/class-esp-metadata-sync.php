<?php
/**
 * Newspack Network class to filter the fields synced by Newspack to the ESP.
 *
 * @package Newspack
 */

namespace Newspack_Network;

use Newspack\Data_Events;

/**
 * Class to handle the ESP metadata sync
 */
class Esp_Metadata_Sync {

	/**
	 * The Newspack ESP metadata option name
	 *
	 * @var string
	 */
	const OPTION_NAME = 'newspack_esp_metadata';

	/**
	 * Gets the current value of the Newspack ESP metadata option.
	 *
	 * @return array|string The string 'default' or an array with metadata field slugs.
	 */
	public static function get_option() {
		$option = get_option( self::OPTION_NAME );
		if ( ! $option || ! is_array( $option ) ) {
			return 'default';
		}
		return $option;
	}

	/**
	 * Checks if the Newspack ESP metadata option is set to the default value.
	 *
	 * @return boolean
	 */
	public static function is_default() {
		return 'default' === self::get_option();
	}

	/**
	 * Initialize this class and register hooks
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_listener' ] );
	}

	/**
	 * Register the listener to the Newspack Data Events API
	 *
	 * We will only register the listener if the site role is hub, as this option can only be set by the hub.
	 *
	 * @return void
	 */
	public static function register_listener() {
		if ( ! class_exists( 'Newspack\Data_Events' ) ) {
			return;
		}

		if ( Site_Role::is_hub() ) {
			Data_Events::register_listener( 'update_option_' . self::OPTION_NAME, 'esp_metadata_settings_updated', [ __CLASS__, 'dispatch_esp_metadata_settings_updated' ] );
		}
	}

	/**
	 * Dispatches the esp_metadata_settings_updated event data
	 *
	 * @param mixed $old_value The old value.
	 * @param mixed $value The new value.
	 * @return array
	 */
	public static function dispatch_esp_metadata_settings_updated( $old_value, $value ) {
		return [
			'value' => $value,
		];
	}
}
