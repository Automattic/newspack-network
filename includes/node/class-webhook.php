<?php
/**
 * Newspack Network Node webhook handler.
 *
 * @package Newspack
 */

namespace Newspack_Network\Node;

use Newspack_Network\Crypto;
use Newspack\Data_Events\Webhooks as Newspack_Webhooks;

/**
 * Class that register the webhook endpoint that will send events to the Hub
 */
class Webhook {

	/**
	 * The endpoint ID.
	 *
	 * @var string
	 */
	const ENDPOINT_ID = 'newspack-network-node';

	/**
	 * The endpoint URL suffix.
	 *
	 * @var string
	 */
	const ENDPOINT_SUFFIX = 'wp-json/newspack-network/v1/webhook';

	/**
	 * Runs the initialization.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_endpoint' ] );
		add_filter( 'newspack_webhooks_request_body', [ __CLASS__, 'filter_webhook_body' ], 10, 2 );
	}

	/**
	 * Registers the endpoint.
	 *
	 * @return void
	 */
	public static function register_endpoint() {
		if ( ! class_exists( 'Newspack\Data_Events\Webhooks' ) || ! method_exists( 'Newspack\Data_Events\Webhooks', 'register_system_endpoint' ) ) {
			return;
		}
		$events = [
			'reader_registered',
			'newspack_node_order_changed',
			'newspack_node_subscription_changed',
		];
		Newspack_Webhooks::register_system_endpoint( self::ENDPOINT_ID, self::get_url(), $events );
	}

	/**
	 * Gets the endpoint URL
	 *
	 * @return string
	 */
	public static function get_url() {
		return trailingslashit( Settings::get_hub_url() ) . self::ENDPOINT_SUFFIX;
	}

	/**
	 * Filters the event body and signs the data
	 *
	 * @param array  $body The Webhook Event body.
	 * @param string $endpoint_id The endpoint ID.
	 * @return array
	 */
	public static function filter_webhook_body( $body, $endpoint_id ) {
		if ( self::ENDPOINT_ID !== $endpoint_id ) {
			return $body;
		}

		$data  = wp_json_encode( $body['data'] );
		$nonce = Crypto::generate_nonce();
		$data  = self::sign( $data, $nonce );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$body['nonce'] = $nonce;
		$body['data']  = $data;
		$body['site']  = get_bloginfo( 'url' );

		return $body;

	}

	/**
	 * Signs the data
	 *
	 * @param string $data The data to be signed.
	 * @param string $nonce The nonce to encrypt the message with, generated with Crypto::generate_nonce().
	 * @param string $secret_key The secret key to use for signing. Default is to use the stored secret key.
	 * @return WP_Error|string The signed data or error.
	 */
	public static function sign( $data, $nonce, $secret_key = null ) {
		if ( ! $secret_key ) {
			$secret_key = Settings::get_secret_key();
		}
		if ( empty( $secret_key ) ) {
			return new \WP_Error( 'newspack-network-node-webhook-signing-error', __( 'Missing Secret key', 'newspack-network-node' ) );
		}

		return Crypto::encrypt_message( $data, $secret_key, $nonce );

	}

}
