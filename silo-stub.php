<?php
/**
 * silo_stub
 *
 * @package Silo Widgets
 **/

class silo_stub extends WP_Widget {
	/**
	 * Plugin instance.
	 *
	 * @see get_instance()
	 * @type object
	 */
	protected static $instance = NULL;

	/**
	 * URL to this plugin's directory.
	 *
	 * @type string
	 */
	public $plugin_url = '';

	/**
	 * Path to this plugin's directory.
	 *
	 * @type string
	 */
	public $plugin_path = '';

	/**
	 * Access this plugin’s working instance
	 *
	 * @wp-hook plugins_loaded
	 * @return  object of this class
	 */
	public static function get_instance()
	{
		NULL === self::$instance and self::$instance = new self;

		return self::$instance;
	}


	/**
	 * Constructor.
	 *
	 *
	 */

	public function __construct() {
		$this->plugin_url    = plugins_url( '/', __FILE__ );
		$this->plugin_path   = plugin_dir_path( __FILE__ );

		$this->init();

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
	 * init()
	 *
	 * @return void
	 **/

	function init() {
		foreach ( array(
  		'switch_theme',
  		'update_option_active_plugins',
  		'update_option_show_on_front',
  		'update_option_page_on_front',
  		'update_option_page_for_posts',
  		'update_option_sidebars_widgets',
  		'update_option_sem5_options',
  		'update_option_sem6_options',
  		'generate_rewrite_rules',
          'clean_post_cache',
          'clean_page_cache',
   //       'updated_post_meta',
   //       'updated_page_meta',
  		'flush_cache',
  		'after_db_upgrade',
  		) as $hook )
  	    add_action($hook, array($this, 'flush_cache'));

		if ( is_admin() ) {
	        add_action('pre_post_update', array($this, 'pre_flush_post'));

	        foreach ( array(
	            'save_post',
	            'delete_post',
	            ) as $hook )
	        add_action($hook, array($this, 'flush_post'), 1); // before _save_post_hook()
		}

        register_activation_hook(__FILE__, array($this, 'flush_cache'));
        register_deactivation_hook(__FILE__, array($this, 'flush_cache'));
	}

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

		global $_wp_using_ext_object_cache;
		global $wp_the_query;
		$page_id = $wp_the_query->get_queried_object_id();
		$cache_id = "_$widget_id";
		if ( $_wp_using_ext_object_cache )
			$o = wp_cache_get($page_id, $widget_id);
		else
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

		if ( !is_preview() ) {
			if ( $_wp_using_ext_object_cache )
				wp_cache_set($page_id, $o, $widget_id);
			else
				update_post_meta($page_id, $cache_id, $o);
		}

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
		$page = get_post($ref);

		if ( !$page || ( (int) get_post_meta($page->ID, '_widgets_exclude', true) && !( (int) get_post_meta($page->ID, '_widgets_exception', true) ) ) )
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

        $ancestors = wp_cache_get($page->ID, 'page_ancestors');
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
			if ( !is_home() || is_home() && is_paged() )
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
			$page = get_post($page_id);
		} else {
			$page_id = 0;
			$page = null;
		}

