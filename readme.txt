=== No Slug Conflicts with Trash ===
Contributors: coffee2code
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=6ARCFJ9TX3522
Tags: slug, post_name, post, trash, coffee2code
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 3.5
Tested up to: 4.4
Stable tag: 1.2

Prevent the slug of a trashed page or post from conflicting with the slug desired for a new page or post. NOTE: NO LONGER NECESSARY AS OF WORDPRESS 4.5.


== Description ==

**NOTE: WordPress 4.5 incorporated the functionality of this plugin and thus it is no longer needed unless you're still running an older version of WP.**

This plugin implements the belief that a trashed page or post should not in any way conflict with a new page or post when it comes to slugs. In essence, a new page or post should take precedence over anything in the trash. After all, the page/post is in the trash for a reason.

By default, WordPress takes into consideration posts and pages that have been trashed when deciding if the slug for a new post is already in use. Obviously, in general, WordPress should not allow duplicate slugs because that could interfere with permalinks. The thinking behind WordPress's handling of the situation is that trashed posts/pages are still technically present, just inaccessible. It is possible that an author or admin would choose to restore a post from the trash, which WordPress feels should then occupy that same permalink as before it was trashed.

If what WordPress does is unclear, here's an example to help clarify things:

* WordPress ships with a page called "About" with the slug of "about". The page's URL would be http://yoursite/about/
* Let's say you trash that page and start a new page with the name "About".
* Due to a trashed page having the slug that would normally have been assigned to the new page ("about"), the new page gets the slug of "about-2", resulting in the page's URL being http://yoursite/about-2/

With this plugin, for this example, the new "About" page would get the slug "about" as one would hope.

That said, the plugin tries its best to restore untrashed posts to their original slug. The only time it fails to do so is if a new page or post has claimed the trashed post's original slug, in which case the untrashed post is assigned a new slug.

See other sections of the documentation for more insight into the plugin's functionality. See WP core [ticket #11863](https://core.trac.wordpress.org/ticket/11863) for discussion on the matter.

Links: [Plugin Homepage](http://coffee2code.com/wp-plugins/no-slug-conflicts-with-trash/) | [Plugin Directory Page](https://wordpress.org/plugins/no-slug-conflicts-with-trash/) | [Author Homepage](http://coffee2code.com)


== Installation ==

1. Unzip `no-slug-conflicts-with-trash.zip` inside the plugins directory for your site (typically `/wp-content/plugins/`). Or install via the built-in WordPress plugin installer)
2. Activate the plugin through the 'Plugins' admin menu in WordPress


== Frequently Asked Questions ==

= What happens if I trash a post and then restore it? =

The post retains its original slug, as was always the case.

= What happens if I trash a post, publish a new post with that same slug, then restore the original post? =

Because the trashed post's original slug is in use by a new post at the time it gets restored from the trash, the original post would use a reassigned slug. Once an untrashed post is given a reassigned slug, it will no longer have the ability to return to its original slug without manual intervention.

= What happens if I trash a post, publish a new post with that same slug, then trash the second post and restore the original post? =

Upon restoration, the original post will retain its original slug. The plugin keeps track when a trashed post's slug gets changed. It tries to restore the post's original slug if it isn't in use at the time the post gets untrashed.

= What slug gets assigned to a trashed post when a newer post wants to have the same slug? =

When a new post gets created, WordPress tries to determine if a conflict exists. If one does, WordPress appends "-" and then a number to the slug until a unique slug is found. Therefore, if "about" is taken, then it tries "about-2". If that's taken, then it tries "about-3" and so on. Rather than let WP assign the "about-2" to the new post, this plugin flips things and gives the new post "about" and the trashed post "about-2".

= Why doesn't WordPress do what this plugin does by default? =

