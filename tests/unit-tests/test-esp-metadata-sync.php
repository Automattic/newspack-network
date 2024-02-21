<?php // phpcs:ignore Squiz.Commenting.FileComment.Missing (something about the require_once below breaks the lint)
/**
 * Class Test_Esp_Metadata_Sync
 *
 * @package Newspack_Network
 */

require_once __DIR__ . '/class-newspack-newsletters.php';

use Newspack_Network\Esp_Metadata_Sync;
use Newspack\Newspack_Newsletters as Newspack_Plugin_Newsletters;

/**
 * Test the Esp_Metadata_Sync class.
 */
class Test_Esp_Metadata_Sync extends WP_UnitTestCase {

	/**
	 * Gets a sample contact for the tests
	 *
	 * @return array
	 */
	public function get_sample_contact() {
		$contact = [];
		$contact['metadata'] = [
			'first_name' => 'John',
			'last_name'  => 'Doe',
		];
		foreach ( array_keys( Newspack_Plugin_Newsletters::$metadata_keys ) as $key ) {
			$contact['metadata'][ Newspack_Plugin_Newsletters::get_metadata_key( $key ) ] = 'value';
		}
		return $contact;
	}

	/**
	 * Sets the Metadata keys option to the given value
	 *
	 * @param array|string $value The value to set the option to.
	 */
	public function set_option( $value ) {
		update_option( Esp_Metadata_Sync::OPTION_NAME, $value );
	}


	/**
	 * Test the normalize_contact_data method with the default option
	 */
	public function test_with_default_option() {
		$contact = $this->get_sample_contact();
		$normalized = Esp_Metadata_Sync::normalize_contact_data( $contact );
		$this->assertSame( $contact, $normalized );
	}

	/**
	 * Test the normalize_contact_data method with the option set to empty
	 */
	public function test_with_empty_selected() {
		$contact = $this->get_sample_contact();
		$this->set_option( [] );
		$normalized = Esp_Metadata_Sync::normalize_contact_data( $contact );
		$this->assertSame( $contact, $normalized );
	}

	/**
	 * Test the normalize_contact_data method with the option containing only invalid values
	 */
	public function test_with_all_invalid_selected() {
		$contact = $this->get_sample_contact();
		$this->set_option( [ 'invalid_1', 'invalid_2' ] );
		$normalized = Esp_Metadata_Sync::normalize_contact_data( $contact );
		$this->assertSame( $contact, $normalized );
	}

	/**
	 * Test the normalize_contact_data method with the option containing only valid values
	 */
	public function test_with_all_valid_selected() {
		$contact = $this->get_sample_contact();
		$this->set_option( [ 'field_1', 'field_2' ] );
		$normalized = Esp_Metadata_Sync::normalize_contact_data( $contact );
		$this->assertArrayHasKey( 'first_name', $normalized['metadata'] );
		$this->assertArrayHasKey( 'last_name', $normalized['metadata'] );
		$this->assertArrayHasKey( Newspack_Plugin_Newsletters::get_metadata_key( 'field_1' ), $normalized['metadata'] );
		$this->assertArrayHasKey( Newspack_Plugin_Newsletters::get_metadata_key( 'field_2' ), $normalized['metadata'] );
		$this->assertArrayNotHasKey( Newspack_Plugin_Newsletters::get_metadata_key( 'field_3' ), $normalized['metadata'] );
		$this->assertArrayNotHasKey( Newspack_Plugin_Newsletters::get_metadata_key( 'field_4' ), $normalized['metadata'] );
	}

	/**
	 * Test the normalize_contact_data method with the option containing valid and invalid values
	 */
	public function test_with_valid_and_invalid_selected() {
		$contact = $this->get_sample_contact();
		$this->set_option( [ 'field_1', 'field_2', 'invalid' ] );
		$normalized = Esp_Metadata_Sync::normalize_contact_data( $contact );
		$this->assertArrayHasKey( 'first_name', $normalized['metadata'] );
		$this->assertArrayHasKey( 'last_name', $normalized['metadata'] );
		$this->assertArrayHasKey( Newspack_Plugin_Newsletters::get_metadata_key( 'field_1' ), $normalized['metadata'] );
		$this->assertArrayHasKey( Newspack_Plugin_Newsletters::get_metadata_key( 'field_2' ), $normalized['metadata'] );
		$this->assertArrayNotHasKey( Newspack_Plugin_Newsletters::get_metadata_key( 'field_3' ), $normalized['metadata'] );
		$this->assertArrayNotHasKey( Newspack_Plugin_Newsletters::get_metadata_key( 'field_4' ), $normalized['metadata'] );
		$this->assertCount( 4, $normalized['metadata'] );
	}
}
