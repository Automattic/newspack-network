<?php
/**
 * Newspack Hub plugin administration screen handling.
 *
 * @package Newspack
 */

namespace Newspack_Hub;

/**
 * Class to handle the plugin admin pages
 */
class Admin {
	const PAGE_SLUG = 'newspack-hub';

	/**
	 * Runs the initialization.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
	}

	/**
	 * Adds the admin page
	 *
	 * @return void
	 */
	public static function add_admin_menu() {
		$page_suffix = add_menu_page(
			__( 'Newspack Hub', 'newspack-network-hub' ),
			__( 'Newspack Hub', 'newspack-network-hub' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);

		add_action( 'load-' . $page_suffix, array( __CLASS__, 'admin_init' ) );
	}

	/**
	 * Adds a child admin page to the main Newspack Hub admin page
	 *
	 * @param string   $title The menu title.
	 * @param string   $slug The menu slug.
	 * @param callable $callback The function to be called to output the content for this page.
	 * @return string|false The resulting page's hook_suffix, or false if the user does not have the capability required.
	 */
	public static function add_submenu_page( $title, $slug, $callback ) {
		return add_submenu_page(
			self::PAGE_SLUG,
			$title,
			$title,
			'manage_options',
			$slug,
			$callback
		);
	}

	/**
	 * Renders the page content
	 *
	 * @return void
	 */
	public static function render_page() {
		echo '<div id="root"></div>';
	}

	/**
	 * Callback for the load admin page hook.
	 *
	 * @return void
	 */
	public static function admin_init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue admin page assets.
	 *
	 * @return void
	 */
	public static function enqueue_scripts() {
		
	}

}
