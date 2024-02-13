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
	 * Initialize hooks.
	 */
	public static function init() {
		\add_action( 'edit_user_profile', [ __CLASS__, 'add_sync_button' ] );
		\add_action( 'show_user_profile', [ __CLASS__, 'add_sync_button' ] );
		\add_action( 'admin_init', [ __CLASS__, 'process_admin_action' ] );
		//\add_action( 'shutdown', [ __CLASS__, 'trigger_sync' ] );
	}

	/**
	 * TODO
	 */
	public static function trigger_sync( $user_email ) {
		Debugger::log( 'Attempting to sync this: ' . $user_email );
		// do_action( 'newspack_network_manual_sync_user', $user_email );
	}

	/**
	 * Create URL to manually sync user.
	 * 
	 * @param WP_User $user The current WP_User object.
	 */
	// TODO: not sure if all of this is needed, or if more is needed (whether new role/new user?)
	public static function get_sync_link( $user ) {
		$url = add_query_arg( array(
			'user_id'         => \esc_attr( $user->ID ),
			'action'          => 'np_network_manual_user_sync',
			'uid'             => \esc_attr( $user->ID ),
			'_wpnonce'        => \wp_create_nonce( 'np_network_manual_user_sync' ),
		), '/wp-admin/user-edit.php' );

		return $url;
	}


	/**
	 * Add button to manually sync users to the Edit User Profile screen.
	 *
	 * @param WP_User $user The current WP_User object.
	 */
	public static function add_sync_button( $user ) {
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
						<a class="button" id="do-newspack-network-manual-sync" href="<?php echo esc_url( self::get_sync_link( $user ) ); ?>">
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
	 * 
	 */
	public static function process_admin_action() {
		/** Allowed actions **/
		$actions = [
			'np_network_manual_user_sync'
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

		$user_email = $user->user_email;
		self::trigger_sync( $user_email );

		$redirect = \add_query_arg( [ 'update' => $action ], \remove_query_arg( [ 'action', 'uid', '_wpnonce' ] ) );
		\wp_safe_redirect( $redirect );
		exit;
	}
}