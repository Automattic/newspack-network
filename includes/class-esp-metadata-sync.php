<?php
/**
 * Newspack Hub ESP Metadata settings
 *
 * @package Newspack
 */

namespace Newspack_Network;

/**
 * Class to handle Node settings page
 */
class Esp_Metadata_Sync {

	/**
	 * Initialize this class and register hooks
	 *
	 * @return void
	 */
	public static function init() {
		\add_filter( 'newspack_ras_metadata_keys', [ __CLASS__, 'add_custom_metadata_fields' ] );
		\add_filter( 'newspack_register_reader_metadata', [ __CLASS__, 'handle_custom_metadata_fields' ], 10, 2 );
	}

	/**
	 * Adds Network-specific metadata fields to the list of ESP metadata fields.
	 *
	 * @param array $metadata_fields The list of ESP metadata fields.
	 *
	 * @return array The updated list of ESP metadata fields.
	 */
	public static function add_custom_metadata_fields( $metadata_fields ) {
		if ( ! isset( $metadata_fields['network_registration_site'] ) ) {
			$metadata_fields['network_registration_site'] = 'Network Registration Site';
		}

		return $metadata_fields;
	}

	/**
	 * Add handling for custom metadata fields. Only fire for newly created users.
	 *
	 * @param array     $metadata The contact metadata data.
	 * @param int|false $user_id Created user ID, or false if the user already exists.
	 *
	 * @return array The updated contact data.
	 */
	public static function handle_custom_metadata_fields( $metadata, $user_id ) {
		if ( $user_id ) {
			$metadata['network_registration_site'] = \esc_url( \get_site_url() );
		}

		return $metadata;
	}
}
