<?php
/**
 * Class TestReconcileMemberships
 *
 * @package Newspack_Network
 */

use Newspack_Network\CLI\Integrity_Check;

/**
 * Test the Reconcile_Memberships::classify_discrepancies method.
 *
 * @group reconcile
 */
class TestReconcileMemberships extends WP_UnitTestCase {

	/**
	 * Helper to get a ReflectionMethod for classify_discrepancies.
	 *
	 * @return ReflectionMethod
	 */
	private function get_classify_discrepancies_method() {
		$classify_discrepancies_method = new ReflectionMethod( Integrity_Check::class, 'classify_discrepancies' );
		$classify_discrepancies_method->setAccessible( true );
		return $classify_discrepancies_method;
	}

	/**
	 * When hub and node data match exactly, there are no discrepancies.
	 */
	public function test_no_discrepancies_when_data_matches() {
		$classify_discrepancies_method = $this->get_classify_discrepancies_method();

		$hub_lookup = [
			'alice@example.com::plan-a' => [
				'email'         => 'alice@example.com',
				'status'        => 'wcm-active',
				'network_id'    => 'plan-a',
				'post_modified' => '2024-01-15 10:00:00',
				'membership_id' => 101,
			],
		];

		$node_memberships = [
			[
				'email'      => 'alice@example.com',
				'status'     => 'wcm-active',
				'network_id' => 'plan-a',
			],
		];

		$node_managed_lookup = [];

		$discrepancies = $classify_discrepancies_method->invoke( null, $hub_lookup, $node_memberships, $node_managed_lookup );

		$this->assertEmpty( $discrepancies );
	}

	/**
	 * When the hub has a membership the node doesn't, it is classified as
	 * missing_on_node with action push_to_node.
	 */
	public function test_missing_on_node_results_in_push_to_node_action() {
		$classify_discrepancies_method = $this->get_classify_discrepancies_method();

		$hub_lookup = [
			'bob@example.com::plan-b' => [
				'email'         => 'bob@example.com',
				'status'        => 'wcm-active',
				'network_id'    => 'plan-b',
				'post_modified' => '2024-02-10 12:00:00',
				'membership_id' => 202,
			],
		];

		$node_memberships    = [];
		$node_managed_lookup = [];

		$discrepancies = $classify_discrepancies_method->invoke( null, $hub_lookup, $node_memberships, $node_managed_lookup );

		$this->assertCount( 1, $discrepancies );
		$this->assertEquals( 'bob@example.com', $discrepancies[0]['email'] );
		$this->assertEquals( 'plan-b', $discrepancies[0]['network_id'] );
		$this->assertEquals( 'missing_on_node', $discrepancies[0]['type'] );
		$this->assertEquals( 'wcm-active', $discrepancies[0]['hub_status'] );
		$this->assertEquals( '', $discrepancies[0]['node_status'] );
		$this->assertEquals( 'push_to_node', $discrepancies[0]['action'] );
	}

	/**
	 * When the node has a membership the hub doesn't, it is classified as
	 * missing_on_hub with action skip.
	 */
	public function test_missing_on_hub_results_in_skip_action() {
		$classify_discrepancies_method = $this->get_classify_discrepancies_method();

		$hub_lookup = [];

		$node_memberships = [
			[
				'email'      => 'carol@example.com',
				'status'     => 'wcm-cancelled',
				'network_id' => 'plan-c',
			],
		];

		$node_managed_lookup = [];

		$discrepancies = $classify_discrepancies_method->invoke( null, $hub_lookup, $node_memberships, $node_managed_lookup );

		$this->assertCount( 1, $discrepancies );
		$this->assertEquals( 'carol@example.com', $discrepancies[0]['email'] );
		$this->assertEquals( 'plan-c', $discrepancies[0]['network_id'] );
		$this->assertEquals( 'missing_on_hub', $discrepancies[0]['type'] );
		$this->assertEquals( '', $discrepancies[0]['hub_status'] );
		$this->assertEquals( 'wcm-cancelled', $discrepancies[0]['node_status'] );
		$this->assertEquals( 'skip', $discrepancies[0]['action'] );
	}

