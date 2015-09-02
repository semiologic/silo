<?php
/**
 * silo_map
 *
 * @package Silo Widgets
 **/

class silo_map extends WP_Widget {
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
			'classname' => 'silo_map',
			'description' => __('A site map. Insert this as an inline widget in a static page for best effect.', 'silo'),
			);

		parent::__construct('silo_map', __('Silo Map', 'silo'), $widget_ops);
	} # silo_map()

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
		'wp_upgrade'
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
		extract($instance, EXTR_SKIP);

        global $_wp_using_ext_object_cache;

		if ( is_admin() || !in_the_loop() )
			return;

		$use_caching = true;
		if ( class_exists('WP_Customize_Widgets' ) )
			if ( $this->is_preview() )
				$use_caching = false;

		$o = '';

		if ( $use_caching ) {
			if ( is_page() ) {
				global $_wp_using_ext_object_cache;
				global $wp_query;
				$page_id = $wp_query->get_queried_object_id();
				$cache_id = "_$widget_id";
				if ( $_wp_using_ext_object_cache )
					$o = wp_cache_get($page_id, $widget_id);
				else
					$o = get_post_meta($page_id, $cache_id, true);
			} else {
				$cache_id = "$widget_id";
				$o = get_transient($cache_id);
	            $page_id = 0;
			}

			if ( !sem_widget_cache_debug && !is_preview() && $o ) {
				echo $o;
				return;
			}
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

		if ( !is_preview() && $use_caching ) {
			if ( is_page() ) {
				if ( $_wp_using_ext_object_cache )
					wp_cache_set($page_id, $o, $widget_id);
				else
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
		if ( is_array(wp_cache_get('page_ids', 'widget_queries')) )
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
			if ( !isset($children[$page->ID]) || !is_array($children[$page->ID]) )
				$children[$page->ID] = array();
			$children[$page->post_parent][] = $page->ID;
			$to_cache[] = $page->ID;
		}

		wp_cache_set('page_ids', $to_cache, 'widget_queries');

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
	 * @return mixed
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
			return silo_map::flush_cache();

		extract($old, EXTR_SKIP);
		foreach ( array_keys($old) as $key ) {
			switch ( $key ) {
			case 'widgets_label':
			case 'widgets_desc':
			case 'widgets_exclude':
			case 'widgets_exception':
				if ( $$key != get_post_meta($post_id, "_$key", true) )
					return silo_map::flush_cache();
				break;

			case 'permalink':
				if ( $$key != apply_filters('the_permalink', get_permalink($post_id)) )
					return silo_map::flush_cache();
				break;

			case 'post_title':
			case 'post_status':
				if ( $$key != $post->$key )
					return silo_map::flush_cache();
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
		$option_name = 'silo_map';

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
			delete_transient($cache_id);
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
} # silo_map
