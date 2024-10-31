<?php
/**
 * Plugin Name: No Slug Conflicts With Trash
 * Version:     1.2
 * Plugin URI:  http://coffee2code.com/wp-plugins/no-slug-conflicts-with-trash/
 * Author:      Scott Reilly
 * Author URI:  http://coffee2code.com/
 * Text Domain: no-slug-conflicts-with-trash
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Description: Prevent the slug of a trashed page or post from conflicting with the slug desired for a new page or post. NOTE: NO LONGER NECESSARY AS OF WORDPRESS 4.5.
 *
 * Compatible with WordPress 3.5 through 4.4+.
 *
 * TODO:
 * * If post restored under different slug, put in an admin notice that indicates the post was
 *   restored under a different permalink. Link to the post that has its original slug, and to the new
 *   version.
 * * Add message to trashed post on post edit page under slug field to indicate that if untrashed,
 *   the post's slug would be in conflict with a published post and would get assigned a different slug
 * * Probably not: Add message on post create page under slug field to indicate if the new post's slug (or
 *   calculated slug) is currently in conflict with a trashed post. The message would just be to notify the
 *   author that the trashed post cannot be untrashed with the same slug. If the previous TODO is
 *   implemented, that seems sufficient with less noise for the author.
 * * Save original slug under a separate meta key if the post gets untrashed to a non-original slug. (FYI,
 *   once a post is untrashed, the existing meta key is removed regardless of whether the post could be
 *   restored to that original slug or not. Thus this second meta key which the original slug value would
 *   be copied to if the post wasn't restored to its original slug.) This would purely be an informative
 *   meta key allowing a notice/message to be displayed to user indicating that the post was originally
 *   published under this different slug. Maybe add a 'Forget original slug' link next to this message
 *   in case user doesn't care about that.
 *
 * =>> Read the accompanying readme.txt file for instructions and documentation.
 * =>> Also, visit the plugin's homepage for additional information and updates.
 * =>> Or visit: https://coffee2code.com/wp-plugins/no-slug-conflicts-with-trash/
 *
 * @package No_Slug_Conflicts_With_Trash
 * @author  Scott Reilly
 * @version 1.2
 */

