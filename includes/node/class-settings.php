<?php
/**
 * Newspack Node Main Settings page.
 *
 * @package Newspack
 */

namespace Newspack_Network\Node;

use Newspack_Network\Admin;

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
		Admin::add_submenu_page( __( 'Node Settings', 'newspack-network-node' ), self::PAGE_SLUG, [ __CLASS__, 'render' ] );
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
			esc_html__( 'Newspack Network Node Settings', 'newspack-network-node' ),
			[ __CLASS__, 'section_callback' ],
			self::PAGE_SLUG
		);

		$settings = [
			[
				'key'      => 'newspack_node_hub_url',
				'label'    => esc_html__( 'Hub URL', 'newspack-network-node' ),
				'callback' => [ __CLASS__, 'hub_url_callback' ],
				'args'     => [
					'sanitize_callback' => [ __CLASS__, 'sanitize_hub_url' ],
				],
			],
			[
				'key'      => 'newspack_node_secret',
				'label'    => esc_html__( 'Secret key', 'newspack-network-node' ),
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
		echo sprintf(
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
		echo sprintf(
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
				__( 'The URL is not valid.', 'newspack-network-node' )
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
				__( 'Invalid Secret key:', 'newspack-network-node' ) . ' ' . $signed->get_error_message()
			);
			return false;
		}
		return $value;
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
		</div>
		<?php
	}

}
