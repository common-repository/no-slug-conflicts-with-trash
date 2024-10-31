<?php

defined( 'ABSPATH' ) or die();

class No_Slug_Conflict_With_Trash_Test extends WP_UnitTestCase {

	public function setUp() {
		if ( version_compare( get_bloginfo( 'version' ), '4.5', '>=' ) ) {
			$this->markTestSkipped( 'These tests only apply for versions of WordPress older than 4.5.' );
		}

		parent::setUp();
		$this->meta_key = c2c_No_Slug_Conflicts_With_Trash::get_meta_key();
	}


	//
	//
	// DATA PROVIDERS
	//
	//


	public static function get_post_types() {
		return array(
			array( 'post' ),
			array( 'page' ),
		);
	}


	//
	//
	// HELPER FUNCTIONS
	//
	//


	protected function remove_plugin_hooks() {
		// Remove default hooks
		$obj = c2c_No_Slug_Conflicts_With_Trash::get_instance();
		remove_filter( 'wp_unique_post_slug',    array( $obj, 'wp_unique_post_slug' ), 10, 6 );
		remove_action( 'transition_post_status', array( $obj, 'maybe_restore_changed_slug' ), 10, 3 );
	}


	//
	//
	// TESTS
	//
	//


	/**
	 * Tests that plugin number is set and valid.
	 */
	public function test_version() {
		$this->assertEquals( '1.2', c2c_No_Slug_Conflicts_With_Trash::version() );
	}

	/**
	 * Tests that the plugin does not affect a post being trashed when
	 * there are no slug conflicts.
	 *
	 * Specifically:
	 * - Slug is not changed.
	 * - Meta field is not created.
	 *
	 * @dataProvider get_post_types
	 */
	public function test_trash_then_immediately_untrash( $post_type ) {
		$post_id_a = $this->factory->post->create( array( 'post_type' => $post_type, 'post_name' => 'about' ) );
		wp_trash_post( $post_id_a );

		$post_a = get_post( $post_id_a );

		$this->assertEquals( 'about', $post_a->post_name );
		$this->assertEmpty( get_post_meta( $post_id_a, $this->meta_key, true ) );
	}

	/**
	 * Tests when a post is created that conflicts with a post in trash.
	 *
	 * Specifically:
	 * - The trashed post should get its slug changed.
	 * - The trashed post should have its original slug stored in meta.
	 * - The new post should get its desired slug.
	 *
	 * @dataProvider get_post_types
	 */
	public function test_trashA_createB( $post_type ) {
		$post_id_a = $this->factory->post->create( array( 'post_type' => $post_type, 'post_name' => 'about' ) );
		wp_trash_post( $post_id_a );
		$post_id_b = $this->factory->post->create( array( 'post_type' => $post_type, 'post_name' => 'about' ) );

		$post_a = get_post( $post_id_a );
		$post_b = get_post( $post_id_b );

		$this->assertEquals( 'about-2', $post_a->post_name );
		$this->assertEquals( 'about', get_post_meta( $post_id_a, $this->meta_key, true ) );
		$this->assertEquals( 'about', $post_b->post_name );
	}

	/**
	 * Tests when a trashed post is restored and conflicts with a published post.
	 *
	 * Specifically:
	 * - The restored post should retain its changed (non-original) slug.
	 * - The restored post should have its meta field deleted.
	 * - The published post should retain its slug.
	 *
	 * @dataProvider get_post_types
	 */
	public function test_trashA_createB_then_untrashA( $post_type ) {
		$post_id_a = $this->factory->post->create( array( 'post_type' => $post_type, 'post_name' => 'about' ) );
		wp_trash_post( $post_id_a );
		$post_id_b = $this->factory->post->create( array( 'post_type' => $post_type, 'post_name' => 'about' ) );
		wp_untrash_post( $post_id_a );

		$post_a = get_post( $post_id_a );
		$post_b = get_post( $post_id_b );

		$this->assertEquals( 'about-2', $post_a->post_name );
		$this->assertEmpty( get_post_meta( $post_id_a, $this->meta_key, true ) );
		$this->assertEquals( 'about', $post_b->post_name );
		$this->assertEmpty( get_post_meta( $post_id_b, $this->meta_key, true ) );
	}

