<?php

$obj_log = new mf_log();
$obj_log->fetch_request();
$obj_log->save_data();

$tbl_group = new mf_log_table();

$tbl_group->select_data(array(
	//'select' => "*",
));

echo "<div class='wrap'>
	<h2>"
		.__("Log", 'lang_log');

		if((!isset($_REQUEST['post_status']) || $_REQUEST['post_status'] == 'all') && $tbl_group->num_rows > 1)
		{
			echo "<a href='".wp_nonce_url(admin_url("admin.php?page=mf_log/list/index.php&btnLogTrashAll".($obj_log->post_status != '' && $obj_log->post_status != 'all' ? "&post_status=".$obj_log->post_status : '')), 'log_trash_all', '_wpnonce_log_trash_all')."' class='add-new-h2' rel='confirm'>".__("Delete All", 'lang_log')."</a>";
		}

	echo "</h2>"
	.get_notification();

	$tbl_group->do_display();

echo "</div>";

update_user_meta(get_current_user_id(), 'meta_log_viewed', date("Y-m-d H:i:s"));