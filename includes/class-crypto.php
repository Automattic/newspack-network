<?php
/**
 * Newspack Hub Crypto methods.
 *
 * @package Newspack
 */

namespace Newspack_Hub;

/**
 * Class with basic crypto methods
 */
class Crypto {

	/**
	 * Generates a new key pair
	 *
	 * @return array An array with the private and public keys
	 */
	public static function generate_key_pair() {
		$sign_pair   = sodium_crypto_sign_seed_keypair( random_bytes( SODIUM_CRYPTO_SIGN_SEEDBYTES ) );
		$private_key = sodium_bin2base64( sodium_crypto_sign_secretkey( $sign_pair ), SODIUM_BASE64_VARIANT_ORIGINAL );
		$public_key  = sodium_bin2base64( sodium_crypto_sign_publickey( $sign_pair ), SODIUM_BASE64_VARIANT_ORIGINAL );
		return [
			'private_key' => $private_key,
			'public_key'  => $public_key,
		];
	}

	/**
	 * Verifies that a signed message
	 *
	 * @param string $message The message to be verified.
	 * @param string $public_key The public key to verify the message with.
	 * @return string|false The verified message or false if the message could not be verified.
	 */
	public static function verify_signed_message( $message, $public_key ) {
		
		if ( ! $public_key || ! is_string( $public_key ) ) {
			return false;
		}

		try {
			$verified = sodium_crypto_sign_open( sodium_base642bin( $message, SODIUM_BASE64_VARIANT_ORIGINAL ), sodium_base642bin( $public_key, SODIUM_BASE64_VARIANT_ORIGINAL ) );
		} catch ( \SodiumException $e ) {
			return false;
		}

		return $verified;
	}
}
