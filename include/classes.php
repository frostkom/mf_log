<?php

class mf_log
{
	function __contruct()
	{

	}

	function fetch_request()
	{
		$this->ID = check_var('intLogID');
		$this->post_status = check_var('post_status', 'char');
	}

	function save_data()
	{
		global $wpdb, $done_text, $error_text;

		if(isset($_REQUEST['btnLogDelete']) && $this->ID > 0 && wp_verify_nonce($_REQUEST['_wpnonce'], 'log_delete_'.$this->ID))
		{
			if(wp_trash_post($this->ID))
			{
				$done_text = __("The information was deleted", 'lang_log');
			}
		}

		if(isset($_REQUEST['btnLogDeleteAll']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'log_delete_all'))
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

		else if(isset($_REQUEST['btnLogIgnore']) && $this->ID > 0 && wp_verify_nonce($_REQUEST['_wpnonce'], 'log_ignore_'.$this->ID))
		{
			$wpdb->query($wpdb->prepare("UPDATE ".$wpdb->posts." SET post_status = 'ignore', post_modified = NOW() WHERE post_type = 'mf_log' AND ID = '%d'", $this->ID));

			if($wpdb->rows_affected > 0)
			{
				$done_text = __("The information is being ignored from now on", 'lang_log');
			}
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
		$this->orderby_default_order = "desc";

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
						$actions['delete'] = "<a href='".wp_nonce_url("?page=mf_log/list/index.php&btnLogDelete&intLogID=".$post_id, 'log_delete_'.$post_id)."'>".__("Delete", 'lang_log')."</a>";
					}
				}

				if($post_status != "ignore")
				{
					if($post_author == get_current_user_id() || IS_ADMIN)
					{
						$actions['ignore'] = "<a href='".wp_nonce_url("?page=mf_log/list/index.php&btnLogIgnore&intLogID=".$post_id, 'log_ignore_'.$post_id)."' rel='confirm'>".__("Ignore", 'lang_log')."</a>";
					}
				}

				/*else
				{
					$actions['recover'] = "<a href='?page=mf_log/list/index.php&intLogID=".$post_id."&recover'>".__("Recover", 'lang_log')."</a>";
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
			add_action('wp_footer', array($this, 'the_queries'));
		}

		// core
		function get_queries()
		{
			global $wpdb;

			$query_time_limit = get_option('setting_log_query_time_limit');
			$page_time_limit = get_option('setting_log_page_time_limit');

			// disabled session cache of mySQL
			/*if(QUERY_CACHE_TYPE_OFF)
			{
				$wpdb->query( 'SET SESSION query_cache_type = 0;');
			}*/

			$debug_queries = "";

			if($wpdb->queries)
			{
				//$total_page_time = timer_stop(FALSE, 22);
				$total_query_time = 0;

				foreach($wpdb->queries as $q)
				{
					$query_text = htmlentities(trim(preg_replace('/[[:space:]]+/', ' ', $q[0])));
					$query_time = mf_format_number($q[1], 6); //number_format_i18n
					$query_called = htmlentities($q[2]);

					$total_query_time += $query_time;

					if($query_time_limit != '' && $query_time > $query_time_limit && substr($query_text, 0, 6) == "SELECT")
					{
						do_log(__("Debug query", 'lang_log').": ".$query_time."s, ".$query_text." (".$query_called.")");

						$debug_queries .= "<li>
							<strong>".$query_time.": </strong>".$query_text
						."</li>";
					}
				}
			}

			if($page_time_limit != '')
			{
				//$php_time = $total_page_time - $total_query_time;

				//$mysqlper = $total_page_time > 0 ? mf_format_number($total_query_time / $total_page_time * 100, 2) : 0;
				//$phpper = $total_page_time > 0 ? mf_format_number($php_time / $total_page_time * 100, 2) : 0;

				$count_queries = count($wpdb->queries);
				$count_num_queries = get_num_queries();

				$total_query_time = mf_format_number($total_query_time, 5);
				//$total_page_time = mf_format_number($total_page_time, 5);
				$total_timer_time = timer_stop();

				if($total_query_time > $page_time_limit || $total_timer_time > $page_time_limit)
				{
					do_log(__("Debug page", 'lang_log').": ".$total_query_time."s (".$count_queries."), ".$total_timer_time."s (".$count_num_queries.")");

					$debug_queries .= "<li>
						<strong>".__("Total query time", 'lang_log').":</strong> ".$total_query_time."s (".$count_queries.")
					</li>";

					$debug_queries .= "<li>
						<strong>".__("Total num_query time", 'lang_log')."</strong>: ".$total_timer_time."s (".$count_num_queries.")
					</li>";

					/*if($total_query_time == 0)
					{
						$debug_queries .= "<li>&raquo; ".__("Query time is null (0)? - please set the constant", 'lang_log')." <code>SAVEQUERIES</code> ".__("at", 'lang_log')." <code>TRUE</code> ".__("in your", 'lang_log')." <code>wp-config.php</code></li>";
					}*/

					/*$debug_queries .= "<li>"
						."<strong>".__("Page generated", 'lang_log')."</strong>: ".$total_page_time.", "
						.$phpper."% ".__("PHP", 'lang_log').", "
						.$mysqlper."% ".__("MySQL", 'lang_log')
					."</li>";*/
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

if(!is_admin())
{
	$log_query_debug = get_option('setting_log_query_debug', 'no');

	if($log_query_debug == 'yes')
	{
		// disable mySQL Session Cache
		/*if(!defined('QUERY_CACHE_TYPE_OFF'))
		{
			define('QUERY_CACHE_TYPE_OFF', TRUE);
		}*/

		if(!defined('SAVEQUERIES'))
		{
			define('SAVEQUERIES', TRUE);
		}

		$debug_queries = new Debug_Queries();
	}
}