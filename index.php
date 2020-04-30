<?php
/*
Plugin Name: MF Log & Debug
Plugin URI: https://github.com/frostkom/mf_log
Description: 
Version: 4.7.11
Licence: GPLv2 or later
Author: Martin Fors
Author URI: https://frostkom.se
Text Domain: lang_log
Domain Path: /lang

Depends: MF Base
GitHub Plugin URI: frostkom/mf_log
*/

include_once("include/classes.php");

$obj_log = new mf_log();

if(defined('SAVEQUERIES') && SAVEQUERIES == true && class_exists('Debug_Queries') && get_option('setting_log_query_debug') == 'yes')
{
	new Debug_Queries();
}

add_action('cron_base', 'activate_log', mt_rand(1, 10));
add_action('cron_base', array($obj_log, 'cron_base'), mt_rand(1, 10));

add_action('init', array($obj_log, 'init'));

if(is_admin())
{
	register_activation_hook(__FILE__, 'activate_log');
	register_uninstall_hook(__FILE__, 'uninstall_log');

	add_action('admin_init', array($obj_log, 'admin_init'), 0);
	add_action('admin_init', array($obj_log, 'settings_log'));
	add_action('admin_menu', array($obj_log, 'admin_menu'));

	add_filter('get_user_notifications', array($obj_log, 'get_user_notifications'), 10, 1);
	//add_filter('get_user_reminders', array($obj_log, 'get_user_reminders'), 10, 1);

	if(is_multisite())
	{
		add_filter('manage_sites-network_columns', array($obj_log, 'column_header'), 5);
		add_action('manage_sites_custom_column', array($obj_log, 'column_cell'), 5, 2);
	}

	load_plugin_textdomain('lang_log', false, dirname(plugin_basename(__FILE__)).'/lang/');
}

else
{
	add_action('wp_head', array($obj_log, 'wp_head'), 0);
	add_action('login_init', array($obj_log, 'login_init'), 0);
}

add_action('wp_ajax_send_js_debug', array($obj_log, 'send_js_debug'));
add_action('wp_ajax_nopriv_send_js_debug', array($obj_log, 'send_js_debug'));

function activate_log()
{
	mf_uninstall_plugin(array(
		'options' => array('setting_log_save_notifications', 'setting_log_curl_debug'),
	));
}

function uninstall_log()
{
	mf_uninstall_plugin(array(
		'options' => array('setting_log_activate', 'setting_log_save_notifications', 'setting_log_query_debug', 'setting_log_js_debug', 'setting_log_query_time_limit', 'setting_log_page_time_limit', 'setting_log_source_percent_limit'),
		'meta' => array('meta_log_viewed'),
		'post_types' => array('mf_log'),
	));
}