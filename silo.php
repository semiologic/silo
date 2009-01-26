<?php
/*
Plugin Name: Silo Widgets
Plugin URI: http://www.semiologic.com/software/widgets/silo/
Description: Silo web design tools for sites built using static pages.
Version: 2.3.2
Author: Denis de Bernardy
Author URI: http://www.getsemiologic.com
Update Package: https://members.semiologic.com/media/plugins/silo/silo.zip
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts and is distributed under the terms of the Mesoconcepts license. In a nutshell, you may freely use it for any purpose, but may not redistribute it without written permission.

http://www.mesoconcepts.com/license/
**/


define('silo_debug', false);

class silo
{
	#
	# init()
	#

	function init()
	{
		add_action('widgets_init', array('silo', 'widgetize'));

		foreach ( array(
				'save_post',
				'delete_post',
				'switch_theme',
				'update_option_active_plugins',
				'update_option_show_on_front',
				'update_option_page_on_front',
				'update_option_page_for_posts',
				'generate_rewrite_rules',
				) as $hook)
		{
			add_action($hook, array('silo', 'clear_cache'));
		}
		
		register_activation_hook(__FILE__, array('silo', 'clear_cache'));
		register_deactivation_hook(__FILE__, array('silo', 'clear_cache'));
	} # init()


	#
	# widgetize()
	#

	function widgetize()
	{
		$options = silo::get_options();
		
		$widget_options = array('classname' => 'silo_widget', 'description' => __( "Silo page widget") );
		$control_options = array('width' => 500, 'id_base' => 'silo_widget');
		
		$id = false;

		# registered widgets
		foreach ( array_keys($options) as $o )
		{
			if ( !is_numeric($o) ) continue;
			$id = "silo_widget-$o";
			wp_register_sidebar_widget($id, __('Silo Pages'), array('silo', 'display_widget'), $widget_options, array( 'number' => $o ));
			wp_register_widget_control($id, __('Silo Pages'), array('silo_admin', 'widget_control'), $control_options, array( 'number' => $o ) );
		}
		
		# default widget if none were registered
		if ( !$id )
		{
			$id = "silo_widget-1";
			wp_register_sidebar_widget($id, __('Silo Pages'), array('silo', 'display_widget'), $widget_options, array( 'number' => -1 ));
			wp_register_widget_control($id, __('Silo Pages'), array('silo_admin', 'widget_control'), $control_options, array( 'number' => -1 ) );
		}
		
		# silo stub
		if ( class_exists('inline_widgets') )
		{
			wp_register_sidebar_widget('silo_stub', __('Silo Stub'), array('silo', 'display_stub'),
				array(
					'classname' => 'silo_stub',
					'description' => __( "The list of sub pages, each with their description. Use as an inline widget, in section stubs, to generate their content automatically."),
					)
				);

			wp_register_sidebar_widget('silo_map', __('Silo Map'), array('silo', 'display_map'),
				array(
					'classname' => 'silo_map',
					'description' => __( "The hierarchical list of all pages, each with their description. Use as an inline widget, in a site map page."),
					)
				);
		}
	} # widgetize()


	#
	# display_widget()
	#

