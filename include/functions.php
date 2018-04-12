<?php

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

	$debug_file = ABSPATH."wp-content/debug.log";

	if(file_exists($debug_file))
	{
		$error_limit = 50 * pow(1024, 2);

		if(filesize($debug_file) < $error_limit)
		{
			$file = file($debug_file);

			if(is_array($file))
			{
				$file = array_unique($file);

				foreach($file as $value)
				{
					if(preg_match("/\]/", $value))
					{
						list($date, $value) = explode("] ", $value, 2);
					}

					do_log($value);
				}
			}

			@unlink($debug_file);
		}

		else
		{
			do_log(sprintf(__("%s was too large so it was deleted", 'lang_log'), "debug.log"));

			@unlink($debug_file);
		}
	}

	$result = $wpdb->get_results("SELECT ID FROM ".$wpdb->posts." WHERE post_type = 'mf_log' AND (
		post_status = 'auto-draft' AND post_modified < DATE_SUB(NOW(), INTERVAL 1 WEEK)
		OR post_status NOT IN ('trash', 'ignore') AND post_modified < DATE_SUB(NOW(), INTERVAL 1 MONTH)
		OR post_status = 'ignore' AND post_modified < DATE_SUB(NOW(), INTERVAL 1 YEAR)
	)");

	foreach($result as $r)
	{
		wp_trash_post($r->ID);
	}
}

function settings_log()
{
	if(IS_SUPER_ADMIN)
	{
		$options_area = __FUNCTION__;

		add_settings_section($options_area, "", $options_area."_callback", BASE_OPTIONS_PAGE);

		$arr_settings = array(
			'setting_log_activate' => __("Activate", 'lang_log'),
		);

		if(get_option('setting_log_activate') != 'no')
		{
			$arr_settings['setting_log_query_debug'] = __("Debug DB Queries", 'lang_log');

			if(get_option('setting_log_query_debug') == 'yes')
			{
				$arr_settings['setting_log_query_time_limit'] = __("Query Time Limit", 'lang_log');
				$arr_settings['setting_log_page_time_limit'] = __("Page Time Limit", 'lang_log');
				$arr_settings['setting_log_source_percent_limit'] = __("Slow Part Percent Limit", 'lang_log');
			}
		}

		show_settings_fields(array('area' => $options_area, 'settings' => $arr_settings));
	}
}

function settings_log_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);

	echo settings_header($setting_key, __("Log & Debug", 'lang_log'));
}

function setting_log_activate_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option($setting_key, 'yes');

	echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));

	if($option == 'yes')
	{
		/*require_once("class_cpu.php");

		$cpuload = new CPULoad();
		$cpuload->get_load();

		$cpu = mf_format_number($cpuload->load['cpu']);

		if($cpu > 0)
		{
			echo "<p><i class='fa ".($cpu < 20 ? "fa-check green" : "fa-close red")."'></i> ".__("CPU", 'lang_log').": ".$cpu."%</p>";
		}*/

		$load = sys_getloadavg();

		echo "<p><i class='fa ".($load[0] < 1 ? "fa-check green" : "fa-close red")."'></i> ".__("Load", 'lang_log')." &lt; 1 ".__("min", 'lang_log').": ".mf_format_number($load[0])."</p>";
		echo "<p><i class='fa ".($load[1] < 1 ? "fa-check green" : "fa-close red")."'></i> ".__("Load", 'lang_log')." &lt; 5 ".__("min", 'lang_log').": ".mf_format_number($load[1])."</p>";
		echo "<p><i class='fa ".($load[2] < 1 ? "fa-check green" : "fa-close red")."'></i> ".__("Load", 'lang_log')." &lt; 15 ".__("min", 'lang_log').": ".mf_format_number($load[2])."</p>";

		/*$memory_used = memory_get_usage();
		$memory_allocated = memory_get_usage(true);
		$memory_peak_used = memory_get_peak_usage();
		$memory_peak_allocated = memory_get_peak_usage(false);

		echo "<p><i class='fa ".($memory_used < ($memory_total * .8) ? "fa-check green" : "fa-close red")."'></i> ".__("Memory", 'lang_log').": ".mf_format_number(($memory_used / $memory_total) * 100)."% (".$memory_used." / ".$memory_total.")</p>";*/

		if(!defined('WP_DEBUG') || WP_DEBUG == false || !defined('WP_DEBUG_LOG') || WP_DEBUG_LOG == false || !defined('WP_DEBUG_DISPLAY'))
		{
			$debug_file = ABSPATH."wp-content/debug.log";
			$recommend_config = "define('WP_DEBUG', true);
			define('WP_DEBUG_LOG', true);
			define('WP_DEBUG_DISPLAY', false);";

			echo "<div class='mf_form'>"
				."<h3 class='display_warning'><i class='fa fa-warning yellow'></i> ".sprintf(__("Add this to the end of %s", 'lang_log'), "wp-config.php")."</h3>";

				if(!file_exists($debug_file))
				{
					if(!is_writable(dirname($debug_file)))
					{
						echo "<p>".sprintf(__("%s is not writable. Please, make sure that the folder can be written to so that Wordpress can log errors", 'lang_log'), dirname($debug_file))."</p>";
					}
				}

				echo "<p class='input'>".nl2br(htmlspecialchars($recommend_config))."</p>"
			."</div>";
		}
	}
}

