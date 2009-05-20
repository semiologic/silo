<?php
/*
Plugin Name: Silo Widgets
Plugin URI: http://www.semiologic.com/software/silo/
Description: Silo web design tools for sites built using static pages.
Version: 3.0 alpha
Author: Denis de Bernardy
Author URI: http://www.getsemiologic.com
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts and is distributed under the terms of the Mesoconcepts license. In a nutshell, you may freely use it for any purpose, but may not redistribute it without written permission.

http://www.mesoconcepts.com/license/
**/


load_plugin_textdomain('silo', null, dirname(__FILE__) . '/lang');


/**
 * silo_map
 *
 * @package Nav Menus
 **/

if ( !defined('sem_widget_cache_debug') )
	define('sem_widget_cache_debug', false);
add_action('widgets_init', array('silo_map', 'widgets_init'));
foreach ( array('page.php', 'page-new.php') as $hook )
	add_action('load-' . $hook, array('silo_map', 'editor_init'));
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
		) as $hook)
	add_action($hook, array('silo_map', 'flush_cache'));

register_activation_hook(__FILE__, array('silo_map', 'flush_cache'));
register_deactivation_hook(__FILE__, array('silo_map', 'flush_cache'));

class silo_map extends WP_Widget {
	/**
	 * editor_init()
	 *
	 * @return void
	 **/

	function editor_init() {
		if ( !class_exists('widget_utils') )
			include dirname(__FILE__) . '/widget-utils/widget-utils.php';
		widget_utils::page_meta_boxes();
		add_action('page_widget_config_affected', array('silo_map', 'widget_config_affected'));
	} # editor_init()
	
	
	/**
	 * widget_config_affected()
	 *
	 * @return void
	 **/

	function widget_config_affected() {
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
	} # widgets_init()
	
	
	/**
	 * silo_map()
	 *
	 * @return void
	 **/

	function silo_map() {
		$widget_ops = array(
			'classname' => 'silo_map',
			'description' => __("A site map. Insert this as an inline widget in a static page for best effect.", 'silo'),
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
		
		if ( is_admin() )
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
		
		if ( !sem_widget_cache_debug && $o ) {
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
		
		if ( is_page() ) {
			update_post_meta($page_id, $cache_id, $o);
		} else {
			set_transient($cache_id, $o);
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
		
		if ( !isset($label) || $label === '' )
			$label = get_post_meta($page->ID, '_widgets_label', true);
		if ( $label === '' )
			$label = $page->post_title;
		if ( $label === '' )
			$label = __('Untitled', 'silo');
		
		$url = clean_url(get_permalink($page->ID));
		
		$ancestors = wp_cache_get($page_id, 'page_ancestors');
		$children = wp_cache_get($page->ID, 'page_children');
		
		$classes = array();
		$link = $label;
		
		if ( get_option('show_on_front') == 'page' && get_option('page_on_front') == $page->ID ) {
			$classes[] = 'nav_home';
			if ( !is_front_page() || is_font_page() && is_paged() )
				$link = '<a href="' . $url . '" title="' . esc_attr($label) . '">'
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
			
			$link_classes = array('nav_page-' . $page->ID);
			if ( $page->ID == $page_id || in_array($page->ID, $ancestors) )
				$link_classes[] = 'nav_active';
			$link = '<span class="' . implode(' ', $link_classes) . '">' . $link . '</span>';
		}
		
		echo '<li class="' . implode(' ', $classes) . '">'
			. $link;
		
		$descr = get_post_meta($page->ID, '_widgets_desc', true);
		if ( $descr )
			echo "\n" . wpautop($descr);
		
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
		update_post_cache($pages);
		
		$children = array();
		$to_cache = array();
		
		foreach ( $pages as $page ) {
			$children[$page->post_parent][] = $page->ID;
			$to_cache[] = $page->ID;
		}
		
		update_postmeta_cache($to_cache);
		wp_cache_set('page_ids', $to_cache, 'widget');
		
		$all_ancestors = array();
		
		foreach ( $children as $parent => $child_ids ) {
			foreach ( $child_ids as $key => $child_id ) {
				$all_ancestors[$child_id][] = $parent;
				if ( get_post_meta($child_id, '_widgets_exclude', true) && !get_post_meta($child_id, '_widgets_exception', true) )
					unset($child_ids[$key]);
			}
			
			wp_cache_set($parent, $child_ids, 'page_children');
		}
		
		foreach ( $all_ancestors as $child_id => $parent_ids ) {
			while ( $parent_ids[0] )
				$parent_ids = array_merge($all_ancestors[$parent_ids[0]], $parent_ids);
			
			wp_cache_set($child_id, $parent_ids, 'page_ancestors');
		}
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
		
		foreach ( array_keys($widgets) as $widget_id )
			$cache_ids[] = "silo_map-$widget_id";
		
		foreach ( $cache_ids as $cache_id ) {
			delete_transient($cache_id);
			delete_post_meta_by_key("_$cache_id");
		}
		
		if ( wp_cache_get('page_ids', 'widget') !== false ) {
			global $wpdb;
			$page_ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_type = 'page'");
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
?>