		if ( get_option('show_on_front') == 'page' ) {
			$front_page_id = (int) get_option('page_on_front');
			$front_page = get_post($front_page_id);
			$blog_page_id = (int) get_option('page_for_posts');
			$blog_page = $blog_page_id ? get_post($blog_page_id) : null;
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
				$page = get_post($page->post_parent);
			}
			$ancestors = array_reverse($ancestors);
			wp_cache_set($page_id, $ancestors, 'page_ancestors');
		}

		$front_page_ancestors = $front_page_id ? wp_cache_get($front_page_id, 'page_ancestors') : array();
		if ( $front_page_ancestors === false ) {
			$front_page_ancestors = array();
			while ( $front_page && $front_page->post_parent != 0 ) {
				$front_page_ancestors[] = (int) $front_page->post_parent;
				$front_page = get_post($front_page->post_parent);
			}
			$front_page_ancestors = array_reverse($front_page_ancestors);
			wp_cache_set($front_page_id, $front_page_ancestors, 'page_ancestors');
		}

		$blog_page_ancestors = $blog_page_id ? wp_cache_get($blog_page_id, 'page_ancestors') : array();
		if ( $blog_page_ancestors === false ) {
			$blog_page_ancestors = array();
			while ( $blog_page && $blog_page->post_parent != 0 ) {
				$blog_page_ancestors[] = (int) $blog_page->post_parent;
				$blog_page = get_post($blog_page->post_parent);
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
		$parent_ids = array_map('intval', $parent_ids);
		$parent_ids = array_unique($parent_ids);
		sort($parent_ids);

		$cached = true;
		foreach ( $parent_ids as $parent_id ) {
			$children_ids = wp_cache_get($parent_id, 'page_children');
			$cached = is_array($children_ids);
			if ( $cached === false )
				break;
			foreach ( $children_ids as $children_id ) {
				$cached = is_array(wp_cache_get($children_id, 'page_children'));
				if ( $cached === false )
					break 2;
			}
		}

		if ( $cached )
			return;

		global $wpdb;

		$root_ids = array();
		if ( $page_id ) {
			$parent_page = get_post($page_id);
			while ( $parent_page->post_parent ) {
				$root_ids[] = $parent_page->post_parent;
				$parent_page = get_post($parent_page->post_parent);
			}
		}
		$root_ids = array_merge($root_ids, array(0, $page_id, $front_page_id, $blog_page_id));
		$root_ids = array_map('intval', $root_ids);
		$root_ids = array_unique($root_ids);
		sort($root_ids);

		$roots = (array) $wpdb->get_col("
			SELECT	posts.ID
			FROM	$wpdb->posts as posts
			WHERE	posts.post_type = 'page'
			AND		post_status IN ( 'publish', 'private' )
			AND		posts.post_parent IN ( " . implode(',', $root_ids) . " )
			");

		$parent_ids = array_merge($parent_ids, $roots, array($page_id, $front_page_id, $blog_page_id));
		$parent_ids = array_map('intval', $parent_ids);
		$parent_ids = array_unique($parent_ids);
		sort($parent_ids);

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
			while ( $parent_ids[0] && $all_ancestors[$parent_ids[0]] )
				$parent_ids = array_merge($all_ancestors[$parent_ids[0]], $parent_ids);
			wp_cache_set($child_id, $parent_ids, 'page_ancestors');
		}

		foreach ( array_keys($pages) as $k ) {
			$ancestors = wp_cache_get($pages[$k]->ID, 'page_ancestors');
			array_shift($ancestors);
            $ancestors = array_reverse($ancestors);
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
		} else {
			$page_id = 0;
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
		$to_do = array_unique($to_do);
		sort($to_do);

		$pages = (array) $wpdb->get_results("
			SELECT	posts.*
			FROM	$wpdb->posts as posts
			WHERE	posts.post_type = 'page'
			AND		posts.post_status = 'publish'
			AND		posts.post_parent IN ( " . implode(',', $to_do) . " )
			ORDER BY posts.menu_order, posts.post_title
			");

		$children = array();

		foreach ( $pages as $page )
			$children[$page->post_parent][] = $page->ID;

		foreach ( $children as $parent_id => $child_ids ) {
			foreach ( $child_ids as $child_id ) {
				$ancestors = (array) wp_cache_get($parent_id, 'page_ancestors');
				$ancestors[] = $parent_id;
				wp_cache_set($child_id, $ancestors, 'page_ancestors');
			}
			wp_cache_set($parent_id, $child_ids, 'page_children');
		}

		foreach ( array_keys($pages) as $k ) {
			$ancestors = wp_cache_get($pages[$k]->ID, 'page_ancestors');
			array_shift($ancestors);
            $ancestors = array_reverse($ancestors);
			$pages[$k]->ancestors = $ancestors;
		}

		update_post_cache($pages);
		update_postmeta_cache($to_do);
	} # cache_extra_pages()


	/**
	 * pre_flush_post()
	 *
	 * @param int $post_id
	 * @return void
	 **/

	function pre_flush_post($post_id) {
		$post_id = (int) $post_id;
		if ( !$post_id )
			return;

		$post = get_post($post_id);
		if ( !$post || $post->post_type != 'page' || wp_is_post_revision($post_id) )
			return;

		$old = wp_cache_get($post_id, 'pre_flush_post');
		if ( $old === false )
			$old = array();

		$update = false;
		foreach ( array(
			'post_title',
			'post_status',
			) as $field ) {
			if ( !isset($old[$field]) ) {
				$old[$field] = $post->$field;
				$update = true;
			}
		}

		if ( !isset($old['permalink']) ) {
			$old['permalink'] = apply_filters('the_permalink', get_permalink($post_id));
			$update = true;
		}

		foreach ( array(
			'widgets_label', 'widgets_desc',
			'widgets_exclude', 'widgets_exception',
			) as $key ) {
			if ( !isset($old[$key]) ) {
				$old[$key] = get_post_meta($post_id, "_$key", true);
				$update = true;
			}
		}


		if ( $update )
			wp_cache_set($post_id, $old, 'pre_flush_post');
	} # pre_flush_post()


	/**
	 * flush_post()
	 *
	 * @param int $post_id
	 * @return void|mixed
	 **/

	function flush_post($post_id) {
		$post_id = (int) $post_id;
		if ( !$post_id )
			return;

		# prevent mass-flushing when the permalink structure hasn't changed
		remove_action('generate_rewrite_rules', array($this, 'flush_cache'));

		$post = get_post($post_id);
		if ( !$post || $post->post_type != 'page' || wp_is_post_revision($post_id) )
			return;

		$old = wp_cache_get($post_id, 'pre_flush_post');

		if ( $post->post_status != 'publish' && ( !$old || $old['post_status'] != 'publish' ) )
			return;

		if ( $old === false )
			return silo_stub::flush_cache();

		extract($old, EXTR_SKIP);
		foreach ( array_keys($old) as $key ) {
			switch ( $key ) {
			case 'widgets_label':
			case 'widgets_desc':
			case 'widgets_exclude':
			case 'widgets_exception':
				if ( $$key != get_post_meta($post_id, "_$key", true) )
					return silo_stub::flush_cache();
				break;

			case 'permalink':
				if ( $$key != apply_filters('the_permalink', get_permalink($post_id)) )
					return silo_stub::flush_cache();
				break;

			case 'post_title':
			case 'post_status':
				if ( $$key != $post->$key )
					return silo_stub::flush_cache();
			}
		}
	} # flush_post()


	/**
	 * flush_cache()
	 *
	 * @param mixed $in
	 * @return mixed $in
	 **/

	function flush_cache($in = null) {
		static $done = false;
		if ( $done )
			return $in;

		$done = true;
		$option_name = 'silo_stub';

		$widgets = get_option("widget_$option_name");

		if ( !$widgets )
			return $in;

		unset($widgets['_multiwidget']);
		unset($widgets['number']);

		if ( !$widgets )
			return $in;

		$cache_ids = array();

		global $_wp_using_ext_object_cache;
		foreach ( array_keys($widgets) as $widget_id ) {
			$cache_id = "$option_name-$widget_id";
			delete_post_meta_by_key("_$cache_id");
			if ( $_wp_using_ext_object_cache )
				$cache_ids[] = $cache_id;
		}

		if ( $cache_ids ) {
			$page_ids = wp_cache_get('page_ids', 'widget_queries');
			if ( $page_ids === false ) {
				global $wpdb;
				$page_ids = $wpdb->get_col("
					SELECT	ID
					FROM	$wpdb->posts
					WHERE	post_type = 'page'
					AND		post_status IN ( 'publish', 'private' )
					");
				wp_cache_set('page_ids', $page_ids, 'widget_queries');
			}
			foreach ( $cache_ids as $cache_id ) {
				foreach ( $page_ids as $page_id )
					wp_cache_delete($page_id, $cache_id);
			}
		}

		return $in;
	} # flush_cache()
} # silo_stub
