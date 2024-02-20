<?php
/**
 * Mock for the Newspack\Newspack_Newsletters class, present in the newspack plugin
 *
 * @package Newspack_Network
 */

namespace Newspack;

/**
 * Mock Class for Newspack\Newspack Newsletters
 */
class Newspack_Newsletters {

	/**
	 * Metadata keys map for Reader Activation.
	 *
	 * @var array
	 */
	public static $metadata_keys = [
		'field_1' => 'Field 1',
		'field_2' => 'Field 2',
		'field_3' => 'Field 3',
		'field_4' => 'Field 4',
	];

	/**
	 * Given a field name, prepend it with the metadata field prefix.
	 *
	 * @param string $key Metadata field to fetch.
	 *
	 * @return string Prefixed field name.
	 */
	public static function get_metadata_key( $key ) {
		if ( ! isset( self::$metadata_keys[ $key ] ) ) {
			return false;
		}

		$prefix = 'NP_';
		$name   = self::$metadata_keys[ $key ];
		return $prefix . $name;
	}
}