	/**
	 * Tests when a trashed post (that had conflicted with a subsequently
	 * published post that itself had since been trashed) is restored.
	 *
	 * Specifically:
	 * - The original trashed post should have its original slug.
	 * - The original trashed post should have its meta field deleted.
	 * - The second trashed post should get its slug changed (since it was the last
	 *   to have been published with the slug).
	 *
	 * @dataProvider get_post_types
	 */
	public function test_trashA_trashB_then_untrashA( $post_type ) {
		$post_id_a = $this->factory->post->create( array( 'post_type' => $post_type, 'post_name' => 'about' ) );
		wp_trash_post( $post_id_a );
		$post_id_b = $this->factory->post->create( array( 'post_type' => $post_type, 'post_name' => 'about' ) );
		wp_trash_post( $post_id_b );
		wp_untrash_post( $post_id_a );

		$post_a = get_post( $post_id_a );
		$post_b = get_post( $post_id_b );

		$this->assertEquals( 'about', $post_a->post_name );
		$this->assertEmpty( get_post_meta( $post_id_a, $this->meta_key, true ) );
		$this->assertEquals( 'about-2-2', $post_b->post_name );
		$this->assertEquals( 'about', get_post_meta( $post_id_b, $this->meta_key, true ) );
	}

	/**
	 * Tests when a trashed post (that had conflicted with an existing
	 * trashed post) is restored.
	 *
	 * Specifically:
	 * - The original trashed post should have its original slug.
	 * - The original trashed post should have its meta field deleted.
	 * - The second trashed post should get its slug changed (since it was the last
	 *   to have been published with the slug).
	 *
	 * @dataProvider get_post_types
	 */
	public function test_trashA_trashB_then_untrashB( $post_type ) {
		$post_id_a = $this->factory->post->create( array( 'post_type' => $post_type, 'post_name' => 'about' ) );
		wp_trash_post( $post_id_a );
		$post_id_b = $this->factory->post->create( array( 'post_type' => $post_type, 'post_name' => 'about' ) );
		wp_trash_post( $post_id_b );
		wp_untrash_post( $post_id_b );

		$post_a = get_post( $post_id_a );
		$post_b = get_post( $post_id_b );

		$this->assertEquals( 'about-2', $post_a->post_name );
		$this->assertEquals( 'about', get_post_meta( $post_id_a, $this->meta_key, true ) );
		$this->assertEquals( 'about', $post_b->post_name );
		$this->assertEmpty( get_post_meta( $post_id_b, $this->meta_key, true ) );
	}

	/**
	 * Tests when a post is published and trashed, then a conflicting post is
	 * published and trashed, then both are restored (in their order of creation).
	 *
	 * Specifically:
	 * - The original trashed post should have its original slug.
	 * - The original trashed post should have its meta field deleted.
	 * - The second trashed post should have a slug that doesn't match its original slug.
	 * - The second trashed post shoul dhave its meta field deleted.
	 *
	 * @dataProvider get_post_types
	 */
	public function test_trashA_trashB_then_untrashA_untrashB( $post_type ) {
		$post_id_a = $this->factory->post->create( array( 'post_type' => $post_type, 'post_name' => 'about' ) );
		wp_trash_post( $post_id_a );
		$post_id_b = $this->factory->post->create( array( 'post_type' => $post_type, 'post_name' => 'about' ) );
		wp_trash_post( $post_id_b );
		wp_untrash_post( $post_id_a );
		wp_untrash_post( $post_id_b );

		$post_a = get_post( $post_id_a );
		$post_b = get_post( $post_id_b );

		$this->assertEquals( 'about', $post_a->post_name );
		$this->assertEmpty( get_post_meta( $post_id_a, $this->meta_key, true ) );
		$this->assertEquals( 'about-2-2', $post_b->post_name );
		$this->assertEmpty( get_post_meta( $post_id_b, $this->meta_key, true ) );
	}

	/**
	 * Tests when a post conflicts with two trashed posts that previously had the
	 * same slug.
	 *
	 * Specifically:
	 * - The new post should have its desired slug.
	 * - The two older trashed posts should have sequentially incremented slug
	 *   variations.
	 * - The two trashed posts should have their identical original slugs saved in
	 * - post meta.
	 * - The new post should not have the meta field set.
	 *
	 * @dataProvider get_post_types
	 */
	public function test_trashA_trashB_then_createC( $post_type ) {
		$post_id_a = $this->factory->post->create( array( 'post_type' => $post_type, 'post_name' => 'about' ) );
		wp_trash_post( $post_id_a );
		$post_id_b = $this->factory->post->create( array( 'post_type' => $post_type, 'post_name' => 'about' ) );
		wp_trash_post( $post_id_b );
		$post_id_c = $this->factory->post->create( array( 'post_type' => $post_type, 'post_name' => 'about' ) );

		$post_a = get_post( $post_id_a );
		$post_b = get_post( $post_id_b );
		$post_c = get_post( $post_id_c );

		$this->assertEquals( 'about-2', $post_a->post_name );
		$this->assertEquals( 'about', get_post_meta( $post_id_a, $this->meta_key, true ) );
		$this->assertEquals( 'about-3', $post_b->post_name );
		$this->assertEquals( 'about', get_post_meta( $post_id_b, $this->meta_key, true ) );
		$this->assertEquals( 'about', $post_c->post_name );
		$this->assertEmpty( get_post_meta( $post_id_c, $this->meta_key, true ) );
	}

