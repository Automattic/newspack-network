<?php
/**
 * Newspack Hub Subscriptions Table
 *
 * @package Newspack
 */

namespace Newspack_Network\Hub\Admin;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * The Subscriptions Table
 */
class Subscriptions_Table extends \WP_List_Table {
	/**
	 * Get the table columns
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = [
			'status'            => __( 'Status', 'newspack-network' ),
			'id'                => __( 'Subscription', 'newspack-network' ),
			'total'             => __( 'Total', 'newspack-network' ),
			'start_date'        => __( 'Start Date', 'newspack-network' ),
			'last_payment_date' => __( 'Last Payment Date', 'newspack-network' ),
			'end_date'          => __( 'End Date', 'newspack-network' ),
			'site_url'          => __( 'Site URL', 'newspack-network' ),
		];
		return $columns;
	}

	/**
	 * Prepare items to be displayed
	 */
	public function prepare_items() {
		$args = [
			'per_page' => 20,
		];
		if ( isset( $_GET['node_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['node_id'] = sanitize_text_field( $_GET['node_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$subscriptions_from_network_data = Subscriptions::get_subscriptions_from_network( $args );

		// Pagination.
		$total_items = count( $subscriptions_from_network_data['subscriptions'] );
		$per_page     = $this->get_items_per_page( 'elements_per_page', $args['per_page'] );
		$current_page = $this->get_pagenum();
		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			]
		);

		// Sorting.
		$order = $_REQUEST['order'] ?? false; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$orderby = $_REQUEST['orderby'] ?? false; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( $order && $orderby ) {
			usort(
				$subscriptions_from_network_data['subscriptions'],
				function( $a, $b ) use ( $orderby, $order ) {
					if ( $order === 'asc' ) {
						return $a[ $orderby ] <=> $b[ $orderby ];
					}
					return $b[ $orderby ] <=> $a[ $orderby ];
				}
			);
		}

		$items = $subscriptions_from_network_data['subscriptions'];

		// Handle search.
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : '';  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $search ) {
			$items = array_filter(
				$items,
				function( $item ) use ( $search ) {
					$searchable_fields = [ 'email', 'first_name', 'last_name' ];
					foreach ( $searchable_fields as $field ) {
						if ( false !== stripos( $item[ $field ], $search ) ) {
							return true;
						}
					}
				}
			);
		}

		// Handle pagination.
		$items = array_slice( $items, $per_page * ( $current_page - 1 ), $per_page );

		$this->items = $items;
		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns(), 'id' ];
	}

	/**
	 * Get the value for each column
	 *
	 * @param Abstract_Event_Log_Item $item The line item.
	 * @param string                  $column_name The column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		$value = isset( $item[ $column_name ] ) ? $item[ $column_name ] : '';
		if ( $value === '' ) {
			return '';
		}
		switch ( $column_name ) {
			case 'status':
				$status_name = wcs_get_subscription_status_name( $item['status'] );
				return sprintf( '<mark class="subscription-status order-status status-%s %s"><span>%s</span></mark>', $item['status'], $item['status'], $status_name );
			case 'id':
				$subscription_link_url = sprintf( '%s/wp-admin/post.php?post=%d&action=edit', $item['site_url'], $item['id'] );
				$subscription_link = sprintf( '<a href="%s"><strong>#%s</strong></a>', $subscription_link_url, $item['id'] );
				$user_link_url = sprintf( '%s/wp-admin/user-edit.php?user_id=%d', $item['site_url'], $item['customer_id'] );
				$user_link_text = sprintf( '%s (#%d)', $item['email'], $item['customer_id'] );
				$user_link = sprintf( '<a href="%s">%s</a>', $user_link_url, $user_link_text );
				return sprintf( '%s for %s', $subscription_link, $user_link );
			case 'total':
				$subscription_details = array(
					'currency'              => $item['currency'],
					'recurring_amount'      => $value,
					'subscription_period'   => $item['billing_period'],
					'subscription_interval' => $item['billing_interval'],
				);
				return wcs_price_string( $subscription_details );
			case 'start_date':
			case 'last_payment_date':
			case 'end_date':
				$datetime = wcs_get_datetime_from( $value );
				if ( $datetime ) {
					return $datetime->date_i18n( 'F j, Y' );
				} else {
					return '';
				}
				break;
			default:
				return isset( $item[ $column_name ] ) ? $item[ $column_name ] : '';
		}
	}

	/**
	 * Get sortable columns.
	 */
	public function get_sortable_columns() {
		return [
			'id'                => [ 'id', false, __( 'Subscription ID' ), __( 'Table ordered by the subscription id.' ) ],
			'total'             => [ 'total', false, __( 'Total' ), __( 'Table ordered by the total amount.' ) ],
			'start_date'        => [ 'start_date', false, __( 'Start Date' ), __( 'Table ordered by the start date.' ) ],
			'end_date'          => [ 'end_date', false, __( 'End Date' ), __( 'Table ordered by the end date.' ) ],
			'last_payment_date' => [ 'last_payment_date', false, __( 'Last Payment Date' ), __( 'Table ordered by the last payment date.' ) ],
		];
	}

	/**
	 * Extra controls to be displayed between bulk actions and pagination.
	 *
	 * @param string $which Which table nave, top or bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$current_node = isset( $_GET['node_id'] ) ? sanitize_text_field( $_GET['node_id'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
			<?php \Newspack_Network\Hub\Nodes::network_sites_dropdown( $current_node ); ?>
			<input type="submit" name="filter_action" class="button" value="<?php esc_attr_e( 'Filter', 'newspack-network' ); ?>">
		<?php
	}
}
