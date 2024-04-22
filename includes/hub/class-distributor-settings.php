<?php
/**
 * Newspack Node Main Settings page.
 *
 * @package Newspack
 */

namespace Newspack_Network\Hub;

use Newspack\Data_Events;
use Newspack_Network\Accepted_Actions;
use Newspack_Network\Admin as Network_Admin;

/**
 * Class to handle Node settings page
 */
class Distributor_Settings {

	/**
	 * The setting section constant
	 */
	const SETTINGS_SECTION = 'newspack_hub_distributor_settings';

	/**
	 * The admin page slug
	 */
	const PAGE_SLUG = 'newspack-network-distributor-settings'; // Same as the main admin page slug, it will become the first menu item.

	/**
	 * The canonical node option name
	 */
	const CANONICAL_NODE_OPTION_NAME = 'newspack_hub_canonical_node';

	/**
	 * Initialize this class and register hooks
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_filter( 'allowed_options', [ __CLASS__, 'allowed_options' ] );
		add_action( 'init', [ __CLASS__, 'register_listeners' ] );
	}

	/**
	 * Register the listeners to the Newspack Data Events API
	 *
	 * @return void
	 */
	public static function register_listeners() {
		if ( ! class_exists( 'Newspack\Data_Events' ) ) {
			return;
		}
		Data_Events::register_listener( 'update_option_' . self::CANONICAL_NODE_OPTION_NAME, 'canonical_url_updated', [ __CLASS__, 'dispatch_canonical_url_updated_event' ] );
	}

	/**
	 * Get the canonical node setting
	 *
	 * @return ?string
	 */
	public static function get_canonical_node() {
		return get_option( self::CANONICAL_NODE_OPTION_NAME );
	}

	/**
	 * Adds the submenu page
	 *
	 * @return void
	 */
	public static function add_menu() {
		Network_Admin::add_submenu_page( __( 'Distributor Settings', 'newspack-network' ), self::PAGE_SLUG, [ __CLASS__, 'render' ] );
	}

	/**
	 * Adds the options page to the allowed list of options
	 *
	 * @param array $allowed_options The allowed options.
	 * @return array
	 */
	public static function allowed_options( $allowed_options ) {
		$allowed_options[ self::SETTINGS_SECTION ] = [
			self::CANONICAL_NODE_OPTION_NAME,
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
			esc_html__( 'Newspack Network Distributor Settings', 'newspack-network-node' ),
			[ __CLASS__, 'section_callback' ],
			self::PAGE_SLUG
		);

		$settings = [
			[
				'key'      => self::CANONICAL_NODE_OPTION_NAME,
				'label'    => esc_html__( 'Node the Canonical URLs should point to', 'newspack-network-node' ),
				'callback' => [ __CLASS__, 'canonical_node_callback' ],
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
		echo esc_html__( 'Settings that modify the Distributor plugin behavior.', 'newspack-network' );
	}

	/**
	 * The canonical node setting callback
	 *
	 * @return void
	 */
	public static function canonical_node_callback() {
		$current = self::get_canonical_node();

		Nodes::network_sites_dropdown( $current, self::CANONICAL_NODE_OPTION_NAME, __( 'Default', 'newspack-network' ) );

		printf(
			'<br/><small>%1$s</small>',
			esc_html__( 'By default, canonical URLs will point to the site where the post was created. Modify this setting if you want them to point to one of the nodes.', 'newspack-network' )
		);
		printf(
			'<br/><small>%1$s</small>',
			esc_html__( 'Note: This assumes that all sites use the same permalink structure for posts.', 'newspack-network' )
		);
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

	/**
	 * Update option callback
	 *
	 * @param mixed  $old_value The old value.
	 * @param mixed  $value The new value.
	 * @param string $option The option name.
	 * @return array
	 */
	public static function dispatch_canonical_url_updated_event( $old_value, $value, $option ) {
		if ( '0' === (string) $value ) {
			return [
				'url' => get_bloginfo( 'url' ),
			];
		}
		$node     = new Node( $value );
		$node_url = $node->get_url();
		if ( ! $node_url ) {
			$node_url = '';
		}

		return [
			'url' => $node_url,
		];
	}
}
