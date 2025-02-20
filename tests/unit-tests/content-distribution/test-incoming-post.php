<?php
/**
 * Class TestIncomingPost
 *
 * @package Newspack_Network
 */

namespace Test\Content_Distribution;

use Newspack_Network\Content_Distribution\Incoming_Post;

/**
 * Test the Incoming_Post class.
 */
class TestIncomingPost extends \WP_UnitTestCase {
	/**
	 * URL for node that distributes posts.
	 *
	 * @var string
	 */
	protected $node_1 = 'https://node1.test';

	/**
	 * URL for node that receives posts.
	 *
	 * @var string
	 */
	protected $node_2 = 'https://node2.test';

	/**
	 * A user with the editor role.
	 *
	 * @var \WP_User
	 */
	protected $some_editor;

	/**
	 * A linked post.
	 *
	 * @var Incoming_Post
	 */
	protected $incoming_post;

	/**
	 * Get sample post payload.
	 */
	private function get_sample_payload() {
		return get_sample_payload( $this->node_1, $this->node_2 );
	}

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		// Set the site URL for the node that receives posts.
		update_option( 'siteurl', $this->node_2 );
		update_option( 'home', $this->node_2 );

		$this->some_editor = $this->factory->user->create_and_get( [ 'role' => 'editor' ] );

