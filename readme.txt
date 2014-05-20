=== Silo Widgets ===
Contributors: Denis-de-Bernardy & Mike_Koepke
Donate link: http://www.semiologic.com/partners/
Tags: semiologic
Requires at least: 3.1
Tested up to: 3.9
Stable tag: trunk

A collection of widgets to create silo web designed sites using static pages.


== Description ==

A collection of widgets to create silo web designed sites using static pages.

= This post/page in widgets =

This plugin shares options with a couple of other plugins from Semiologic. They're available when editing your posts and pages, in meta boxes called "This post in widgets" and "This page in widgets."

These options allow you to configure a title and a description that are then used by Fuzzy Widgets, Random Widgets, Related Widgets, Nav Menu Widgets, Silo Widgets, and so on. They additionally allow you to exclude a post or page from all of them in one go.

= Help Me! =

The [Semiologic forum](http://forum.semiologic.com) is the best place to report issues.


== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress


== Change Log ==

= 3.4.1 =

- Use more full proof WP version check to alter plugin behavior instead of relying on $wp_version constant.

= 3.4 =

- clear caches on WP upgrade
- Code refactoring
- WP 3.9 compat

= 3.3.1 =

- WP 3.8 compat

= 3.3 =

- WP 3.6 compat
- PHP 5.4 compat

= 3.2 =

- Fixed incorrect url being generated for hierarchies with children of children in the Silo Stub widget.  url was being generated as parent/grandparent/child (props Todd)
- Tested with WordPress 3.6

= 3.1.2 =

- Fixed incorrect url being generated for hierarchies with children of children in the Silo Map and NavMenus widget.  url was being generated as parent/grandparent/child (props Todd)

= 3.1.1 =

- Fix caching issue with "This Page in Widgets" not refreshing on title or description updates

= 3.1 =

- WP 3.5 compat
 
= 3.0.4 =

- Further cache improvements (fix priority)
- Fix a potential infinite loop

= 3.0.3 =

- Sem Cache 2.0 related tweaks
- Fix blog link on search/404 pages
- Apply filters to permalinks

= 3.0.2 =

- WP 2.9 compat

= 3.0.1 =

- Allow silo maps in posts

= 3.0 =

- Complete rewrite
- WP_Widget class
- Localization
- Code enhancements and optimizations
