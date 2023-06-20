<?php
/**
 * Newspack Hub Node Orders post type registration
 *
 * @package Newspack
 */

namespace Newspack_Network\Hub\Database;

use Newspack_Network\Hub\Admin;
use Newspack_Network\Admin as Network_Admin;
use Newspack_Network\Debugger;

/**
 * Class to handle the ubscriptions post type registration
 */
class Orders {

	/**
	 * POST_TYPE_SLUG for Node Orders.
	 *
	 * @var string
	 */
	const POST_TYPE_SLUG = 'np_hub_orders';

	/**
	 * Initialize this class and register hooks
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_post_type' ] );
		add_action( 'init', [ __CLASS__, 'register_post_statuses' ] );
	}

	/**
	 * Register the custom post type
	 *
	 * @return void
	 */
	public static function register_post_type() {

		$labels = array(
			'name'                  => _x( 'Orders', 'Post Type General Name', 'newspack-network' ),
			'singular_name'         => _x( 'Order', 'Post Type Singular Name', 'newspack-network' ),
			'menu_name'             => __( 'Orders', 'newspack-network' ),
			'name_admin_bar'        => __( 'Orders', 'newspack-network' ),
			'archives'              => __( 'Orders', 'newspack-network' ),
			'attributes'            => __( 'Orders', 'newspack-network' ),
			'parent_item_colon'     => __( 'Parent Order', 'newspack-network' ),
			'all_items'             => __( 'Orders', 'newspack-network' ),
			'add_new_item'          => __( 'Add new Order', 'newspack-network' ),
			'add_new'               => __( 'Add New', 'newspack-network' ),
			'new_item'              => __( 'New Order', 'newspack-network' ),
			'edit_item'             => __( 'Edit Order', 'newspack-network' ),
			'update_item'           => __( 'Update Order', 'newspack-network' ),
			'view_item'             => __( 'View Order', 'newspack-network' ),
			'view_items'            => __( 'View Orders', 'newspack-network' ),
			'search_items'          => __( 'Search Order', 'newspack-network' ),
			'not_found'             => __( 'Not found', 'newspack-network' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'newspack-network' ),
			'featured_image'        => __( 'Featured Image', 'newspack-network' ),
			'set_featured_image'    => __( 'Set featured image', 'newspack-network' ),
			'remove_featured_image' => __( 'Remove featured image', 'newspack-network' ),
			'use_featured_image'    => __( 'Use as featured image', 'newspack-network' ),
			'insert_into_item'      => __( 'Insert into item', 'newspack-network' ),
			'uploaded_to_this_item' => __( 'Uploaded to this item', 'newspack-network' ),
			'items_list'            => __( 'Items list', 'newspack-network' ),
			'items_list_navigation' => __( 'Items list navigation', 'newspack-network' ),
			'filter_items_list'     => __( 'Filter items list', 'newspack-network' ),
		);
		$args   = array(
			'label'            => __( 'Orders', 'newspack-network' ),
			'description'      => __( 'Node Orders', 'newspack-network' ),
			'labels'           => $labels,
			'supports'         => [],
			'hierarchical'     => false,
			'public'           => false,
			'show_ui'          => true,
			'show_in_menu'     => Network_Admin::PAGE_SLUG,
			'can_export'       => false,
			'capability_type'  => 'post',
			'show_in_rest'     => false,
			'delete_with_user' => false,
		);
		register_post_type( self::POST_TYPE_SLUG, $args );
	}

	/**
	 * Register the custom post statuses
	 *
	 * @return void
	 */
	public static function register_post_statuses() {
		$subscription_statuses = array(
			'pending'    => _x( 'Pending', 'Order status', 'newspack-network' ),
			'processing' => _x( 'Processing', 'Order status', 'newspack-network' ),
			'completed'  => _x( 'Completed', 'Order status', 'newspack-network' ),
			'on-hold'    => _x( 'On hold', 'Order status', 'newspack-network' ),
			'cancelled'  => _x( 'Cancelled', 'Order status', 'newspack-network' ),
			'refunded'   => _x( 'Refunded', 'Order status', 'newspack-network' ),
			'failed'     => _x( 'Failed', 'Order status', 'newspack-network' ),
		);
		foreach ( $subscription_statuses as $status => $label ) {
			register_post_status(
				$status,
				array(
					'label'                     => $label,
					'public'                    => true,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
				)
			);
		}
	}

}
