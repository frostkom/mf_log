<?php
/*
Plugin Name: MF Log & Debug
Plugin URI: https://github.com/frostkom/mf_log
Description: 
Version: 4.3.3
Author: Martin Fors
Author URI: http://frostkom.se
Text Domain: lang_log
Domain Path: /lang

GitHub Plugin URI: frostkom/mf_log
*/

include_once("include/classes.php");
include_once("include/functions.php");

if(1 == 1) //!is_admin()
{
	$log_query_debug = get_option('setting_log_query_debug');

	if($log_query_debug == 'yes')
	{
		/*if(!defined('SAVEQUERIES'))
		{
			define('SAVEQUERIES', true);
		}*/

		if(defined('SAVEQUERIES') && SAVEQUERIES == true && class_exists('Debug_Queries'))
		{
			$debug_queries = new Debug_Queries();
		}
	}
}

add_action('cron_base', 'cron_log', mt_rand(1, 10));

add_action('init', 'init_log');

if(is_admin())
{
	register_uninstall_hook(__FILE__, 'uninstall_log');

	add_action('admin_init', 'settings_log');
	add_action('admin_menu', 'menu_log');

	add_filter('get_user_notifications', 'get_user_notifications_log', 10, 1);
	//add_filter('get_user_reminders', 'get_user_reminders_log', 10, 1);

	if(is_multisite())
	{
		add_filter('manage_sites-network_columns', 'column_header_log', 5);
		add_action('manage_sites_custom_column', 'column_cell_log', 5, 2);
	}

	load_plugin_textdomain('lang_log', false, dirname(plugin_basename(__FILE__)).'/lang/');
}

function uninstall_log()
{
	mf_uninstall_plugin(array(
		'options' => array('setting_log_query_debug', 'setting_log_query_time_limit', 'setting_log_page_time_limit', 'mf_log_viewed'),
		'post_types' => array('mf_log'),
	));
}