<?php
/*
Plugin Name: Silo Widgets
Plugin URI: http://www.semiologic.com/software/silo/
Description: Silo web design tools for sites built using static pages.
Version: 3.0.2 beta
Author: Denis de Bernardy
Author URI: http://www.getsemiologic.com
Text Domain: silo
Domain Path: /lang
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts and is distributed under the terms of the Mesoconcepts license. In a nutshell, you may freely use it for any purpose, but may not redistribute it without written permission.

http://www.mesoconcepts.com/license/
**/


load_plugin_textdomain('silo', false, dirname(plugin_basename(__FILE__)) . '/lang');

if ( !defined('widget_utils_textdomain') )
	define('widget_utils_textdomain', 'silo');

if ( !defined('page_tags_textdomain') )
	define('page_tags_textdomain', 'silo');

if ( !defined('sem_widget_cache_debug') )
	define('sem_widget_cache_debug', false);


/**
 * silo_widgets
 *
 * @package Silo Widgets
 **/

class silo_widgets {
	/**
	 * editor_init()
	 *
	 * @return void
	 **/

	function editor_init() {
		if ( !class_exists('widget_utils') )
			include dirname(__FILE__) . '/widget-utils/widget-utils.php';
		
		widget_utils::page_meta_boxes();
		add_action('page_widget_config_affected', array('silo_widgets', 'widget_config_affected'));
		
		if ( !class_exists('page_tags') )
			include dirname(__FILE__) . '/page-tags/page-tags.php';
		
		page_tags::meta_boxes();
	} # editor_init()
	
	
	/**
	 * widget_config_affected()
	 *
	 * @return void
	 **/

	function widget_config_affected() {
		echo '<li>'
			. __('Silo Stubs', 'silo')
			. '</li>' . "\n";
		echo '<li>'
			. __('Silo Maps', 'silo')
			. '</li>' . "\n";
	} # widget_config_affected()
	
	
	/**
	 * widgets_init()
	 *
	 * @return void
	 **/

	function widgets_init() {
		register_widget('silo_map');
		register_widget('silo_stub');
	} # widgets_init()
} # silo_widgets


/**
 * silo_map
 *
 * @package Silo Widgets
 **/

class silo_map extends WP_Widget {
	/**
	 * silo_map()
	 *
	 * @return void
	 **/

	function silo_map() {
		$widget_ops = array(
			'classname' => 'silo_map',
			'description' => __('A site map. Insert this as an inline widget in a static page for best effect.', 'silo'),
			);
		
		$this->WP_Widget('silo_map', __('Silo Map', 'silo'), $widget_ops);
	} # silo_map()
	
	
	/**
	 * widget()
	 *
	 * @param array $args widget args
	 * @param array $instance widget options
	 * @return void
	 **/

	function widget($args, $instance) {
		extract($args, EXTR_SKIP);
		extract($instance, EXTR_SKIP);
		
		if ( is_admin() || !in_the_loop() )
			return;
		
		if ( is_page() ) {
			global $wp_query;
			$page_id = $wp_query->get_queried_object_id();
			$cache_id = "_$widget_id";
			$o = get_post_meta($page_id, $cache_id, true);
		} else {
			$cache_id = "$widget_id";
			$o = get_transient($cache_id);
		}
		
		if ( !sem_widget_cache_debug && !is_preview() && $o ) {
			echo $o;
			return;
		}
		
		silo_map::cache_pages();
		
		$root_ids = wp_cache_get(0, 'page_children');
		
		ob_start();
		
		echo $before_widget;
		
		echo '<ul>' . "\n";
		
		foreach ( $root_ids as $root_id )
			silo_map::display_page($root_id);
		
		echo '</ul>' . "\n";
		
		echo $after_widget;
		
		$o = ob_get_clean();
		
		if ( !is_preview() ) {
			if ( is_page() ) {
				update_post_meta($page_id, $cache_id, $o);
			} else {
				set_transient($cache_id, $o);
			}
		}
		
		echo $o;
	} # widget()
	
	
	/**
	 * display_page()
	 *
	 * @param int $ref
	 * @return void
	 **/

