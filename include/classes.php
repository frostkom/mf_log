<?php

class mf_log
{
	var $post_type = 'mf_log';
	var $ID = "";
	var $post_status = "";

	function __construct(){}

	function get_amount($data = [])
	{
		global $wpdb;

		if(!isset($data['post_status'])){	$data['post_status'] = 'publish';}

		return $wpdb->get_var($wpdb->prepare("SELECT COUNT(ID) FROM ".$wpdb->posts." WHERE post_type = %s AND post_status = %s", $this->post_type, $data['post_status']));
	}

	function get_log_file_dir($data = [])
	{
		if(!isset($data['type'])){	$data['type'] = '';}

		$out = ABSPATH."wp-content/debug.log";

		switch($data['type'])
		{
			case 'setting':
				$out = get_option_or_default('setting_log_custom_debug_file', $out);
			break;
		}

		return $out;
	}

	function cron_base()
	{
		global $wpdb;

		$obj_cron = new mf_cron();
		$obj_cron->start(__CLASS__);

		if($obj_cron->is_running == false)
		{
			mf_uninstall_plugin(array(
				'options' => array('setting_log_save_notifications', 'setting_log_curl_debug'),
			));

			// Trash old logs
			#################
			$result = $wpdb->get_results($wpdb->prepare("SELECT ID FROM ".$wpdb->posts." WHERE post_type = %s AND (
				post_status = %s AND post_modified < DATE_SUB(NOW(), INTERVAL 1 MONTH)
				OR post_status = %s AND post_modified < DATE_SUB(NOW(), INTERVAL 2 WEEK)
				OR post_status = %s AND post_modified < DATE_SUB(NOW(), INTERVAL 1 YEAR)
			) LIMIT 0, 1000", $this->post_type, 'publish', 'notification', 'ignore'));

			foreach($result as $r)
			{
				wp_trash_post($r->ID);
			}
			#################

			if(is_main_site())
			{
				$debug_file = $this->get_log_file_dir(array('type' => 'setting'));

				if(file_exists($debug_file))
				{
					$error_limit = (MB_IN_BYTES * 50);

					if(filesize($debug_file) < $error_limit)
					{
						$arr_file = file($debug_file);

						if(is_array($arr_file))
						{
							$arr_file = array_unique($arr_file);

							foreach($arr_file as $value)
							{
								if(preg_match("/\]/", $value))
								{
									list($date, $value) = explode("] ", $value, 2);
								}

								do_log($value);
							}
						}

						if(file_exists($debug_file))
						{
							unlink($debug_file);
						}
					}

					else if(file_exists($debug_file))
					{
						if(unlink($debug_file))
						{
							do_log(sprintf(__("%s was too big so it was deleted", 'lang_log'), basename($debug_file)));
						}

						else
						{
							do_log(sprintf(__("%s was too big so I tried to delete it, but I could not", 'lang_log'), basename($debug_file)));
						}
					}
				}

				else if(!touch($debug_file))
				{
					do_log(sprintf(__("%s is not writeable", 'lang_log'), basename($debug_file)));
				}
			}
		}

		$obj_cron->end();
	}

	function init()
	{
		load_plugin_textdomain('lang_log', false, str_replace("/include", "", dirname(plugin_basename(__FILE__))."/lang/"));

		// Post types
		#######################
		register_post_type($this->post_type, array(
			'labels' => array(
				'name' => __("Log", 'lang_log'),
				'singular_name' => __("Log", 'lang_log'),
				'menu_name' => __("Log", 'lang_log'),
				'all_items' => __('List', 'lang_log'),
				'edit_item' => __('Edit', 'lang_log'),
				'view_item' => __('View', 'lang_log'),
				'add_new_item' => __('Add New', 'lang_log'),
			),
			'public' => false,
			'supports' => array('title'),
			'hierarchical' => true,
			'has_archive' => false,
		));
		#######################
	}

	function combined_head()
	{
		if(get_option('setting_log_js_debug') == 'yes')
		{
			$plugin_include_url = plugin_dir_url(__FILE__);

			mf_enqueue_script('script_log', $plugin_include_url."script.js", array('ajax_url' => admin_url('admin-ajax.php')));
		}
	}

	function settings_log()
	{
		if(IS_SUPER_ADMIN)
		{
			$options_area = __FUNCTION__;

			add_settings_section($options_area, "", array($this, $options_area."_callback"), BASE_OPTIONS_PAGE);

			$arr_settings = array(
				'setting_log_activate' => __("Activate", 'lang_log'),
			);

			if(get_site_option('setting_log_activate', get_option('setting_log_activate')) == 'yes')
			{
				$arr_settings['setting_log_js_debug'] = sprintf(__("Debug %s", 'lang_log'), "Javascript");
				$arr_settings['setting_log_query_debug'] = __("Debug Database Queries", 'lang_log');

				if(get_option('setting_log_query_debug') == 'yes')
				{
					$arr_settings['setting_log_query_time_limit'] = __("Query Time Limit", 'lang_log');
					$arr_settings['setting_log_page_time_limit'] = __("Page Time Limit", 'lang_log');
					$arr_settings['setting_log_source_percent_limit'] = __("Slow Part Percent Limit", 'lang_log');
				}

				$arr_settings['setting_log_custom_debug_file'] = __("Custom Debug File", 'lang_log');
			}

			show_settings_fields(array('area' => $options_area, 'object' => $this, 'settings' => $arr_settings));
		}
	}

	function settings_log_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);

