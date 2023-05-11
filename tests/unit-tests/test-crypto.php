<?php
/**
 * Class TestCrypto
 *
 * @package Newspack_Network_Hub
 */

use \Newspack_Hub\Crypto;

/**
 * Test the Node class.
 */
class TestCrypto extends WP_UnitTestCase {

	/**
	 * Signs the data
	 *
	 * @param string $data The data to be signed.
	 * @param string $private_key The private key to use for signing.
	 * @return false|string The signed data or false.
	 */
	public function sign_message( $data, $private_key ) {
		try {
			$signed = sodium_crypto_sign( $data, sodium_base642bin( $private_key, SODIUM_BASE64_VARIANT_ORIGINAL ) );
			return sodium_bin2base64( $signed, SODIUM_BASE64_VARIANT_ORIGINAL );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Test verify with empty key
	 */
	public function test_verify_empty_key() {
		$verified = Crypto::verify_signed_message( 'test', '' );
		$this->assertFalse( $verified );
	}

	/**
	 * Test verify with invalid key
	 */
	public function test_verify_invalid_key() {
		$verified = Crypto::verify_signed_message( 'test', 'asdasd' );
		$this->assertFalse( $verified );

		$verified = Crypto::verify_signed_message( 'test', [ 'asdasd' ] );
		$this->assertFalse( $verified );
	}

	/**
	 * Test verify with wrong key
	 */
	public function test_verify_wrong_key() {
		$keys1          = Crypto::generate_key_pair();
		$keys2          = Crypto::generate_key_pair();
		$signed_message = $this->sign_message( 'test', $keys1['private_key'] );
		$this->assertFalse( Crypto::verify_signed_message( $signed_message, $keys2['public_key'] ) );
	}

	/**
	 * Test verify with correct key
	 */
	public function test_verify_correct_key() {
		$keys           = Crypto::generate_key_pair();
		$signed_message = $this->sign_message( 'test', $keys['private_key'] );
		$this->assertSame( 'test', Crypto::verify_signed_message( $signed_message, $keys['public_key'] ) );
	}

}