	/**
	 * Tests when a post conflicts a trashed post whose slug was the
	 * auto-incremented variation of what is now an also-trashed post.
	 *
	 * Specifically:
	 * - The new post should have its desired slug.
	 * - The conflicting trashed post should get a new slug.
	 * - The conflicting trashed post should have its original slug saved in meta.
	 * - The non-conflicting trashed post should not be affected.
	 * - The new post should not have the meta field set.
	 *
	 * @dataProvider get_post_types
	 */
	public function test_trashA_trashB_then_createC_with_number_suffixed_slug( $post_type ) {
		$post_id_a = $this->factory->post->create( array( 'post_type' => $post_type, 'post_name' => 'about' ) );
		wp_trash_post( $post_id_a );
		$post_id_b = $this->factory->post->create( array( 'post_type' => $post_type, 'post_name' => 'about' ) );
		wp_trash_post( $post_id_b );
		$post_id_c = $this->factory->post->create( array( 'post_type' => $post_type, 'post_name' => 'about-2' ) );
		$post_a = get_post( $post_id_a );
		$post_b = get_post( $post_id_b );
		$post_c = get_post( $post_id_c );

		$this->assertEquals( 'about-2-2', $post_a->post_name );
		$this->assertEquals( 'about', get_post_meta( $post_id_a, $this->meta_key, true ) );
		$this->assertEquals( 'about', $post_b->post_name );
		$this->assertEmpty( get_post_meta( $post_id_b, $this->meta_key, true ) );
		$this->assertEquals( 'about-2', $post_c->post_name );
		$this->assertEmpty( get_post_meta( $post_id_c, $this->meta_key, true ) );
	}

	/**
	 * Tests when a subpage is created that conflicts with the slug of a subpage
	 * that has the same post parent.
	 *
	 * Specifically:
	 * - The trashed page should get its slug changed.
	 * - The trashed page should have its original slug stored in meta.
	 * - The new page should get its desired slug.
	 */
	public function test_page_conflicts_in_same_hierarchy() {
		$post_id_a = $this->factory->post->create( array( 'post_type' => 'page', 'post_name' => 'alpha' ) );
		$post_id_b = $this->factory->post->create( array( 'post_type' => 'page', 'post_name' => 'bravo', 'post_parent' => $post_id_a ) );
		wp_trash_post( $post_id_b );
		$post_id_c = $this->factory->post->create( array( 'post_type' => 'page', 'post_name' => 'bravo', 'post_parent' => $post_id_a ) );

		$post_b = get_post( $post_id_b );
		$post_c = get_post( $post_id_c );

		$this->assertEquals( 'bravo-2', $post_b->post_name );
		$this->assertEquals( 'bravo', get_post_meta( $post_id_b, $this->meta_key, true ) );
		$this->assertEquals( 'bravo', $post_c->post_name );
	}

	/**
	 * Tests when a subpage is created that is identical to the slug of a subpage
	 * that has a different post parent. (Slug uniqueness is only enforced in a
	 * given page hierarchy, not across all pages.)
	 *
	 * Specifically:
	 * - The trashed page should not be affected.
	 * - The new page should get its desired slug.
	 */
	public function test_page_no_conflicts_in_different_hierarchies() {
		$post_id_a = $this->factory->post->create( array( 'post_type' => 'page', 'post_name' => 'alpha' ) );
		$post_id_b = $this->factory->post->create( array( 'post_type' => 'page', 'post_name' => 'bravo' ) );

		$post_id_c = $this->factory->post->create( array( 'post_type' => 'page', 'post_name' => 'charlie', 'post_parent' => $post_id_a ) );
		wp_trash_post( $post_id_c );
		$post_id_d = $this->factory->post->create( array( 'post_type' => 'page', 'post_name' => 'charlie', 'post_parent' => $post_id_b ) );

		$post_c = get_post( $post_id_c );
		$post_d = get_post( $post_id_d );

		$this->assertEquals( 'charlie', $post_c->post_name );
		$this->assertEmpty( get_post_meta( $post_id_c, $this->meta_key, true ) );
		$this->assertEquals( 'charlie', $post_d->post_name );
	}

