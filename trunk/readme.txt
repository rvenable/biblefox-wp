=== Biblefox for WordPress ===
Contributors: Biblefox.com, rvenable
Tags: bible, buddypress
Requires at least: 3.0
Tested up to: 3.0
Stable tag: 0.8.3

== Description ==

Turns your WordPress site into an online Bible study tool.

<h3>Bible Reference Index</h3>
Creates a Bible Reference index for your WordPress site, allowing your users to easily search your blog posts (or BuddyPress activities, when using BuddyPress) for any Bible reference. Use it for WordPress sites that involve a lot of discussion of the Bible.

For instance, if a user searches your site for 'Genesis 2', they will find posts that not only contain the string 'Genesis 2', but also other equivalent strings like 'Gen 2', or other references that intersect Genesis 2 like 'Gen 1-3'.

<h3>Bible Tool Custom Post Type</h3>

Adds a custom post type (named 'bfox_tool') that can be used for adding Bible translations (or other Bible tools) to your site. Bible tools can be inside of tooltips that appear when clicking on Bible references.

Biblefox allows you to embed Bible tools within an iframe from around the internet, or use data from a locally installed database.

<h3>Bible Reading Plan Custom Post Type</h3>

Adds a custom post type (named 'bfox_plan') for creating your own Bible reading plans. Includes optional scheduling mechanism, along with RSS and iCal feeds.

<h3>Other Features</h3>
* Bible references automatically become links with a tooltip that gives quick access to the Scripture
* Adds a post meta box that displays the Bible text while writing a blog post

<h4>Try Biblefox.com</h4>

Biblefox for Wordpress was developed for a new online community: <a href="http://biblefox.com/">Biblefox.com</a>. Biblefox is designed to use great open source tools like WordPress and BuddyPress to help people engage deeply with the Bible in online community. If you are interested, feel free to create an account at <a href="http://biblefox.com/">Biblefox.com</a> and study the Bible with us! 

== Installation ==

You can download and install Biblefox using the built in WordPress plugin installer. If you download Biblefox manually, make sure it is uploaded to "/wp-content/plugins/".

Activate Biblefox in the "Plugins" admin panel using the "Activate" link.

You will need to refresh your Bible index in the Biblefox setting page. This updates the Bible index for all of your current posts. While Biblefox is active, new posts will automatically be indexed.

For BuddyPress sites, you should also add support to your menus for the Bible Directory by adding a link. Your Bible Directory has the url: http://yoursite.com/bible/

== Frequently Asked Questions ==

= Where can I get support? =

The support forums can be found here: http://dev.biblefox.com/forums/

Biblefox is developed through GitHub, and you can find the latest source and add issues/feature requests here: https://github.com/rvenable/biblefox-wp

= Does Biblefox install any database tables? =

Yes. Biblefox creates tables for storing the Bible references in each post. For a blog, the table is usually 'wp_posts_bfox_refs' (ie. "{$wpdb->posts}_bfox_refs") and for BuddyPress activities it is 'wp_bp_activity_bfox_refs'. These tables have a column for a post_id, and 2 columns to store the start and end verses for any Bible reference in the post.

== Screenshots ==

1. **Bible Index** - Notice the 'Bible References' column. Biblefox indexes all of your blog posts by the Bible references they contain.
2. **Bible Directory** - For BuddyPress sites, a Bible Directory is added that displays the Bible text, followed by an activity stream for that Bible reference.
3. **Tooltips** - Bible references automatically become links with a tooltip that gives quick access to the Scripture.
4. **Bible Post Meta Box** - Biblefox adds a post meta box that displays the Bible text while writing a blog post.

== Changelog ==

= 1.0 =
* Added 'bfox_tool' custom post type for managing Bible translations and other tools
* Added 'bfox_plan' custom post type for Bible reading plans
* Added API directory with functions that can be used to customize themes

= 0.8.3 =
* Fixed JavaScript errors in IE
* Fixed some issues with saving Biblefox options on single site installations

= 0.8.2 =
* Fixed hardcoded plugin URL from 'biblefox' to 'biblefox-for-wordpress'

= 0.8.1 =
* Fixed BuddyPress setting page and Activity refresh

= 0.8 =
* Initial release