		echo settings_header($setting_key, __("Log and Debug", 'lang_log'));
	}

	function setting_log_activate_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		settings_save_site_wide($setting_key);
		$option = get_site_option($setting_key, get_option($setting_key, 'yes'));

		echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));

		$debug_file = $this->get_log_file_dir(array('type' => 'setting'));

		if($option == 'yes')
		{
			if(!defined('WP_DEBUG') || WP_DEBUG == false || !defined('WP_DEBUG_LOG') || WP_DEBUG_LOG == false || !defined('WP_DEBUG_DISPLAY'))
			{
				$recommend_config = "define('WP_DEBUG', true);
				define('WP_DEBUG_LOG', true);
				define('WP_DEBUG_DISPLAY', false);";
				// define('WP_DISABLE_FATAL_ERROR_HANDLER', true); // In case we don't want an e-mail to be sent on fatal error

				echo "<div class='mf_form'>"
					."<h3 class='display_warning'><i class='fa fa-exclamation-triangle yellow'></i> ".sprintf(__("Add this to the end of %s", 'lang_log'), "wp-config.php")."</h3>";

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

		else
		{
			if(defined('WP_DEBUG_LOG') && WP_DEBUG_LOG == true)
			{
				echo "<div class='mf_form'>"
					."<h3 class='display_warning'><i class='fa fa-exclamation-triangle yellow'></i> ".sprintf(__("Change settings in %s", 'lang_log'), "wp-config.php")."</h3>
					<p>".sprintf(__("Change %s to %s in %s or else you have to handle the content in %s so that it does not grow in size", 'lang_log'), "WP_DEBUG_LOG", "false", "wp-config.php", $debug_file)."</p>"
				."</div>";
			}
		}
	}

	function setting_log_js_debug_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		$option = get_option($setting_key, 'no');

		echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));
	}

	function setting_log_query_debug_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		$option = get_option($setting_key, 'no');

		list($option, $description) = setting_time_limit(array('key' => $setting_key, 'value' => $option, 'return' => 'array'));

		echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option, 'suffix' => __("This will hurt performance on the frontend so use this for debugging only and then turn off", 'lang_log'), 'description' => $description));

		if($option == 'yes')
		{
			if(!defined('SAVEQUERIES') || SAVEQUERIES != true)
			{
				$recommend_config = "define('SAVEQUERIES', true);";

				echo "<div class='mf_form'>"
					."<h3 class='display_warning'><i class='fa fa-exclamation-triangle yellow'></i> ".sprintf(__("Add this to the end of %s", 'lang_log'), "wp-config.php")."</h3>
					<p class='input'>".nl2br(htmlspecialchars($recommend_config))."</p>"
				."</div>";
			}
		}

		else
		{
			if(defined('SAVEQUERIES') && SAVEQUERIES == true)
			{
				echo "<p><i class='fa fa-exclamation-triangle yellow'></i>".sprintf(__("Remove %s from %s", 'lang_log'), "'SAVEQUERIES'", "wp-config.php")."</p>";
			}
		}
	}

	function setting_log_query_time_limit_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		$option = get_option($setting_key, .5);

		echo show_textfield(array('type' => 'number', 'name' => $setting_key, 'value' => $option, 'placeholder' => "0-10", 'xtra' => "min='0' max='10' step='0.1'", 'suffix' => __("s", 'lang_log')));
	}

	function setting_log_page_time_limit_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		$option = get_option($setting_key, 8);

		echo show_textfield(array('type' => 'number', 'name' => $setting_key, 'value' => $option, 'placeholder' => "0-10", 'xtra' => "min='0' max='10' step='0.1'", 'suffix' => __("s", 'lang_log')));
	}

	function setting_log_source_percent_limit_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		$option = get_option($setting_key, 25);

		echo show_textfield(array('type' => 'number', 'name' => $setting_key, 'value' => $option, 'placeholder' => "10-100", 'xtra' => "min='10' max='100'", 'suffix' => "%"));
	}

	function setting_log_custom_debug_file_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		$option = get_option($setting_key);

		$placeholder = $this->get_log_file_dir();

		echo show_textfield(array('name' => $setting_key, 'value' => $option, 'placeholder' => $placeholder));

		if($option == '')
		{
			$option = $placeholder;
		}

		if(!touch($option))
		{
			echo "<em>".sprintf(__("%s is not writeable", 'lang_log'), basename($option))."</em>";
		}

		/*if(file_exists($option))
		{
			if(!is_readable($option))
			{
				echo "<em><i class='fa fa-exclamation-triangle yellow'></i> ".__("The file is not readable", 'lang_log')."</em>";
			}
		}

		else
		{
			echo "<em><i class='fa fa-times red'></i> ".__("The file does not exist", 'lang_log')."</em>";
		}*/
	}

	function admin_init()
	{
		$this->combined_head();
	}

	function filter_sites_table_settings($arr_settings)
	{
		$arr_settings['settings_log'] = array(
			'setting_log_activate' => array(
				'type' => 'bool',
				'global' => true,
				'icon' => "fas fa-exclamation-triangle",
				'name' => __("Log", 'lang_log')." - ".__("Activate", 'lang_log'),
			),
		);

		return $arr_settings;
	}

	function get_count_message()
	{
		global $wpdb;

		$count_message = "";

		$last_viewed = date("Y-m-d H:i:s", strtotime(get_user_meta(get_current_user_id(), 'meta_log_viewed', true)));

		$result = $wpdb->get_results($wpdb->prepare("SELECT ID FROM ".$wpdb->posts." WHERE post_type = %s AND post_status = %s AND post_modified > %s", $this->post_type, 'publish', $last_viewed));
		$rows = $wpdb->num_rows;

		if($rows > 0)
		{
			$count_message = " <span class='update-plugins'><span>".$rows."</span></span>";
		}

		return $count_message;
	}

	function admin_menu()
	{
		global $menu;

		if(get_site_option('setting_log_activate', get_option('setting_log_activate')) == 'yes')
		{
			$menu_root = 'mf_log/';
			$menu_start = $menu_root.'list/index.php';
			$menu_capability = 'update_core';

			$count_message = $this->get_count_message();

			if($count_message != '' && is_array($menu))
			{
				foreach($menu as $key => $menu_item)
				{
					if(isset($menu_item[2]) && $menu_item[2] == 'tools.php')
					{
						if(!preg_match("/update-plugins/i", $menu[$key][0]))
						{
							$menu[$key][0] .= $count_message;
							break;
						}
					}
				}
			}

			$menu_title = __("Log", 'lang_log');
			add_submenu_page("tools.php", $menu_title, $menu_title.$count_message, $menu_capability, $menu_start);
		}
	}

	function get_update_log($data = [])
	{
		global $wpdb;

		if(!isset($data['cutoff'])){	$data['cutoff'] = date("Y-m-d H:i:s", strtotime("-2 minute"));}

		if(IS_ADMINISTRATOR)
		{
			$result = $wpdb->get_results($wpdb->prepare("SELECT ID, post_modified FROM ".$wpdb->posts." WHERE post_type = %s AND post_status = %s AND post_modified > %s", $this->post_type, 'publish', $data['cutoff']));
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

	function get_user_notifications($array)
	{
		$update_log = $this->get_update_log();

		if($update_log != '')
		{
			$array[] = $update_log;
		}

		return $array;
	}

	function column_header($columns)
	{
		unset($columns['registered']);

		if(get_site_option('setting_log_activate', get_option('setting_log_activate')) == 'yes')
		{
			$columns['log'] = __("Log", 'lang_log');
		}

		return $columns;
	}

	function column_cell($column, $post_id)
	{
		switch($column)
		{
			case 'log':
				if(get_blog_status($post_id, 'deleted') == 0 && get_blog_status($post_id, 'archived') == 0)
				{
					switch_to_blog($post_id);

					$arr_count = [];
					$count_total = 0;

					$arr_post_status = array(
						'publish' => __("Publish", 'lang_log'),
						'notification' => __("Notice", 'lang_log'),
						//'ignore' => __("Ignore", 'lang_log'),
						//'trash' => __("Trash", 'lang_log'),
					);

					foreach($arr_post_status as $post_status => $value)
					{
						$count_temp = $this->get_amount(array('post_status' => $post_status));

						$arr_count[$post_status] = $count_temp;
						$count_total += $count_temp;
					}

					$base_log_url = get_home_url($post_id, '/')."wp-admin/admin.php?page=mf_log/list/index.php";

					$i = 0;

					foreach($arr_post_status as $post_status => $value)
					{
						if(isset($arr_count[$post_status]) && $arr_count[$post_status] > 0 || $post_status == 'publish' && $count_total > 0)
						{
							echo ($i > 0 ? "/" : "")."<a href='".$base_log_url."&post_status=".$post_status."' title='".$value."'".($arr_count[$post_status] > 0 ? "" : " class='grey'").">".$arr_count[$post_status]."</a>";

							$i++;
						}
					}

					restore_current_blog();
				}
			break;
		}
	}

	function wp_head()
	{
		$this->combined_head();
	}

	function login_init()
	{
		$this->combined_head();
	}

	function api_log_js_debug()
	{
		$json_output = [];

		$url = check_var('url');
		$msg = check_var('msg');
		$lineNo = check_var('lineNo');
		$columnNo = check_var('columnNo');

		if($url != '')
		{
			do_log(sprintf("%s in %s on line %d:%d", $msg, $url, $lineNo, $columnNo));

			$json_output['success'] = true;
		}

		else
		{
			$json_output['success'] = false;
		}

		header('Content-Type: application/json');
		echo json_encode($json_output);
		die();
	}

	function fetch_request()
	{
		$this->ID = check_var('intLogID');
		$this->post_status = check_var('post_status', 'char');
	}

	function row_affect($data)
	{
		global $wpdb, $done_text, $error_text;

		$data['id'] = check_var($data['id'], 'int', false);

		if($data['id'] > 0)
		{
			switch($data['type'])
			{
				case 'trash':
					if(wp_trash_post($data['id']))
					{
						$done_text = __("The information was trashed", 'lang_log');
					}
				break;

				case 'restore':
					if(wp_untrash_post($data['id']))
					{
						$done_text = __("The information was restored", 'lang_log');
					}
				break;

				case 'delete':
					if(wp_delete_post($data['id']))
					{
						$done_text = __("The information was deleted", 'lang_log');
					}
				break;

				case 'ignore':
					$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->posts." SET post_status = %s, post_modified = NOW() WHERE post_type = %s AND ID = '%d'", 'ignore', $this->post_type, $data['id']));

					if($wpdb->rows_affected > 0)
					{
						$done_text = __("The information is being ignored from now on", 'lang_log');
					}
				break;
			}

			/*if(IS_SUPER_ADMIN && is_multisite())
			{
				$this->multisite_affect($data);
			}*/
		}
	}

	/*function multisite_affect($data)
	{
		global $wpdb;

		$post_content = mf_get_post_content($data['id']);

		if($post_content != '')
		{
			$result = get_sites(array('site__not_in' => array($wpdb->blogid), 'deleted' => 0));

			foreach($result as $r)
			{
				switch_to_blog($r->blog_id);

				$post_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM ".$wpdb->posts." WHERE post_type = %s AND post_content = %s", $this->post_type, $post_content));

				switch($data['type'])
				{
					case 'delete':
						wp_trash_post($post_id);
					break;

					case 'ignore':
						$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->posts." SET post_status = %s, post_modified = NOW() WHERE post_type = %s AND ID = '%d'", 'ignore', $this->post_type, $post_id));
					break;
				}

				restore_current_blog();
			}
		}
	}*/

	function save_data()
	{
		global $wpdb, $done_text, $error_text;

		if(isset($_REQUEST['btnLogTrashAll']) && wp_verify_nonce($_REQUEST['_wpnonce_log_trash_all'], 'log_trash_all'))
		{
			$obj_microtime = new mf_microtime();

			$i = 0;

			$result = $wpdb->get_results($wpdb->prepare("SELECT ID FROM ".$wpdb->posts." WHERE post_type = %s AND post_status".($this->post_status != '' ? " = '".esc_sql($this->post_status)."'" : " IN ('publish', 'draft')")." ORDER BY post_date ASC", $this->post_type));

			foreach($result as $r)
			{
				wp_trash_post($r->ID);

				$i++;

				if($i % 20 == 0)
				{
					$time_limit = 120;

					if($obj_microtime->check_time($time_limit))
					{
						$error_text = sprintf(__("I could only delete %d within %d seconds", 'lang_log'), $i, $time_limit);

						break;
					}

					sleep(1);
					set_time_limit(60);
				}
			}

			if($error_text == '')
			{
				$done_text = __("I deleted them all for you", 'lang_log');
			}
		}

		else if(isset($_REQUEST['btnLogTrash']) && $this->ID > 0 && wp_verify_nonce($_REQUEST['_wpnonce_log_trash'], 'log_trash_'.$this->ID))
		{
			$this->row_affect(array('type' => 'trash', 'id' => $this->ID));
		}

		else if(isset($_REQUEST['btnLogIgnore']) && $this->ID > 0 && wp_verify_nonce($_REQUEST['_wpnonce_log_ignore'], 'log_ignore_'.$this->ID))
		{
			$this->row_affect(array('type' => 'ignore', 'id' => $this->ID));
		}
	}

	function use_filter($string)
	{
		$string = trim($string);

		$arr_ignore = array(
			"/^(thrown in)/",
			"/^(\#[0-9]+\s)/",
			"/^(Stack trace\:)/",
			"/^((o)*n line [0-9]+)$/",
			"/^([0-9]+)$/",
			"/^(maybe_log_events_response)/",
			"/^(spam \= \'0\' AND deleted \= \'0\')/",
			"/^(auditor\:)/",
			"/(A0001 NO \[UNAVAILABLE\] Temporary authentication failure)/",
			"/Authenticated requests get a higher rate limit/",
			"/data\: \{\"schedule\"\:\"/",
			"/Implicit conversion from float/",
			"/".__("Error deleting scheduled event for action hook", 'lang_log')."/",
			"/_yoast_indexable/",
			"/Automatic updates starting.../",
			"/Automatic plugin updates starting.../",
			"/Automatic plugin updates complete./",
			"/Automatic theme updates starting.../",
			"/Automatic theme updates complete./",
			"/Automatic updates complete./",
		);

		foreach($arr_ignore as $regexp)
		{
			if(preg_match($regexp, $string))
			{
				$string = '';

				break;
			}
		}

		/*if($string != '')
		{
			$arr_exclude = array(
				ABSPATH,
			);

			$string = str_replace($arr_exclude, "", $string);
		}*/

		return $string;
	}

	function create($post_title, $action = 'publish', $increment = true)
	{
		global $wpdb;

		$post_title = $this->use_filter($post_title);

		if($post_title != '')
		{
			$post_md5 = md5($post_title);

			switch($action)
			{
				case 'draft':
				case 'notification':
				case 'publish':
					$result = $wpdb->get_results($wpdb->prepare("SELECT ID, post_status, menu_order, post_modified FROM ".$wpdb->posts." WHERE post_type = %s AND (post_title = %s OR post_content = %s)", $this->post_type, $post_title, $post_md5));

					if($wpdb->num_rows > 0)
					{
						$i = 0;

						foreach($result as $r)
						{
							$post_id = $r->ID;
							$post_status = $r->post_status;
							$menu_order = $r->menu_order;
							$post_modified = $r->post_modified;

							if($post_status != 'ignore')
							{
								if($i == 0)
								{
									if($increment == true)
									{
										$post_data = array(
											'ID' => $post_id,
											'post_status' => $action,
											'post_type' => $this->post_type,
											'post_title' => $post_title,
											'post_content' => $post_md5,
											'menu_order' => ($menu_order == 0 ? 2 : ++$menu_order),
										);

										wp_update_post($post_data);
									}
								}

								else
								{
									wp_trash_post($post_id);
								}
							}

							$i++;
						}
					}

					else
					{
						$post_data = array(
							'post_status' => $action,
							'post_type' => $this->post_type,
							'post_title' => $post_title,
							'post_content' => $post_md5,
						);

						wp_insert_post($post_data);
					}
				break;

				case 'trash':
					$result = $wpdb->get_results($wpdb->prepare("SELECT ID FROM ".$wpdb->posts." WHERE post_type = %s AND post_status NOT IN ('trash', 'ignore') AND (post_title LIKE %s OR post_content = %s)", $this->post_type, $post_title."%", $post_md5));

					foreach($result as $r)
					{
						$post_id = $r->ID;

						wp_trash_post($post_id);
					}
				break;
			}
		}
	}
}

