<?php
/*
Plugin Name: Silo Widgets
Plugin URI: http://www.semiologic.com/software/silo/
Description: Silo web design tools for sites built using static pages.
Version: 3.5
Author: Denis de Bernardy & Mike Koepke
Author URI: http://www.getsemiologic.com
Text Domain: silo
Domain Path: /lang
License: Dual licensed under the MIT and GPLv2 licenses
*/

/*
Terms of use
------------

This software is copyright Denis de Bernardy & Mike Koepke, and is distributed under the terms of the MIT and GPLv2 licenses.
**/


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
	 * Loads translation file.
	 *
	 * Accessible to other classes to load different language files (admin and
	 * front-end for example).
	 *
	 * @wp-hook init
	 * @param   string $domain
	 * @return  void
	 */
	public function load_language( $domain )
	{
		load_plugin_textdomain(
			$domain,
			FALSE,
			dirname(plugin_basename(__FILE__)) . '/lang'
		);
	}

	/**
	 * Constructor.
	 *
	 *
	 */
    public function __construct() {
	    $this->plugin_url    = plugins_url( '/', __FILE__ );
        $this->plugin_path   = plugin_dir_path( __FILE__ );
        $this->load_language( 'silo' );

	    add_action( 'plugins_loaded', array ( $this, 'init' ) );
    }

	/**
	 * init()
	 *
	 * @return void
	 **/

	function init() {
		// more stuff: register actions and filters
		add_action('widgets_init', array($this, 'widgets_init'));

		foreach ( array('page.php', 'page-new.php') as $hook )
			add_action('load-' . $hook, array($this, 'editor_init'));

		if ( function_exists('is_multisite') ) {
			foreach ( array('post.php', 'post-new.php') as $hook )
		        add_action('load-' . $hook, array($this, 'editor_init'));
		}

		wp_cache_add_non_persistent_groups(array('page_ancestors', 'page_children'));
		wp_cache_add_non_persistent_groups(array('widget_queries', 'pre_flush_post'));

		require_once dirname(__FILE__) . '/silo-map.php';
		require_once dirname(__FILE__) . '/silo-stub.php';

		$this->silo_map = silo_map::get_instance();
		$this->silo_stub = silo_stub::get_instance();
	}

    /**
	 * editor_init()
	 *
	 * @return void
	 **/

	function editor_init() {
		if ( !class_exists('widget_utils') )
			include $this->plugin_path . '/widget-utils/widget-utils.php';
		
		widget_utils::page_meta_boxes();
		add_action('page_widget_config_affected', array($this, 'widget_config_affected'));
		
		if ( !class_exists('page_tags') )
			include $this->plugin_path . '/page-tags/page-tags.php';
		
		page_tags::meta_boxes();
	} # editor_init()
	
	
	/**
	 * widget_config_affected()
	 *
	 * @return void
	 **/

	static function widget_config_affected() {
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

	static function widgets_init() {
		register_widget('silo_map');
		register_widget('silo_stub');
	} # widgets_init()
} # silo_widgets

$silo_widgets = silo_widgets::get_instance();





