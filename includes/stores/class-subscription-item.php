<?php
/**
 * Newspack Subscription Item
 *
 * @package Newspack
 */

namespace Newspack_Hub\Stores;

use Newspack_Hub\Debugger;
use Newspack_Hub\Node;
use Newspack_Hub\Database\Subscriptions as Subscriptions_DB;
use WP_Post;

/**
 * Subscription Item
 */
class Subscription_Item {

	/**
	 * The WP_Post object for this Item
	 *
	 * @var WP_Post
	 */
	private $post;

	/**
	 * Constructs a new Item
	 *
	 * @param WP_Post|int $item A WP_Post object or a post ID.
	 */
	public function __construct( $item ) {
		if ( is_numeric( $item ) ) {
			$item = get_post( $item );
		}

		if ( ! $item instanceof WP_Post || Subscriptions_DB::POST_TYPE_SLUG !== $item->post_type ) {
			return false;
		}

		$this->post = $item;
	}

	/**
	 * Returns the Item's ID
	 *
	 * @return ?int
	 */
	public function get_id() {
		if ( $this->post instanceof WP_Post && ! empty( $this->post->ID ) ) {
			return $this->post->ID;
		}
	}

	/**
	 * Returns the Item's Title
	 *
	 * @return ?string
	 */
	public function get_title() {
		if ( $this->post instanceof WP_Post && ! empty( $this->post->post_title ) ) {
			return $this->post->post_title;
		}
	}

	/**
	 * Returns the Item's Status
	 *
	 * @return ?int
	 */
	public function get_status() {
		if ( $this->post instanceof WP_Post && ! empty( $this->post->post_status ) ) {
			return $this->post->post_status;
		}
	}

	/**
	 * Returns the Item's Status label
	 *
	 * @return ?string
	 */
	public function get_status_label() {
		$status_object = get_post_status_object( $this->get_status() );
		if ( ! is_object( $status_object ) ) {
			return;
		}
		return $status_object->label ?? null;
	}

	/**
	 * Returns the Item's remote ID
	 *
	 * @return ?string
	 */
	public function get_remote_id() {
		return get_post_meta( $this->get_id(), 'remote_id', true );
	}

	/**
	 * Returns the Item's Node
	 *
	 * @return ?string
	 */
	public function get_node() {
		return new Node( get_post_meta( $this->get_id(), 'node_id', true ) );
	}

	/**
	 * Returns the Item's user_email
	 *
	 * @return ?string
	 */
	public function get_user_email() {
		return get_post_meta( $this->get_id(), 'user_email', true );
	}

	/**
	 * Returns the Item's user_name
	 *
	 * @return ?string
	 */
	public function get_user_name() {
		return get_post_meta( $this->get_id(), 'user_name', true );
	}

	/**
	 * Returns the Item's payment_count
	 *
	 * @return ?string
	 */
	public function get_payment_count() {
		return get_post_meta( $this->get_id(), 'payment_count', true );
	}

	/**
	 * Returns the Item's currency
	 *
	 * @return ?string
	 */
	public function get_currency() {
		return get_post_meta( $this->get_id(), 'currency', true );
	}

	/**
	 * Returns the Item's total
	 *
	 * @return ?string
	 */
	public function get_total() {
		return get_post_meta( $this->get_id(), 'total', true );
	}

	/**
	 * Returns the Item's formatted_total
	 *
	 * @return ?string
	 */
	public function get_formatted_total() {
		return get_post_meta( $this->get_id(), 'formatted_total', true );
	}

	/**
	 * Returns the Item's payment_method_title
	 *
	 * @return ?string
	 */
	public function get_payment_method_title() {
		return get_post_meta( $this->get_id(), 'payment_method_title', true );
	}

	/**
	 * Returns the Item's start_date
	 *
	 * @return ?string
	 */
	public function get_start_date() {
		return get_post_meta( $this->get_id(), 'start_date', true );
	}

	/**
	 * Returns the Item's trial_end_date
	 *
	 * @return ?string
	 */
	public function get_trial_end_date() {
		return get_post_meta( $this->get_id(), 'trial_end_date', true );
	}

	/**
	 * Returns the Item's next_payment_date
	 *
	 * @return ?string
	 */
	public function get_next_payment_date() {
		return get_post_meta( $this->get_id(), 'next_payment_date', true );
	}

	/**
	 * Returns the Item's last_payment_date
	 *
	 * @return ?string
	 */
	public function get_last_payment_date() {
		return get_post_meta( $this->get_id(), 'last_payment_date', true );
	}

	/**
	 * Returns the Item's end_date
	 *
	 * @return ?string
	 */
	public function get_end_date() {
		return get_post_meta( $this->get_id(), 'end_date', true );
	}

	/**
	 * Returns the Item's line_items
	 *
	 * @return ?array Array of line items with name and product_id keys.
	 */
	public function get_line_items() {
		return get_post_meta( $this->get_id(), 'line_items', false );
	}

	/**
	 * Returns the Item's edit link
	 *
	 * @return ?string
	 */
	public function get_edit_link() {
		$remote_id = $this->get_remote_id();
		$node_url  = $this->get_node()->get_url();
		if ( ! $remote_id || ! $node_url ) {
			return;
		}
		return $node_url . '/wp-admin/post.php?post=' . $remote_id . '&action=edit';
	}
	
}