	/**
	 * When there is a status mismatch and the hub's post_modified is later than
	 * the node's, the hub is authoritative and the action is push_to_node.
	 */
	public function test_status_mismatch_hub_newer_results_in_push_to_node() {
		$classify_discrepancies_method = $this->get_classify_discrepancies_method();

		$hub_lookup = [
			'dan@example.com::plan-d' => [
				'email'         => 'dan@example.com',
				'status'        => 'wcm-active',
				'network_id'    => 'plan-d',
				'post_modified' => '2024-06-20 09:00:00',
				'membership_id' => 303,
			],
		];

		$node_memberships = [
			[
				'email'      => 'dan@example.com',
				'status'     => 'wcm-cancelled',
				'network_id' => 'plan-d',
			],
		];

		// Node managed lookup has an older timestamp than the hub.
		$node_managed_lookup = [
			'dan@example.com::plan-d' => [
				'email'         => 'dan@example.com',
				'status'        => 'wcm-cancelled',
				'network_id'    => 'plan-d',
				'post_modified' => '2024-06-10 08:00:00',
				'remote_id'     => 303,
			],
		];

		$discrepancies = $classify_discrepancies_method->invoke( null, $hub_lookup, $node_memberships, $node_managed_lookup );

		$this->assertCount( 1, $discrepancies );
		$this->assertEquals( 'status_mismatch', $discrepancies[0]['type'] );
		$this->assertEquals( 'wcm-active', $discrepancies[0]['hub_status'] );
		$this->assertEquals( 'wcm-cancelled', $discrepancies[0]['node_status'] );
		$this->assertEquals( 'push_to_node', $discrepancies[0]['action'] );
	}

	/**
	 * When there is a status mismatch and the node's post_modified is later than
	 * the hub's, the node is treated as having fresher data and the action is skip.
	 */
	public function test_status_mismatch_node_newer_results_in_skip() {
		$classify_discrepancies_method = $this->get_classify_discrepancies_method();

		$hub_lookup = [
			'eve@example.com::plan-e' => [
				'email'         => 'eve@example.com',
				'status'        => 'wcm-cancelled',
				'network_id'    => 'plan-e',
				'post_modified' => '2024-03-01 07:00:00',
				'membership_id' => 404,
			],
		];

		$node_memberships = [
			[
				'email'      => 'eve@example.com',
				'status'     => 'wcm-active',
				'network_id' => 'plan-e',
			],
		];

		// Node managed lookup has a newer timestamp than the hub.
		$node_managed_lookup = [
			'eve@example.com::plan-e' => [
				'email'         => 'eve@example.com',
				'status'        => 'wcm-active',
				'network_id'    => 'plan-e',
				'post_modified' => '2024-03-15 14:00:00',
				'remote_id'     => 404,
			],
		];

		$discrepancies = $classify_discrepancies_method->invoke( null, $hub_lookup, $node_memberships, $node_managed_lookup );

		$this->assertCount( 1, $discrepancies );
		$this->assertEquals( 'status_mismatch', $discrepancies[0]['type'] );
		$this->assertEquals( 'skip', $discrepancies[0]['action'] );
	}

