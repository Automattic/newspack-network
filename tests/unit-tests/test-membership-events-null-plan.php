<?php
/**
 * Test that Events listeners handle memberships with deleted plans gracefully.
 *
 * Regression test for NPPM-2742: a membership whose plan post was deleted
 * caused a PHP fatal in Events::membership_deleted() and
 * Events::membership_transferred() when calling get_plan()->get_id() on null.
 *
 * @package Newspack_Network
 */

use Newspack_Network\Woocommerce_Memberships\Events as Memberships_Events;

/**
 * Minimal stub for WC_Memberships_User_Membership used in Events listener tests.
 *
 * Only the methods called by the listeners are implemented.
 */
class Mock_User_Membership {
	/**
	 * The user.
	 *
	 * @var WP_User|null
	 */
	private $user;

	/**
	 * The plan.
	 *
	 * @var object|null
	 */
	private $plan;

	/**
	 * The membership ID.
	 *
	 * @var int
	 */
	private $id;

	/**
	 * Constructor.
	 *
	 * @param WP_User|null $user The user object.
	 * @param object|null  $plan The plan object (null to simulate deleted plan).
	 * @param int          $id   The membership ID.
	 */
	public function __construct( $user = null, $plan = null, $id = 1 ) {
		$this->user = $user;
		$this->plan = $plan;
		$this->id   = $id;
	}

	/**
	 * Get the user.
	 *
	 * @return WP_User|false
	 */
	public function get_user() {
		return $this->user ? $this->user : false;
	}

	/**
	 * Get the plan.
	 *
	 * @return object|null
	 */
	public function get_plan() {
		return $this->plan;
	}

	/**
	 * Get the membership ID.
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}
}

/**
 * Tests for Events listeners when the membership plan has been deleted.
 *
 * @group membership-events
 */
class TestMembershipEventsNullPlan extends WP_UnitTestCase {

	/**
	 * Ensure $pause_events is false for these tests.
	 */
	public function setUp(): void {
		parent::setUp();
		Memberships_Events::$pause_events = false;
	}

	/**
	 * Restore $pause_events after each test.
	 */
	public function tearDown(): void {
		Memberships_Events::$pause_events = false;
		parent::tearDown();
	}

	/**
	 * Test that membership_deleted returns null (no fatal) when plan is missing.
	 */
	public function test_membership_deleted_returns_null_when_plan_missing() {
		$user = $this->factory->user->create_and_get( [ 'user_email' => 'deleted-plan@example.com' ] );

		$membership = new Mock_User_Membership( $user, null, 42 );

		$result = Memberships_Events::membership_deleted( $membership );

		$this->assertNull( $result, 'membership_deleted should return null when plan is missing, not fatal.' );
	}

	/**
	 * Ensure the null-plan fix didn't break the existing no-network-ID early return.
	 */
	public function test_membership_deleted_returns_null_when_plan_has_no_network_id() {
		$user = $this->factory->user->create_and_get( [ 'user_email' => 'no-network-id@example.com' ] );

		$plan_id = $this->factory->post->create( [ 'post_type' => 'wc_membership_plan' ] );
		$plan    = (object) [ 'id' => $plan_id ];
		$plan->get_id = function() use ( $plan_id ) {
			return $plan_id;
		};

		// Use a proper mock plan object with get_id method.
		$mock_plan = new class( $plan_id ) {
			private $id;
			public function __construct( $id ) {
				$this->id = $id;
			}
			public function get_id() {
				return $this->id;
			}
		};

		$membership = new Mock_User_Membership( $user, $mock_plan, 43 );

		// Plan exists but has no NETWORK_ID_META_KEY, so should return null.
		$result = Memberships_Events::membership_deleted( $membership );

		$this->assertNull( $result, 'membership_deleted should return null when plan has no network ID.' );
	}

}