function setting_log_query_debug_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option($setting_key, 'no');

	echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option, 'suffix' => __("This will hurt performance on the frontend so use this for debugging only and then turn off", 'lang_log')));

	if($option == 'yes')
	{
		if(!defined('SAVEQUERIES') || SAVEQUERIES != true)
		{
			$recommend_config = "define('SAVEQUERIES', true);";

			echo "<div class='mf_form'>"
				."<h3 class='display_warning'><i class='fa fa-warning yellow'></i> ".sprintf(__("Add this to the end of %s", 'lang_log'), "wp-config.php")."</h3>
				<p class='input'>".nl2br(htmlspecialchars($recommend_config))."</p>"
			."</div>";
		}
	}

	else
	{
		if(defined('SAVEQUERIES') && SAVEQUERIES == true)
		{
			echo "<p><i class='fa fa-warning yellow'></i>".sprintf(__("Remove %s from %s", 'lang_log'), "'SAVEQUERIES'", "wp-config.php")."</p>";
		}
	}
}

function setting_log_query_time_limit_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option($setting_key, .5);

	echo show_textfield(array('type' => 'number', 'name' => $setting_key, 'value' => $option, 'placeholder' => "0-10", 'xtra' => "min='0' max='10' step='0.1'", 'suffix' => __("s", 'lang_log'))); //, 'pattern' => "\d{1}(\.\d{0,4})?"
}

function setting_log_page_time_limit_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option($setting_key, 8);

	echo show_textfield(array('type' => 'number', 'name' => $setting_key, 'value' => $option, 'placeholder' => "0-10", 'xtra' => "min='0' max='10' step='0.1'", 'suffix' => __("s", 'lang_log'))); //, 'pattern' => "\d{1}(\.\d{0,4})?"
}

function setting_log_source_percent_limit_callback()
{
	$setting_key = get_setting_key(__FUNCTION__);
	$option = get_option($setting_key, 25);

	echo show_textfield(array('type' => 'number', 'name' => $setting_key, 'value' => $option, 'placeholder' => "10-100", 'xtra' => "min='10' max='100'", 'suffix' => "%"));
}

function get_count_log($id = 0)
{
	global $wpdb;

	$count_message = "";

	$last_viewed = get_user_meta(get_current_user_id(), 'meta_log_viewed', true);

	$result = $wpdb->get_results($wpdb->prepare("SELECT ID FROM ".$wpdb->posts." WHERE post_type = 'mf_log' AND post_status = 'publish' AND post_modified > %s", $last_viewed));
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

	add_menu_page($menu_title, $menu_title.$count_message, $menu_capability, $menu_start, '', 'dashicons-warning', 100);
}

function get_update_log($data = array())
{
	global $wpdb;

	if(!isset($data['cutoff'])){	$data['cutoff'] = date("Y-m-d H:i:s", strtotime("-2 minute"));} //"DATE_SUB(NOW(), INTERVAL 2 MINUTE)"

	if(IS_ADMIN)
	{
		$result = $wpdb->get_results($wpdb->prepare("SELECT ID, post_modified FROM ".$wpdb->posts." WHERE post_type = 'mf_log' AND post_status NOT IN ('trash', 'ignore') AND post_modified > %s", $data['cutoff']));
		$rows = $wpdb->num_rows;

		if($rows > 0)
		{
			return array(
				'title' => $rows > 1 ? sprintf(__("There are %d new errors in the log", 'lang_log'), $rows) : __("There is one new error in the log", 'lang_log'),
				'tag' => 'log',
				//'text' => "",
				//'icon' => "",
				'link' => admin_url("admin.php?page=mf_log/list/index.php"),
			);
		}
	}
}

function get_user_notifications_log($array)
{
	$update_log = get_update_log();

	if($update_log != '')
	{
		$array[] = $update_log;
	}

	return $array;
}

/*function get_user_reminders_log($array)
{
	$user_id = $array['user_id'];
	$reminder_cutoff = $array['cutoff'];

	do_log("get_user_reminders_log was run for ".$user_id." (".$reminder_cutoff.")");

	if(user_can($user_id, 'manage_options'))
	{
		$update_log = get_update_log(array('cutoff' => $reminder_cutoff));

		if($update_log != '')
		{
			$array['reminder'][] = $update_log;
		}
	}

	return $array;
}*/

function column_header_log($cols)
{
	unset($cols['registered']);

	$cols['log'] = __("Log", 'lang_log');

	return $cols;
}

function column_cell_log($col, $id)
{
	global $wpdb;

	switch($col)
	{
		case 'log':
			switch_to_blog($id);

			$tbl_group = new mf_log_table();

			$tbl_group->select_data(array(
				'select' => "ID",
				//'debug' => true,
			));

			$count_temp = count($tbl_group->data);

			if($count_temp > 0)
			{
				echo "<a href='".admin_url("admin.php?page=mf_log/list/index.php", $id)."'>".$count_temp."</a>";
			}

			restore_current_blog();
		break;
	}
}