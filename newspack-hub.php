<?php
/**
 * Plugin Name: Newspack Hub
 * Description: The Newspack Hub plugin.
 * Version: 0.1
 * Author: Automattic
 * Author URI: https://newspack.com/
 * License: GPL3
 * Text Domain: newspack-hub
 * Domain Path: /languages/
 *
 * @package newspack-hub
 */

defined( 'ABSPATH' ) || exit;

// Define NEWSPACK_HUB_PLUGIN_DIR.
if ( ! defined( 'NEWSPACK_HUB_PLUGIN_DIR' ) ) {
	define( 'NEWSPACK_HUB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Define NEWSPACK_HUB_PLUGIN_FILE.
if ( ! defined( 'NEWSPACK_HUB_PLUGIN_FILE' ) ) {
	define( 'NEWSPACK_HUB_PLUGIN_FILE', __FILE__ );
}

// Load language files.
load_plugin_textdomain( 'newspack-hub', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

require_once __DIR__ . '/vendor/autoload.php';

Newspack_Network\Initializer::init();
