<?php

function get_user_notifications_log($arr_notifications)
{
	global $wpdb;

	if(IS_ADMIN)
	{
		$result = $wpdb->get_results("SELECT ID, post_modified FROM ".$wpdb->posts." WHERE post_type = 'mf_log' AND post_status NOT IN ('trash', 'ignore') AND post_modified > DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
		$rows = $wpdb->num_rows;

		if($rows > 0)
		{
			$arr_notifications[] = array(
				'title' => $rows > 1 ? sprintf(__("There are %d new errors in the log", 'lang_log'), $rows) : __("There is one new error in the log", 'lang_log'),
				'tag' => 'log',
				//'text' => "",
				//'icon' => "",
				'link' => admin_url("admin.php?page=mf_log/list/index.php"),
			);
		}
	}

	return $arr_notifications;
}

function init_log()
{
	$labels = array(
		'name' => _x(__("Log", 'lang_log'), 'post type general name'),
		'singular_name' => _x(__("Log", 'lang_log'), 'post type singular name'),
		'menu_name' => __("Log", 'lang_log')
	);

	$args = array(
		'labels' => $labels,
		'public' => false,
		'exclude_from_search' => true,
		'supports' => array('title'),
		'hierarchical' => true,
		'has_archive' => false,
	);

	register_post_type('mf_log', $args);
}

function cron_log()
{
	global $wpdb;

	$str_path = ABSPATH."wp-content/debug.log";

	if(file_exists($str_path))
	{
		$error_limit = 50 * pow(1024, 2);

		if(filesize($str_path) < $error_limit)
		{
			$file = file($str_path);

			if($file != '')
			{
				foreach($file as $value)
				{
					list($date, $value) = explode("] ", $value, 2);

					do_log($value);
				}
			}

			@unlink($str_path);
		}

		else
		{
			do_log(__("debug.log was too large so it was deleted", 'lang_log'));

			@unlink($str_path);
		}
	}

	$result = $wpdb->get_results("SELECT ID FROM ".$wpdb->posts." WHERE post_type = 'mf_log' AND (
		post_status != 'trash' AND post_status != 'ignore' AND post_modified < DATE_SUB(NOW(), INTERVAL 1 MONTH)
		OR 
		post_status = 'ignore' AND post_modified < DATE_SUB(NOW(), INTERVAL 1 YEAR)
	)");

	foreach($result as $r)
	{
		wp_trash_post($r->ID);
	}
}

function settings_log()
{
	$options_area = __FUNCTION__;

	add_settings_section($options_area, "", $options_area."_callback", BASE_OPTIONS_PAGE);

	$arr_settings = array(
		'setting_log_query_debug' => __("Debug DB queries", 'lang_log'),
	);

	if(get_option('setting_log_query_debug') == 'yes')
	{
		$arr_settings['setting_log_query_time_limit'] = __("Query time limit", 'lang_log');
		$arr_settings['setting_log_page_time_limit'] = __("Page time limit", 'lang_log');
	}

	show_settings_fields(array('area' => $options_area, 'settings' => $arr_settings));
}

function settings_log_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);

	echo settings_header($setting_key, __("Log", 'lang_log'));
}

function setting_log_query_debug_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option($setting_key, 'no');

	echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option, 'suffix' => __("This will hurt performance on the frontend so use this for debugging only and then turn off", 'lang_log')));
}

function setting_log_query_time_limit_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option($setting_key);

	echo show_textfield(array('name' => $setting_key, 'value' => $option, 'placeholder' => __("0-9.9999", 'lang_log'), 'pattern' => "\d{1}(\.\d{0,4})?"));
}

function setting_log_page_time_limit_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option($setting_key);

	echo show_textfield(array('name' => $setting_key, 'value' => $option, 'placeholder' => __("0-9.9999", 'lang_log'), 'pattern' => "\d{1}(\.\d{0,4})?"));
}

function get_count_log($id = 0)
{
	global $wpdb;

	$count_message = "";

	$last_viewed = get_user_meta(get_current_user_id(), 'mf_log_viewed', true);

	$result = $wpdb->get_results($wpdb->prepare("SELECT ID FROM ".$wpdb->posts." WHERE post_type = 'mf_log' AND post_status NOT IN ('trash', 'ignore') AND post_modified > %s", $last_viewed));
	$rows = $wpdb->num_rows;

	if($rows > 0)
	{
		$count_message = "&nbsp;<span class='update-plugins' title='".__("New", 'lang_log')."'>
			<span>".$rows."</span>
		</span>";
	}

	return $count_message;
}

function menu_log()
{
	$menu_root = 'mf_log/';
	$menu_start = $menu_root.'list/index.php';
	$menu_capability = "update_core";

	$menu_title = __("Log", 'lang_log');

	$count_message = get_count_log();

	add_menu_page($menu_title, $menu_title.$count_message, $menu_capability, $menu_start, '', 'dashicons-warning');
}

function notices_log()
{
	global $wpdb, $error_text;

	if(IS_ADMIN)
	{
		$arr_conditions = array(
			array('constant' => "WP_DEBUG", 'check' => false, 'check_text' => "true"),
			array('constant' => "WP_DEBUG_LOG", 'check' => false, 'check_text' => "true"),
			array('constant' => "WP_DEBUG_DISPLAY"), //, 'check' => true, 'check_text' => "false"
			array('file' => ABSPATH."wp-content/debug.log"),
		);

		foreach($arr_conditions as $condition)
		{
			if(isset($condition['file']))
			{
				if(!file_exists($condition['file']))
				{
					if(!is_writable(dirname($condition['file'])))
					{
						$error_text = sprintf(__("%s is not writable. Please, make sure that the folder can be written to so that Wordpress can log errors", 'lang_log'), dirname($condition['file']))."";

						break;
					}
				}
			}

			else if(!defined($condition['constant']) || isset($condition['check']) && constant($condition['constant']) == $condition['check'])
			{
				$error_text = sprintf(__("%s should be set to %s in wp-config.php", 'lang_log'), $condition['constant'], $condition['check_text'])."";

				break;
			}
		}

		echo get_notification();
	}
}