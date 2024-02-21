<?php
/**
 * Newspack Node Main Settings page.
 *
 * @package Newspack
 */

namespace Newspack_Network\Node;

use Newspack_Network\Admin;
use Newspack_Network\Crypto;

/**
 * Class to handle Node settings page
 */
class Settings {

	/**
	 * The setting section constant
	 */
	const SETTINGS_SECTION = 'newspack_node_settings';

	/**
	 * The admin page slug
	 */
	const PAGE_SLUG = 'newspack-network-node'; // Same as the main admin page slug, it will become the first menu item.

	/**
	 * Initialize this class and register hooks
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_notices', [ __CLASS__, 'linking_interface_notice' ] );
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_filter( 'allowed_options', [ __CLASS__, 'allowed_options' ] );
	}

	/**
	 * Get the Hub URL setting
	 *
	 * @return ?string
	 */
	public static function get_hub_url() {
		return get_option( 'newspack_node_hub_url' );
	}

	/**
	 * Get the Secret key setting
	 *
	 * @return ?string
	 */
	public static function get_secret_key() {
		return get_option( 'newspack_node_secret_key' );
	}

	/**
	 * Adds the submenu page
	 *
	 * @return void
	 */
	public static function add_menu() {
		Admin::add_submenu_page( __( 'Node Settings', 'newspack-network' ), self::PAGE_SLUG, [ __CLASS__, 'render' ] );
	}

	/**
	 * Adds the options page to the allowed list of options
	 *
	 * @param array $allowed_options The allowed options.
	 * @return array
	 */
	public static function allowed_options( $allowed_options ) {
		$allowed_options[ self::SETTINGS_SECTION ] = [
			'newspack_node_hub_url',
			'newspack_node_secret_key',
		];
		return $allowed_options;
	}

	/**
	 * Register the settings
	 *
	 * @return void
	 */
	public static function register_settings() {

		add_settings_section(
			self::SETTINGS_SECTION,
			esc_html__( 'Newspack Network Node Settings', 'newspack-network' ),
			[ __CLASS__, 'section_callback' ],
			self::PAGE_SLUG
		);

		$settings = [
			[
				'key'      => 'newspack_node_hub_url',
				'label'    => esc_html__( 'Hub URL', 'newspack-network' ),
				'callback' => [ __CLASS__, 'hub_url_callback' ],
				'args'     => [
					'sanitize_callback' => [ __CLASS__, 'sanitize_hub_url' ],
				],
			],
			[
				'key'      => 'newspack_node_secret',
				'label'    => esc_html__( 'Secret key', 'newspack-network' ),
				'callback' => [ __CLASS__, 'secret_key_callback' ],
				'args'     => [
					'sanitize_callback' => [ __CLASS__, 'sanitize_secret_key' ],
				],
			],
		];
		foreach ( $settings as $setting ) {
			add_settings_field(
				$setting['key'],
				$setting['label'],
				$setting['callback'],
				self::PAGE_SLUG,
				self::SETTINGS_SECTION
			);
			register_setting(
				self::PAGE_SLUG,
				$setting['key'],
				$setting['args'] ?? []
			);
		}
	}

	/**
	 * The Settings page callback
	 *
	 * @return void
	 */
	public static function section_callback() {
		// Nothing here for now.
	}

	/**
	 * The hub_url setting callback
	 *
	 * @return void
	 */
	public static function hub_url_callback() {
		$content = get_option( 'newspack_node_hub_url' );
		$referrer = self::get_referrer();
		if ( Admin::is_updating_from_url() && $referrer ) {
			$content = $referrer;
		}
		printf(
			'<input type="text" name="%1$s" value="%2$s">',
			'newspack_node_hub_url',
			esc_html( $content )
		);
	}

	/**
	 * The secret_key setting callback
	 *
	 * @return void
	 */
	public static function secret_key_callback() {
		$content = get_option( 'newspack_node_secret_key' );
		$secret_key = self::get_secret_key_from_url();
		if ( Admin::is_updating_from_url() && $secret_key ) {
			$content = $secret_key;
		}
		printf(
			'<input type="text" name="%1$s" value="%2$s">',
			'newspack_node_secret_key',
			esc_html( $content )
		);
	}