	/**
	 * When there is a status mismatch but the membership is absent from the node
	 * managed lookup, the hub is treated as authoritative and action is push_to_node.
	 */
	public function test_status_mismatch_no_node_timestamp_defaults_to_hub_authoritative() {
		$classify_discrepancies_method = $this->get_classify_discrepancies_method();

		$hub_lookup = [
			'frank@example.com::plan-f' => [
				'email'         => 'frank@example.com',
				'status'        => 'wcm-active',
				'network_id'    => 'plan-f',
				'post_modified' => '2024-04-05 11:00:00',
				'membership_id' => 505,
			],
		];

		$node_memberships = [
			[
				'email'      => 'frank@example.com',
				'status'     => 'wcm-paused',
				'network_id' => 'plan-f',
			],
		];

		// Node has the membership but it is not in the managed lookup, so no
		// timestamp is available for comparison.
		$node_managed_lookup = [];

		$discrepancies = $classify_discrepancies_method->invoke( null, $hub_lookup, $node_memberships, $node_managed_lookup );

		$this->assertCount( 1, $discrepancies );
		$this->assertEquals( 'status_mismatch', $discrepancies[0]['type'] );
		$this->assertEquals( 'wcm-active', $discrepancies[0]['hub_status'] );
		$this->assertEquals( 'wcm-paused', $discrepancies[0]['node_status'] );
		$this->assertEquals( 'push_to_node', $discrepancies[0]['action'] );
	}

	/**
	 * Multiple discrepancies of different types are all returned in a single call.
	 */
	public function test_mixed_discrepancies_returns_all_types() {
		$classify_discrepancies_method = $this->get_classify_discrepancies_method();

		// Grace is on hub only → missing_on_node / push_to_node.
		// Henry is on node only → missing_on_hub / skip.
		// Iris has a status mismatch with hub newer → status_mismatch / push_to_node.
		// Jane matches → no discrepancy.
		$hub_lookup = [
			'grace@example.com::plan-g' => [
				'email'         => 'grace@example.com',
				'status'        => 'wcm-active',
				'network_id'    => 'plan-g',
				'post_modified' => '2024-05-01 10:00:00',
				'membership_id' => 601,
			],
			'iris@example.com::plan-i'  => [
				'email'         => 'iris@example.com',
				'status'        => 'wcm-active',
				'network_id'    => 'plan-i',
				'post_modified' => '2024-05-10 10:00:00',
				'membership_id' => 603,
			],
			'jane@example.com::plan-j'  => [
				'email'         => 'jane@example.com',
				'status'        => 'wcm-active',
				'network_id'    => 'plan-j',
				'post_modified' => '2024-05-12 10:00:00',
				'membership_id' => 604,
			],
		];

		$node_memberships = [
			[
				'email'      => 'henry@example.com',
				'status'     => 'wcm-cancelled',
				'network_id' => 'plan-h',
			],
			[
				'email'      => 'iris@example.com',
				'status'     => 'wcm-cancelled',
				'network_id' => 'plan-i',
			],
			[
				'email'      => 'jane@example.com',
				'status'     => 'wcm-active',
				'network_id' => 'plan-j',
			],
		];

		$node_managed_lookup = [
			'iris@example.com::plan-i' => [
				'email'         => 'iris@example.com',
				'status'        => 'wcm-cancelled',
				'network_id'    => 'plan-i',
				'post_modified' => '2024-05-08 08:00:00',
				'remote_id'     => 603,
			],
		];

		$discrepancies = $classify_discrepancies_method->invoke( null, $hub_lookup, $node_memberships, $node_managed_lookup );

		$this->assertCount( 3, $discrepancies );

		$discrepancy_types_by_email = [];
		$discrepancy_actions_by_email = [];
		foreach ( $discrepancies as $discrepancy ) {
			$discrepancy_types_by_email[ $discrepancy['email'] ]   = $discrepancy['type'];
			$discrepancy_actions_by_email[ $discrepancy['email'] ] = $discrepancy['action'];
		}

		$this->assertEquals( 'missing_on_node', $discrepancy_types_by_email['grace@example.com'] );
		$this->assertEquals( 'push_to_node', $discrepancy_actions_by_email['grace@example.com'] );

		$this->assertEquals( 'missing_on_hub', $discrepancy_types_by_email['henry@example.com'] );
		$this->assertEquals( 'skip', $discrepancy_actions_by_email['henry@example.com'] );

		$this->assertEquals( 'status_mismatch', $discrepancy_types_by_email['iris@example.com'] );
		$this->assertEquals( 'push_to_node', $discrepancy_actions_by_email['iris@example.com'] );

		// Jane matches exactly and must not appear in discrepancies.
		$this->assertArrayNotHasKey( 'jane@example.com', $discrepancy_types_by_email );
	}