if(class_exists('mf_list_table'))
{
	class mf_log_table extends mf_list_table
	{
		function set_default()
		{
			global $obj_log;

			$this->post_type = $obj_log->post_type; // This has to be here and can not be simplified

			$this->orderby_default = "post_modified";
			$this->orderby_default_order = "DESC";

			$this->arr_settings['query_trash_id'] = array('notification', 'ignore', 'trash');
		}

		function init_fetch()
		{
			global $wpdb, $obj_log;

			if($this->search != '')
			{
				$this->query_where .= ($this->query_where != '' ? " AND " : "")."(post_title LIKE '".$this->filter_search_before_like($this->search)."' OR SOUNDEX(post_title) = SOUNDEX('".$this->search."'))";
			}

			$this->set_views(array(
				'db_field' => 'post_status',
				'types' => array(
					'all' => __("All", 'lang_log'),
					'notification' => __("Notice", 'lang_log'),
					'ignore' => __("Ignore", 'lang_log'),
					'trash' => __("Trash", 'lang_log'),
				),
			));

			$this->set_columns(array(
				'cb' => '<input type="checkbox">',
				'post_title' => __("Name", 'lang_log'),
				'menu_order' => __("Amount", 'lang_log'),
				'post_modified' => __("Date", 'lang_log'),
			));

			$this->set_sortable_columns(array(
				'post_title',
				'menu_order',
				'post_modified',
			));
		}

		function get_bulk_actions()
		{
			global $obj_log;

			$arr_actions = [];

			if(isset($this->columns['cb']))
			{
				$post_status = check_var('post_status');

				if($post_status == 'trash')
				{
					$arr_actions['restore'] = __("Restore", 'lang_log');
					$arr_actions['delete'] = __("Permanently Delete", 'lang_log');
				}

				else
				{
					$arr_actions['trash'] = __("Delete", 'lang_log');
				}

				if($post_status != 'ignore')
				{
					$arr_actions['ignore'] = __("Ignore", 'lang_log');
				}
			}

			return $arr_actions;
		}

		function process_bulk_action()
		{
			if(isset($_GET['_wpnonce']) && !empty($_GET['_wpnonce']))
			{
				switch($this->current_action())
				{
					case 'trash':
						$this->bulk_trash();
					break;

					case 'restore':
						$this->bulk_restore();
					break;

					case 'delete':
						$this->bulk_delete();
					break;

					case 'ignore':
						$this->bulk_ignore();
					break;
				}
			}
		}

		function bulk_trash()
		{
			global $obj_log;

			if(isset($_GET[$obj_log->post_type]))
			{
				foreach($_GET[$obj_log->post_type] as $post_id)
				{
					$obj_log->row_affect(array('type' => 'trash', 'id' => $post_id));
				}
			}
		}

		function bulk_restore()
		{
			global $obj_log;

			if(isset($_GET[$obj_log->post_type]))
			{
				foreach($_GET[$obj_log->post_type] as $post_id)
				{
					$obj_log->row_affect(array('type' => 'restore', 'id' => $post_id));
				}
			}
		}

		function bulk_delete()
		{
			global $obj_log;

			if(isset($_GET[$obj_log->post_type]))
			{
				foreach($_GET[$obj_log->post_type] as $post_id)
				{
					$obj_log->row_affect(array('type' => 'delete', 'id' => $post_id));
				}
			}
		}

		function bulk_ignore()
		{
			global $obj_log;

			if(isset($_GET[$obj_log->post_type]))
			{
				foreach($_GET[$obj_log->post_type] as $post_id)
				{
					$obj_log->row_affect(array('type' => 'ignore', 'id' => $post_id));
				}
			}
		}

		function column_default($item, $column_name)
		{
			global $obj_log;

			$out = "";

			switch($column_name)
			{
				case 'post_title':
					$post_id = $item['ID'];
					$post_status = $item['post_status'];
					$post_author = $item['post_author'];

					$arr_actions = [];

					if($post_author == get_current_user_id() || IS_ADMINISTRATOR)
					{
						$list_url = admin_url("admin.php?page=mf_log/list/index.php&intLogID=".$post_id."&post_status=".check_var('post_status'));

						if($post_status != "trash")
						{
							$arr_actions['delete'] = "<a href='".wp_nonce_url($list_url."&btnLogTrash", 'log_trash_'.$post_id, '_wpnonce_log_trash')."'>".__("Delete", 'lang_log')."</a>";
						}

						if($post_status != "ignore")
						{
							$arr_actions['ignore'] = "<a href='".wp_nonce_url($list_url."&btnLogIgnore", 'log_ignore_'.$post_id, '_wpnonce_log_ignore')."'".make_link_confirm().">".__("Ignore", 'lang_log')."</a>";
						}
					}

					$arr_exclude = $arr_include = [];
					$arr_exclude[] = "PHP Deprecated:";		$arr_include[] = "<i class='fa fa-exclamation-triangle yellow' title='".__("Deprecated", 'lang_log')."'></i>";
					$arr_exclude[] = "PHP Notice:";			$arr_include[] = "<i class='fa fa-exclamation-triangle yellow' title='".__("Notice", 'lang_log')."'></i>";
					$arr_exclude[] = "PHP Warning:";		$arr_include[] = "<i class='fa fa-exclamation-triangle yellow' title='".__("Warning", 'lang_log')."'></i>";
					$arr_exclude[] = "PHP Fatal error:";	$arr_include[] = "<i class='fa fa-times red' title='".__("Fatal Error", 'lang_log')."'></i>";
					$arr_exclude[] = "Git Updater Error:";	$arr_include[] = "<i class='fa fa-exclamation-triangle yellow' title='".__("Git Error", 'lang_log')."'></i>";

					$item['post_title'] = str_replace($arr_exclude, $arr_include, $item['post_title']);

					$out .= $item['post_title']
					.$this->row_actions($arr_actions);
				break;

				case 'menu_order':
					$out .= ($item['menu_order'] > 1 ? $item['menu_order'] : "");
				break;

				default:
					if(isset($item[$column_name]))
					{
						$out .= $item[$column_name];
					}
				break;
			}

			return $out;
		}
	}
}

