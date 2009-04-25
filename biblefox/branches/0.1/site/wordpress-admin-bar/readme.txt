=== WordPress Admin Bar ===
Contributors: Viper007Bond
Donate link: http://www.viper007bond.com/donate/
Tags: admin, bar
Requires at least: 2.5
Stable tag: trunk

An upload-and-activate plugin that creates an admin bar at the top of your site like the one at WordPress.com.

== Description ==

Ever seen the admin bar located on [WordPress.com](http://wordpress.com/) and wanted it on your own site? Well, this plugin is what you need then.

It replicates all of the menu links in your normal admin area at the top of your main site for logged in users (i.e. you). You can go right to the "Write Post" or manage options pages in one click from anywhere on your blog. No more having to go to your dashboard first. You can even have it replace your admin area menus if you want.

It features a full options page where you can hide any of the menus or switch themes.

**Legacy Version**

If for some reason you have chosen not to upgrade to the latest and most secure version of WordPress, you can use the [legacy version](http://downloads.wordpress.org/plugin/wordpress-admin-bar.2.0.5.zip).

== Installation ==

###Upgrading From A Previous Version###

To upgrade from a previous version of this plugin, delete the entire folder and files from the previous version of the plugin and then follow the installation instructions below.

###Installing The Plugin###

Extract all files from the ZIP file, making sure to keep the file structure intact, and then upload the plugin's folder to `/wp-content/plugins/`.

This should result in the following file structure:

`- wp-content
    - plugins
        - wordpress-admin-bar
            | jquery.checkboxes.pack.js
            | readme.txt
            | screenshot1.png
            | wordpress-admin-bar.js
            | wordpress-admin-bar.php
            - themes
                - blue
                    | blue.css
                    - images
                    [...]`

Then just visit your admin area and activate the plugin.

**See Also:** ["Installing Plugins" article on the WP Codex](http://codex.wordpress.org/Managing_Plugins#Installing_Plugins)

###Installing The Plugin For WordPress MU###

Install normally, but move **just** `wordpress-admin-bar.php` to `mu-plugins`. Leave the rest of the files in the normal place.

== Frequently Asked Questions ==

= It's not working! =

If it's not working for you, first try switching to the default WordPress theme. If that makes it show up, then you know it's an issue with your regular theme. Make sure your theme has `<?php wp_head(); ?>` in inside it's `<head>` in it's `header.php` file and `<?php wp_footer(); ?>` somewhere in it's `footer.php` file, like before `</body>`.

== Screenshots ==

1. The admin bar in action
2. The settings page

== Themes API ==

If you're a WordPress theme author, consider bundling a theme for this plugin with your WordPress theme. It's really easy to do and it looks great when this plugin matches the theme in use.

Full details on how to add a custom theme can be found on [my website](http://www.viper007bond.com/wordpress-plugins/wordpress-admin-bar/theme-api/).

== ChangeLog ==

**Version 3.0.2**

* Fix navigation tabs being hidden in the media uploader.
* Add a link to the Settings page next to the plugin (de)activation link.
* Minor code improvements.

**Version 3.0.1**

* Fix display issues in IE. Props Mark.
* Don't allow this plugin's menu item to be hidden in the admin area, otherwise it'd be possible to hide all menus and not easily be able to get them back.
* Hide the "Dashboard" tab in the upper-left on non-WPMU installs if the admin bar is enabled in the admin area.

**Version 3.0.0**

* Complete recode from scatch. Changes too many to list (themes, options page, etc.)

**Version 2.0.5**

* `position: fixed` made the cursor not show up in input boxes. The default has been changed to `position: absolute` which means it will no longer stick at the top of your screen when you scroll.

**Version 2.0.4**

* Some CSS added in 2.0.3 hides the comments in moderation count if it's 0, but the plugin was still adding parathensis regardless. This has been fixed.

**Version 2.0.3**

* Added a tweak to put parathensis around the comments in moderation count in WordPress 2.5.
* Some CSS, image, and code improvements.

**Version 2.0.2**

* Need to include_once() rather than include() to avoid having all menu items being listed twice in the admin area if this plugin is enabled there. Props Nigel Kane.

**Version 2.0.1**

* Support added for using this plugin in your admin area. Just edit the plugin file and uncomment the two hooks as described in the file.

**Version 2.0.0**

* Plugin renamed to "WordPress Admin Bar" to avoid any confusion that this plugin is officially related to WordPress.com.
* Complete recode from scratch. Everything is dynamic now and pulled directly from the regular admin area.

**Version 1.0.3**

* Support for Democracy added as well as the upcoming WordPress v2.1.

**Version 1.0.2**

* Support for more plugins added, including Akismet (thanks Paul for reminding me).

**Version 1.0.1**

* All CSS declarations in the CSS file are now marked as !important to make sure to overwrite your theme’s declarations and make the admin bar display correctly.

**Version 1.0.0**

* Initial release.