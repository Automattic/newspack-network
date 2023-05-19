<?php
/**
 * Newspack Hub Orders Admin page
 *
 * @package Newspack
 */

namespace Newspack_Hub\Admin;

use Newspack_Hub\Admin;
use Newspack_Hub\Nodes;
use Newspack_Hub\Stores\Woo_Item;

/**
 * Class to handle the Orders admin page by customizing the Custom Post type screen
 */
class Orders extends Woo {

	/**
	 * Modify columns on post type table
	 *
	 * @param array $columns Registered columns.
	 * @return array
	 */
	public static function posts_columns( $columns ) {
		unset( $columns['cb'] );
		unset( $columns['title'] );
		unset( $columns['date'] );
		$columns['order']                     = __( 'Order', 'newspack-network-hub' );
		$columns['date']                      = __( 'Date', 'newspack-network-hub' );
		$columns['status']                    = __( 'Status', 'newspack-network-hub' );
		$columns['subscription_relationship'] = __( 'x', 'newspack-network-hub' );
		$columns['total']                     = __( 'Total', 'newspack-network-hub' );
		return $columns;

	}

	/**
	 * Add content to the custom column
	 *
	 * @param string $column The current column.
	 * @param int    $post_id The current post ID.
	 * @return void
	 */
	public static function posts_columns_values( $column, $post_id ) {
		
		$store_class_name = str_replace( 'Admin', 'Stores', get_called_class() );
		$item             = $store_class_name::get_item( $post_id );

		switch ( $column ) {
			case 'order':
				$output = sprintf(
					'<a href="%s" target="_blank">%s</a> for %s on <a href="%s" target="_blank">%s</a>',
					$item->get_edit_link(),
					$item->get_title(),
					$item->get_user_name(),
					$item->get_node()->get_url(),
					$item->get_node()->get_url()
				);
				echo $output; // phpcs:ignore
				break;
			case 'status':
				?>
				<mark class="subscription-status order-status status-<?php echo esc_attr( $item->get_status() ); ?>">
					<span><?php echo esc_html( $item->get_status_label() ); ?></span>
				<?php
				break;
			case 'date':
				$value = $item->get_date_created();
				if ( empty( $value ) ) {
					echo '-';
					break;
				}
				echo esc_html(
					gmdate( get_option( 'date_format' ), strtotime( $value ) )
				);
				break;
			case 'subscription_relationship':
				echo $item->get_subscription_relationship(); // phpcs:ignore
				break;
			case 'total':
				echo $item->get_formatted_total();
				break;
		}

	}

}
