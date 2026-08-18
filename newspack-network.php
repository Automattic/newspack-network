<?php
/**
 * Plugin Name: Newspack Network (final version, please migrate)
 * Description: Final version released from the legacy plugin repository. This copy will not receive further updates. Download the current version at https://newspack.com/download-center
 * Version: 2.20.3
 * Author: Automattic
 * Author URI: https://newspack.com/
 * License: GPL3
 * Requires PHP: 8.1
 * Text Domain: newspack-network
 * Domain Path: /languages/
 *
 * @package newspack-network
 */

defined( 'ABSPATH' ) || exit;

// Path to the Newspack Network plugin directory.
if ( ! defined( 'NEWSPACK_NETWORK_PLUGIN_DIR' ) ) {
	define( 'NEWSPACK_NETWORK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Path to the main Newspack Network plugin file.
if ( ! defined( 'NEWSPACK_NETWORK_PLUGIN_FILE' ) ) {
	define( 'NEWSPACK_NETWORK_PLUGIN_FILE', __FILE__ );
}

/**
 * The role added by Newspack Network plugin for readers propagated from other sites.
 */
define( 'NEWSPACK_NETWORK_READER_ROLE', 'network_reader' );

// Load language files.
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'newspack-network', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);

require_once __DIR__ . '/vendor/autoload.php';

Newspack_Network\Initializer::init();

/**
 * Warn administrators that this build came from the legacy plugin repository.
 */
function newspack_network_legacy_repo_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="notice notice-error">
		<p><strong><?php esc_html_e( 'You are running an outdated version of the Newspack Network plugin.', 'newspack-network' ); ?></strong></p>
		<p>
			<?php
			printf(
				wp_kses(
					/* translators: 1: URL of the announcement post. 2: URL of the download center. */
					__( 'This is the final version released from the legacy plugin repository, and it will not receive further updates. <a href="%1$s">Read the announcement</a>, then download the current version from the <a href="%2$s">Newspack download center</a>.', 'newspack-network' ),
					[
						'a' => [
							'href' => [],
						],
					]
				),
				esc_url( 'https://newspack.com/newspack-plugins-and-themes-have-a-new-home/' ),
				esc_url( 'https://newspack.com/download-center' )
			);
			?>
		</p>
	</div>
	<?php
}

/*
 * Newspack wizard screens call remove_all_actions() on the notice hooks at priority -9999,
 * so this notice runs ahead of that to stay visible on every admin screen.
 */
add_action( 'all_admin_notices', 'newspack_network_legacy_repo_notice', -99999 );