	/**
	 * Test that a membership transfer is detected when missing_on_hub and missing_on_node
	 * entries for the same network_id are linked by the node's managed membership remote_id.
	 */
	public function test_transfer_detected_via_remote_id() {
		$classify_discrepancies_method = new ReflectionMethod( Integrity_Check::class, 'classify_discrepancies' );
		$classify_discrepancies_method->setAccessible( true );

		// Hub: membership 500 belongs to newowner@example.com (after transfer).
		$hub_lookup = [
			'newowner@example.com::plan-x' => [
				'email'         => 'newowner@example.com',
				'status'        => 'wcm-active',
				'network_id'    => 'plan-x',
				'post_modified' => '2024-06-01 12:00:00',
				'membership_id' => 500,
			],
		];

		// Node: still has it under the old owner.
		$node_memberships = [
			[
				'email'      => 'oldowner@example.com',
				'status'     => 'wcm-active',
				'network_id' => 'plan-x',
			],
		];

		// Node managed lookup: the old owner's membership points to remote_id 500.
		$node_managed_lookup = [
			'oldowner@example.com::plan-x' => [
				'email'         => 'oldowner@example.com',
				'status'        => 'wcm-active',
				'network_id'    => 'plan-x',
				'post_modified' => '2024-04-01 12:00:00',
				'remote_id'     => 500,
			],
		];

		$discrepancies = $classify_discrepancies_method->invoke( null, $hub_lookup, $node_memberships, $node_managed_lookup );

		// Should be a single transfer, not two separate missing entries.
		$this->assertCount( 1, $discrepancies );
		$this->assertEquals( 'transfer', $discrepancies[0]['type'] );
		$this->assertEquals( 'push_transfer', $discrepancies[0]['action'] );
		$this->assertEquals( 'newowner@example.com', $discrepancies[0]['email'] );
		$this->assertEquals( 'oldowner@example.com', $discrepancies[0]['previous_email'] );
		$this->assertEquals( 'plan-x', $discrepancies[0]['network_id'] );
	}

	/**
	 * Test that missing_on_hub entries without a matching remote_id are not converted to transfers.
	 */
	public function test_non_transfer_missing_on_hub_stays_as_skip() {
		$classify_discrepancies_method = new ReflectionMethod( Integrity_Check::class, 'classify_discrepancies' );
		$classify_discrepancies_method->setAccessible( true );

		$hub_lookup = [
			'alice@example.com::plan-y' => [
				'email'         => 'alice@example.com',
				'status'        => 'wcm-active',
				'network_id'    => 'plan-y',
				'post_modified' => '2024-06-01 12:00:00',
				'membership_id' => 600,
			],
		];

		// Node has a membership under a different email, same plan, but remote_id doesn't match.
		$node_memberships = [
			[
				'email'      => 'bob@example.com',
				'status'     => 'wcm-active',
				'network_id' => 'plan-y',
			],
		];

		$node_managed_lookup = [
			'bob@example.com::plan-y' => [
				'email'         => 'bob@example.com',
				'status'        => 'wcm-active',
				'network_id'    => 'plan-y',
				'post_modified' => '2024-04-01 12:00:00',
				'remote_id'     => 999, // Different from hub's membership_id 600.
			],
		];

		$discrepancies = $classify_discrepancies_method->invoke( null, $hub_lookup, $node_memberships, $node_managed_lookup );

		// Should be two separate entries, not a transfer.
		$this->assertCount( 2, $discrepancies );

		$types = array_column( $discrepancies, 'type' );
		$this->assertContains( 'missing_on_node', $types );
		$this->assertContains( 'missing_on_hub', $types );
		$this->assertNotContains( 'transfer', $types );
	}
}
