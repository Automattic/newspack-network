<?php
/**
 * Newspack Hub ESP Metadata settings
 *
 * @package Newspack
 */

namespace Newspack_Network\Hub\Admin;

use Newspack_Network\Admin as Network_Admin;
use Newspack_Network\Esp_Metadata_Sync;
use Newspack\Newspack_Newsletters as Newspack_Plugin_Newsletters;
/**
 * Class to handle Node settings page
 */
class Esp_Metadata_Settings {

	/**
	 * The admin page slug
	 */
	const PAGE_SLUG = 'newspack-network-esp-metadata';

	/**
	 * The nonce action slug
	 */
	const NONCE = 'newspack-network-esp-metadata-save';

	/**
	 * Initialize this class and register hooks
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_enqueue_scripts' ] );
		add_action( 'admin_init', [ __CLASS__, 'maybe_process_form' ] );
	}

	/**
	 * Adds the submenu page
	 *
	 * @return void
	 */
	public static function add_menu() {
		if ( ! class_exists( 'Newspack\Newspack_Newsletters' ) ) {
			return;
		}
		Network_Admin::add_submenu_page( __( 'ESP Metadata sync settings', 'newspack-network' ), self::PAGE_SLUG, [ __CLASS__, 'render' ] );
	}

	/**
	 * Enqueues the admin script.
	 *
	 * @return void
	 */
	public static function admin_enqueue_scripts() {
		$page_slug = Network_Admin::PAGE_SLUG . '_page_' . self::PAGE_SLUG;
		if ( get_current_screen()->id !== $page_slug ) {
			return;
		}

		wp_enqueue_script(
			'newspack-network-esp-metadata-settings',
			plugins_url( 'js/esp-metadata-settings.js', __FILE__ ),
			[],
			filemtime( NEWSPACK_NETWORK_PLUGIN_DIR . '/includes/hub/admin/js/esp-metadata-settings.js' ),
			true
		);
	}

	/**
	 * Renders the settings page
	 *
	 * @return void
	 */
	public static function render() {
		self::maybe_process_form();
		$current_value = Esp_Metadata_Sync::get_option();
		$current_selected_fields = Esp_Metadata_Sync::is_default() ? [] : $current_value;
		?>
		<div class='wrap'>
			<form method="post">
			<?php wp_nonce_field( self::NONCE ); ?>
				<h2>
					<?php esc_html_e( 'ESP Metadata sync settings', 'newspack-network' ); ?>
				</h2>
				<p>
					<?php esc_html_e( 'This page allows you to modify which information about a user Newspack will sync to the connected ESP.', 'newspack-network' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'If you have many sites in your network connected to the same ESP account, you might want to reduce the number of synced fields. This is especially important for Mailchimp, since it has a small limit of Contacts metadata fields you can have.', 'newspack-network' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'You also need to configure a unique prefix for each site in Nespack > Engagement > Reader Activation > Advanced > Metadata Field Prefix.', 'newspack-network' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="blogname"><?php esc_html_e( 'Metadata settings', 'newspack-network' ); ?></label></th>
							<td>
								<select name="np_network_esp_metadata_settings" id="np_network_esp_metadata_settings">
									<option value="default" <?php selected( Esp_Metadata_Sync::is_default() ); ?>><?php esc_html_e( 'Newspack Default: Sync all fields', 'newspack-network' ); ?></option>
									<option value="custom" <?php selected( ! Esp_Metadata_Sync::is_default() ); ?>><?php esc_html_e( 'Custom', 'newspack-network' ); ?></option>
								</select>
							</td>
						</tr>
						<tr id="newspack-network-select-fields-row" style="display: none">
							<th scope="row">
								<label for="short_name"><?php esc_html_e( 'Fields to sync', 'newspack-network' ); ?></label>
							</th>
							<td>
								<?php foreach ( Newspack_Plugin_Newsletters::$metadata_keys as $slug => $name ) : ?>

									<input type="checkbox" name="metadata_keys[]" id="<?php echo esc_attr( $slug ); ?>" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $current_selected_fields ) ); ?>>
									<label for="<?php echo esc_attr( $slug ); ?>">
									<?php echo esc_html( $name ); ?>
									</label><br>

								<?php endforeach; ?>
							</td>
						</tr>
					</tbody>
				</table>
				<p class='submit'>
						<input name='submit' type='submit' id='submit' class='button-primary' value='<?php _e( 'Save Changes' ); ?>' />
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Process the form when it's submitted
	 *
	 * @return void
	 */
	public static function maybe_process_form() {
		if ( ! isset( $_POST['np_network_esp_metadata_settings'] ) ) {
			return;
		}

		if ( ! check_admin_referer( self::NONCE ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$metadata_settings = sanitize_text_field( $_POST['np_network_esp_metadata_settings'] );
		$metadata_keys = isset( $_POST['metadata_keys'] ) ? array_map( 'sanitize_text_field', $_POST['metadata_keys'] ) : [];

		if ( 'default' === $metadata_settings ) {
			update_option( Esp_Metadata_Sync::OPTION_NAME, 'default' );
		} else {
			update_option( Esp_Metadata_Sync::OPTION_NAME, $metadata_keys );
		}

		add_action( 'admin_notices', [ __CLASS__, 'notice_success' ] );
	}

	/**
	 * Displays a success notice
	 *
	 * @return void
	 */
	public static function notice_success() {
		?>
			<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved!', 'newspack-network' ); ?></p>
			</div>
		<?php
	}
}
