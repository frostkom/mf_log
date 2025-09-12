<?php
/*
Plugin Name: MF Log & Debug
Plugin URI: https://github.com/frostkom/mf_log
Description:
Version: 4.9.31
Licence: GPLv2 or later
Author: Martin Fors
Author URI: https://martinfors.se
Text Domain: lang_log
Domain Path: /lang
*/

if(!function_exists('is_plugin_active') || function_exists('is_plugin_active') && is_plugin_active("mf_base/index.php"))
{
	include_once("include/classes.php");

	$obj_log = new mf_log();

	if(defined('SAVEQUERIES') && SAVEQUERIES == true && class_exists('Debug_Queries') && get_option('setting_log_query_debug') == 'yes')
	{
		new Debug_Queries();
	}

	add_action('cron_base', array($obj_log, 'cron_base'), mt_rand(1, 10));

	add_action('init', array($obj_log, 'init'));

	if(is_admin())
	{
		register_uninstall_hook(__FILE__, 'uninstall_log');

		add_action('admin_init', array($obj_log, 'settings_log'));
		add_action('admin_init', array($obj_log, 'admin_init'), 0);

		add_filter('filter_sites_table_settings', array($obj_log, 'filter_sites_table_settings'));

		add_action('admin_menu', array($obj_log, 'admin_menu'));

		add_filter('get_user_notifications', array($obj_log, 'get_user_notifications'), 10, 1);

		if(is_multisite())
		{
			add_filter('manage_sites-network_columns', array($obj_log, 'column_header'), 5);
			add_action('manage_sites_custom_column', array($obj_log, 'column_cell'), 5, 2);
		}
	}

	else
	{
		add_action('wp_head', array($obj_log, 'wp_head'), 0);
		add_action('login_init', array($obj_log, 'login_init'), 0);
	}

	add_action('wp_ajax_api_log_js_debug', array($obj_log, 'api_log_js_debug'));
	add_action('wp_ajax_nopriv_api_log_js_debug', array($obj_log, 'api_log_js_debug'));


	function uninstall_log()
	{
		include_once("include/classes.php");

		$obj_log = new mf_log();

		mf_uninstall_plugin(array(
			'options' => array('setting_log_activate', 'setting_log_save_notifications', 'setting_log_query_debug', 'setting_log_js_debug', 'setting_log_query_time_limit', 'setting_log_page_time_limit', 'setting_log_source_percent_limit'),
			'meta' => array('meta_log_viewed'),
			'post_types' => array($obj_log->post_type),
		));
	}
}