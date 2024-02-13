<?php
/**
 * Newspack Network Admin customizations for woocommerce memberships.
 *
 * @package Newspack
 */

namespace Newspack_Network\Woocommerce_Memberships;

/**
 * Handles admin tweaks for woocommerce memberships.
 *
 * Adds a metabox to the membership plan edit screen to allow the user to add a network id metadata to the plans
 */
class Admin {

	/**
	 * The membership plan custom post type.
	 *
	 * @var string
	 */
	const MEMBERSHIPS_CPT = 'wc_membership_plan';

	/**
	 * The network id meta key.
	 *
	 * @var string
	 */
	const NETWORK_ID_META_KEY = '_newspack_network_id';

	/**
	 * The key of the metadata that flags a user membership that is managed in another site in the network.
	 *
	 * @var string
	 */
	const NETWORK_MANAGED_META_KEY = '_managed_by_newspack_network';

	/**
	 * Initializer.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ), 100 ); // Memberships plugin does something there and we need a higher priority.
		add_action( 'save_post', array( __CLASS__, 'save_meta_box' ) );
		add_filter( 'get_edit_post_link', array( __CLASS__, 'get_edit_post_link' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'post_row_actions' ), 99, 2 ); // After the Memberships plugin.
	}

	/**
	 * Adds a meta box to the membership plan edit screen.
	 */
	public static function add_meta_box() {
		add_meta_box(
			'newspack-network-memberships-meta-box',
			__( 'Newspack Network', 'newspack-network' ),
			array( __CLASS__, 'render_meta_box' ),
			self::MEMBERSHIPS_CPT,
			'advanced'
		);
	}

	/**
	 * Renders the meta box.
	 *
	 * @param \WP_Post $post The post object.
	 */
	public static function render_meta_box( $post ) {
		$network_id = get_post_meta( $post->ID, self::NETWORK_ID_META_KEY, true );

		wp_nonce_field( 'newspack_network_save_membership_plan', 'newspack_network_save_membership_plan_nonce' );
		?>
		<label for="newspack-network-id"><?php esc_html_e( 'Network ID', 'newspack-network' ); ?></label>
		<input type="text" id="newspack-network-id" name="newspack_network_id" value="<?php echo esc_attr( $network_id ); ?>" />
		<p><?php esc_html_e( 'If a Network ID is informed, the user memberships will be propagated to the network. The user will be granted with the membership with the matching Network ID in all other sites in the network.', 'newspack-network' ); ?></p>
		<?php
	}

	/**
	 * Saves the meta box.
	 *
	 * @param int $post_id The post ID.
	 */
	public static function save_meta_box( $post_id ) {

		$post_type = sanitize_text_field( $_POST['post_type'] ?? '' );

		if ( self::MEMBERSHIPS_CPT !== $post_type ) {
			return;
		}

		if ( ! isset( $_POST['newspack_network_save_membership_plan_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( $_POST['newspack_network_save_membership_plan_nonce'] ), 'newspack_network_save_membership_plan' )
		) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$post_type_object = get_post_type_object( $post_type );

		if ( ! current_user_can( $post_type_object->cap->edit_post, $post_id ) ) {
			return;
		}

		$network_id = sanitize_text_field( wp_unslash( $_POST['newspack_network_id'] ?? '' ) );
		update_post_meta( $post_id, self::NETWORK_ID_META_KEY, $network_id );
	}

	/**
	 * Filters the edit post link for user memberships managed in another site of the network.
	 *
	 * @param string $link The edit post link.
	 * @param int    $post_id The post ID.
	 * @return string
	 */
	public static function get_edit_post_link( $link, $post_id ) {
		$managed_by_newspack_network = get_post_meta( $post_id, self::NETWORK_MANAGED_META_KEY, true );
		if ( $managed_by_newspack_network ) {
			$link = str_replace( get_bloginfo( 'url' ), $managed_by_newspack_network, $link );
		}
		return $link;
	}

	/**
	 * Filters the row actions for user memberships managed in another site of the network.
	 *
	 * @param string[] $actions An array of row action links. Defaults are
	 *                          'Edit', 'Quick Edit', 'Restore', 'Trash',
	 *                          'Delete Permanently', 'Preview', and 'View'.
	 * @param WP_Post  $post    The post object.
	 * @return array
	 */
	public static function post_row_actions( $actions, $post ) {
		$managed_by_newspack_network = get_post_meta( $post->ID, self::NETWORK_MANAGED_META_KEY, true );
		if ( ! $managed_by_newspack_network ) {
			return $actions;
		}
		$link     = get_edit_post_link( $post->ID );
		$link_tag = sprintf( '<a href="%s" target="_blank">%s</a>', $link, $managed_by_newspack_network );
		return [
			'none' => sprintf(
				// translators: %s is the site URL with a link to edit the post.
				__( 'Managed in %s', 'newspack-network' ),
				$link_tag
			),
		];
	}
}