		$this->incoming_post = new Incoming_Post( $this->get_sample_payload() );
	}

	/**
	 * Test get payload error
	 */
	public function test_validate_payload() {
		$payload = $this->get_sample_payload();
		$error = Incoming_Post::get_payload_error( $payload );
		$this->assertFalse( is_wp_error( $error ) );

		// Assert with invalid post.
		$error = Incoming_Post::get_payload_error( [] );
		$this->assertTrue( is_wp_error( $error ) );
		$this->assertSame( 'invalid_post', $error->get_error_code() );

		// Assert with invalid site.
		update_option( 'siteurl', $this->node_1 );
		update_option( 'home', $this->node_1 );
		$error = Incoming_Post::get_payload_error( $payload );
		$this->assertTrue( is_wp_error( $error ) );
		$this->assertSame( 'not_distributed_to_site', $error->get_error_code() );
	}

	/**
	 * Test insert linked post.
	 */
	public function test_insert() {
		$this->assertEmpty( $this->incoming_post->ID );

		$post_id = $this->incoming_post->insert();

		$this->assertNotEmpty( $this->incoming_post->ID );

		$this->assertFalse( is_wp_error( $post_id ) );
		$this->assertGreaterThan( 0, $post_id );

		$payload = $this->get_sample_payload();

		// Assert post data.
		$this->assertSame( $payload['post_data']['date_gmt'], get_the_date( 'Y-m-d H:i:s', $post_id ) );
		$this->assertSame( $payload['post_data']['title'], get_the_title( $post_id ) );
		$this->assertSame( $payload['post_data']['raw_content'], get_post_field( 'post_content', $post_id ) );

		// Assert featured image.
		$this->assertNotEmpty( get_post_thumbnail_id( $post_id ) );

		// Assert taxonomy terms.
		$terms = wp_get_post_terms( $post_id, [ 'category', 'post_tag' ] );
		$this->assertSame( [ 'Category 1', 'Category 2', 'Tag 1', 'Tag 2' ], wp_list_pluck( $terms, 'name' ) );
		$this->assertSame( [ 'category-1', 'category-2', 'tag-1', 'tag-2' ], wp_list_pluck( $terms, 'slug' ) );

		// Assert post meta.
		$this->assertSame( 'value', get_post_meta( $post_id, 'single', true ) );
		$this->assertSame( [ 'a' => 'b', 'c' => 'd' ], get_post_meta( $post_id, 'array', true ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$this->assertSame( [ 'value 1', 'value 2' ], get_post_meta( $post_id, 'multiple' ) );
	}

	/**
	 * Test instantiation with post ID.
	 */
	public function test_instantiation_with_post_id() {
		$this->incoming_post->insert();

		$incoming_post = new Incoming_Post( $this->incoming_post->ID );

		$this->assertInstanceOf( Incoming_Post::class, $incoming_post );
		$this->assertSame( $this->incoming_post->ID, $incoming_post->ID );
	}

	/**
	 * Test insert existing linked post.
	 */
	public function test_insert_existing_post() {
		// Insert the linked post for the first time.
		$post_id = $this->incoming_post->insert();

		// Modify the post payload to simulate an update.
		$payload = $this->get_sample_payload();
		$payload['post_data']['title'] = 'Updated Title';
		$payload['post_data']['content'] = 'Updated Content';
		$payload['post_data']['raw_content'] = 'Updated Content';

		// Insert the updated linked post.
		$new_linked_post = new Incoming_Post( $payload );
		$updated_post_id = $new_linked_post->insert();

		// Assert that the updated post has the same ID as the original post.
		$this->assertSame( $post_id, $updated_post_id );

		// Assert that the updated post has the updated title and content.
		$incoming_post = get_post( $post_id );
		$this->assertSame( 'Updated Title', $incoming_post->post_title );
		$this->assertSame( 'Updated Content', $incoming_post->post_content );
	}

	/**
	 * Test insert post when unlinked.
	 */
	public function test_insert_post_when_unlinked() {
		$post_id = $this->incoming_post->insert();

		// Unlink the post.
		$this->incoming_post->set_unlinked();

		// Update linked post with custom content.
		$this->factory->post->update_object(
			$post_id,
			[
				'post_title'   => 'Custom Title',
				'post_content' => 'Custom Content',
			]
		);

		// Modify the post payload for an update.
		$payload = $this->get_sample_payload();
		$payload['post_data']['title'] = 'Updated Title';
		$payload['post_data']['content'] = 'Updated Content';
		$payload['post_data']['raw_content'] = 'Updated Content';

		// Insert the updated linked post.
		$this->incoming_post->insert( $payload );

		// Assert that the custom content was preserved.
		$incoming_post = get_post( $post_id );
		$this->assertSame( 'Custom Title', $incoming_post->post_title );
		$this->assertSame( 'Custom Content', $incoming_post->post_content );
	}

	/**
	 * Test get original post URL.
	 */
	public function test_get_original_post_url() {
		$post_id = $this->incoming_post->insert();
		$original_url = $this->incoming_post->get_original_post_url();
		$payload = $this->get_sample_payload();
		$this->assertSame( $payload['post_url'], $original_url );
	}

	/**
	 * Test get original site URL.
	 */
	public function test_get_original_site_url() {
		$post_id = $this->incoming_post->insert();
		$original_url = $this->incoming_post->get_original_site_url();
		$payload = $this->get_sample_payload();
		$this->assertSame( $payload['site_url'], $original_url );
	}

	/**
	 * Test get original post edit URL.
	 */
	public function test_get_original_post_edit_url() {
		$this->incoming_post->insert();
		$this->assertSame(
			'https://node1.test/wp-admin/post.php?post=1&action=edit',
			$this->incoming_post->get_original_post_edit_url()
		);
	}

	/**
	 * Test relink post.
	 */
	public function test_relink_post() {
		$post_id = $this->incoming_post->insert();

		// Unlink the post.
		$this->incoming_post->set_unlinked();

		// Update linked post with custom content.
		$this->factory->post->update_object(
			$post_id,
			[
				'post_title'   => 'Custom Title',
				'post_content' => 'Custom Content',
			]
		);

		// Relink the post.
		$this->incoming_post->set_unlinked( false );

		// Assert that the post is linked and distributed content restored.
		$payload = $this->get_sample_payload();
		$this->assertSame( $payload['post_data']['title'], get_the_title( $post_id ) );
		$this->assertSame( $payload['post_data']['raw_content'], get_post_field( 'post_content', $post_id ) );
	}

	/**
	 * Test insert post with old modified date.
	 */
	public function test_insert_post_with_old_modified_date() {
		// Insert the linked post for the first time.
		$post_id = $this->incoming_post->insert();

		// Modify the post payload to simulate an update with an old modified date.
		$payload = $this->get_sample_payload();
		$payload['post_data']['title'] = 'Old Title';
		$payload['post_data']['modified_gmt'] = '2020-01-01 00:00:00';

		// Insert the updated linked post.
		$error = $this->incoming_post->insert( $payload );

		// Assert that the insertion returned an error.
		$this->assertTrue( is_wp_error( $error ) );
		$this->assertSame( 'old_modified_date', $error->get_error_code() );

		// Assert that the linked post kept the most recent title.
		$incoming_post = get_post( $post_id );
		$this->assertSame( 'Title', $incoming_post->post_title );
	}

	/**
	 * Test update post thumbnail.
	 */
	public function test_update_post_thumbnail() {
		$post_id = $this->incoming_post->insert();

		$thumbnail_id = get_post_thumbnail_id( $post_id );

		// Set a different thumbnail URL.
		$payload = $this->get_sample_payload();
		$payload['post_data']['thumbnail_url'] = 'https://picsum.photos/id/2/300/300.jpg';

		// Insert the linked post with the updated thumbnail.
		$this->incoming_post->insert( $payload );

		// Assert that the thumbnail was updated.
		$new_thumbnail_id = get_post_thumbnail_id( $post_id );

		$this->assertNotEmpty( $new_thumbnail_id );
		$this->assertNotEquals( $thumbnail_id, $new_thumbnail_id );
	}

	/**
	 * Test remove post thumbnail.
	 */
	public function test_remove_post_thumbnail() {
		$post_id = $this->incoming_post->insert();

		// Remove the thumbnail.
		$payload = $this->get_sample_payload();
		$payload['post_data']['thumbnail_url'] = false;

		// Insert the linked post with the removed thumbnail.
		$this->incoming_post->insert( $payload );

		// Assert that the thumbnail was removed.
		$thumbnail_id = get_post_thumbnail_id( $post_id );
		$this->assertEmpty( $thumbnail_id );
	}

	/**
	 * Test post meta sync.
	 */
	public function test_post_meta_sync() {
		$post_id = $this->incoming_post->insert();

		// Unlink the post.
		$this->incoming_post->set_unlinked();

		// Update the post meta.
		update_post_meta( $post_id, 'custom', 'new value' );

		// Relink the post.
		$this->incoming_post->set_unlinked( false );

		// Assert that the custom post meta was removed on relink.
		$this->assertEmpty( get_post_meta( $post_id, 'custom', true ) );
	}

	/**
	 * Test adding and deleting post meta.
	 */
	public function test_add_and_delete_multiple_post_meta() {
		$post_id = $this->incoming_post->insert();

		$payload = $this->get_sample_payload();

		$payload['post_data']['post_meta']['multiple'] = [ 'value 2', 'value 3' ];
		$this->incoming_post->insert( $payload );
		$this->assertSame( [ 'value 2', 'value 3' ], get_post_meta( $post_id, 'multiple' ) );

		$payload['post_data']['post_meta']['multiple'] = [ 'value 3', 'value 3' ];
		$this->incoming_post->insert( $payload );
		$this->assertSame( [ 'value 3', 'value 3' ], get_post_meta( $post_id, 'multiple' ) );
	}

	/**
	 * Test status changes.
	 */
	public function test_status_changes() {
		$post_id = $this->incoming_post->insert();

		// Assert that the default post status is draft.
		$this->assertSame( 'draft', get_post_status( $post_id ) );

		// Publish the linked post.
		wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => 'publish',
			]
		);

		$payload = $this->get_sample_payload();

		// Assert that the post status updates to draft.
		$payload['post_data']['post_status'] = 'draft';
		$this->incoming_post->insert( $payload );
		$this->assertSame( 'draft', get_post_status( $post_id ) );

		// Assert that the post status does NOT update to publish.
		$payload['post_data']['post_status'] = 'publish';
		$this->incoming_post->insert( $payload );
		$this->assertSame( 'draft', get_post_status( $post_id ) );

		// Assert that the post status updates to trash.
		$payload['post_data']['post_status'] = 'trash';
		$this->incoming_post->insert( $payload );
		$this->assertSame( 'trash', get_post_status( $post_id ) );
	}

	/**
	 * Test delete.
	 */
	public function test_delete() {
		$post_id = $this->incoming_post->insert();

		$this->incoming_post->delete();

		// Assert that the post was trashed and the payload was removed.
		$this->assertSame( 'trash', get_post_status( $post_id ) );
		$this->assertEmpty( get_post_meta( $post_id, Incoming_Post::PAYLOAD_META, true ) );
	}

	/**
	 * Test delete trashed post.
	 */
	public function test_delete_trashed_post() {
		$post_id = $this->incoming_post->insert();

		wp_trash_post( $post_id );

		$this->incoming_post->delete();

		// Assert that the post remained trashed and the payload was removed.
		$this->assertSame( 'trash', get_post_status( $post_id ) );
		$this->assertEmpty( get_post_meta( $post_id, Incoming_Post::PAYLOAD_META, true ) );
	}

	/**
	 * Test delete unlinked.
	 */
	public function test_delete_unlinked() {
		$post_id = $this->incoming_post->insert();

		$this->assertNotEmpty( get_post( $post_id ) );

		$this->incoming_post->set_unlinked();
		$this->incoming_post->delete();

		// Assert that the post remained as draft and the payload was removed.
		$this->assertSame( 'draft', get_post_status( $post_id ) );
		$this->assertEmpty( get_post_meta( $post_id, Incoming_Post::PAYLOAD_META, true ) );
	}

	/**
	 * Test ignored taxonomies.
	 */
	public function test_ignored_taxonomies() {
		$payload = $this->get_sample_payload();
		$taxonomy = 'author';

		// Register an ignored taxonomy.
		register_taxonomy( $taxonomy, 'post', [ 'public' => true ] );

		$payload['post_data']['taxonomy']['author'] = [
			[
				'name' => 'Author 1',
				'slug' => 'author-1',
			],
		];

		// Insert the linked post.
		$post_id = $this->incoming_post->insert( $payload );

		// Assert that the post does not have the ignored taxonomy term.
		$terms = wp_get_post_terms( $post_id, $taxonomy );
		$this->assertEmpty( $terms );
	}

	/**
	 * Test comment and ping statuses.
	 */
	public function test_comment_and_ping_statuses() {
		$payload = $this->get_sample_payload();

		// Insert the linked post with comment and ping statuses.
		$post_id = $this->incoming_post->insert( $payload );

		// Assert that the post has the comment and ping statuses.
		$this->assertSame( 'open', get_post_field( 'comment_status', $post_id ) );
		$this->assertSame( 'open', get_post_field( 'ping_status', $post_id ) );

		// Update the comment and ping statuses.
		$payload['post_data']['comment_status'] = 'closed';
		$payload['post_data']['ping_status'] = 'closed';

		// Insert the updated linked post.
		$this->incoming_post->insert( $payload );

		// Assert that the post has the updated comment and ping statuses.
		$this->assertSame( 'closed', get_post_field( 'comment_status', $post_id ) );
		$this->assertSame( 'closed', get_post_field( 'ping_status', $post_id ) );
	}

	/**
	 * Test status on create.
	 */
	public function test_status_on_create() {
		$payload = $this->get_sample_payload();

		$payload['status_on_create'] = 'publish';

		$post_id = $this->incoming_post->insert( $payload );

		// Assert that the post is published.
		$this->assertSame( 'publish', get_post_status( $post_id ) );

		// Place the post back to draft.
		wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => 'draft',
			]
		);

		// Assert that distributing again will not publish it.
		$this->incoming_post->insert( $payload );
		$this->assertSame( 'draft', get_post_status( $post_id ) );
	}

	/**
	 * Test partial post payload on insert.
	 */
	public function test_partial_payload_insert() {
		$post_id = $this->incoming_post->insert();

		// Make the payload a partial.
		$payload              = $this->get_sample_payload();
		$payload['partial']   = true;
		$payload['post_data'] = [
			'title'        => 'Updated Title',
			'date_gmt'     => $payload['post_data']['date_gmt'],
			'modified_gmt' => $payload['post_data']['modified_gmt'],
		];

		$this->incoming_post->insert( $payload );

		// Assert that the post title was updated and the content was not.
		$this->assertSame( 'Updated Title', get_the_title( $post_id ) );
		$this->assertSame( 'Content', get_post_field( 'post_content', $post_id ) );
	}

	/**
	 * Test partial post payload on instantiation.
	 */
	public function test_partial_payload_instantiation() {
		$post_id = $this->incoming_post->insert();

		// Make the payload a partial.
		$payload              = $this->get_sample_payload();
		$payload['partial']   = true;
		$payload['post_data'] = [
			'title'        => 'Updated Title',
			'date_gmt'     => $payload['post_data']['date_gmt'],
			'modified_gmt' => $payload['post_data']['modified_gmt'],
		];

		$incoming_post = new Incoming_Post( $payload );
		$incoming_post->insert();

		// Assert that the post title was updated and the content was not.
		$this->assertSame( 'Updated Title', get_the_title( $post_id ) );
		$this->assertSame( 'Content', get_post_field( 'post_content', $post_id ) );
	}

	/**
	 * Test partial payload on missing post.
	 */
	public function test_partial_payload_missing_post() {
		$payload = $this->get_sample_payload();

		// Make the payload a partial.
		$payload['partial']   = true;
		$payload['post_data'] = [
			'title'        => 'Updated Title',
			'date_gmt'     => $payload['post_data']['date_gmt'],
			'modified_gmt' => $payload['post_data']['modified_gmt'],
		];

		// Assert that instantiating a partial payload will throw an exception.
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Partial payload requires an existing post.' );
		new Incoming_Post( $payload );
	}
}