	function display_widget($args = null, $widget_args = 1)
	{
		if ( is_numeric($widget_args) )
			$widget_args = array( 'number' => $widget_args );
		$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
		extract( $widget_args, EXTR_SKIP );
		
		$number = intval($number);
		
		if ( is_page() )
		{
			$page_id = intval($GLOBALS['wp_query']->get_queried_object_id());
		}
		else
		{
			$page_id = false;
		}
		
		# front end: serve cache if available
		if ( !is_admin() && !silo_debug )
		{
			if ( is_page() )
			{
				if ( in_array(
						'_silo_widgets_cache_' . $number,
						(array) get_post_custom_keys($page_id)
						)
					)
				{
					$cache = get_post_meta($page_id, '_silo_widgets_cache_' . $number, true);
					echo $cache;
					return;
				}
			}
			else
			{
				$cache = get_option('silo_widgets_cache');

				if ( isset($cache[$number]) )
				{
					echo $cache[$number];
					return;
				}
			}
		}
		
		# get options
		$options = silo::get_options();
		$options = $options[$number];
		
		# admin area: serve a formatted title
		if ( is_admin() )
		{
			echo $args['before_widget']
				. $args['before_title'] . $options['title'] . $args['after_title']
				. $args['after_widget'];

			return;
		}
		
		# init
		global $wpdb;
		global $post_label;
		global $post_desc;
		static $page_ids;
		static $ancestors;
		static $children = array();
		
		$exclude_sql = "
			SELECT	post_id
			FROM	$wpdb->postmeta
			WHERE	meta_key = '_widgets_exclude'
			";
		
		# fetch root page details
		if ( !isset($page_ids) )
		{
			$page_ids = array();

			$pages = (array) $wpdb->get_results("
				# silo pages: fetch root pages
				SELECT	posts.*,
						COALESCE(post_label.meta_value, post_title) as post_label,
						COALESCE(post_desc.meta_value, '') as post_desc
				FROM	$wpdb->posts as posts
				LEFT JOIN $wpdb->postmeta as post_label
				ON		post_label.post_id = posts.ID
				AND		post_label.meta_key = '_widgets_label'
				LEFT JOIN $wpdb->postmeta as post_desc
				ON		post_desc.post_id = posts.ID
				AND		post_desc.meta_key = '_widgets_desc'
				WHERE	post_type = 'page'
				AND		post_status = 'publish'
				AND		post_parent = 0
				AND		ID NOT IN ( $exclude_sql )
				ORDER BY menu_order, LOWER(COALESCE(post_label.meta_value, post_title))
				");
			
			update_post_cache($pages);
			
			foreach ( $pages as $page )
			{
				$page_ids[] = $page->ID;
				$post_label[$page->ID] = $page->post_label;
				$post_desc[$page->ID] = $page->post_desc;
			}
		}
		
		# page: fetch ancestors
		if ( !$page_id )
		{
			$ancestors = array();
		}
		elseif ( !isset($ancestors) )
		{
			$ancestors = array($page_id);
			
			if ( !in_array($page_id, $page_ids) )
			{
				# current page is in the wp cache already
				$page = wp_cache_get($page_id, 'posts');
				
				if ( !isset($post_label[$page_id]) )
				{
					if ( $page_label = get_post_meta($page_id, '_widgets_label', true) )
					{
						$post_label[$page_id] = $page_label;
					}
					else
					{
						$post_label[$page_id] = $page->post_title;
					}
					
					$post_desc[$page_id] = get_post_meta($page_id, '_widgets_desc', true);
				}
				
				if ( $page->post_parent != 0 )
				{
					# traverse pages until we bump into the trunk
					do {
						$page = (object) $wpdb->get_row("
							# silo pages: fetch page ancestors
							SELECT	posts.*,
									COALESCE(post_label.meta_value, post_title) as post_label,
									COALESCE(post_desc.meta_value, '') as post_desc
							FROM	$wpdb->posts as posts
							LEFT JOIN $wpdb->postmeta as post_label
							ON		post_label.post_id = posts.ID
							AND		post_label.meta_key = '_widgets_label'
							LEFT JOIN $wpdb->postmeta as post_desc
							ON		post_desc.post_id = posts.ID
							AND		post_desc.meta_key = '_widgets_desc'
							WHERE	post_type = 'page'
							AND		post_status = 'publish'
							AND		ID = $page->post_parent
							");

						$pages = array($page);
						update_post_cache($pages);

						$post_label[$page->ID] = $page->post_label;
						$post_desc[$page->ID] = $page->post_desc;

						array_unshift($ancestors, $page->ID);
					} while ( $page->post_parent > 0 ); # > 0 to stop at unpublished pages if necessary
				}
			}
		}
		
		# page: fetch children
		if ( $page_id )
		{
			# fetch children of each ancestor
			foreach ( $ancestors as $ancestor_id )
			{
				if ( !isset($children[$ancestor_id]) )
				{
					$children[$ancestor_id] = array();
					
					$pages = (array) $wpdb->get_results("
						# silo pages: fetch siblings
						SELECT	posts.*,
								COALESCE(post_label.meta_value, post_title) as post_label,
								COALESCE(post_desc.meta_value, '') as post_desc
						FROM	$wpdb->posts as posts
						LEFT JOIN $wpdb->postmeta as post_label
						ON		post_label.post_id = posts.ID
						AND		post_label.meta_key = '_widgets_label'
						LEFT JOIN $wpdb->postmeta as post_desc
						ON		post_desc.post_id = posts.ID
						AND		post_desc.meta_key = '_widgets_desc'
						WHERE	post_type = 'page'
						AND		post_status = 'publish'
						AND		post_parent = $ancestor_id
						AND		ID NOT IN ( $exclude_sql )
						ORDER BY menu_order, LOWER(COALESCE(post_label.meta_value, post_title))
						");
					
					update_post_cache($pages);

					foreach ( $pages as $page )
					{
						$children[$ancestor_id][] = $page->ID;

						$post_label[$page->ID] = $page->post_label;
						$post_desc[$page->ID] = $page->post_desc;
					}
				}
			}
		}
		
		# fetch relevant children, in order to set the correct branch or leaf class
		$parent_ids = $page_ids;
		
		if ( $page_id )
		{
			foreach ( $ancestors as $ancestor_id )
			{
				$parent_ids = array_merge($parent_ids, $children[$ancestor_id]);
			}
		}
		
		$parent_ids = array_diff($parent_ids, array_keys($children));
		$parent_ids = array_unique($parent_ids);
		
		if ( $parent_ids )
		{
			$parent_ids_sql = implode(', ', $parent_ids);
			
			$pages = (array) $wpdb->get_results("
				# silo pages: fetch children
				SELECT	posts.*,
						COALESCE(post_label.meta_value, post_title) as post_label,
						COALESCE(post_desc.meta_value, '') as post_desc
				FROM	$wpdb->posts as posts
				LEFT JOIN $wpdb->postmeta as post_label
				ON		post_label.post_id = posts.ID
				AND		post_label.meta_key = '_widgets_label'
				LEFT JOIN $wpdb->postmeta as post_desc
				ON		post_desc.post_id = posts.ID
				AND		post_desc.meta_key = '_widgets_desc'
				WHERE	post_type = 'page'
				AND		post_status = 'publish'
				AND		post_parent IN ( $parent_ids_sql )
				AND		ID NOT IN ( $exclude_sql )
				ORDER BY menu_order, LOWER(COALESCE(post_label.meta_value, post_title))
				");

			update_post_cache($pages);

			foreach ( $pages as $page )
			{
				$children[$page->post_parent][] = $page->ID;

				$post_label[$page->ID] = $page->post_label;
				$post_desc[$page->ID] = $page->post_desc;
			}
		}
		
		$o = '';
		
		# fetch output
		if ( $page_ids )
		{
			$o .= $args['before_widget'] . "\n"
				. ( $options['title']
					? ( $args['before_title'] . $options['title'] . $args['after_title'] . "\n" )
					: ''
					);

			$o .= '<ul>' . "\n";
			
			foreach ( $page_ids as $item_id )
			{
				$o .= silo::display_page($item_id, $page_id, $ancestors, $children, $options['desc']);
			}
			
			$o .= '</ul>' . "\n";
			
			$o .= $args['after_widget'] . "\n";
		}
		
		# cache
		if ( is_page() )
		{
			add_post_meta($page_id, '_silo_widgets_cache_' . $number, $o, true);
		}
		else
		{
			$cache[$number] = $o;
			update_option('silo_widgets_cache', $cache);
		}
		
		# display
		echo $o;
	} # display_widget()


	#
	# display_page()
	#
	
	function display_page($item_id, $page_id, $ancestors, $children, $desc)
	{
		$is_home_page = ( get_option('show_on_front') == 'page' )
				&& ( get_option('page_on_front') == $item_id );
		$is_blog_page = ( get_option('show_on_front') == 'page' )
				&& ( get_option('page_for_posts') == $item_id );
		
		global $post_label;
		global $post_desc;
		$classes = array();
		
		# process link
		$link = $post_label[$item_id];
		
		if ( ( $page_id != $item_id )
			&& !( $is_blog_page && is_home() )
			)
		{
			$link = '<a href="' . htmlspecialchars(get_permalink($item_id)) . '">'
				. $link
				. '</a>';
		}
		
		# process classes
		if ( $is_home_page )
		{
			$li_class = 'nav_home';
		}
		elseif ( $is_blog_page )
		{
			$li_class = 'nav_blog';
		}
		elseif ( $children[$item_id] )
		{
			$li_class = 'nav_branch';
		}
		else
		{
			$li_class = 'nav_leaf';
		}

		$classes[] = 'nav__' . preg_replace("/[^0-9a-z]+/i", "_", strtolower($post_label[$item_id]));
		
		if ( $page_id && in_array($item_id, $ancestors)
			|| $is_blog_page && !is_page()
			)
		{
			$classes[] = 'nav_active';
		}
		
		$classes = array_unique($classes);
		
		$o = '<li class="' . $li_class . '">'
			. '<span class="' . implode(' ', $classes) . '">'
			. $link
			. '</span>';
			
		if ( $desc && $post_desc[$item_id] )
		{
			$o .= wpautop($post_desc[$item_id]);
		}
		
		# display children if there are children and if that item is in the page's ancestors
		if ( $children[$item_id] && in_array($item_id, $ancestors) )
		{
			$o .= "\n" . '<ul>' . "\n";
			
			foreach ( $children[$item_id] as $child_id )
			{
				$o .= silo::display_page($child_id, $page_id, $ancestors, $children, $desc);
			}
			
			$o .= '</ul>' . "\n";
		}
		
		$o .= '</li>' . "\n";
		
		return $o;
	} # display_page()
	
	
	#
	# display_stub()
	#
	
	function display_stub($args)
	{
		if ( is_admin() )
		{
			return;
		}
		elseif ( in_the_loop() )
		{
			$page_id = get_the_ID();
			$page = get_post($page_id);
			
			if ( !$page->post_type == 'page' ) return;
		}
		else
		{
			echo $args['before_widget']
				. $args['before_title']
				. 'Silo Stub Widget'
				. $args['after_title']
				. '<p>'
				. 'This widget is meant to be used as an inline widget in a static page. It will automatically generate a list of child pages, using the title and description that you enter under Write / Page: This Page In Widgets.'
				. '</p>'
				. $args['after_widget'];
			
			return;
		}
		
		# front end: serve cache if available
		if ( !silo_debug && in_array(
				'_silo_widgets_cache_stub',
				(array) get_post_custom_keys($page_id)
				)
			)
		{
			$cache = get_post_meta($page_id, '_silo_widgets_cache_stub', true);
			echo $cache;
			return;
		}
		
		# init
		global $wpdb;
		global $post_label;
		global $post_desc;
		static $page_ids;
		static $children = array();
		
		$exclude_sql = "
			SELECT	exclude.post_id
			FROM	$wpdb->postmeta as exclude
			LEFT JOIN $wpdb->postmeta as exception
			ON		exception.post_id = exclude.post_id
			AND		exception.meta_key = '_widgets_exception'
			WHERE	exclude.meta_key = '_widgets_exclude'
			AND		exception.post_id IS NULL
			";
		
		# fetch root page details
		if ( !isset($page_ids) )
		{
			$page_ids = array();

			$pages = (array) $wpdb->get_results("
				# silo stub: fetch children
				SELECT	posts.*,
						COALESCE(post_label.meta_value, post_title) as post_label,
						COALESCE(post_desc.meta_value, '') as post_desc
				FROM	$wpdb->posts as posts
				LEFT JOIN $wpdb->postmeta as post_label
				ON		post_label.post_id = posts.ID
				AND		post_label.meta_key = '_widgets_label'
				LEFT JOIN $wpdb->postmeta as post_desc
				ON		post_desc.post_id = posts.ID
				AND		post_desc.meta_key = '_widgets_desc'
				WHERE	post_type = 'page'
				AND		post_status = 'publish'
				AND		post_parent = $page_id
				AND		ID NOT IN ( $exclude_sql )
				ORDER BY menu_order, LOWER(COALESCE(post_label.meta_value, post_title))
				");
			
			update_post_cache($pages);
			
			foreach ( $pages as $page )
			{
				$page_ids[] = $page->ID;
				$post_label[$page->ID] = $page->post_label;
				$post_desc[$page->ID] = $page->post_desc;
			}
			
			if ( $page_ids )
			{
				# fetch children
				$page_ids_sql = implode(', ', $page_ids);
			
				$pages = (array) $wpdb->get_results("
					# silo stub: fetch sub-children
					SELECT	posts.*,
							COALESCE(post_label.meta_value, post_title) as post_label,
							COALESCE(post_desc.meta_value, '') as post_desc
					FROM	$wpdb->posts as posts
					LEFT JOIN $wpdb->postmeta as post_label
					ON		post_label.post_id = posts.ID
					AND		post_label.meta_key = '_widgets_label'
					LEFT JOIN $wpdb->postmeta as post_desc
					ON		post_desc.post_id = posts.ID
					AND		post_desc.meta_key = '_widgets_desc'
					WHERE	post_type = 'page'
					AND		post_status = 'publish'
					AND		post_parent IN ( $page_ids_sql )
					AND		ID NOT IN ( $exclude_sql )
					ORDER BY menu_order, LOWER(COALESCE(post_label.meta_value, post_title))
					");
			
				update_post_cache($pages);
			
				foreach ( $pages as $page )
				{
					$children[$page->post_parent][] = $page->ID;
					$post_label[$page->ID] = $page->post_label;
					$post_desc[$page->ID] = $page->post_desc;
				}
			}
		}
		
		$o = '';
		
		# fetch output
		if ( $page_ids )
		{
			$o .= $args['before_widget'] . "\n";

			foreach ( $page_ids as $item_id )
			{
				$o .= silo::display_stub_page($item_id, $children);
			}
			
			$o .= $args['after_widget'] . "\n";
		}
		
		# cache
		add_post_meta($page_id, '_silo_widgets_cache_stub', $o, true);
		
		# display
		echo $o;
	} # display_stub()


	#
	# display_stub_page()
	#
	
	function display_stub_page($item_id, $children)
	{
		global $post_label;
		global $post_desc;
		
		# process link
		$link = $post_label[$item_id];
		
		$link = '<a href="' . htmlspecialchars(get_permalink($item_id)) . '">'
			. $link
			. '</a>';
		
		$o = '<h2>'
			. $link
			. '</h2>' . "\n\n";
			
		if ( $post_desc[$item_id] )
		{
			$o .= wpautop($post_desc[$item_id]);
		}
		
		if ( $children[$item_id] )
		{
			$o .= '<ul>' . "\n";
			
			foreach ( $children[$item_id] as $child_id )
			{
				if ( $children[$child_id] )
				{
					$o .= '<li class="nav_branch">';
				}
				else
				{
					$o .= '<li class="nav_leaf">';
				}
				
				$link = $post_label[$child_id];

				$link = '<a href="' . htmlspecialchars(get_permalink($child_id)) . '">'
					. $link
					. '</a>';

				$o .= '<h3>'
					. $link
					. '</h3>';

				if ( $post_desc[$child_id] )
				{
					$o .= "\n\n"
						. wpautop($post_desc[$child_id]);
				}
				
				$o .= '</li>' . "\n\n";
			}
			
			$o .= '</ul>';
		}
		
		return $o;
	} # display_stub_page()
	
	
	#
	# display_map()
	#
	
	function display_map($args)
	{
		if ( is_admin() )
		{
			return;
		}
		elseif ( in_the_loop() )
		{
			$page_id = get_the_ID();
			$page = get_post($page_id);
			
			if ( !$page->post_type == 'page' ) return;
		}
		else
		{
			echo $args['before_widget']
				. $args['before_title']
				. 'Silo Map Widget'
				. $args['after_title']
				. '<p>'
				. 'This widget is meant to be used as an inline widget in a static page. It will automatically generate the full list of pages, using the title and description that you enter under Write / Page: This Page In Widgets.'
				. '</p>'
				. $args['after_widget'];
			
			return;
		}
		
		# front end: serve cache if available
		if ( !silo_debug && in_array(
				'_silo_widgets_cache_map',
				(array) get_post_custom_keys($page_id)
				)
			)
		{
			$cache = get_post_meta($page_id, '_silo_widgets_cache_map', true);
			echo $cache;
			return;
		}
		
		# init
		global $wpdb;
		global $post_label;
		global $post_desc;
		static $page_ids;
		static $children = array();
		
		$exclude_sql = "
			SELECT	exclude.post_id
			FROM	$wpdb->postmeta as exclude
			LEFT JOIN $wpdb->postmeta as exception
			ON		exception.post_id = exclude.post_id
			AND		exception.meta_key = '_widgets_exception'
			WHERE	exclude.meta_key = '_widgets_exclude'
			AND		exception.post_id IS NULL
			";
		
		# fetch root page details
		if ( !isset($page_ids) )
		{
			$page_ids = array();

			$pages = (array) $wpdb->get_results("
				# silo map: fetch root pages
				SELECT	posts.*,
						COALESCE(post_label.meta_value, post_title) as post_label,
						COALESCE(post_desc.meta_value, '') as post_desc
				FROM	$wpdb->posts as posts
				LEFT JOIN $wpdb->postmeta as post_label
				ON		post_label.post_id = posts.ID
				AND		post_label.meta_key = '_widgets_label'
				LEFT JOIN $wpdb->postmeta as post_desc
				ON		post_desc.post_id = posts.ID
				AND		post_desc.meta_key = '_widgets_desc'
				WHERE	post_type = 'page'
				AND		post_status = 'publish'
				AND		post_parent = 0
				AND		ID NOT IN ( $exclude_sql )
				ORDER BY menu_order, LOWER(COALESCE(post_label.meta_value, post_title))
				");
			
			update_post_cache($pages);
			
			foreach ( $pages as $page )
			{
				$page_ids[] = $page->ID;
				$post_label[$page->ID] = $page->post_label;
				$post_desc[$page->ID] = $page->post_desc;
			}

			# loop through each parent until we found all pages
			$found = $page_ids;
			
			if ( $found )
			{
				do
				{
					$found_sql = implode(', ', $found);

					$pages = (array) $wpdb->get_results("
						# silo map: fetch children
						SELECT	posts.*,
								COALESCE(post_label.meta_value, post_title) as post_label,
								COALESCE(post_desc.meta_value, '') as post_desc
						FROM	$wpdb->posts as posts
						LEFT JOIN $wpdb->postmeta as post_label
						ON		post_label.post_id = posts.ID
						AND		post_label.meta_key = '_widgets_label'
						LEFT JOIN $wpdb->postmeta as post_desc
						ON		post_desc.post_id = posts.ID
						AND		post_desc.meta_key = '_widgets_desc'
						WHERE	post_type = 'page'
						AND		post_status = 'publish'
						AND		post_parent IN ( $found_sql )
						AND		ID NOT IN ( $found_sql )
						AND		ID NOT IN ( $exclude_sql )
						ORDER BY menu_order, LOWER(COALESCE(post_label.meta_value, post_title))
						");

					update_post_cache($pages);

					foreach ( $pages as $page )
					{
						$found[] = $page->ID;
						$children[$page->post_parent][] = $page->ID;

						$post_label[$page->ID] = $page->post_label;
						$post_desc[$page->ID] = $page->post_desc;
					}
				} while ( $pages );
			}
		}
		
		$o = '';
		
		# fetch output
		if ( $page_ids )
		{
			$o .= $args['before_widget'] . "\n";

			$o .= '<ul>' . "\n";
			
			foreach ( $page_ids as $item_id )
			{
				$o .= silo::display_map_page($item_id, $page_id, $children);
			}
			
			$o .= '</ul>' . "\n";
			
			$o .= $args['after_widget'] . "\n";
		}

		# cache
		add_post_meta($page_id, '_silo_widgets_cache_map', $o, true);
		
		# display
		echo $o;
	} # display_map()


	#
	# display_map_page()
	#
	
	function display_map_page($item_id, $page_id, $children)
	{
		$is_home_page = ( get_option('show_on_front') == 'page' )
				&& ( get_option('page_on_front') == $item_id );
		$is_blog_page = ( get_option('show_on_front') == 'page' )
				&& ( get_option('page_for_posts') == $item_id );
		
		global $post_desc;
		$classes = array();
		
		# process link
		$page = get_post($item_id);
		$link = $page->post_title;
		
		if ( $page_id != $item_id )
		{
			$link = '<a href="' . htmlspecialchars(get_permalink($item_id)) . '">'
				. $link
				. '</a>';
		}
		
		# process classes
		if ( $is_home_page )
		{
			$li_class = 'nav_home';
		}
		elseif ( $is_blog_page )
		{
			$li_class = 'nav_blog';
		}
		elseif ( $children[$item_id] )
		{
			$li_class = 'nav_branch';
		}
		else
		{
			$li_class = 'nav_leaf';
		}

		$classes[] = 'nav__' . preg_replace("/[^0-9a-z]+/i", "_", strtolower($post_label[$item_id]));
		
		if ( $page_id == $item_id )
		{
			$classes[] = 'nav_active';
		}
		
		$classes = array_unique($classes);
		
		$o = '<li class="' . $li_class . '">'
			. '<span class="' . implode(' ', $classes) . '">'
			. $link
			. '</span>';
			
		if ( $post_desc[$item_id] )
		{
			$o .= wpautop($post_desc[$item_id]);
		}
		
		# display children if there are any
		if ( $children[$item_id] )
		{
			$o .= "\n" . '<ul>' . "\n";
			
			foreach ( $children[$item_id] as $child_id )
			{
				$o .= silo::display_map_page($child_id, $page_id, $children);
			}
			
			$o .= '</ul>' . "\n";
		}
		
		$o .= '</li>' . "\n";
		
		return $o;
	} # display_map_page()


	#
	# clear_cache()
	#

	function clear_cache($id = null)
	{
		global $wpdb;
		$wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '_silo_widgets_cache%'");
		
		update_option('silo_widgets_cache', array());
		
		return $id;
	} # clear_cache()
	
	
	#
	# get_options()
	#
	
	function get_options()
	{
		if ( ( $o = get_option('silo_widgets') ) === false )
		{
			if ( ( $o = get_option('silo_options') ) !== false )
			{
				$o = array( 1 => $o );
				
				foreach ( array_keys( (array) $sidebars = get_option('sidebars_widgets') ) as $k )
				{
					if ( !is_array($sidebars[$k]) )
					{
						continue;
					}

					if ( ( $key = array_search('silo-pages', $sidebars[$k]) ) !== false )
					{
						$sidebars[$k][$key] = 'silo_widget-1';
						update_option('sidebars_widgets', $sidebars);
						break;
					}
					elseif ( ( $key = array_search('Silo Pages', $sidebars[$k]) ) !== false )
					{
						$sidebars[$k][$key] = 'silo_widget-1';
						update_option('sidebars_widgets', $sidebars);
						break;
					}
				}

				if ( $files = glob(ABSPATH . 'wp-content/cache/silo-pages/*') )
				{
					foreach ( $files as $file )
					{
						@unlink($file);
					}
					
					@rmdir(ABSPATH . 'wp-content/cache/silo-pages');
				}
			}	
			else
			{
				$o = array();
			}
			
			update_option('silo_widgets', $o);
		}

		return $o;
	} # get_options()
	
	
	#
	# new_widget()
	#
	
	function new_widget()
	{
		$o = silo::get_options();
		$k = time();
		do $k++; while ( isset($o[$k]) );
		$o[$k] = silo::default_options();
		
		update_option('silo_widgets', $o);
		
		return 'silo_widget-' . $k;
	} # new_widget()


	#
	# default_options()
	#

	function default_options()
	{
		return array(
			'title' => 'Browse',
			'desc' => false,
			);
	} # default_options()
} # silo

silo::init();


if ( is_admin() )
{
	include dirname(__FILE__) . '/silo-admin.php';
}
?>