<?php

defined( 'ABSPATH' ) or die();

class No_Slug_Conflict_With_Trash_4_5_Test extends WP_UnitTestCase {

	public function setUp() {
		if ( version_compare( get_bloginfo( 'version' ), '4.5', '<' ) ) {
			$this->markTestSkipped( 'These tests only apply for versions of WordPress >= 4.5.' );
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
	 * Tests when a post is created that conflicts with a post in trash.
	 *
	 * Ensures that default WP behavior is performed and not plugin's behavior.
	 *
	 * @dataProvider get_post_types
	 */
	public function test_trashA_createB( $post_type ) {
		$post_id_a = $this->factory->post->create( array( 'post_type' => $post_type, 'post_name' => 'about' ) );
		wp_trash_post( $post_id_a );
		$post_id_b = $this->factory->post->create( array( 'post_type' => $post_type, 'post_name' => 'about' ) );

		$post_a = get_post( $post_id_a );
		$post_b = get_post( $post_id_b );

		$this->assertEquals( 'about__trashed', $post_a->post_name );
		$this->assertEmpty( get_post_meta( $post_id_a, $this->meta_key, true ) );
		$this->assertEquals( 'about', get_post_meta( $post_id_a, '_wp_desired_post_slug', true ) );
		$this->assertEquals( 'about', $post_b->post_name );
	}

	/**
	 * Tests that existing meta keys get migrated to the meta key name used by WP.
	 *
	 * @dataProvider get_post_types
	 */
	public function test_migrates_existing_meta_keys( $post_type ) {
		// Set up some posts to match how things might look with previous use of the plugin.
		$post_id_a = $this->factory->post->create( array( 'post_type' => $post_type, 'post_status' => 'trash', 'post_name' => 'about-2' ) );
		add_post_meta( $post_id_a, $this->meta_key, 'about', true );
		$post_id_b = $this->factory->post->create( array( 'post_type' => $post_type, 'post_status' => 'trash', 'post_name' => 'about-3' ) );
		add_post_meta( $post_id_b, $this->meta_key, 'about', true );

		$this->assertEquals( 'about', get_post_meta( $post_id_a, $this->meta_key, true ) );
		$this->assertEquals( 'about', get_post_meta( $post_id_b, $this->meta_key, true ) );
		$this->assertEmpty( get_post_meta( $post_id_a, '_wp_desired_post_slug', true ) );
		$this->assertEmpty( get_post_meta( $post_id_b, '_wp_desired_post_slug', true ) );

		// Trigger meta_key migration.
		$this->flush_cache();
		c2c_No_Slug_Conflicts_With_Trash::get_instance()->migrate_meta_keys();

		$this->assertEmpty( get_post_meta( $post_id_a, $this->meta_key, true ) );
		$this->assertEmpty( get_post_meta( $post_id_b, $this->meta_key, true ) );
		$this->assertEquals( 'about', get_post_meta( $post_id_a, '_wp_desired_post_slug', true ) );
		$this->assertEquals( 'about', get_post_meta( $post_id_b, '_wp_desired_post_slug', true ) );

		wp_untrash_post( $post_id_a );
		$post_a = get_post( $post_id_a );

		$this->assertEquals( 'about', $post_a->post_name );
	}

	/**
	 * Tests that an existing meta keys is not migrated to the meta key name used
	 * by WP if an instance of the new meta key is present for the post.
	 *
	 * @dataProvider get_post_types
	 */
	public function test_does_not_migrate_superceded_meta_key( $post_type ) {
		// Set up some posts to match how things might look with previous use of the plugin.
		$post_id_a = $this->factory->post->create( array( 'post_type' => $post_type, 'post_status' => 'trash', 'post_name' => 'about-2' ) );
		add_post_meta( $post_id_a, $this->meta_key, 'about', true );
		// Presume user used WP 4.5 to delete a post before updating to 1.2 of the plugin.
		update_post_meta( $post_id_a, '_wp_desired_post_slug', 'aboutx', true );

		$this->assertEquals( 'about', get_post_meta( $post_id_a, $this->meta_key, true ) );
		$this->assertEquals( 'aboutx', get_post_meta( $post_id_a, '_wp_desired_post_slug', true ) );

		// Trigger meta_key migration.
		$this->flush_cache();
		c2c_No_Slug_Conflicts_With_Trash::get_instance()->migrate_meta_keys();

		$this->assertEmpty( get_post_meta( $post_id_a, $this->meta_key, true ) );
		$this->assertEquals( 'aboutx', get_post_meta( $post_id_a, '_wp_desired_post_slug', true ) );

		wp_untrash_post( $post_id_a );
		$post_a = get_post( $post_id_a );

		$this->assertEquals( 'aboutx', $post_a->post_name );
		$this->assertEmpty( get_post_meta( $post_id_a, '_wp_desired_post_slug', true ) );
	}

}