	function display_page($ref) {
		$page = get_page($ref);
		
		if ( !$page || get_post_meta($page->ID, '_widgets_exclude', true) && !get_post_meta($page->ID, '_widgets_exception', true) )
			return;
		
		if ( is_page() ) {
			global $wp_the_query;
			$page_id = $wp_the_query->get_queried_object_id();
		} elseif ( get_option('show_on_front') == 'page' ) {
			$page_id = (int) get_option('page_for_posts');
		} else {
			$page_id = 0;
		}
		
		$label = $page->post_title;
		if ( (string) $label === '' )
			$label = __('Untitled', 'silo');
		
		$url = esc_url(apply_filters('the_permalink', get_permalink($page->ID)));
		
		$ancestors = wp_cache_get($page_id, 'page_ancestors');
		if ( $ancestors === false )
			$ancestors = array();
		$children = wp_cache_get($page->ID, 'page_children');
		
		$classes = array();
		$link = $label;
		
		if ( get_option('show_on_front') == 'page' && get_option('page_on_front') == $page->ID ) {
			$classes[] = 'nav_home';
			if ( !is_front_page() || is_front_page() && is_paged() )
				$link = '<a href="' . user_trailingslashit($url) . '" title="' . esc_attr($label) . '">'
					. $link
					. '</a>';
			if ( is_front_page() || in_array($page->ID, $ancestors) )
				$link = '<span class="nav_active">' . $link . '</span>';
		} elseif ( get_option('show_on_front') == 'page' && get_option('page_for_posts') == $page->ID ) {
			$classes[] = 'nav_blog';
			if ( !is_search() && !is_404() && ( !is_home() || is_home() && is_paged() ) )
				$link = '<a href="' . $url . '" title="' . esc_attr($label) . '">'
					. $link
					. '</a>';
			if ( !is_search() && !is_404() && ( !is_page() || in_array($page->ID, $ancestors) ) )
				$link = '<span class="nav_active">' . $link . '</span>';
		} else {
			if ( $children )
				$classes[] = 'nav_branch';
			else
				$classes[] = 'nav_leaf';
			
			if ( $page->ID != $page_id )
				$link = '<a href="' . $url . '" title="' . esc_attr($label) . '">'
					. $link
					. '</a>';
			
			$link_classes = array('nav_page-' . sanitize_html_class($page->post_name, $page->ID));
			if ( $page->ID == $page_id || in_array($page->ID, $ancestors) )
				$link_classes[] = 'nav_active';
			$link = '<span class="' . implode(' ', $link_classes) . '">' . $link . '</span>';
		}
		
		echo '<li class="' . implode(' ', $classes) . '">'
			. $link;
		
		$descr = trim(get_post_meta($page->ID, '_widgets_desc', true));
		if ( $descr )
			echo "\n\n" . wpautop(apply_filters('widget_text', $descr));
		
		if ( $children ) {
			echo "\n"
				. '<ul>' . "\n";
			foreach ( $children as $child_id )
				silo_map::display_page($child_id);
			echo '</ul>' . "\n";
		}
		
		echo '</li>' . "\n";
	} # display_page()
	
	
	/**
	 * cache_pages()
	 *
	 * @return void
	 **/

