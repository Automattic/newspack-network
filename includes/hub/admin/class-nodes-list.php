<?php
/**
 * Newspack Hub Event Log List Table
 *
 * @package Newspack
 */

namespace Newspack_Network\Hub\Admin;

use Newspack_Network\Hub\Nodes;
use Newspack_Network\Hub\Node;

/**
 * The Event Log List Table
 */
class Nodes_List {

	/**
	 * Initialize this class and register hooks
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'manage_' . Nodes::POST_TYPE_SLUG . '_posts_columns', [ __CLASS__, 'posts_columns' ] );
		add_action( 'manage_' . Nodes::POST_TYPE_SLUG . '_posts_custom_column', [ __CLASS__, 'posts_columns_values' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_enqueue_scripts' ] );
	}

	/**
	 * Modify columns on post type table
	 *
	 * @param array $columns Registered columns.
	 * @return array
	 */
	public static function posts_columns( $columns ) {
		unset( $columns['date'] );
		unset( $columns['stats'] );
		$columns['links'] = __( 'Useful links', 'newspack-network' );
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
		if ( 'links' === $column ) {
			$node         = new Node( $post_id );
			$links        = array_map(
				function ( $bookmark ) {
					return sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $bookmark['url'] ), esc_html( $bookmark['label'] ) );
				},
				$node->get_bookmarks()
			);
			$allowed_tags = [
				'a' => [
					'href'   => [],
					'target' => [],
				],
			];
			?>
				<p>
					<?php echo wp_kses( implode( ' | ', $links ), $allowed_tags ); ?>
				</p>
			<?php
		}
	}

	/**
	 * Enqueues the admin styles.
	 *
	 * @return void
	 */
	public static function admin_enqueue_scripts() {
		$page_slug = 'edit-' . Nodes::POST_TYPE_SLUG;
		if ( get_current_screen()->id !== $page_slug ) {
			return;
		}

		wp_enqueue_style(
			'newspack-network-nodes-list',
			plugins_url( 'css/nodes-list.css', __FILE__ ),
			[],
			filemtime( NEWSPACK_NETWORK_PLUGIN_DIR . '/includes/hub/admin/css/nodes-list.css' )
		);
	}

}
