<?php
/**
 * Newspack Network Woocommerce Membership Updated event
 *
 * @package Newspack
 */

namespace Newspack_Network\Incoming_Events;

use Newspack_Network\Debugger;
use Newspack_Network\Utils\Users as User_Utils;
use Newspack_Network\User_Update_Watcher;
use Newspack_Network\Woocommerce_Memberships\Admin as Memberships_Admin;
use Newspack_Network\Woocommerce_Memberships\Events as Memberships_Events;
use WC_Memberships_User_Membership;

/**
 * Class to handle the Registered Incoming Event
 */
class Woocommerce_Membership_Updated extends Abstract_Incoming_Event {

	/**
	 * Processes the event
	 *
	 * @return void
	 */
	public function post_process_in_hub() {
		$this->update_membership();
	}

	/**
	 * Process event in Node
	 *
	 * @return void
	 */
	public function process_in_node() {
		$this->update_membership();
	}

	/**
	 * Maybe creates a new WP user based on this event
	 *
	 * @return void
	 */
	public function update_membership() {
		$email = $this->get_email();
		Debugger::log( 'Processing Woo Membership update with email: ' . $email );
		if ( ! $email ) {
			return;
		}

		if ( ! function_exists( 'wc_memberships_get_user_membership' ) || ! function_exists( 'wc_memberships_create_user_membership' ) ) {
			return;
		}

		global $wpdb;

		$local_plan_id = $wpdb->get_var( // phpcs:ignore
			$wpdb->prepare(
				"SELECT post_id from $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s AND post_id IN ( SELECT ID FROM $wpdb->posts WHERE post_type = %s ) ",
				Memberships_Admin::NETWORK_ID_META_KEY,
				$this->get_plan_network_id(),
				Memberships_Admin::MEMBERSHIP_PLANS_CPT
			)
		);

		if ( ! $local_plan_id ) {
			Debugger::log( 'Local plan not found' );
			return;
		}

		Memberships_Events::$pause_events = true;
		User_Update_Watcher::$enabled     = false;

		$user = User_Utils::get_or_create_user_by_email( $email, $this->get_site(), $this->data->user_id ?? '' );

		$user_membership = wc_memberships_get_user_membership( $user->ID, $local_plan_id );

		if ( null === $user_membership ) {
			$user_membership = wc_memberships_create_user_membership(
				[
					'plan_id' => $local_plan_id,
					'user_id' => $user->ID,
				]
			);

			update_post_meta( $user_membership->get_id(), Memberships_Admin::NETWORK_MANAGED_META_KEY, true );
			update_post_meta( $user_membership->get_id(), Memberships_Admin::REMOTE_ID_META_KEY, $this->get_membership_id() );
			update_post_meta( $user_membership->get_id(), Memberships_Admin::SITE_URL_META_KEY, $this->get_site() );
		}

		if ( is_wp_error( $user_membership ) ) {
			Debugger::log( 'Error creating membership plan: ' . $user_membership->get_error_message() );
			return;
		}

		if ( ! $user_membership instanceof WC_Memberships_User_Membership ) {
			Debugger::log( 'Error creating membership plan' );
			return;
		}

		$user_membership->update_status( $this->get_new_status() );
		$user_membership->add_note(
			sprintf(
				// translators: %s is the site URL.
				__( 'Membership status updated via Newspack Network. Status propagated from %s', 'newspack-network' ),
				$this->get_site()
			)
		);

		Debugger::log( 'User membership updated' );
	}

	/**
	 * Get the network id of the membership's pan
	 *
	 * @return ?string
	 */
	public function get_plan_network_id() {
		return $this->data->plan_network_id ?? null;
	}

	/**
	 * Get the new status of the membership
	 *
	 * @return ?string
	 */
	public function get_new_status() {
		return $this->data->new_status ?? null;
	}

	/**
	 * Get the original membership id
	 *
	 * @return ?string
	 */
	public function get_membership_id() {
		return $this->data->membership_id ?? null;
	}
}
