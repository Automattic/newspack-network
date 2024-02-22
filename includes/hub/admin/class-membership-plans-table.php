<?php
/**
 * Newspack Hub Membership_Plans Table
 *
 * @package Newspack
 */

namespace Newspack_Network\Hub\Admin;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * The Membership_Plans Table
 */
class Membership_Plans_Table extends \WP_List_Table {
	/**
	 * Whether to show local or network plans.
	 *
	 * @var bool
	 */
	private $is_local = false;

	/**
	 * Constructs the controller.
	 *
	 * @param bool $is_local Whether to show local or network plans.
	 */
	public function __construct( $is_local = false ) {
		$this->is_local = $is_local;
		parent::__construct();
	}

	/**
	 * Get the table columns
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = [
			'id'   => __( 'ID', 'newspack-network' ),
			'name' => __( 'Name', 'newspack-network' ),
		];
		if ( $this->is_local ) {
			$columns['node_url'] = __( '-', 'newspack-network' );
		} else {
			$columns['node_url'] = __( 'Node URL', 'newspack-network' );
		}
		$columns['network_pass_id'] = __( 'Network ID', 'newspack-network' );
		$columns['links'] = __( 'Links', 'newspack-network' );
		return $columns;
	}

	/**
	 * Prepare items to be displayed
	 */
	public function prepare_items() {
		$this->_column_headers = [ $this->get_columns(), [], [], 'id' ];
		if ( $this->is_local ) {
			$this->items = Membership_Plans::get_local_membership_plans();
		} else {
			$this->items = Membership_Plans::get_membershp_plans_from_nodes();
		}
	}

	/**
	 * Get the value for each column
	 *
	 * @param Abstract_Event_Log_Item $item The line item.
	 * @param string                  $column_name The column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		if ( $column_name === 'network_pass_id' && $item[ $column_name ] ) {
			return sprintf( '<code>%s</code>', $item[ $column_name ] );
		}
		if ( $column_name === 'links' ) {
			$edit_url = get_edit_post_link( $item['id'] );
			if ( isset( $item['node_url'] ) ) {
				$edit_url = sprintf( '%s/wp-admin/post.php?post=%d&action=edit', $item['node_url'], $item['id'] );
			}
			return sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'newspack-network' ) );
		}
		return isset( $item[ $column_name ] ) ? $item[ $column_name ] : '';
	}
}
