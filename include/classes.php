<?php

class mf_log
{
	function __contruct(){}

	function cron_base()
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
			post_status = 'auto-draft' AND post_modified < DATE_SUB(NOW(), INTERVAL 6 MONTH)
			OR post_status NOT IN ('trash', 'ignore') AND post_modified < DATE_SUB(NOW(), INTERVAL 1 MONTH)
			OR post_status = 'ignore' AND post_modified < DATE_SUB(NOW(), INTERVAL 1 YEAR)
		)");

		foreach($result as $r)
		{
			wp_trash_post($r->ID);
		}
	}

	function init()
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

	function combined_head()
	{
		if(get_option('setting_log_js_debug') == 'yes')
		{
			$plugin_include_url = plugin_dir_url(__FILE__);
			$plugin_version = get_plugin_version(__FILE__);

			mf_enqueue_script('script_log', $plugin_include_url."script.js", array('ajax_url' => admin_url('admin-ajax.php')), $plugin_version, false);
		}
	}

	function admin_init()
	{
		$this->combined_head();
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

			if(get_option('setting_log_activate') == 'yes')
			{
				$arr_settings['setting_log_curl_debug'] = __("Debug cURL", 'lang_log');
				$arr_settings['setting_log_js_debug'] = __("Debug Javascript", 'lang_log');
				$arr_settings['setting_log_query_debug'] = __("Debug Database Queries", 'lang_log');

				if(get_option('setting_log_query_debug') == 'yes')
				{
					$arr_settings['setting_log_query_time_limit'] = __("Query Time Limit", 'lang_log');
					$arr_settings['setting_log_page_time_limit'] = __("Page Time Limit", 'lang_log');
					$arr_settings['setting_log_source_percent_limit'] = __("Slow Part Percent Limit", 'lang_log');
				}
			}

			show_settings_fields(array('area' => $options_area, 'object' => $this, 'settings' => $arr_settings));
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

		$debug_file = ABSPATH."wp-content/debug.log";

		if($option == 'yes')
		{
			if(!defined('WP_DEBUG') || WP_DEBUG == false || !defined('WP_DEBUG_LOG') || WP_DEBUG_LOG == false || !defined('WP_DEBUG_DISPLAY'))
			{
				$recommend_config = "define('WP_DEBUG', true);
				define('WP_DEBUG_LOG', true);
				define('WP_DEBUG_DISPLAY', false);";

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
					<p>".sprintf(__("Change %s to %s in %s or else you have to handle the content in %s so that it doesn't grow in size", 'lang_log'), "WP_DEBUG_LOG", "false", "wp-config.php", $debug_file)."</p>"
				."</div>";
			}
		}
	}

	function setting_log_curl_debug_callback()
	{
		$setting_key = get_setting_key(__FUNCTION__);
		$option = get_option($setting_key, 'no');

		echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option));
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

		echo show_select(array('data' => get_yes_no_for_select(), 'name' => $setting_key, 'value' => $option, 'suffix' => __("This will hurt performance on the frontend so use this for debugging only and then turn off", 'lang_log')));

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

	function admin_menu()
	{
		if(get_option('setting_log_activate') == 'yes')
		{
			$menu_root = 'mf_log/';
			$menu_start = $menu_root.'list/index.php';
			$menu_capability = override_capability(array('page' => $menu_start, 'default' => 'update_core'));

			$menu_title = __("Log", 'lang_log');
			add_menu_page($menu_title, $menu_title.$this->get_count_log(), $menu_capability, $menu_start, '', 'dashicons-warning', 100);
		}
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

	function get_user_notifications($array)
	{
		$update_log = $this->get_update_log();

		if($update_log != '')
		{
			$array[] = $update_log;
		}

		return $array;
	}

	/*function get_user_reminders($array)
	{
		$user_id = $array['user_id'];
		$reminder_cutoff = $array['cutoff'];

		do_log("get_user_reminders_log was run for ".$user_id." (".$reminder_cutoff.")");

		if(user_can($user_id, 'manage_options'))
		{
			$update_log = $this->get_update_log(array('cutoff' => $reminder_cutoff));

			if($update_log != '')
			{
				$array['reminder'][] = $update_log;
			}
		}

		return $array;
	}*/

	function column_header($cols)
	{
		unset($cols['registered']);

		$cols['log'] = __("Log", 'lang_log');

		return $cols;
	}

	function column_cell($col, $id)
	{
		global $wpdb;

		switch($col)
		{
			case 'log':
				if(get_blog_status($id, 'deleted') == 0)
				{
					switch_to_blog($id);

					$tbl_group = new mf_log_table();

					$tbl_group->select_data(array(
						'select' => "ID",
						//'debug' => true,
					));

					$count_temp = count($tbl_group->data);

					if($count_temp > 0)
					{
						echo "<a href='".get_home_url($id, '/')."wp-admin/admin.php?page=mf_log/list/index.php'>".$count_temp."</a>";
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

	function send_js_debug()
	{
		$url = check_var('url');
		$msg = check_var('msg');
		$lineNo = check_var('lineNo');
		$columnNo = check_var('columnNo');

		if($url != '')
		{
			do_log(sprintf(__("%s in %s on line %d:%d", 'lang_log'), $msg, $url, $lineNo, $columnNo));

			$result['success'] = true;
		}

		else
		{
			$result['success'] = false;
		}

		header('Content-Type: application/json');
		echo json_encode($result);
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
				case 'delete':
					if(wp_trash_post($data['id']))
					{
						$done_text = __("The information was deleted", 'lang_log');
					}
				break;

				case 'ignore':
					$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->posts." SET post_status = %s, post_modified = NOW() WHERE post_type = %s AND ID = '%d'", 'ignore', 'mf_log', $data['id']));

					if($wpdb->rows_affected > 0)
					{
						$done_text = __("The information is being ignored from now on", 'lang_log');
					}
				break;
			}

			if(IS_SUPER_ADMIN && is_multisite())
			{
				$this->multisite_affect($data);
			}
		}
	}

	function multisite_affect($data)
	{
		global $wpdb;

		$post_content = mf_get_post_content($data['id']);

		if($post_content != '')
		{
			$result = get_sites(array('site__not_in' => array($wpdb->blogid)));

			foreach($result as $r)
			{
				switch_to_blog($r->blog_id);

				$post_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM ".$wpdb->posts." WHERE post_type = %s AND post_content = %s", 'mf_log', $post_content));

				switch($data['type'])
				{
					case 'delete':
						wp_trash_post($post_id);
					break;

					case 'ignore':
						$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->posts." SET post_status = %s, post_modified = NOW() WHERE post_type = %s AND ID = '%d'", 'ignore', 'mf_log', $post_id));
					break;
				}

				restore_current_blog();
			}
		}
	}

	function save_data()
	{
		global $wpdb, $done_text, $error_text;

		if(isset($_REQUEST['btnLogDeleteAll']) && wp_verify_nonce($_REQUEST['_wpnonce_log_delete_all'], 'log_delete_all'))
		{
			$obj_microtime = new mf_microtime();

			$i = 0;

			$result = $wpdb->get_results("SELECT ID FROM ".$wpdb->posts." WHERE post_type = 'mf_log' AND post_status".($this->post_status != '' ? " = '".esc_sql($this->post_status)."'" : " IN ('publish', 'draft')")." ORDER BY post_date ASC");

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

					sleep(0.1);
					set_time_limit(60);
				}
			}

			if($error_text == '')
			{
				$done_text = __("I deleted them all for you", 'lang_log');
			}
		}

		else if(isset($_REQUEST['btnLogDelete']) && $this->ID > 0 && wp_verify_nonce($_REQUEST['_wpnonce_log_delete'], 'log_delete_'.$this->ID))
		{
			$this->row_affect(array('type' => 'delete', 'id' => $this->ID));
		}

		else if(isset($_REQUEST['btnLogIgnore']) && $this->ID > 0 && wp_verify_nonce($_REQUEST['_wpnonce_log_ignore'], 'log_ignore_'.$this->ID))
		{
			$this->row_affect(array('type' => 'ignore', 'id' => $this->ID));
		}
	}

	function filter($string)
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
		);

		foreach($arr_ignore as $regexp)
		{
			if(preg_match($regexp, $string))
			{
				$string = '';

				break;
			}
		}

		if($string != '')
		{
			$arr_exclude = array(
				ABSPATH,
			);

			$string = str_replace($arr_exclude, "", $string);
		}

		return $string;
	}

	function create($post_title, $action = 'publish')
	{
		global $wpdb;

		$post_title = $this->filter($post_title);

		if($post_title != '')
		{
			$post_md5 = md5($post_title);

			if(in_array($action, array('publish', 'draft', 'auto-draft')))
			{
				$result = $wpdb->get_results($wpdb->prepare("SELECT ID, post_status, menu_order, post_modified FROM ".$wpdb->posts." WHERE post_type = 'mf_log' AND (post_title = %s OR post_content = %s)", $post_title, $post_md5));

				if($wpdb->num_rows > 0)
				{
					$i = 0;

					foreach($result as $r)
					{
						$post_id = $r->ID;
						$post_status = $r->post_status;
						$menu_order = $r->menu_order;
						$post_modified = $r->post_modified;

						if($post_status != 'ignore' && $post_modified < date("Y-m-d H:i:s", strtotime("-60 minute")))
						{
							if($i == 0)
							{
								$post_data = array(
									'ID' => $post_id,
									'post_status' => $action,
									'post_type' => 'mf_log',
									'post_title' => $post_title,
									'post_content' => $post_md5,
									'menu_order' => ($menu_order == 0 ? 2 : ++$menu_order),
								);

								wp_update_post($post_data);
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
						'post_type' => 'mf_log',
						'post_title' => $post_title,
						'post_content' => $post_md5,
					);

					wp_insert_post($post_data);
				}
			}

			else if($action == 'trash')
			{
				$result = $wpdb->get_results("SELECT ID FROM ".$wpdb->posts." WHERE post_type = 'mf_log' AND post_status != 'trash' AND post_status != 'ignore' AND (post_title LIKE '".esc_sql($post_title)."%' OR post_content = '".esc_sql($post_md5)."')");

				foreach($result as $r)
				{
					$post_id = $r->ID;

					wp_trash_post($post_id);
				}
			}
		}
	}
}

class mf_log_table extends mf_list_table
{
	function set_default()
	{
		$this->post_type = "mf_log";

		$this->orderby_default = "post_modified";
		$this->orderby_default_order = "DESC";

		$this->arr_settings['query_trash_id'] = array('trash', 'ignore', 'auto-draft');

		//$this->arr_settings['has_autocomplete'] = true;
		//$this->arr_settings['plugin_name'] = 'mf_log';

		if($this->search != '')
		{
			$this->query_where .= ($this->query_where != '' ? " AND " : "")."(post_title LIKE '%".$this->search."%')";
		}

		$this->set_views(array(
			'db_field' => 'post_status',
			'types' => array(
				'all' => __("All", 'lang_log'),
				'auto-draft' => __("Notice", 'lang_log'),
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
		$actions = array();

		if(isset($this->columns['cb']))
		{
			if(!isset($_GET['post_status']) || $_GET['post_status'] != 'trash')
			{
				$actions['delete'] = __("Delete", 'lang_log');
			}

			if(!isset($_GET['post_status']) || $_GET['post_status'] != 'ignore')
			{
				$actions['ignore'] = __("Ignore", 'lang_log');
			}
		}

		return $actions;
	}

	function process_bulk_action()
	{
		if(isset($_GET['_wpnonce']) && !empty($_GET['_wpnonce']))
		{
			switch($this->current_action())
			{
				case 'delete':
					$this->bulk_delete();
				break;

				case 'ignore':
					$this->bulk_ignore();
				break;
			}
		}
	}

	function bulk_delete()
	{
		if(isset($_GET[$this->post_type]))
		{
			$obj_log = new mf_log();

			foreach($_GET[$this->post_type] as $post_id)
			{
				//$post_id = check_var($post_id, 'int', false);
				//wp_trash_post($post_id);
				$obj_log->row_affect(array('type' => 'delete', 'id' => $post_id));
			}
		}
	}

	function bulk_ignore()
	{
		global $wpdb;

		if(isset($_GET[$this->post_type]))
		{
			$obj_log = new mf_log();

			foreach($_GET[$this->post_type] as $post_id)
			{
				//$post_id = check_var($post_id, 'int', false);
				//$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->posts." SET post_status = 'ignore', post_modified = NOW() WHERE post_type = 'mf_log' AND ID = '%d'", $id));
				$obj_log->row_affect(array('type' => 'delete', 'id' => $post_id));
			}
		}
	}

	function column_default($item, $column_name)
	{
		global $wpdb;

		$out = "";

		switch($column_name)
		{
			case 'post_title':
				$post_id = $item['ID'];
				$post_status = $item['post_status'];
				$post_author = $item['post_author'];

				$actions = array();

				if($post_status != "trash")
				{
					if($post_author == get_current_user_id() || IS_ADMIN)
					{
						$actions['delete'] = "<a href='".wp_nonce_url(admin_url("admin.php?page=mf_log/list/index.php&btnLogDelete&intLogID=".$post_id), 'log_delete_'.$post_id, '_wpnonce_log_delete')."'>".__("Delete", 'lang_log')."</a>";
					}
				}

				if($post_status != "ignore")
				{
					if($post_author == get_current_user_id() || IS_ADMIN)
					{
						$actions['ignore'] = "<a href='".wp_nonce_url(admin_url("admin.php?page=mf_log/list/index.php&btnLogIgnore&intLogID=".$post_id), 'log_ignore_'.$post_id, '_wpnonce_log_ignore')."' rel='confirm'>".__("Ignore", 'lang_log')."</a>";
					}
				}

				/*else
				{
					$actions['recover'] = "<a href='admin_url("admin.php?page=mf_log/list/index.php&intLogID=".$post_id."&recover)."'>".__("Recover", 'lang_log')."</a>";
				}*/

				$out .= $item[$column_name]
				.$this->row_actions($actions);
			break;

			case 'menu_order':
				$out .= ($item[$column_name] > 1 ? $item[$column_name] : "");
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

if(!class_exists('Debug_Queries'))
{
	class Debug_Queries
	{
		function __construct()
		{
			add_action('shutdown', array($this, 'the_queries'));
		}

		// core
		function get_queries()
		{
			global $wpdb;

			$debug_queries = "";
			$count_queries = count($wpdb->queries);

			if($count_queries > 0)
			{
				$check_duplicates = true;
				$check_source = true;

				$query_time_limit = get_option('setting_log_query_time_limit', .5);
				$page_time_limit = get_option('setting_log_page_time_limit', 8);
				$source_percent_limit = get_option('setting_log_source_percent_limit', 25);

				$arr_duplicates = array();
				$arr_sources = array(
					'total_time' => 0,
					'core' => 0,
					'plugins' => array(),
					'themes' => array(),
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
							do_log(__("Debug Query", 'lang_log').": ".$query_time.__("s", 'lang_log').", ".$query_text." (".$query_called.")");
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
						$arr_duplicates = array_sort(array('array' => $arr_duplicates, 'on' => 'total_time', 'order' => 'desc'));

						//do_log("Duplicates: ".var_export($arr_duplicates, true));

						$count_temp = count($arr_duplicates);

						$i = 0;

						while($i < $count_temp && $i < 5)
						{
							if($arr_duplicates[$i]['count'] > 1 && $arr_duplicates[$i]['total_time'] > $query_time_limit)
							{
								do_log(__("Duplicate Query", 'lang_log').": ".$arr_duplicates[$i]['count']." x ".$arr_duplicates[$i]['time'].__("s", 'lang_log')." (".$arr_duplicates[$i]['query'].")");
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
										do_log(__("Slow Part", 'lang_log')." - ".$key.": ".$percent."% of ".mf_format_number($total_time).__("s", 'lang_log'));
									}*/
								break;

								case 'plugins':
								case 'themes':
									foreach($source as $folder => $time)
									{
										if(!in_array($folder, array('mf_log'))) // This will naturally be a performance drain when activated to debug
										{
											$percent = round(($time / $total_time) * 100);

											if($percent > $source_percent_limit && $total_time > $query_time_limit)
											{
												do_log(__("Slow Part", 'lang_log')." - ".$key." - ".$folder.": ".$percent."% of ".mf_format_number($total_time).__("s", 'lang_log')); //." (".var_export($wpdb->queries, true).")"
											}
										}
									}
								break;
							}
						}

						//do_log("By Source: ".var_export($arr_sources, true));
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
						do_log(__("Debug Page", 'lang_log').": ".mf_format_number($total_query_time).__("s", 'lang_log')." (".__("MySQL", 'lang_log')." - ".$count_queries."), ".$total_timer_time.__("s", 'lang_log')." (".__("Total", 'lang_log')." - ".$_SERVER['REQUEST_URI'].")"); // (".get_num_queries().")

						/*if($total_query_time == 0)
						{
							$debug_queries .= "<li>&raquo; ".__("Query time is null (0)? - please set the constant", 'lang_log')." <code>SAVEQUERIES</code> ".__("at", 'lang_log')." <code>TRUE</code> ".__("in your", 'lang_log')." <code>wp-config.php</code></li>";
						}*/

						/*$debug_queries .= "<li>"
							."<strong>".__("Page generated", 'lang_log')."</strong>: ".mf_format_number($total_page_time, 5).", "
							.$phpper."% ".__("PHP", 'lang_log').", "
							.$mysqlper."% ".__("MySQL", 'lang_log')
						."</li>";*/
					}
				}
			}

			return $debug_queries;
		}

		function the_queries()
		{
			$debug_output = $this->get_queries();

			/*if($debug_output != '' && IS_SUPER_ADMIN)
			{
				echo "<ul>".$debug_output."</ul>";
			}*/
		}
	}
}