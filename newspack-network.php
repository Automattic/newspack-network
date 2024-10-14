<?php
/**
 * Plugin Name: Newspack Network
 * Description: The Newspack Network plugin.
 * Version: 2.3.0-alpha.1
 * Author: Automattic
 * Author URI: https://newspack.com/
 * License: GPL3
 * Text Domain: newspack-network
 * Domain Path: /languages/
 *
 * @package newspack-network
 */

defined( 'ABSPATH' ) || exit;

// Define NEWSPACK_NETWORK_PLUGIN_DIR.
if ( ! defined( 'NEWSPACK_NETWORK_PLUGIN_DIR' ) ) {
	define( 'NEWSPACK_NETWORK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Define NEWSPACK_NETWORK_PLUGIN_FILE.
if ( ! defined( 'NEWSPACK_NETWORK_PLUGIN_FILE' ) ) {
	define( 'NEWSPACK_NETWORK_PLUGIN_FILE', __FILE__ );
}

/**
 * The role added by Newspack Network plugin for readers propagated from other sites.
 */
define( 'NEWSPACK_NETWORK_READER_ROLE', 'network_reader' );

// Load language files.
load_plugin_textdomain( 'newspack-network', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

require_once __DIR__ . '/vendor/autoload.php';

Newspack_Network\Initializer::init();