	function cache_pages() {
		if ( is_array(wp_cache_get('page_ids', 'widget')) )
			return;
		
		global $wpdb;
		
		$pages = (array) $wpdb->get_results("
			SELECT	posts.*
			FROM	$wpdb->posts as posts
			WHERE	posts.post_type = 'page'
			AND		posts.post_status = 'publish'
			ORDER BY posts.menu_order, posts.post_title
			");
		
		$children = array();
		$to_cache = array();
		
		foreach ( $pages as $page ) {
			if ( !is_array($children[$page->ID]) )
				$children[$page->ID] = array();
			$children[$page->post_parent][] = $page->ID;
			$to_cache[] = $page->ID;
		}
		
		wp_cache_set('page_ids', $to_cache, 'widget');
		
		$all_ancestors = array();
		
		foreach ( $children as $parent => $child_ids ) {
			foreach ( $child_ids as $key => $child_id )
				$all_ancestors[$child_id][] = $parent;
			wp_cache_set($parent, $child_ids, 'page_children');
		}
		
		foreach ( $all_ancestors as $child_id => $parent_ids ) {
			while ( $parent_ids[0] )
				$parent_ids = array_merge((array) $all_ancestors[$parent_ids[0]], $parent_ids);
			
			wp_cache_set($child_id, $parent_ids, 'page_ancestors');
		}
		
		foreach ( array_keys($pages) as $k ) {
			$ancestors = wp_cache_get($pages[$k]->ID, 'page_ancestors');
			array_shift($ancestors);
			$pages[$k]->ancestors = $ancestors;
		}

		update_post_cache($pages);
		update_postmeta_cache($to_cache);
	} # cache_pages()
	
	
	/**
	 * flush_cache()
	 *
	 * @param mixed $in
	 * @return mixed $in
	 **/

	function flush_cache($in = null) {
		$cache_ids = array();
		
		$widgets = get_option("widget_silo_map");
		
		if ( !$widgets )
			return $in;
		
		unset($widgets['_multiwidget']);
		unset($widgets['number']);
		
		foreach ( array_keys($widgets) as $widget_id )
			$cache_ids[] = "silo_map-$widget_id";
		
		foreach ( $cache_ids as $cache_id ) {
			delete_transient($cache_id);
			delete_post_meta_by_key("_$cache_id");
		}
		
		if ( wp_cache_get('page_ids', 'widget') !== false ) {
			global $wpdb;
			$page_ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_type = 'page' AND post_status = 'publish'");
			foreach ( $page_ids as $page_id ) {
				wp_cache_delete($page_id, 'page_ancestors');
				wp_cache_delete($page_id, 'page_children');
			}
			wp_cache_delete(0, 'page_ancestors');
			wp_cache_delete(0, 'page_children');
		}
		
		return $in;
	} # flush_cache()
} # silo_map


/**
 * silo_stub
 *
 * @package Silo Widgets
 **/

class silo_stub extends WP_Widget {
	/**
	 * silo_stub()
	 *
	 * @return void
	 **/

	function silo_stub() {
		$widget_ops = array(
			'classname' => 'silo_stub',
			'description' => __('Lists child pages and sub-child page in a section. Insert this as an inline widget in a static page.', 'silo'),
			);
		$control_ops = array(
			'width' => 460,
			);
		
		$this->WP_Widget('silo_stub', __('Silo Stub', 'silo'), $widget_ops);
	} # silo_stub()
	
	
	/**
	 * widget()
	 *
	 * @param array $args widget args
	 * @param array $instance widget options
	 * @return void
	 **/

	function widget($args, $instance) {
		extract($args, EXTR_SKIP);
		$instance = wp_parse_args($instance, silo_stub::defaults());
		extract($instance, EXTR_SKIP);
		
		if ( is_admin() ) {
			$formats = array(
				'deep' => __('Deep', 'silo'),
				'hybrid' => __('Hybrid', 'silo'),
				'shallow' => __('Shallow', 'silo'),
				);
			
			echo $before_widget
				. $before_title . $formats[$format] . $after_title
				. $after_widget;
			return;
		}
		
		if ( !in_the_loop() || !is_page() )
			return;
		
		global $wp_the_query;
		$page_id = $wp_the_query->get_queried_object_id();
		$cache_id = "_$widget_id";
		$o = get_post_meta($page_id, $cache_id, true);
		
		if ( !sem_widget_cache_debug && !is_preview() && $o ) {
			echo $o;
			return;
		}
		
		$deep = $format != 'shallow';
		$shallow = $format != 'deep';
		
		silo_stub::cache_pages();
		if ( $deep )
			silo_stub::cache_extra_pages();
		
		$root_ids = wp_cache_get($page_id, 'page_children');
		
		foreach ( $root_ids as $root_id )
			$shallow &= !wp_cache_get($root_id, 'page_children');
		
		$deep &= !$shallow;
		
		ob_start();
		
		echo $before_widget;
		
		if ( !$deep )
			echo '<ul>' . "\n";
		
		foreach ( $root_ids as $root_id )
			silo_stub::display_page($root_id, $deep);
		
		if ( !$deep )
			echo '</ul>' . "\n";
		
		echo $after_widget;
		
		$o = ob_get_clean();
		
		if ( !is_preview() )
			update_post_meta($page_id, $cache_id, $o);
		
		echo $o;
	} # widget()
	
	
	/**
	 * display_page()
	 *
	 * @param int $ref
	 * @param bool $deep
	 * @return void
	 **/

