<?php
/**
 * Newspack Hub Sites post type handling.
 *
 * @package Newspack
 */

namespace Newspack_Hub\Admin;

use Newspack_Hub\Admin;

/**
 * Class to handle Sites post type
 */
class Sites {

    /**
	 * CPT for Newsletter Lists.
	 */
	const CPT = 'newspack_hub_nodes';

	/**
	 * Initialize this class and register hooks
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_post_type' ] );
		//add_filter( 'wp_editor_settings', [ __CLASS__, 'filter_editor_settings' ], 10, 2 );
		add_action( 'save_post', [ __CLASS__, 'save_post' ] );
		add_filter( 'manage_' . self::CPT . '_posts_columns', [ __CLASS__, 'posts_columns' ] );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', [ __CLASS__, 'posts_columns_values' ], 10, 2 );

		add_action( 'delete_post', [ __CLASS__, 'delete_post' ] );
		add_action( 'wp_trash_post', [ __CLASS__, 'delete_post' ] );
	}

	/**
	 * Disable Rich text editing from the editor
	 *
	 * @param array  $settings The settings to be filtered.
	 * @param string $editor_id The editor identifier.
	 * @return array
	 */
	public static function filter_editor_settings( $settings, $editor_id ) {
		if ( 'content' === $editor_id && get_current_screen()->post_type === self::CPT ) {
			$settings['tinymce']       = false;
			$settings['quicktags']     = false;
			$settings['media_buttons'] = false;
		}

		return $settings;
	}

	/**
	 * Register the custom post type
	 *
	 * @return void
	 */
	public static function register_post_type() {

		$labels = array(
			'name'                  => _x( 'Sites', 'Post Type General Name', 'newspack' ),
			'singular_name'         => _x( 'Site', 'Post Type Singular Name', 'newspack' ),
			'menu_name'             => __( 'Sites', 'newspack' ),
			'name_admin_bar'        => __( 'Sites', 'newspack' ),
			'archives'              => __( 'Sites', 'newspack' ),
			'attributes'            => __( 'Sites', 'newspack' ),
			'parent_item_colon'     => __( 'Parent Site', 'newspack' ),
			'all_items'             => __( 'Sites', 'newspack' ),
			'add_new_item'          => __( 'Add new Site', 'newspack' ),
			'add_new'               => __( 'Add New', 'newspack' ),
			'new_item'              => __( 'New Site', 'newspack' ),
			'edit_item'             => __( 'Edit Site', 'newspack' ),
			'update_item'           => __( 'Update Site', 'newspack' ),
			'view_item'             => __( 'View Site', 'newspack' ),
			'view_items'            => __( 'View Sites', 'newspack' ),
			'search_items'          => __( 'Search Site', 'newspack' ),
			'not_found'             => __( 'Not found', 'newspack' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'newspack' ),
			'featured_image'        => __( 'Featured Image', 'newspack' ),
			'set_featured_image'    => __( 'Set featured image', 'newspack' ),
			'remove_featured_image' => __( 'Remove featured image', 'newspack' ),
			'use_featured_image'    => __( 'Use as featured image', 'newspack' ),
			'insert_into_item'      => __( 'Insert into item', 'newspack' ),
			'uploaded_to_this_item' => __( 'Uploaded to this item', 'newspack' ),
			'items_list'            => __( 'Items list', 'newspack' ),
			'items_list_navigation' => __( 'Items list navigation', 'newspack' ),
			'filter_items_list'     => __( 'Filter items list', 'newspack' ),
		);
		$args   = array(
			'label'                => __( 'Sites', 'newspack' ),
			'description'          => __( 'Newspack Sites', 'newspack' ),
			'labels'               => $labels,
			'supports'             => array( 'title' ),
			'hierarchical'         => false,
			'public'               => false,
			'show_ui'              => true,
			'show_in_menu'         => Admin::PAGE_SLUG,
			'can_export'           => false,
			'capability_type'      => 'page',
			'show_in_rest'         => false,
			'delete_with_user'     => false,
			'register_meta_box_cb' => [ __CLASS__, 'add_metabox' ],
		);
		register_post_type( self::CPT, $args );
	}

	/**
	 * Adds post type metaboxes
	 *
	 * @param WP_Post $post The current post.
	 * @return void
	 */
	public static function add_metabox( $post ) {
		add_meta_box(
			'newspack-hub-metabox',
			__( 'Site details' ),
			[ __CLASS__, 'metabox_content' ],
			self::CPT,
			'normal',
			'core'
		);
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
		$columns['links'] = __( 'Useful links', 'newspack-hub' );
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
			?>
				<p>
					Coming soon...
				</p>
			<?php
		}
	}

	/**
	 * Outputs metabox content
	 *
	 * @param WP_Post $post The current post.
	 * @return void
	 */
	public static function metabox_content( $post ) {

		wp_nonce_field( 'newspack_hub_save_site', 'newspack_hub_save_site_nonce' );

        $site_url = get_post_meta( $post->ID, 'site-url', true );
        $public_key = get_post_meta( $post->ID, 'public-key', true );
        $private_key = get_post_meta( $post->ID, 'private-key', true );

		?>
		<div class="misc-pub-section">
            Site URL: <input type="text" name="newspack-site-url" value="<?php echo esc_attr( $site_url ); ?>" />
		</div>

        <?php if ( $public_key || $private_key ): ?>

            <div class="misc-pub-section">
                Public Key: <?php echo esc_attr( $public_key ); ?>
                <br/>
                Private Key: <?php echo esc_attr( $private_key ); ?>
            </div>

        <?php endif; ?>

		<?php
	}

	/**
	 * Save post callback
	 *
	 * @param int $post_id The ID of the post being saved.
	 * @return void
	 */
	public static function save_post( $post_id ) {

		$post_type = sanitize_text_field( $_POST['post_type'] ?? '' );

		if ( self::CPT !== $post_type ) {
			return;
		}

		if ( ! isset( $_POST['newspack_hub_save_site_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( $_POST['newspack_hub_save_site_nonce'] ), 'newspack_hub_save_site' )
		) {
			return;
		}

		/*
		 * If this is an autosave, our form has not been submitted,
		 * so we don't want to do anything.
		 */
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$post_type_object = get_post_type_object( $post_type );

		if ( ! current_user_can( $post_type_object->cap->edit_post, $post_id ) ) {
			return;
		}

		if ( ! empty( $_POST['newspack-site-url'] ) && filter_var( $_POST['newspack-site-url'], FILTER_VALIDATE_URL ) ) {
            update_post_meta( $post_id, 'site-url', sanitize_text_field( $_POST['newspack-site-url'] ) );
        }

        $key = get_post_meta( $post_id, 'public-key', true );
        if ( ! $key ) {
            $sign_pair = sodium_crypto_sign_seed_keypair(random_bytes(SODIUM_CRYPTO_SIGN_SEEDBYTES));
            $private_key = sodium_bin2base64(sodium_crypto_sign_secretkey($sign_pair),SODIUM_BASE64_VARIANT_ORIGINAL);
            $public_key = sodium_bin2base64(sodium_crypto_sign_publickey($sign_pair),SODIUM_BASE64_VARIANT_ORIGINAL);
            update_post_meta( $post_id, 'public-key', $public_key );
            update_post_meta( $post_id, 'private-key', $private_key );
        }

	}

}