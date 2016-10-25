<?php

$obj_log = new mf_log();
$obj_log->fetch_request();
$obj_log->save_data();

echo "<div class='wrap'>
	<h2>".__("Log", 'lang_log')."</h2>"
	.get_notification();

	$tbl_group = new mf_log_table();

	$tbl_group->select_data(array(
		//'select' => "*",
		//'debug' => true,
	));

	$tbl_group->do_display();

echo "</div>";

update_user_meta(get_current_user_id(), 'mf_log_viewed', date("Y-m-d H:i:s"));