	function display_page($ref, $deep = false) {
		$page = get_page($ref);
		
		if ( !$page || get_post_meta($page->ID, '_widgets_exclude', true) && !get_post_meta($page->ID, '_widgets_exception', true) )
			return;
		
		if ( is_page() ) {
			global $wp_the_query;
			$page_id = $wp_the_query->get_queried_object_id();
		} elseif ( get_option('show_on_front') == 'page' ) {
			$page_id = (int) get_option('page_for_posts');
		} else {
			$page_id = 0;
		}
		
		$label = get_post_meta($page->ID, '_widgets_label', true);
		if ( (string) $label === '' )
			$label = $page->post_title;
		if ( (string) $label === '' )
			$label = __('Untitled', 'silo');
		
		$url = esc_url(apply_filters('the_permalink', get_permalink($page->ID)));
		
		$ancestors = wp_cache_get($page_id, 'page_ancestors');
		$children = wp_cache_get($page->ID, 'page_children');
		
		$classes = array();
		$link = $label;
		
		if ( get_option('show_on_front') == 'page' && get_option('page_on_front') == $page->ID ) {
			$classes[] = 'nav_home';
			if ( !is_front_page() || is_front_page() && is_paged() )
				$link = '<a href="' . user_trailingslashit($url) . '" title="' . esc_attr($label) . '">'
					. $link
					. '</a>';
			if ( is_front_page() || in_array($page->ID, $ancestors) )
				$link = '<span class="nav_active">' . $link . '</span>';
		} elseif ( get_option('show_on_front') == 'page' && get_option('page_for_posts') == $page->ID ) {
			$classes[] = 'nav_blog';
			if ( !is_search() && !is_404() && ( !is_home() || is_home() && is_paged() ) )
				$link = '<a href="' . $url . '" title="' . esc_attr($label) . '">'
					. $link
					. '</a>';
			if ( !is_search() && !is_404() && ( !is_page() || in_array($page->ID, $ancestors) ) )
				$link = '<span class="nav_active">' . $link . '</span>';
		} else {
			if ( $children )
				$classes[] = 'nav_branch';
			else
				$classes[] = 'nav_leaf';
			
			if ( $page->ID != $page_id )
				$link = '<a href="' . $url . '" title="' . esc_attr($label) . '">'
					. $link
					. '</a>';
			
			$link_classes = array('nav_page-' . sanitize_html_class($page->post_name, $page->ID));
			if ( $page->ID == $page_id || in_array($page->ID, $ancestors) )
				$link_classes[] = 'nav_active';
			$link = '<span class="' . implode(' ', $link_classes) . '">' . $link . '</span>';
		}
		
		if ( $page->post_parent == $page_id && $deep ) {
			echo '<h2 class="' . implode(' ', $classes) . '">'
				. $link
				. '</h2>' . "\n";
			
			$descr = trim(get_post_meta($page->ID, '_widgets_desc', true));
			if ( $descr )
				echo "\n" . wpautop(apply_filters('widget_text', $descr)) . "\n";
			
			if ( $children ) {
				echo "\n"
					. '<ul>' . "\n";
				foreach ( $children as $child_id )
					silo_stub::display_page($child_id);
				echo '</ul>' . "\n";
			}
		} else {
			echo '<li class="' . implode(' ', $classes) . '">'
				. '<h3>' . $link . '</h3>';

			$descr = trim(get_post_meta($page->ID, '_widgets_desc', true));
			if ( $descr )
				echo "\n\n" . wpautop(apply_filters('widget_text', $descr));
			
			echo '</li>' . "\n";
		}
	} # display_page()
	
	
	/**
	 * update()
	 *
	 * @param array $new_instance new widget options
	 * @param array $old_instance old widget options
	 * @return array $instance
	 **/

