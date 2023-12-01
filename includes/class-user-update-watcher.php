<?php
/**
 * Newspack Network watcher for updates on users' information
 *
 * @package Newspack
 */

namespace Newspack_Network;

/**
 * Class to watch the user for updates and trigger events
 */
class User_Update_Watcher {

	/**
	 * Flag to indicate if the processing of the user updated event is in progress.
	 * If true, this class won't fire any events to avoid an infinite loop in author updates.
	 *
	 * @var boolean
	 */
	public static $processing_user_updated_event = false;

	/**
	 * Holds information about the users that were updated in this request, if any.
	 *
	 * @var array
	 */
	public static $updated_users = [];

	/**
	 * The metadata we're watching and syncing.
	 *
	 * @var array
	 */
	public static $watched_meta = [
		// Social Links.
		'facebook',
		'instagram',
		'linkedin',
		'myspace',
		'pinterest',
		'soundcloud',
		'tumblr',
		'twitter',
		'youtube',
		'wikipedia',

		// Core bio.
		'first_name',
		'last_name',
		'description',

		// Newspack.
		'newspack_job_title',
		'newspack_role',
		'newspack_employer',
		'newspack_phone_number',

		// Yoast SEO.
		'wpseo_title',
		'wpseo_metadesc',
		'wpseo_noindex_author',
		'wpseo_content_analysis_disable',
		'wpseo_keyword_analysis_disable',
		'wpseo_inclusive_language_analysis_disable',
	];

	/**
	 * The user properties we're watching and syncing.
	 *
	 * @var array
	 */
	public static $user_props = [
		'display_name',
		'user_email',
		'ID',
		'user_url',
	];

	/**
	 * Runs the initialization.
	 */
	public static function init() {
		add_action( 'update_user_meta', [ __CLASS__, 'update_user_meta' ], 10, 4 );
		add_action( 'profile_update', [ __CLASS__, 'profile_update' ], 10, 3 );
		add_action( 'shutdown', [ __CLASS__, 'maybe_trigger_event' ] );
	}

	/**
	 * Adds a change to the list of changes in the current request
	 *
	 * @param int    $user_id The updated user ID.
	 * @param string $type The change type: meta or prop.
	 * @param string $key The key of the changed meta or prop.
	 * @param string $value The new value of the changed meta or prop.
	 * @return void
	 */
	private static function add_change( $user_id, $type, $key, $value ) {
		if ( ! isset( self::$updated_users[ $user_id ] ) ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				self::$updated_users[ $user_id ] = [
					'email' => $user->user_email,
				];
			} else {
				return;
			}
		}

		if ( ! isset( self::$updated_users[ $user_id ][ $type ] ) ) {
			self::$updated_users[ $user_id ][ $type ] = [];
		}

		Debugger::log( 'Author update detected for ' . self::$updated_users[ $user_id ]['email'] . ": $type: $key" );

		self::$updated_users[ $user_id ][ $type ][ $key ] = $value;
	}

	/**
	 * Runs when a user meta is updated
	 *
	 * @param int    $meta_id    The meta ID after successful update.
	 * @param int    $user_id    The user ID.
	 * @param string $meta_key   The meta key.
	 * @param mixed  $meta_value The meta value.
	 * @return void
	 */
	public static function update_user_meta( $meta_id, $user_id, $meta_key, $meta_value ) {
		if ( self::$processing_user_updated_event ) {
			return;
		}

		if ( in_array( $meta_key, self::$watched_meta, true ) ) {
			self::add_change( $user_id, 'meta', $meta_key, $meta_value );
		}
	}

	/**
	 * Runs when a user is updated
	 *
	 * @param int     $user_id The user ID.
	 * @param WP_User $old_user_data The old user data.
	 * @param array   $user_data The new user data.
	 * @return void
	 */
	public static function profile_update( $user_id, $old_user_data, $user_data ) {
		if ( self::$processing_user_updated_event ) {
			return;
		}

		foreach ( self::$user_props as $prop ) {
			if ( $old_user_data->$prop !== $user_data[ $prop ] ) {
				self::add_change( $user_id, 'prop', $prop, $user_data[ $prop ] );
			}
		}
	}

	/**
	 * If there are any author updates, trigger an event.
	 *
	 * @return void
	 */
	public static function maybe_trigger_event() {
		if ( self::$processing_user_updated_event ) {
			return;
		}
		foreach ( self::$updated_users as $author ) {
			do_action( 'newspack_network_user_updated', $author );
		}
	}

}