if(!class_exists('Debug_Queries'))
{
	class Debug_Queries
	{
		function __construct()
		{
			add_action('shutdown', array($this, 'the_queries'));
		}

		function get_queries()
		{
			global $wpdb, $obj_base, $obj_log;

			$debug_queries = "";
			$count_queries = count($wpdb->queries);

			if($count_queries > 0)
			{
				$check_duplicates = true;
				$check_source = true;

				$query_time_limit = get_option('setting_log_query_time_limit', .5);
				$page_time_limit = get_option('setting_log_page_time_limit', 8);
				$source_percent_limit = get_option('setting_log_source_percent_limit', 25);

				$arr_duplicates = [];
				$arr_sources = array(
					'total_time' => 0,
					'core' => 0,
					'plugins' => [],
					'themes' => [],
				);

				//do_log("All queries: ".var_export($wpdb->queries, true));

				$total_query_time = 0;

				foreach($wpdb->queries as $q)
				{
					$query_text = htmlentities(trim(preg_replace('/[[:space:]]+/', ' ', $q[0])));
					$query_time = mf_format_number($q[1], 6); //number_format_i18n
					$query_called = htmlentities($q[2]);

					$total_query_time += $query_time;

					if($query_time_limit > 0)
					{
						if($query_time > $query_time_limit && substr($query_text, 0, 6) == "SELECT")
						{
							do_log("Debug Query: ".$query_time."s, ".$query_text." (".$query_called.")");
						}

						if($check_duplicates)
						{
							$md5_temp = md5($query_text);

							if(isset($arr_duplicates[$md5_temp]))
							{
								$arr_duplicates[$md5_temp]['count']++;
								$arr_duplicates[$md5_temp]['total_time'] = $arr_duplicates[$md5_temp]['count'] * $arr_duplicates[$md5_temp]['time'];
							}

							else
							{
								$arr_duplicates[$md5_temp] = array('query' => $query_text, 'time' => $query_time, 'count' => 1, 'total_time' => $query_time);
							}
						}

						if($check_source)
						{
							$arr_sources['total_time'] += $query_time;

							$plugin_name = get_match("/plugins\/(.*?)\//is", $query_called, false);

							if($plugin_name != '')
							{
								if(isset($arr_sources['plugins'][$plugin_name]))
								{
									$arr_sources['plugins'][$plugin_name] += $query_time;
								}

								else
								{
									$arr_sources['plugins'][$plugin_name] = $query_time;
								}
							}

							else
							{
								$theme_name = get_match("/themes\/(.*?)\//is", $query_called, false);

								if($theme_name != '')
								{
									if(isset($arr_sources['themes'][$theme_name]))
									{
										$arr_sources['themes'][$theme_name] += $query_time;
									}

									else
									{
										$arr_sources['themes'][$theme_name] = $query_time;
									}
								}

								else
								{
									$arr_sources['core'] += $query_time;
								}
							}
						}
					}
				}

				if($query_time_limit > 0)
				{
					if($check_duplicates)
					{
						if(!isset($obj_base))
						{
							$obj_base = new mf_base();
						}

						$arr_duplicates = $obj_base->array_sort(array('array' => $arr_duplicates, 'on' => 'total_time', 'order' => 'desc'));

						$count_temp = count($arr_duplicates);

						$i = 0;

						while($i < $count_temp && $i < 5)
						{
							if($arr_duplicates[$i]['count'] > 1 && $arr_duplicates[$i]['total_time'] > $query_time_limit)
							{
								do_log("Duplicate Query: ".$arr_duplicates[$i]['count']." x ".$arr_duplicates[$i]['time']."s (".$arr_duplicates[$i]['query'].")");
							}

							$i++;
						}
					}

					if($check_source)
					{
						foreach($arr_sources as $key => $source)
						{
							switch($key)
							{
								case 'total_time':
									$total_time = $source;
								break;

								case 'core':
									/*$percent = round(($source / $total_time) * 100);

									if($percent > $source_percent_limit && $total_time > $query_time_limit)
									{
										do_log("Slow Part - ".$key.": ".$percent."% of ".mf_format_number($total_time)."s");
									}*/
								break;

								case 'plugins':
								case 'themes':
									if(!isset($obj_log))
									{
										$obj_log = new mf_log();
									}

									foreach($source as $folder => $time)
									{
										if(!in_array($folder, array($obj_log->post_type))) // This will naturally be a performance drain when activated to debug
										{
											$percent = round(($time / $total_time) * 100);

											if($percent > $source_percent_limit && $total_time > $query_time_limit)
											{
												do_log("Slow Part - ".$key." - ".$folder.": ".$percent."% of ".mf_format_number($total_time)."s"); //." (".var_export($wpdb->queries, true).")"
											}
										}
									}
								break;
							}
						}
					}
				}

				if($page_time_limit > 0)
				{
					//$php_time = $total_page_time - $total_query_time;

					//$mysqlper = $total_page_time > 0 ? mf_format_number($total_query_time / $total_page_time * 100, 2) : 0;
					//$phpper = $total_page_time > 0 ? mf_format_number($php_time / $total_page_time * 100, 2) : 0;

					$total_timer_time = timer_stop();

					if($total_query_time > $page_time_limit || $total_timer_time > $page_time_limit)
					{
						do_log("Debug Page: ".mf_format_number($total_query_time)."s (MySQL - ".$count_queries."), ".$total_timer_time."s (Total - ".$_SERVER['REQUEST_URI'].")");
					}
				}
			}

			return $debug_queries;
		}

		function the_queries()
		{
			$debug_output = $this->get_queries();
		}
	}
}