	function update($new_instance, $old_instance) {
		$instance = silo_stub::defaults();
		$instance['format'] = in_array($new_instance['format'], array('deep', 'hybrid', 'shallow'))
			? $new_instance['format']
			: 'deep';
		return $instance;
	} # update()
	
	
	/**
	 * form()
	 *
	 * @param array $instance widget options
	 * @return void
	 **/

	function form($instance) {
		$instance = wp_parse_args($instance, silo_stub::defaults());
		extract($instance, EXTR_SKIP);
		
		echo '<h3>' . __('Format', 'silo') . '</h3>' . "\n";
		
		echo '<p>'
			. '<label>'
			. '<input type="radio"'
				. ' name="' . $this->get_field_name('format') . '" value="deep"'
				. checked($format, 'deep', false)
				. ' />'
			. '&nbsp;'
			. __('Display child pages as sections, and sub-child pages as lists within these sections. Even if there are no sub-child pages.', 'silo') . "\n"
			. '</label>'
			. '</p>' . "\n";
		
		echo '<p>'
			. '<label>'
			. '<input type="radio"'
				. ' name="' . $this->get_field_name('format') . '" value="hybrid"'
				. checked($format, 'hybrid', false)
				. ' />'
			. '&nbsp;'
			. __('Display child pages as sections, and sub-child pages as lists within these sections. If there are no sub-child pages, display a list of child pages instead.', 'silo') . "\n"
			. '</label>'
			. '</p>' . "\n";
		
		echo '<p>'
			. '<label>'
			. '<input type="radio"'
				. ' name="' . $this->get_field_name('format') . '" value="shallow"'
				. checked($format, 'shallow', false)
				. ' />'
			. '&nbsp;'
			. __('Display a list of child pages. Don\'t display sub-child pages.', 'silo') . "\n"
			. '</label>'
			. '</p>' . "\n";
	} # form()
	
	
	/**
	 * defaults()
	 *
	 * @return array $instance default options
	 **/

	function defaults() {
		return array(
			'format' => 'deep',
			);
	} # defaults()
	
	
	/**
	 * cache_pages()
	 *
	 * @return void
	 **/