	/**
	 * Tests when an attachment is created that conflicts with a page in trash.
	 *
	 * Specifically:
	 * - The trashed page should get its slug changed.
	 * - The trashed page should have its original slug stored in meta.
	 * - The attachment should get its desired slug.
	 */
	public function test_attachment_conflict_with_page() {
		$post_id_a = $this->factory->post->create( array( 'post_type' => 'page', 'post_name' => 'about' ) );
		wp_trash_post( $post_id_a );
		$post_id_b = $this->factory->post->create( array( 'post_type' => 'attachment', 'post_name' => 'about' ) );

		$post_a = get_post( $post_id_a );
		$post_b = get_post( $post_id_b );

		$this->assertEquals( 'about-2', $post_a->post_name );
		$this->assertEquals( 'about', get_post_meta( $post_id_a, $this->meta_key, true ) );
		$this->assertEquals( 'about', $post_b->post_name );
	}

	/**
	 * Tests when an attachment is created that conflicts with a post in trash.
	 *
	 * Specifically:
	 * - The trashed post should get its slug changed.
	 * - The trashed post should have its original slug stored in meta.
	 * - The attachment should get its desired slug.
	 */
	public function test_attachment_conflict_with_post() {
		$post_id_a = $this->factory->post->create( array( 'post_type' => 'post', 'post_name' => 'about' ) );
		wp_trash_post( $post_id_a );
		$post_id_b = $this->factory->post->create( array( 'post_type' => 'attachment', 'post_name' => 'about' ) );

		$post_a = get_post( $post_id_a );
		$post_b = get_post( $post_id_b );

		$this->assertEquals( 'about-2', $post_a->post_name );
		$this->assertEquals( 'about', get_post_meta( $post_id_a, $this->meta_key, true ) );
		$this->assertEquals( 'about', $post_b->post_name );
	}

	/**
	 * Tests when a page is created that is identical to slug of a post in trash.
	 *
	 * Specifically:
	 * - The trashed post is unaffected (slug is not changed, meta field is not set)
	 * - The page should get its desired slug.
	 */
	public function test_page_does_not_conflict_with_post() {
		$post_id_a = $this->factory->post->create( array( 'post_type' => 'post', 'post_name' => 'about' ) );
		wp_trash_post( $post_id_a );
		$post_id_b = $this->factory->post->create( array( 'post_type' => 'page', 'post_name' => 'about' ) );

		$post_a = get_post( $post_id_a );
		$post_b = get_post( $post_id_b );

		$this->assertEquals( 'about', $post_a->post_name );
		$this->assertEmpty( get_post_meta( $post_id_a, $this->meta_key, true ) );
		$this->assertEquals( 'about', $post_b->post_name );
	}

	/**
	 * Tests when a post is created that is identical to slug of a page in trash.
	 *
	 * Specifically:
	 * - The trashed page is unaffected (slug is not changed, meta field is not set)
	 * - The post should get its desired slug.
	 */
	public function test_post_does_not_conflict_with_page() {
		$post_id_a = $this->factory->post->create( array( 'post_type' => 'page', 'post_name' => 'about' ) );
		wp_trash_post( $post_id_a );
		$post_id_b = $this->factory->post->create( array( 'post_type' => 'post', 'post_name' => 'about' ) );

		$post_a = get_post( $post_id_a );
		$post_b = get_post( $post_id_b );

		$this->assertEquals( 'about', $post_a->post_name );
		$this->assertEmpty( get_post_meta( $post_id_a, $this->meta_key, true ) );
		$this->assertEquals( 'about', $post_b->post_name );
	}


	/**
	 * Tests that the default, undesired slug conflict handling is present in
	 * WordPress.
	 *
	 * NOTE: TEST THIS LAST (unless it reinstates the plugin's hooks when done).
	 */
	public function test_default_wp_slug_conflict() {
		$this->remove_plugin_hooks();

		$post_id_a = $this->factory->post->create( array( 'post_type' => 'post', 'post_name' => 'about' ) );
		wp_trash_post( $post_id_a );
		$post_id_b = $this->factory->post->create( array( 'post_type' => 'post', 'post_name' => 'about' ) );

		$post_a = get_post( $post_id_a );
		$post_b = get_post( $post_id_b );

		$this->assertEquals( 'about', $post_a->post_name );
		$this->assertEquals( 'about-2', $post_b->post_name );
	}

}

