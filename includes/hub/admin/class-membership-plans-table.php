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
	 * Get the table columns
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = [
			'id'   => __( 'ID', 'newspack-network' ),
			'name' => __( 'Name', 'newspack-network' ),
		];
		$columns['site_url'] = __( 'Site URL', 'newspack-network' );
		$columns['network_pass_id'] = __( 'Network ID', 'newspack-network' );
		if ( \Newspack_Network\Admin::use_experimental_auditing_features() ) {
			$columns['active_members_count'] = __( 'Active Members', 'newspack-network' );
			$columns['network_pass_discrepancies'] = __( 'Discrepancies', 'newspack-network' );
		}
		$columns['links'] = __( 'Links', 'newspack-network' );
		return $columns;
	}

	/**
	 * Prepare items to be displayed
	 */
	public function prepare_items() {
		$this->_column_headers = [ $this->get_columns(), [], [], 'id' ];
		$this->items = Membership_Plans::get_membershp_plans_from_network();
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
		if ( $column_name === 'network_pass_discrepancies' && isset( $item['network_pass_discrepancies'] ) && $item['network_pass_id'] ) {
			$discrepancies = $item['network_pass_discrepancies'];
			return sprintf(
				/* translators: %d is the number of members */
				_n(
					'%d member doesn\'t match the shared member pool',
					'%d members don\'t match the shared member pool',
					count( $discrepancies ),
					'newspack-plugin'
				),
				count( $discrepancies )
			);
		}
		if ( $column_name === 'links' ) {
			$edit_url = sprintf( '%s/wp-admin/post.php?post=%d&action=edit', $item['site_url'], $item['id'] );
			return sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'newspack-network' ) );
		}
		if ( $column_name === 'active_members_count' && $item[ $column_name ] ) {
			$list_url = sprintf( '%s/wp-admin/edit.php?s&post_status=wcm-active&post_type=wc_user_membership&post_parent=%d', $item['site_url'], $item['id'] );
			return sprintf( '<a href="%s">%s</a>', esc_url( $list_url ), $item[ $column_name ] );
		}
		return isset( $item[ $column_name ] ) ? $item[ $column_name ] : '';
	}
}