	/**
	 * Validates and sanitizes the hub_url setting
	 *
	 * @param string $value The value to sanitize.
	 * @return string|false
	 */
	public static function sanitize_hub_url( $value ) {
		$value = trim( $value );
		if ( ! empty( $value ) && ! preg_match( '#^https?://#', $value ) ) {
			$value = 'https://' . $value;
		}

		if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
			add_settings_error(
				'newspack_node_hub_url',
				'newspack_node_hub_url',
				__( 'The URL is not valid.', 'newspack-network' )
			);
			return false;
		}

		return $value;
	}

	/**
	 * Tests if the string is a valid secret key
	 *
	 * @param string $value The value to sanitize.
	 * @return bool|string
	 */
	public static function sanitize_secret_key( $value ) {
		$signed = Webhook::sign( 'test', $value );
		if ( is_wp_error( $signed ) ) {
			add_settings_error(
				'newspack_node_secret_key',
				'newspack_node_secret_key',
				__( 'Invalid Secret key:', 'newspack-network' ) . ' ' . $signed->get_error_message()
			);
			return false;
		}
		return $value;
	}

	/**
	 * Get secret key from URL.
	 */
	private static function get_secret_key_from_url() {
		return filter_input( INPUT_GET, 'secret_key', FILTER_SANITIZE_SPECIAL_CHARS );
	}

	/**
	 * Get referrer.
	 */
	private static function get_referrer() {
		return isset( $_SERVER['HTTP_REFERER'] ) ? \esc_url_raw( \wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
	}

	/**
	 * Render linking receiving interface.
	 */
	public static function linking_interface_notice() {
		if ( ! Admin::is_updating_from_url() ) {
			return;
		}
		$referrer = self::get_referrer();
		if ( isset( $_REQUEST['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// Remove the params from URL - settings were just saved.
			if ( $referrer ) {
				if ( ! \Newspack_Network\Site_Role::get() ) {
					\Newspack_Network\Site_Role::set_as_node();
				}
				$referrer = remove_query_arg( [ 'secret_key', 'action' ], $referrer );
				wp_safe_redirect( $referrer );
				exit;
			}
			return;
		}
		$secret_key = self::get_secret_key_from_url();
		if ( ! $secret_key || ! $referrer ) {
			return;
		}
		$existing_secret_key = self::get_secret_key();
		$hub_url = self::get_hub_url();
		if ( $existing_secret_key === $secret_key && $referrer === $hub_url ) {
			?>
			<div id="message" class="updated notice is-dismissible">
				<p><?php esc_html_e( 'This site is already linked to this Hub.', 'newspack-network' ); ?></p>
			</div>
			<?php
			return;
		}
		?>
		<div id="message" class="notice">
			<h2>
				<?php esc_html_e( 'Link this site to the Hub', 'newspack-network' ); ?>
			</h2>
			<p>
			<?php
			printf(
				/* translators: %s is the Hub URL */
				esc_html__( 'Click "Save Changes" below to link this site to the hub at %s.', 'newspack-network' ),
				esc_html( $referrer )
			);
			?>
			</p>
		</div>
		<?php
	}

	/**
	 * Renders the settings page
	 *
	 * @return void
	 */
	public static function render() {
		?>
		<div class='wrap'>
			<?php settings_errors(); ?>
			<form method='post' action='options.php'>
			<?php
				do_settings_sections( self::PAGE_SLUG );
				settings_fields( self::SETTINGS_SECTION );
			?>
				<p class='submit'>
					<input name='submit' type='submit' id='submit' class='button-primary' value='<?php _e( 'Save Changes' ); ?>' />
				</p>
			</form>
			<hr />
			<?php self::render_debug_tools(); ?>
		</div>
		<?php
	}

	/**
	 * Renders the debug tools.
	 *
	 * @return void
	 */
	private static function render_debug_tools() {
		$icon          = '❌';
		$error_message = Pulling::get_last_error_message();
		if ( empty( $error_message ) ) {
			$icon          = '✅';
			$error_message = __( 'No recent errors.', 'newspack-network' );
		}
		?>
		<h2><?php esc_html_e( 'Debug Tools', 'newspack-network' ); ?></h2>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><?php _e( 'Last Processed Item ID', 'newspack-network' ); ?></th>
					<td><code><?php echo esc_html( Pulling::get_last_processed_id() ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php _e( 'Last Error From Server', 'newspack-network' ); ?></th>
					<td><?php echo esc_html( $icon ); ?> <?php echo esc_html( $error_message ); ?></td>
				</tr>
			</tbody>
		</table>
		<form method="post">
			<?php wp_nonce_field( Pulling::MANUAL_PULL_ACTION_NAME ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( Pulling::MANUAL_PULL_ACTION_NAME ); ?>">
			<button class="button"><?php esc_html_e( 'Synchronize latest data', 'newspack-network' ); ?></button>
		</form>

		<?php

		self::render_webhooks_requests();
	}

	/**
	 * Renders the table with the scheduled webhooks requests.
	 *
	 * @return void
	 */
	private static function render_webhooks_requests() {
		$secret_key = self::get_secret_key();
		if ( ! $secret_key ) {
			// Node is not configured yet.
			return;
		}

		$requests = \Newspack\Data_Events\Webhooks::get_endpoint_requests( Webhook::ENDPOINT_ID );

		?>
		<h3>
			<?php esc_html_e( 'Scheduled Events', 'newspack-network' ); ?>
		</h3>
		<p>
			<?php esc_html_e( 'The following events are scheduled to be sent or have recently been sent to the Hub.', 'newspack-network' ); ?>
		</p>
		<table class="wp-list-table widefat fixed striped table-view-list">
			<thead>
				<tr>
					<th scope="col" id="date" class="manage-column column-date">Date</th>
					<th scope="col" id="action_name" class="manage-column column-action_name">Action name</th>
					<th scope="col" id="data" class="manage-column column-data">Data</th>
					<th scope="col" id="data" class="manage-column column-status">Status</th>
					<th scope="col" id="data" class="manage-column column-status">Errors</th>
				</tr>
			</thead>

			<?php
			foreach ( $requests as $request ) :
				$status_label = __( 'Pending', 'newspack-network' );
				$icon         = '🕒';
				if ( 'finished' === $request['status'] ) {
					$status_label = __( 'Sent', 'newspack-network' );
					$icon         = '✅';
				}
				$r             = json_decode( $request['body'], true );
				$data          = Crypto::decrypt_message( $r['data'], $secret_key, $r['nonce'] );
				$date          = gmdate( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $r['timestamp'] );
				$scheduled_for = gmdate( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $request['scheduled'] );
				?>

				<tr>
					<td><?php echo esc_html( $date ); ?> (#<?php echo esc_html( $r['request_id'] ); ?>)</td>
					<td><?php echo esc_html( $r['action'] ); ?></td>
					<td><code><?php echo esc_html( $data ); ?></code></td>
					<td>
						<?php echo esc_html( $icon . ' ' . $status_label ); ?>.
						<?php
							echo esc_html(
								sprintf(
								/* translators: %s: scheduled date */
									__( 'Scheduled for %s', 'newspack-network' ),
									$scheduled_for
								)
							);
						?>
					</td>
					<td>
						<?php if ( ! empty( $request['errors'] ) ) : ?>
							<?php
								echo esc_html(
									sprintf(
										/* translators: %s is the number of errors */
										_n(
											'There was %s failed attempt to send this request',
											'There were %s failed attempts to send this request',
											count( $request['errors'] ),
											'newspack-network'
										),
										count( $request['errors'] )
									)
								);
								echo '<ul>';
							foreach ( $request['errors'] as $error ) {
								echo '<li><code>';
								echo esc_html( $error );
								echo '</code></li>';
							}
								echo '</ul>';
							?>
						<?php endif; ?>
					</td>
				</tr>

			<?php endforeach; ?>
		</table>

		<?php
	}
}
