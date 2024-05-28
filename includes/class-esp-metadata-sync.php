<?php
/**
 * Newspack Hub ESP Metadata settings
 *
 * @package Newspack
 */

namespace Newspack_Network;

use Newspack_Network\Utils\Users as User_Utils;

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
		\add_filter( 'newspack_data_events_reader_registered_metadata', [ __CLASS__, 'handle_custom_metadata_fields' ], 10, 2 );
		\add_action( 'newspack_network_network_reader', [ __CLASS__, 'handle_custom_metadata_for_network_readers' ] );
		\add_action( 'newspack_network_new_network_reader', [ __CLASS__, 'handle_custom_metadata_for_network_readers' ] );
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
			$remote_site       = \get_user_meta( $user_id, User_Utils::USER_META_REMOTE_SITE, true );
			$registration_site = \esc_url( ! empty( \wp_http_validate_url( $remote_site ) ) ? $remote_site : \get_site_url() );
			$metadata['network_registration_site'] = $registration_site;
		}

		return $metadata;
	}

	/**
	 * Trigger a reader data sync to the connected ESP.
	 *
	 * @param array $contact The contact data to sync.
	 */
	public static function sync_contact( $contact ) {
		// Only if Reader Activation and Newspack Newsletters are available.
		if ( ! class_exists( 'Newspack\Reader_Activation' ) || ! method_exists( 'Newspack_Newsletters', 'service_provider' ) ) {
			return;
		}

		// Only if RAS + ESP sync is enabled.
		if ( ! \Newspack\Reader_Activation::is_enabled() || ! \Newspack\Reader_Activation::get_setting( 'sync_esp' ) ) {
			return;
		}

		// Only if we have the ESP Data Events connectors.
		if ( ! class_exists( 'Newspack\Data_Events\Connectors\Mailchimp' ) || ! class_exists( 'Newspack\Data_Events\Connectors\ActiveCampaign' ) ) {
			return;
		}

		$service_provider = \Newspack_Newsletters::service_provider();
		if ( 'mailchimp' === $service_provider ) {
			return \Newspack\Data_Events\Connectors\Mailchimp::put( $contact );
		} elseif ( 'active_campaign' === $service_provider ) {
			return \Newspack\Data_Events\Connectors\ActiveCampaign::put( $contact );
		}
	}

	/**
	 * Sync custom metadata fields for network readers.
	 *
	 * @param WP_User $user The newly created or existing user.
	 */
	public static function handle_custom_metadata_for_network_readers( $user ) {
		if ( ! $user ) {
			return;
		}
		$contact  = \Newspack\WooCommerce_Connection::get_contact_from_customer( new \WC_Customer( $user->ID ) );
		$metadata = $contact['metadata'] ?? [];

		// Ensure email is set as the user probably won't have a billing email.
		if ( ! isset( $contact['email'] ) ) {
			$contact['email'] = $user->user_email;
		}

		$contact['metadata'] = self::handle_custom_metadata_fields( $metadata, $user->ID );

		self::sync_contact( $contact );
	}
}
