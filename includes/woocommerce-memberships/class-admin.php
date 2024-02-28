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
	 * The membership plan custom post type (defined in the woocommerce-memberships plugin).
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
	 * The key of the metadata that holds the ID of the user membership in the origin site
	 *
	 * @var string
	 */
	const REMOTE_ID_META_KEY = '_remote_id';

	/**
	 * The key of the metadata that holds the URL of the origin site
	 *
	 * @var string
	 */
	const SITE_URL_META_KEY = '_remote_site_url';

	/**
	 * Initializer.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ), 100 ); // Memberships plugin does something there and we need a higher priority.
		add_action( 'save_post', array( __CLASS__, 'save_meta_box' ) );
		add_filter( 'get_edit_post_link', array( __CLASS__, 'get_edit_post_link' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'post_row_actions' ), 99, 2 ); // After the Memberships plugin.
		add_filter( 'map_meta_cap', array( __CLASS__, 'map_meta_cap' ), 20, 4 );
		add_filter( 'wc_memberships_rest_api_membership_plan_data', [ __CLASS__, 'add_data_to_membership_plan_response' ], 2, 3 );
	}

	/**
	 * Filter membership plans to add user count.
	 *
	 * @param array                           $data associative array of membership plan data.
	 * @param \WC_Memberships_Membership_Plan $plan the membership plan.
	 * @param null|\WP_REST_Request           $request The request object.
	 */
	public static function add_data_to_membership_plan_response( $data, $plan, $request ) {
		if ( $request && isset( $request->get_headers()['x_np_network_signature'] ) ) {
			$data['active_members_count'] = $plan->get_memberships_count( 'active' );
		}
		return $data;
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
		<p><?php esc_html_e( 'If a Network ID is set, the user memberships will be propagated on the network. Users will be granted the membership with the matching Network ID in all other sites in the network.', 'newspack-network' ); ?></p>
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
			$remote_url = get_post_meta( $post_id, self::SITE_URL_META_KEY, true );
			$remote_id  = get_post_meta( $post_id, self::REMOTE_ID_META_KEY, true );
			$link       = sprintf( '%s/wp-admin/post.php?post=%d&action=edit', $remote_url, $remote_id );
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
		$remote_url                  = get_post_meta( $post->ID, self::SITE_URL_META_KEY, true );
		if ( ! $managed_by_newspack_network ) {
			return $actions;
		}
		$link     = self::get_edit_post_link( '', $post->ID );
		$link_tag = sprintf( '<a href="%s" target="_blank">%s</a>', $link, $remote_url );
		return [
			'none' => sprintf(
				// translators: %s is the site URL with a link to edit the post.
				__( 'Managed in %s', 'newspack-network' ),
				$link_tag
			),
		];
	}

	/**
	 * Blocks any user from editing a user membership that is managed in another site of the network.
	 *
	 * @param array  $caps The array of required primitive capabilities or roles for the requested capability.
	 * @param string $cap The requested capability.
	 * @param int    $user_id The user ID we are checking permissions for.
	 * @param array  $args The context args passed to the check.
	 * @return array
	 */
	public static function map_meta_cap( $caps, $cap, $user_id, $args ) {
		if ( 'edit_post' === $cap ) {
			$post_id                     = $args[0];
			$managed_by_newspack_network = get_post_meta( $post_id, self::NETWORK_MANAGED_META_KEY, true );
			if ( $managed_by_newspack_network ) {
				$caps = [ 'do_not_allow' ];
			}
		}
		return $caps;
	}
}