/*
	Copyright (c) 2013-2016 by Scott Reilly (aka coffee2code)

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

defined( 'ABSPATH' ) or die();

if ( ! class_exists( 'c2c_No_Slug_Conflicts_With_Trash' ) ) :

class c2c_No_Slug_Conflicts_With_Trash {

	/**
	 * The singleton instance of this class.
	 *
	 * @var c2c_No_Slug_Conflicts_With_Trash
	 * @access private
	 */
	private static $instance;

	/**
	 * The meta key for storing the original slug a trashed post had before
	 * being taken over by a new post.
	 *
	 * @var string
	 * @access private
	 */
	private static $meta_key = '_nscwt_original_slug';

	/**
	 * Memoized array of hierarchical post types.
	 *
	 * @var array
	 * @access private
	 */
	private static $hierarchical_post_types = array();

	/**
	 * Returns version of the plugin.
	 *
	 * @return string
	 */
	public static function version() {
		return '1.2';
	}

	/**
	 * Gets singleton instance, creating it if necessary.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * The constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	/**
	 * Initializes the plugin.
	 */
	public function plugins_loaded() {
		// If WP is >=4.5, then don't do anything but deactivate the plugin and show an admin notice.
		if ( version_compare( get_bloginfo( 'version' ), '4.5', '>=' ) ) {
			if ( current_user_can( 'activate_plugins' ) ) {
				add_action( 'admin_init',    array( $this, 'migrate_meta_keys' ) );
				add_action( 'admin_init',    array( $this, 'deactivate_plugin' ) );
				add_action( 'admin_notices', array( $this, 'deprecation_notice' ) );
			}
		} else {
			add_filter( 'wp_unique_post_slug',    array( $this, 'wp_unique_post_slug' ), 10, 6 );
			add_action( 'transition_post_status', array( $this, 'maybe_restore_changed_slug' ), 10, 3 );
		}
	}

	/**
	 * Migrates the meta key values from that used by the plugin to that now used
	 * by WP.
	 *
	 * The plugin stored a trashed post's original slug in the meta key ''.
	 * WP uses the meta key '_wp_desired_post_slug'.
	 */
	public function migrate_meta_keys() {
		global $wpdb;

		// As a precaution, get list of post IDs that already use the new meta key
		// so that the bulk rename doesn't inadvertently violate the key's uniqueness.
		$post_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s",
			'_wp_desired_post_slug'
		) );

		// Bulk rename the meta keys.
		$update_sql = "UPDATE $wpdb->postmeta SET meta_key = %s WHERE meta_key = %s";
		if ( $post_ids ) {
			$update_sql .= sprintf( ' AND post_id NOT IN (%s)', implode( ',', array_map( 'intval', $post_ids ) ) );
		}

		$wpdb->query( $wpdb->prepare(
			$update_sql,
			'_wp_desired_post_slug',
			$this->get_meta_key()
		) );

		// If any plugin meta keys still exist, they have been superceded by WP usage
		// and should be removed.
		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $this->get_meta_key() ) );

	}

	/**
	 * Deactivates the plugin.
	 *
	 * @since 1.2
	 */
	public function deactivate_plugin() {
		deactivate_plugins( plugin_basename( __FILE__ ) );
	}

	/**
	 * Outputs deprecation admin notice.
	 *
	 * @since 1.2
	 */
	public function deprecation_notice() {
		printf(
			"<div id='message' class='updated'><p>%s</p></div>",
			__( 'WordPress 4.5 implemented the functionality provided by <strong>No Slug Conflicts With Trash</strong> so the plugin has been <strong>deactivated</strong>.', 'no-slug-conflicts-with-trash' )
		);

		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		 }
	}

	/**
	 * Returns the meta key name used to store the post's original slug.
	 *
	 * @return string
	 */
	public function get_meta_key() {
		return self::$meta_key;
	}

	/**
	 * Gets the trashed post that may be conflicting with the provided slug.
	 *
	 * Different slug conflict rules apply depending on whether the post in
	 * question is an attachment, hierarchical post, or a post.
	 *
	 * Much of this function is adapted from WP core's wp_unique_post_slug().
	 *
	 * @param string $slug        The slug with a potential conflict
	 * @param int    $post_ID     The post ID
	 * @param string $post_status The status of the post
	 * @param string $post_type   The post type of the post
	 * @param int    $post_parent The ID of the post's parent
	 * @return null|WP_Post       Either the trashed post with the same slug, or null
	 */
	private function get_trashed_post( $slug, $post_ID, $post_status, $post_type, $post_parent ) {
		global $wpdb, $wp_rewrite;

		$feeds = $wp_rewrite->feeds;
		if ( ! is_array( $feeds ) ) {
			$feeds = array();
		}

		$trashed_id = null;

		if ( 'attachment' == $post_type ) {

			// Attachment slugs must be unique across all types.
			$sql = "SELECT ID FROM $wpdb->posts WHERE post_status = %s AND post_name = %s AND ID != %d LIMIT 1";

			// Only search trash if the slug otherwise is otherwise permissible
			if ( ! in_array( $slug, $feeds ) && ! apply_filters( 'wp_unique_post_slug_is_bad_attachment_slug', false, $slug ) ) {
				$trashed_id = $wpdb->get_var( $wpdb->prepare( $sql, 'trash', $slug, $post_ID ) );
			}

		} elseif ( is_post_type_hierarchical( $post_type ) ) {

			if ( 'nav_menu_item' == $post_type ) {
				return null;
			}

			// Page slugs must be unique within their own trees. Pages are in a separate
			// namespace than posts so page slugs are allowed to overlap post slugs.
			$sql = "SELECT ID FROM $wpdb->posts WHERE post_status = %s AND post_name = %s AND post_type IN ( %s, 'attachment' ) AND ID != %d AND post_parent = %d LIMIT 1";

			// Only search trash if the slug otherwise is otherwise permissible
			if ( ! in_array( $slug, $feeds ) && ! preg_match( "@^($wp_rewrite->pagination_base)?\d+$@", $slug ) &&
			! apply_filters( 'wp_unique_post_slug_is_bad_hierarchical_slug', false, $slug, $post_type, $post_parent ) ) {
				$trashed_id = $wpdb->get_var( $wpdb->prepare( $sql, 'trash', $slug, $post_type, $post_ID, $post_parent ) );
			}

		} else {

			// Post slugs must be unique across all posts.
			$sql = "SELECT ID FROM $wpdb->posts WHERE post_status = %s AND post_name = %s AND post_type = %s AND ID != %d LIMIT 1";

			// Prevent new post slugs that could result in URLs that conflict with date archives.
			$post = get_post( $post_ID );
			$conflicts_with_date_archive = false;
			if ( 'post' === $post_type && ( ! $post || $post->post_name !== $slug ) && preg_match( '/^[0-9]+$/', $slug ) && $slug_num = intval( $slug ) ) {
				$permastructs   = array_values( array_filter( explode( '/', get_option( 'permalink_structure' ) ) ) );
				$postname_index = array_search( '%postname%', $permastructs );

				/*
				 * Potential date clashes are as follows:
				 *
				 * - Any integer in the first permastruct position could be a year.
				 * - An integer between 1 and 12 that follows 'year' conflicts with 'monthnum'.
				 * - An integer between 1 and 31 that follows 'monthnum' conflicts with 'day'.
				 */
				if ( 0 === $postname_index ||
					( $postname_index && '%year%' === $permastructs[ $postname_index - 1 ] && 13 > $slug_num ) ||
					( $postname_index && '%monthnum%' === $permastructs[ $postname_index - 1 ] && 32 > $slug_num )
				) {
					$conflicts_with_date_archive = true;
				}
			}

			// Only search trash if the slug otherwise is otherwise permissible
			if ( ! in_array( $slug, $feeds ) && ! apply_filters( 'wp_unique_post_slug_is_bad_flat_slug', false, $slug, $post_type ) ) {
				$trashed_id = $wpdb->get_var( $wpdb->prepare( $sql, 'trash', $slug, $post_type, $post_ID ) );
			}

		}

		if ( $trashed_id ) {
			return get_post( $trashed_id );
		}

		return null;
	}

	/**
	 * Handles potential slug conflict with a trashed post.
	 *
	 * If such a conflict arises, permit the new post to have the slug and
	 * modify the trashed post accordingly.
	 *
	 * @param string $slug        The unique slug that WP calculated for the post
	 * @param int    $post_ID     The post ID
	 * @param string $post_status The status of the post
	 * @param string $post_type   The post type of the post
	 * @param int    $post_parent The ID of the post's parent
	 * @param string $raw_slug    The originally attempted slug for the post
	 */
	public function wp_unique_post_slug( $slug, $post_ID, $post_status, $post_type, $post_parent, $raw_slug = '' ) {
		// If the slug wasn't modified by WP's wp_unique_post_slug(), it's fine.
		if ( $slug == $raw_slug ) {
			return $slug;
		}

		// Before WP 3.5, the $raw_slug argument wss not passed via the
		// 'wp_unique_post_slug' filter, so the originally desired slug cannot be
		// determined with certainty, but it can be assumed to be based on the post
		// title as is generally the case.
		if ( empty( $raw_slug ) ) {
			// Get the post in question.
			$post = get_post( $post_ID );

			// Use defined post_name, or figure one out in the same manner as WP.
			$post_name = $post->post_name;
			if ( empty( $post_name ) ) {
				$post_name = sanitize_title( $post->post_title );
			} else {
				$post_name = sanitize_title( $post_name );
			}

			// If the slug doesn't appear modified, it's fine.
			if ( $slug == $post_name ) {
				return $slug;
			}

			// As best as can be figured out, this is the original post_name.
			$raw_slug = $post_name;
		}

		// Find a trashed object with the conflicting slug.
		$trashed_post = $this->get_trashed_post( $raw_slug, $post_ID, $post_status, $post_type, $post_parent );

		// If there wasn't a trashed post with the desired slug, then it means
		// a live post has the slug (a legitimate conflict) or the slug
		// shouldn't otherwise be reassigned. Therefore, use the WP-chosen slug.
		if ( empty( $trashed_post ) ) {
			return $slug;
		}

		// Change the slug of the trashed post. Give it the modified slug that
		// WP already determined was unique and was about to give the new post.
		$trashed_post->post_name = $slug;
		wp_update_post( $trashed_post );

		// Add a custom field to the trashed post with its original slug
		// This does nothing if the post already has the meta key, since a post
		// could have its slug remapped a few times while in the trash. We only
		// care to save its original-most slug.
		add_post_meta( $trashed_post->ID, self::$meta_key, $raw_slug, true );

		// Return the originally attempted slug as the slug for the new post.
		return $raw_slug;

	}

	/**
	 * If a post is being restored from trash, attempt to restore its original
	 * slug (assuming it had its original slug changed via use of this plugin).
	 *
	 * @param string  $new_status The new status for the post
	 * @param string  $old_status The old status for the post
	 * @param WP_Post $post       The post
	 */
	public function maybe_restore_changed_slug( $new_status, $old_status, $post ) {

		// Only concerned with posts transitioning from the 'trash' status.
		if ( 'trash' !== $old_status ) {
			return;
		}

		// Not concerned if transitioning to trash.
		if ( 'trash' === $new_status ) {
			return;
		}

		// Only concerned if the slug was changed by this plugin.
		$original_slug = get_post_meta( $post->ID, self::$meta_key, true );
		if ( empty( $original_slug ) ) {
			return;
		}

		// Regardless of what happens here on out, the post is now live with
		// some slug, so disregard the previously original slug.
		delete_post_meta( $post->ID, self::$meta_key );

		// Nothing to do if the slugs already match (unlikely they would).
		if ( $original_slug == $post->post_name ) {
			return;
		}

		// Determine if the old slug can be restored.
		$current_slug = $post->post_name;
		$new_slug = $this->wp_unique_post_slug( $current_slug, $post->ID, $post->post_status, $post->post_type, $post->post_parent, $original_slug );

		// If the returned slug is the original slug, then the post can be restored to its original slug.
		if ( $new_slug == $original_slug ) {
			$post->post_name = $original_slug;
			wp_update_post( $post );
		}
	}
}

c2c_No_Slug_Conflicts_With_Trash::get_instance();

endif;
