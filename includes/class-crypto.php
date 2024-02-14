<?php
/**
 * Newspack Hub Crypto methods.
 *
 * @package Newspack
 */

namespace Newspack_Network;

/**
 * Class with basic crypto methods
 */
class Crypto {

	/**
	 * Generates a new key pair
	 *
	 * @return array An array with the private and public keys
	 */
	public static function generate_secret_key() {
		$key = sodium_crypto_aead_xchacha20poly1305_ietf_keygen();
		return bin2hex( $key );
	}

	/**
	 * Decrypts a message
	 *
	 * @param string $message The message to be decrypted.
	 * @param string $secret_key The secret key to verify the message with.
	 * @param string $nonce The nonce to verify the message with, generated with Crypto::generate_nonce().
	 * @return string|false The decrypted message or false if the message could not be decrypted.
	 */
	public static function decrypt_message( $message, $secret_key, $nonce ) {
		if ( ! $secret_key || ! is_string( $secret_key ) || ! $nonce || ! is_string( $nonce ) || ! is_string( $message ) ) {
			return false;
		}

		try {
			$decrypted = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt( hex2bin( $message ), '', hex2bin( $nonce ), hex2bin( $secret_key ) );
		} catch ( \Exception $e ) {
			return false;
		}

		return $decrypted;
	}

	/**
	 * Encrypts a message
	 *
	 * @param string $message The message to be encrypted.
	 * @param string $secret_key The secret key to encrypt the message with.
	 * @param string $nonce The nonce to verify the message with, generated with Crypto::generate_nonce().
	 * @return string|WP_Error The encrypted message or WP_Error if the message could not be encrypted.
	 */
	public static function encrypt_message( $message, $secret_key, $nonce ) {
		if ( ! $secret_key || ! is_string( $secret_key ) || ! $nonce || ! is_string( $nonce ) ) {
			return false;
		}

		try {
			$encrypted = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt( $message, '', hex2bin( $nonce ), hex2bin( $secret_key ) );
			return bin2hex( $encrypted );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'newspack-network-node-webhook-encrypting-error', $e->getMessage() );
		}
	}

	/**
	 * Generates a nonce to encrypt messages
	 *
	 * @return string
	 */
	public static function generate_nonce() {
		return bin2hex( random_bytes( SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES ) );
	}
}
