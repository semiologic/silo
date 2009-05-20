<?php
if ( !class_exists('widget_utils') )
{
	include dirname(__FILE__) . '/widget-utils/widget-utils.php';
}

if ( !class_exists('page_tags') )
{
	include dirname(__FILE__) . '/page-tags.php';
}

class silo_admin
{
	#
	# init()
	#

	function init()
	{
		add_action('admin_menu', array('silo_admin', 'meta_boxes'));
		
		if ( get_option('silo_widgets_cache') === false )
		{
			update_option('silo_widgets_cache', array());
		}
		
		if ( version_compare(mysql_get_server_info(), '4.1', '<') )
		{
			add_action('admin_notices', array('silo_admin', 'mysql_warning'));
			remove_action('widgets_init', array('silo', 'widgetize'));
		}
	} # init()
	
	
	#
	# mysql_warning()
	#
	
	function mysql_warning()
	{
		echo '<div class="error">'
			. '<p><b style="color: firebrick;">Silo Web Design Error</b><br /><b>Your MySQL version is lower than 4.1.</b> It\'s time to <a href="http://www.semiologic.com/resources/wp-basics/wordpress-server-requirements/">change hosts</a> if yours doesn\'t want to upgrade.</p>'
			. '</div>';
	} # mysql_warning()


	#
	# meta_boxes()
	#

	function meta_boxes()
	{
		if ( !class_exists('widget_utils') ) return;
		
		widget_utils::post_meta_boxes();
		widget_utils::page_meta_boxes();

		add_action('post_widget_config_affected', array('silo_admin', 'widget_config_affected'));
		add_action('page_widget_config_affected', array('silo_admin', 'widget_config_affected'));
	} # meta_boxes()

	
	#
	# widget_config_affected()
	#

	function widget_config_affected()
	{
		echo '<li>'
			. 'Silo Pages'
			. '</li>' . "\n";
		
		echo '<li>'
			. 'Silo Stub'
			. '</li>' . "\n";
		
		echo '<li>'
			. 'Silo Map (except title)'
			. '</li>' . "\n";
	} # widget_config_affected()


	#
	# widget_control()
	#

	function widget_control($widget_args)
	{
		global $wp_registered_widgets;
		static $updated = false;

		if ( is_numeric($widget_args) )
			$widget_args = array( 'number' => $widget_args );
		$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
		extract( $widget_args, EXTR_SKIP ); // extract number

		$options = silo::get_options();

		if ( !$updated && !empty($_POST['sidebar']) )
		{
			$sidebar = (string) $_POST['sidebar'];

			$sidebars_widgets = wp_get_sidebars_widgets();
			
			if ( isset($sidebars_widgets[$sidebar]) )
				$this_sidebar =& $sidebars_widgets[$sidebar];
			else
				$this_sidebar = array();

			foreach ( $this_sidebar as $_widget_id )
			{
				if ( array('silo', 'display_widget') == $wp_registered_widgets[$_widget_id]['callback']
					&& isset($wp_registered_widgets[$_widget_id]['params'][0]['number'])
					)
				{
					$widget_number = $wp_registered_widgets[$_widget_id]['params'][0]['number'];
					if ( !in_array( "silo_widget-$widget_number", $_POST['widget-id'] ) ) // the widget has been removed.
						unset($options[$widget_number]);
					
					silo::clear_cache();
				}
			}

			foreach ( (array) $_POST['silo-widget'] as $num => $opt ) {
				$title = stripslashes(strip_tags($opt['title']));
				$desc = isset($opt['desc']);
				
				$options[$num] = compact( 'title', 'desc' );
			}

			update_option('silo_widgets', $options);
			$updated = true;
		}

		if ( -1 == $number )
		{
			$ops = silo::default_options();
			$number = '%i%';
		}
		else
		{
			$ops = $options[$number];
		}
		
		extract($ops);

		echo '<div style="margin: 0px 0px 6px 0px;">'
			. '<div style="width: 120px; float: left; padding-top: 2px;">'
			. '<label for="silo-widget-title-' . $number . '">'
			. __('Title', 'silo-widgets')
			. '</label>'
			. '</div>'
			. '<div style="width: 330px; float: right;">'
			. '<input style="width: 320px;"'
			. ' id="silo-widget-title-' . $number . '" name="silo-widget[' . $number . '][title]"'
			. ' type="text" value="' . attribute_escape($title) . '"'
			. ' />'
			. '</div>'
			. '<div style="clear: both;"></div>'
			. '</div>';


		echo '<div style="margin: 0px 0px 6px 0px;">'
			. '<div style="width: 330px; float: right;">'
			. '<label for="silo-widget-desc-' . $number . '">'
			. '<input'
			. ' id="silo-widget-desc-' . $number . '" name="silo-widget[' . $number . '][desc]"'
			. ' type="checkbox"'
			. ( $desc
				? ' checked="checked"'
				: ''
				)
			. ' />'
			. '&nbsp;' . __('Show Descriptions', 'silo-widgets')
			. '</label>'
			. '</div>'
			. '<div style="clear: both;"></div>'
			. '</div>';
	} # widget_control()
} # silo_admin

silo_admin::init();
?>