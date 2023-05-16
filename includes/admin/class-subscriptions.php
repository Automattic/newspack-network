<?php
/**
 * Newspack Hub Subscriptions Admin page
 *
 * @package Newspack
 */

namespace Newspack_Hub\Admin;

use Newspack_Hub\Admin;
use Newspack_Hub\Nodes;
use Newspack_Hub\Stores\Subscription_Item;
use Newspack_Hub\Database\Subscriptions as Subscriptions_DB;

/**
 * Class to handle the Subscriptions admin page by customizing the Custom Post type screen
 */
class Subscriptions {

	/**
	 * Runs the initialization.
	 */
	public static function init() {
		
		// Removes the Bulk actions dropdown.
		add_filter( 'bulk_actions-edit-' . Subscriptions_DB::POST_TYPE_SLUG, '__return_empty_array' );

		add_filter( 'post_row_actions', [ __CLASS__, 'remove_row_actions' ], 10, 2 );

		add_filter( 'get_edit_post_link', [ __CLASS__, 'get_edit_post_link' ], 10, 2 );

		add_filter( 'manage_' . Subscriptions_DB::POST_TYPE_SLUG . '_posts_columns', [ __CLASS__, 'posts_columns' ] );
		add_action( 'manage_' . Subscriptions_DB::POST_TYPE_SLUG . '_posts_custom_column', [ __CLASS__, 'posts_columns_values' ], 10, 2 );

		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_enqueue_scripts' ] );

		add_filter( 'disable_months_dropdown', [ __CLASS__, 'disable_restrict_manage_posts' ], 10, 2 );
		add_filter( 'disable_categories_dropdown', [ __CLASS__, 'disable_restrict_manage_posts' ], 10, 2 );
		add_filter( 'disable_formats_dropdown', [ __CLASS__, 'disable_restrict_manage_posts' ], 10, 2 );

		add_action( 'restrict_manage_posts', [ __CLASS__, 'restrict_manage_posts' ], 10, 2 );
		add_filter( 'pre_get_posts', [ __CLASS__, 'pre_get_posts' ] );
	}

	/**
	 * Enqueues the admin styles.
	 *
	 * @return void
	 */
	public static function admin_enqueue_scripts() {
		global $pagenow;
		if ( 'edit.php' !== $pagenow || Subscriptions_DB::POST_TYPE_SLUG !== get_post_type() ) {
			return;
		}
		
		wp_enqueue_style(
			'newspack-hub-subscriptions',
			plugins_url( 'css/subscriptions.css', __FILE__ ),
			[],
			filemtime( NEWSPACK_HUB_PLUGIN_DIR . '/incldes/admin/css/subscription.css' )
		);
	}

	/**
	 * Removes the row actions for the Subscriptions post type.
	 *
	 * @param array   $actions An array of row action links.
	 * @param WP_Post $post    The post object.
	 * @return array
	 */
	public static function remove_row_actions( $actions, $post ) {
		if ( Subscriptions_DB::POST_TYPE_SLUG === $post->post_type ) {
			return [];
		}
		return $actions;
	}

	/**
	 * Filters the edit link for a Subscription Item.
	 *
	 * @param string $link    The edit link.
	 * @param int    $post_id The post ID.
	 * @return string
	 */
	public static function get_edit_post_link( $link, $post_id ) {
		$item = new Subscription_Item( $post_id );
		if ( ! $item->get_id() ) { // Not a Subscription item.
			return $link;
		}
		$edit_link = $item->get_edit_link();
		// Even if get_edit_link returns null, that's what we'll use, because we don't want to link to the post edit page.
		return (string) $edit_link;
	}

	/**
	 * Disable the restrict manage posts dropdowns
	 *
	 * @param bool   $disable Whether to disable the dropdown.
	 * @param string $post_type The post type.
	 * @return bool
	 */
	public static function disable_restrict_manage_posts( $disable, $post_type ) {
		if ( Subscriptions_DB::POST_TYPE_SLUG === $post_type ) {
			return true;
		}
		return $disable;
	}

	/**
	 * Add filter options to the nav bar
	 *
	 * @param string $post_type The post type slug.
	 * @param string $which     The location of the extra table nav markup.
	 * @return void
	 */
	public static function restrict_manage_posts( $post_type, $which ) {
		if ( 'top' !== $which || Subscriptions_DB::POST_TYPE_SLUG !== $post_type ) {
			return;
		}

		$current_node = $_GET['node_id'] ?? '';

		Nodes::nodes_dropdown( $current_node );

	}

	/**
	 * Filters the main query in the admin page
	 *
	 * @param \WP_Query $query The Query object.
	 * @return void
	 */
	public static function pre_get_posts( $query ) {
		global $pagenow;
		$post_type = $_GET['post_type'] ?? '';
		if ( 'edit.php' !== $pagenow || Subscriptions_DB::POST_TYPE_SLUG !== $post_type || ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		
		if ( empty( $_GET['node_id'] ) ) {
			return;
		}
		$query->query_vars['meta_query'][] = [
			'key'   => 'node_id',
			'value' => $_GET['node_id'],
		];
		
	}

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
		$columns['status']            = __( 'Status', 'newspack-network-hub' );
		$columns['subscription']      = __( 'Subscription', 'newspack-network-hub' );
		$columns['items']             = __( 'Items', 'newspack-network-hub' );
		$columns['total']             = __( 'Total', 'newspack-network-hub' );
		$columns['start_date']        = __( 'Start Date', 'newspack-network-hub' );
		$columns['trial_end_date']    = __( 'Trial End', 'newspack-network-hub' );
		$columns['next_payment_date'] = __( 'Next Payment', 'newspack-network-hub' );
		$columns['last_payment_date'] = __( 'Last Order Date', 'newspack-network-hub' );
		$columns['end_date']          = __( 'End Date', 'newspack-network-hub' );
		$columns['orders']            = __( 'Orders', 'newspack-network-hub' );
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
		
		$item = new Subscription_Item( $post_id );

		switch ( $column ) {
			case 'status':
				?>
				<mark class="subscription-status order-status status-<?php echo esc_attr( $item->get_status() ); ?>">
					<span><?php echo esc_html( $item->get_status_label() ); ?></span>
				<?php
				break;
			case 'start_date':
			case 'trial_end_date':
			case 'next_payment_date':
			case 'last_payment_date':
			case 'end_date':
				$method_name = 'get_' . $column;
				$value       = $item->$method_name();
				if ( empty( $value ) ) {
					echo '-';
					break;
				}
				echo esc_html(
					gmdate( get_option( 'date_format' ), strtotime( $value ) )
				);
				break;
			case 'subscription':
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
			case 'items':
				$line_items = $item->get_line_items();
				$output     = '';
				foreach ( $line_items as $line_item ) {
					$output .= sprintf(
						'<a href="%s" target="_blank">%s</a><br />',
						sprintf(
							'%s/wp-admin/post.php?post=%d&action=edit',
							$item->get_node()->get_url(),
							$line_item['product_id'] ?? ''
						),
						$line_item['name'] ?? ''
					);
				}
                echo $output; // phpcs:ignore
				break;
			case 'orders':
				$link = sprintf(
					'%s/wp-admin/edit.php?post_status=all&post_type=shop_order&_subscription_related_orders=%d',
					$item->get_node()->get_url(), 
					$item->get_remote_id()
				);
				printf( '<a href="%s" target="blank">%s</a>', $link, $item->get_payment_count() ); // phpcs:ignore
				break;
			case 'total':
				printf( '%s<br/>Via %s', $item->get_formatted_total(), $item->get_payment_method_title() ); // phpcs:ignore
				break;
		}

	}

}
