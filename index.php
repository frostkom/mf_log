<?php
/*
Plugin Name: MF Log
Plugin URI: https://github.com/frostkom/mf_log
Description: 
Version: 3.3.5
Author: Martin Fors
Author URI: http://frostkom.se
Text Domain: lang_log
Domain Path: /lang

GitHub Plugin URI: frostkom/mf_log
*/

include_once("include/classes.php");
include_once("include/functions.php");

add_action('init', 'init_log');
add_action('cron_base', 'cron_log');

if(is_admin())
{
	register_uninstall_hook(__FILE__, 'uninstall_log');

	add_action('admin_notices', 'notices_log');
	//add_action('admin_init', 'settings_log');
	add_action('admin_menu', 'menu_log');
	add_filter('get_user_notifications', 'get_user_notifications_log', 10, 1);

	load_plugin_textdomain('lang_log', false, dirname(plugin_basename(__FILE__)).'/lang/');
}

function uninstall_log()
{
	mf_uninstall_plugin(array(
		'options' => array('setting_log_query_debug', 'setting_log_query_time_limit', 'setting_log_page_time_limit', 'mf_log_viewed'),
	));
}