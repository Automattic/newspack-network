<?php
/**
 * Desc TK
 *
 * @package Newspack
 */

namespace Newspack_Network;

use Newspack\Data_Events;
use Newspack_Network\Debugger;

/**
 * Class to watch the user for updates and trigger events
 */
class User_Manual_Sync {

	/**
	 * The metadata we're syncing.
	 *
	 * @var array
	 */
	public static $meta_to_sync = [
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

		// Simple Local Avatars.
		'simple_local_avatar',
		'simple_local_avatar_rating',
	];

	/**
	 * Meta keys we watched but we don't want to update in the same way we do with all the others.
	 *
	 * @var array
	 */
	public static $read_only_meta = [
		'simple_local_avatar', // The avatar is sideloaded in a different way.
	];

	/**
	 * The user properties we're syncing.
	 *
	 * @var array
	 */
	public static $user_props = [
		'display_name',
		'user_email',
		'user_url',
	];

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		\add_action( 'edit_user_profile', [ __CLASS__, 'add_manual_sync_button' ] );
		\add_action( 'show_user_profile', [ __CLASS__, 'add_manual_sync_button' ] );
		\add_action( 'admin_init', [ __CLASS__, 'process_admin_action' ] );
		\add_action( 'init', [ __CLASS__, 'register_listener' ] );
	}

	/**
	 * Register the listeners to the Newspack Data Events API
	 *
	 * @return void
	 */
	public static function register_listener() {
		if ( ! class_exists( 'Newspack\Data_Events' ) ) {
			return;
		}

		Data_Events::register_listener( 'newspack_network_manual_sync_user', 'network_manual_sync_user', [ __CLASS__, 'manual_sync_user' ] );
	}

	/**
	 * Filters the user data for the event being triggered
	 *
	 * @param array $user_data The user data.
	 * @return array
	 */
	public static function manual_sync_user( $user_data ) {
		$synced_metadata = [];
		$synced_props    = [];

		// Create an array of all of the synced user meta values.
		foreach ( self::$meta_to_sync as $key ) {
			$synced_metadata[ $key ] = $user_data->$key;
		}

		foreach ( self::$user_props as $key ) {
			$synced_props[ $key ] = $user_data->$key;
		}

		return [
			'email'   => $user_data->user_email,
			'role'    => array_shift( $user_data->roles ),
			'user_id' => $user_data->ID,
			'meta'    => $synced_metadata,
			'prop'    => $synced_props,
		];
	}

	/**
	 * Create URL to manually sync user.
	 *
	 * @param WP_User $user The current WP_User object.
	 * @return string $url The wp-admin URL.
	 */
	public static function get_manual_sync_link( $user ) {
		$url = add_query_arg(
			array(
				'user_id'  => \esc_attr( $user->ID ),
				'action'   => 'np_network_manual_user_sync',
				'uid'      => \esc_attr( $user->ID ),
				'_wpnonce' => \wp_create_nonce( 'np_network_manual_user_sync' ),
			),
			'/wp-admin/user-edit.php'
		);

		return $url;
	}

	/**
	 * Add button to manually sync users to the Edit User Profile screen.
	 *
	 * @param WP_User $user The current WP_User object.
	 */
	public static function add_manual_sync_button( $user ) {
		// TODO: should this be limited to specific roles? Author, Editor, Contributor... and not Subscriber?
		if ( \current_user_can( 'edit_user', $user->ID ) ) :
			?>
		<div class="newspack-network-sync-user">
			<h2><?php \esc_html_e( 'Newspack Network Tools', 'newspack-network' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th>
						<label><?php esc_html_e( 'Manually sync user', 'newspack-network' ); ?></label>
					</th>
					<td>
						<a class="button" id="do-newspack-network-manual-sync" href="<?php echo esc_url( self::get_manual_sync_link( $user ) ); ?>">
							<?php esc_html_e( 'Sync user across network', 'newspack-network' ); ?>
						</a>
						<p class="description">
							<?php esc_html_e( 'Manually sync this user\'s role across all sites in your network. If this is a new user, clicking this button will also propigate the user account across your network.', 'newspack-network' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>
			<?php
	endif;
	}

	/**
	 * Process the admin action request
	 */
	public static function process_admin_action() {
		/** Allowed actions */
		$actions = [
			'np_network_manual_user_sync',
		];

		/** Add notice if admin action was successful. */
		if ( isset( $_GET['update'] ) && in_array( $_GET['update'], $actions, true ) ) {
			$update  = \sanitize_text_field( \wp_unslash( $_GET['update'] ) );
			$message = __( 'This user is scheduled to be synced.', 'newspack-network' );
			if ( ! empty( $message ) ) {
				\add_action(
					'admin_notices',
					function() use ( $message ) {
						?>
						<div id="message" class="updated notice is-dismissible"><p><?php echo \esc_html( $message ); ?></p></div>
						<?php
					}
				);
			}
		}

		if ( ! isset( $_GET['action'] ) || ! in_array( $_GET['action'], $actions, true ) ) {
			return;
		}

		$action = \sanitize_text_field( \wp_unslash( $_GET['action'] ) );

		if ( ! isset( $_GET['uid'] ) ) {
			\wp_die( \esc_html__( 'Invalid request.', 'newspack' ) );
		}

		if ( ! \check_admin_referer( $action ) ) {
			\wp_die( \esc_html__( 'Invalid request.', 'newspack' ) );
		}

		$user_id = \absint( \wp_unslash( $_GET['uid'] ) );

		if ( ! \current_user_can( 'edit_user', $user_id ) ) {
			\wp_die( \esc_html__( 'You do not have permission to do that.', 'newspack' ) );
		}

		$user = \get_user_by( 'id', $user_id );

		if ( ! $user || \is_wp_error( $user ) ) {
			\wp_die( \esc_html__( 'User not found.', 'newspack' ) );
		}

		do_action( 'newspack_network_manual_sync_user', $user );

		$redirect = \add_query_arg( [ 'update' => $action ], \remove_query_arg( [ 'action', 'uid', '_wpnonce' ] ) );
		\wp_safe_redirect( $redirect );
		exit;
	}
}