It should! There is an ages-old, still open Trac ticket ([ticket #11863](http://core.trac.wordpress.org/ticket/11863)) concerning how to handle slug conflicts with trashed posts. No consensus to change existing behavior has been reached. Feel free to chime in to the discussion there and advocate the plugin's approach if you agree with how the plugin handles things.

**UPDATE:** The aforementioned Trac ticket has been resolved as of WordPress 4.5. Therefore, this plugin is no longer necessary.

= As a user of this plugin, what happens now that WordPress 4.5 and beyond now natively supports this functionality? =

If v1.2 of the plugin is run under WP 4.5 or later, it migrates any previously stored original slugs for trashed posts to the postmeta name recognized by WordPress. Then the plugin deactivates itself since it has no further use. At this point, feel free to delete the plugin from your site.

v1.2 of the plugin will continue to work as expected for sites running a version of WP earlier than 4.5.

= Does this plugin include unit tests? =

Yes.

== The Solution Explained ==

An overview of the approach employed by the plugin to resolve the issue of slugs potentially conflicting with posts in the trash.

= What WordPress does =

In order to understand the crux of the implemented solution, a quick refresher on unique slug handling by WP:

Before published use, a desired slug is passed to `wp_unique_post_slug()` and a safe slug is returned. The safe slug may differ from the desired slug if any existing post (including a trashed post) has that slug (or, less likely, is invalid for permalink reasons such as feeds or date archives).

In order to prevent a trashed post's slug from conflicting with a new post, this plugin takes an approach that is comprised of two primary tasks:

= 1. Permit a post to use a slug that conflicts with a trashed post. =

The plugin hooks the 'wp_unique_post_slug' filter. If the desired slug matches the safe slug, the desired slug is unique and nothing needs to be done.

When the two slugs don't match, it has to determine if the conflict is due to a trashed post or not, so it attempts to find a trashed post with the desired slug.

* If no such trashed post is found, the conflict is with a live post (or was an otherwise unsuitable slug) so the safe slug must be used by the new post (so, again, nothing needs to be done).
* If such a trashed post is found, then the plugin simply inverses the traditional behavior of `wp_unique_post_slug()`: give the trashed post the safe slug and return the desired slug for use by the new post (rather than having the trashed post retain its slug and forcing the new post to use the safe slug). In order to be able to restore a trashed post to its original slug, the trashed post's original slug is stored in postmeta.

= 2. Restore a trashed post to its original slug, if necessary and possible. =

For a post transitioning away from the 'trash' post status, check to see if its slug was rewritten (denoted by the presence of the postmeta field only assigned if the original slug was changed due to a conflict while the post was in the trash).

If there was no original slug, the slug was never changed and nothing more needs to happen.

When there is an original slug, check to see if that slug is still in use by a non-trashed post.

* If not, restore the post slug to the original value.
* If so, there is no recourse but to leave the post slug as-is (it is guaranteed to be unique already).

In either case, the postmeta field for the original slug value gets deleted since the now untrashed post is exposed in some fashion with its current slug and should abide by it going forward.


== Changelog ==

= 1.2 (2016-05-07) =
Highlights: WordPress 4.5 has effectively implemented the functionality provided by this plugin, thus it is no longer needed.

* New: Self-deactivate and show deprecation notice if plugin is active under WP 4.5+ and don't do anything else.
* New: Migrate existing meta keys that stored original post slugs to the meta key used by WP.
* New: Add unit tests that only run under versions of WP equal to or greater than 4.5.
* Change: Prevent existing tests from running under versions of WP older than 4.5.
* Change: Prevent web invocation of unit test bootstrap.php.
* Change: Prevent direct loading of test file.
* Change: Add 'Text Domain' plugin header.
* New: Create empty index.php to prevent files from being listed if web server has enabled directory listings.
* New: Add LICENSE file.

= 1.1 (2015-12-08) =
Highlights:

* This minor release sync's the plugin's checks with those recently added/changed in core and enhances the unit tests.

Details:

* Change: Re-sync `get_trashed_post()` with changes to `wp_unique_post_slug()`
    * Check for slugs that could result in URLs that conflict with date archives
    * Simplify post hierarchy checking for hierarchical post types
* Change: Modify many unit tests to use a dataProvider to allow testing for both posts and pages

= 1.0.4 (2015-12-07) =
Highlights:

* This is a very minor update primarily consisting of documentation improvements, including an addition that provides an overview of the plugin's approach to the problem it solves and documentation for all unit tests.

Details:

* Add: Create new section in readme explaining the implemented solution to the trash slug conflict problem
* Add: Document the purpose and expectations of each unit test
* Change: Improvements to existing documentation
* Change: Switch a majority of unit tests to work with posts rather than pages (though it doesn't really matter)
* Change: Minor code formatting changes (braces)
* Change: Explicitly declare methods in unit tests as public
* Change: Minor improvements to inline docs and test docs
* Change: Note compatibility through WP 4.4+
* Change: Update copyright date (2016)

= 1.0.3 (2015-02-11) =
* Note compatibility through WP 4.1+
* Update copyright date (2015)

= 1.0.2 (2014-08-25) =
* Add an FAQ question regarding why WP core doesn't do things the way the plugin does things
* Minor code reformatting (bracing)
* Change documentation links to wp.org to be https
* Change donate link
* Note compatibility through WP 4.0+
* Add plugin icon

= 1.0.1 =
* Add `c2c_No_Slug_Conflicts_With_Trash::version()` to return version number for plugin (with unit test)
* Note compatibility through WP 3.8+
* Update copyright date (2014)
* Change donate link

= 1.0 =
* Initial public release


== Upgrade Notice ==

= 1.2 =
Recommended update: WordPress 4.5 incorporated the functionality of this plugin and thus it is no longer needed for WP 4.5+. It will perform necessary data migrations to ensure trashed slug restoration continues to work and then deactivate itself.

= 1.1 =
Minor update: added check for slugs that could result in URLs that conflict with date archives; enhanced unit tests

= 1.0.4 =
Trivial update: added explanation of plugin's methodology; documented unit tests; noted compatibility through WP 4.4+; updated copyright date (2016)

= 1.0.3 =
Trivial update: noted compatibility through WP 4.1+ and updated copyright date (2015)

= 1.0.2 =
Trivial update: noted compatibility through WP 4.0+; added plugin icon.

= 1.0.1 =
Trivial update: added version() function to return plugin's version number; noted compatibility through WP 3.8+

= 1.0 =
Initial public release.