	function cache_pages() {
		if ( is_page() ) {
			global $wp_the_query;
			$page_id = (int) $wp_the_query->get_queried_object_id();
			$page = get_page($page_id);
		} else {
			$page_id = 0;
			$page = null;
		}
		
		if ( get_option('show_on_front') == 'page' ) {
			$front_page_id = (int) get_option('page_on_front');
			$front_page = get_page($front_page_id);
			$blog_page_id = (int) get_option('page_for_posts');
			$blog_page = $blog_page_id ? get_page($blog_page_id) : null;
		} else {
			$front_page_id = 0;
			$front_page = null;
			$blog_page_id = 0;
			$blog_page = null;
		}
		
		$ancestors = $page_id ? wp_cache_get($page_id, 'page_ancestors') : array();
		if ( $ancestors === false ) {
			$ancestors = array();
			while ( $page && $page->post_parent != 0 ) {
				$ancestors[] = (int) $page->post_parent;
				$page = get_page($page->post_parent);
			}
			$ancestors = array_reverse($ancestors);
			wp_cache_set($page_id, $ancestors, 'page_ancestors');
		}
		
		$front_page_ancestors = $front_page_id ? wp_cache_get($front_page_id, 'page_ancestors') : array();
		if ( $front_page_ancestors === false ) {
			$front_page_ancestors = array();
			while ( $front_page && $front_page->post_parent != 0 ) {
				$front_page_ancestors[] = (int) $front_page->post_parent;
				$front_page = get_page($front_page->post_parent);
			}
			$front_page_ancestors = array_reverse($front_page_ancestors);
			wp_cache_set($front_page_id, $front_page_ancestors, 'page_ancestors');
		}
		
		$blog_page_ancestors = $blog_page_id ? wp_cache_get($blog_page_id, 'page_ancestors') : array();
		if ( $blog_page_ancestors === false ) {
			$blog_page_ancestors = array();
			while ( $blog_page && $blog_page->post_parent != 0 ) {
				$blog_page_ancestors[] = (int) $blog_page->post_parent;
				$blog_page = get_page($blog_page->post_parent);
			}
			$blog_page_ancestors = array_reverse($blog_page_ancestors);
			wp_cache_set($blog_page_id, $blog_page_ancestors, 'page_ancestors');
		}
		
		$parent_ids = array_merge($ancestors, $front_page_ancestors, $blog_page_ancestors);
		array_unshift($parent_ids, 0);
		if ( $page_id )
			$parent_ids[] = $page_id;
		if ( $front_page_id )
			$parent_ids[] = $front_page_id;
		if ( $blog_page_id )
			$parent_ids[] = $blog_page_id;
		
		$cached = true;
		foreach ( $parent_ids as $parent_id ) {
			$cached = is_array(wp_cache_get($parent_id, 'page_children'));
			if ( $cached === false )
				break;
		}
		
		if ( $cached )
			return;
		
		global $wpdb;
		
		$roots = (array) $wpdb->get_col("
			SELECT	posts.ID
			FROM	$wpdb->posts as posts
			WHERE	posts.post_type = 'page'
			AND		post_status <> 'trash'
			AND		posts.post_parent IN ( 0, $page_id, $front_page_id, $blog_page_id )
			");
		
		$parent_ids = array_merge($parent_ids, $roots, array($page_id, $front_page_id, $blog_page_id));
		$parent_ids = array_unique($parent_ids);
		$parent_ids = array_map('intval', $parent_ids);
		
		$pages = (array) $wpdb->get_results("
			SELECT	posts.*
			FROM	$wpdb->posts as posts
			WHERE	posts.post_type = 'page'
			AND		posts.post_status = 'publish'
			AND		posts.post_parent IN ( " . implode(',', $parent_ids) . " )
			ORDER BY posts.menu_order, posts.post_title
			");
		
		$children = array();
		$to_cache = array();
		
		foreach ( $parent_ids as $parent_id )
			$children[$parent_id] = array();
		
		foreach ( $pages as $page ) {
			$children[$page->post_parent][] = $page->ID;
			$to_cache[] = $page->ID;
		}
		
		$all_ancestors = array();
		
		foreach ( $children as $parent => $child_ids ) {
			foreach ( $child_ids as $key => $child_id )
				$all_ancestors[$child_id][] = $parent;
			wp_cache_set($parent, $child_ids, 'page_children');
		}
		
		foreach ( $all_ancestors as $child_id => $parent_ids ) {
			while ( $parent_ids[0] && is_array($all_ancestors[$parent_ids[0]]) )
				$parent_ids = array_merge($all_ancestors[$parent_ids[0]], $parent_ids);
			wp_cache_set($child_id, $parent_ids, 'page_ancestors');
		}
		
		foreach ( array_keys($pages) as $k ) {
			$ancestors = wp_cache_get($pages[$k]->ID, 'page_ancestors');
			array_shift($ancestors);
			$pages[$k]->ancestors = $ancestors;
		}

		update_post_cache($pages);
		update_postmeta_cache($to_cache);
	} # cache_pages()
	
	
	/**
	 * cache_extra_pages()
	 *
	 * @return void
	 **/

	function cache_extra_pages() {
		if ( is_page() ) {
			global $wp_the_query;
			$page_id = (int) $wp_the_query->get_queried_object_id();
			$page = get_page($page_id);
		} else {
			$page_id = 0;
			$page = null;
		}
		
		$to_do = array();
		foreach ( wp_cache_get($page_id, 'page_children') as $child_id ) {
			foreach ( wp_cache_get($child_id, 'page_children') as $extra_id ) {
				if ( !is_array(wp_cache_get($extra_id)) )
					$to_do[] = $extra_id;
			}
		}
		
		if ( !$to_do )
			return;
		
		global $wpdb;
		
		$to_do = array_map('intval', $to_do);
		
		$pages = (array) $wpdb->get_results("
			SELECT	posts.*
			FROM	$wpdb->posts as posts
			WHERE	posts.post_type = 'page'
			AND		posts.post_status = 'publish'
			AND		posts.post_parent IN ( " . implode(',', $to_do) . " )
			ORDER BY posts.menu_order, posts.post_title
			");
		
		update_post_cache($pages);
		update_post_caches($to_do);
		
		$children = array();
		
		foreach ( $pages as $page )
			$children[$page->post_parent][] = $page->ID;
		
		foreach ( $children as $parent_id => $child_ids ) {
			foreach ( $child_ids as $child_id ) {
				$ancestors = array();
				$ancestors = wp_cache_get($parent_id, 'page_ancestors');
				$ancestors[] = $parent_id;
				wp_cache_set($child_id, $ancestors, 'page_ancestors');
			}
			wp_cache_set($parent_id, $child_ids, 'page_children');
		}
	} # cache_extra_pages()
	
	
	/**
	 * flush_cache()
	 *
	 * @param mixed $in
	 * @return mixed $in
	 **/
	
	function flush_cache($in = null) {
		$cache_ids = array();
		
		$widgets = get_option("widget_silo_stub");
		
		if ( !$widgets )
			return $in;
		
		unset($widgets['_multiwidget']);
		unset($widgets['number']);
		
		foreach ( array_keys($widgets) as $widget_id )
			$cache_ids[] = "silo_stub-$widget_id";
		
		foreach ( $cache_ids as $cache_id )
			delete_post_meta_by_key("_$cache_id");
		
		if ( wp_cache_get(0, 'page_children') !== false ) {
			global $wpdb;
			$page_ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_type = 'page' AND post_status = 'publish'");
			foreach ( $page_ids as $page_id ) {
				wp_cache_delete($page_id, 'page_ancestors');
				wp_cache_delete($page_id, 'page_children');
			}
			wp_cache_delete(0, 'page_ancestors');
			wp_cache_delete(0, 'page_children');
		}
		
		return $in;
	} # flush_cache()
} # silo_stub

add_action('widgets_init', array('silo_widgets', 'widgets_init'));

foreach ( array('page.php', 'page-new.php') as $hook )
	add_action('load-' . $hook, array('silo_widgets', 'editor_init'));

foreach ( array(
		'save_post',
		'delete_post',
		'switch_theme',
		'update_option_active_plugins',
		'update_option_show_on_front',
		'update_option_page_on_front',
		'update_option_page_for_posts',
		'update_option_sidebars_widgets',
		'update_option_sem5_options',
		'update_option_sem6_options',
		'generate_rewrite_rules',
		
		'flush_cache',
		'after_db_upgrade',
		) as $hook)
	add_action($hook, array('silo_map', 'flush_cache'));

register_activation_hook(__FILE__, array('silo_map', 'flush_cache'));
register_deactivation_hook(__FILE__, array('silo_map', 'flush_cache'));


foreach ( array(
		'save_post',
		'delete_post',
		'switch_theme',
		'update_option_active_plugins',
		'update_option_show_on_front',
		'update_option_page_on_front',
		'update_option_page_for_posts',
		'update_option_sidebars_widgets',
		'update_option_sem5_options',
		'update_option_sem6_options',
		'generate_rewrite_rules',
		
		'flush_cache',
		'after_db_upgrade',
		) as $hook)
	add_action($hook, array('silo_stub', 'flush_cache'));

register_activation_hook(__FILE__, array('silo_stub', 'flush_cache'));
register_deactivation_hook(__FILE__, array('silo_stub', 'flush_cache'));
?>