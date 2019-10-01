<?php
/**
 * Forum Ban
 * Copyright 2019 Starpaul20
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->forum_bans, "index.php?module=tools-forumbans");

$sub_tabs['forum_bans'] = array(
	'title' => $lang->forum_bans,
	'link' => "index.php?module=tools-forumbans",
	'description' => $lang->forum_bans_desc
);

if($mybb->input['action'] == "lift")
{
	$query = $db->query("
		SELECT r.*, u.username, f.name
		FROM ".TABLE_PREFIX."forumbans r
		LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=r.fid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=r.uid)
		WHERE r.bid='".$mybb->get_input('bid', MyBB::INPUT_INT)."'
	");
	$forumban = $db->fetch_array($query);

	// Does the forum ban not exist?
	if(!$forumban['bid'])
	{
		flash_message($lang->error_invalid_forum_ban, 'error');
		admin_redirect("index.php?module=tools-forumbans");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=tools-forumbans");
	}

	if($mybb->request_method == "post")
	{
		// Lift the forum ban
		$db->delete_query("forumbans", "bid='{$forumban['bid']}'");

		// Log admin action
		log_admin_action($forumban['uid'], htmlspecialchars_uni($forumban['username']), $forumban['fid'], htmlspecialchars_uni($forumban['name']));

		flash_message($lang->success_forum_ban_lifted, 'success');
		admin_redirect("index.php?module=tools-forumbans");
	}
	else
	{
		$page->output_confirm_action("index.php?module=tools-forumbans&amp;action=delete&amp;bid={$forumban['bid']}", $lang->confirm_forum_ban_lift);
	}
}

if(!$mybb->input['action'])
{
	$page->output_header($lang->forum_bans);

	$page->output_nav_tabs($sub_tabs, 'forum_bans');

	$perpage = $mybb->get_input('perpage', MyBB::INPUT_INT);
	if(!$perpage)
	{
		if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
		{
			$mybb->settings['threadsperpage'] = 20;
		}

		$perpage = $mybb->settings['threadsperpage'];
	}

	$where = 'WHERE 1=1';

	// Searching for entries by a particular user
	if($mybb->input['uid'])
	{
		$where .= " AND r.uid='".$mybb->get_input('uid', MyBB::INPUT_INT)."'";
	}

	// Searching for entries in a specific forum
	if($mybb->input['fid'] > 0)
	{
		$where .= " AND r.fid='".$mybb->get_input('fid', MyBB::INPUT_INT)."'";
	}

	// Order?
	switch($mybb->input['sortby'])
	{
		case "username":
			$sortby = "u.username";
			break;
		case "forum":
			$sortby = "f.name";
			break;
		case "lifted":
			$sortby = "r.lifted";
			break;
		default:
			$sortby = "r.dateline";
	}
	$order = $mybb->input['order'];
	if($order != "asc")
	{
		$order = "desc";
	}

	$query = $db->query("
		SELECT COUNT(r.dateline) AS count
		FROM ".TABLE_PREFIX."forumbans r
		{$where}
	");
	$rescount = $db->fetch_field($query, "count");

	// Figure out if we need to display multiple pages.
	if($mybb->input['page'] != "last")
	{
		$pagecnt = $mybb->get_input('page', MyBB::INPUT_INT);
	}

	$postcount = (int)$rescount;
	$pages = $postcount / $perpage;
	$pages = ceil($pages);

	if($mybb->input['page'] == "last")
	{
		$pagecnt = $pages;
	}

	if($pagecnt > $pages)
	{
		$pagecnt = 1;
	}

	if($pagecnt)
	{
		$start = ($pagecnt-1) * $perpage;
	}
	else
	{
		$start = 0;
		$pagecnt = 1;
	}

	$table = new Table;
	$table->construct_header($lang->username, array('width' => '15%'));
	$table->construct_header($lang->forum, array("class" => "align_center", 'width' => '25%'));
	$table->construct_header($lang->date_banned_on, array("class" => "align_center", 'width' => '15%'));
	$table->construct_header($lang->lifted_on, array("class" => "align_center", 'width' => '15%'));
	$table->construct_header($lang->reason, array("class" => "align_center", 'width' => '20%'));
	$table->construct_header($lang->action, array("class" => "align_center", 'width' => '100'));

	$query = $db->query("
		SELECT r.*, u.username, u.usergroup, u.displaygroup, f.name
		FROM ".TABLE_PREFIX."forumbans r
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=r.uid)
		LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=r.fid)
		{$where}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	while($forumban = $db->fetch_array($query))
	{
		$trow = alt_trow();
		$forumban['dateline'] = my_date('relative', $forumban['dateline']);

		if($forumban['lifted'] == 0)
		{
			$forumban['lifted'] = $lang->permanently;
		}
		else
		{
			$forumban['lifted'] = my_date('relative', $forumban['lifted']);
		}

		if($forumban['username'])
		{
			$username = format_name(htmlspecialchars_uni($forumban['username']), $forumban['usergroup'], $forumban['displaygroup']);
			$forumban['profilelink'] = build_profile_link($username, $forumban['uid'], "_blank");
		}
		else
		{
			$username = $forumban['profilelink'] = $forumban['username'] = htmlspecialchars_uni($lang->na_deleted);
		}

		$forumban['name'] = htmlspecialchars_uni($forumban['name']);
		$forumban['reason'] = htmlspecialchars_uni($forumban['reason']);

		$table->construct_cell($forumban['profilelink']);
		$table->construct_cell("<a href=\"../".get_forum_link($forumban['fid'])."\" target=\"_blank\">{$forumban['name']}</a>", array("class" => "align_center"));
		$table->construct_cell($forumban['dateline'], array("class" => "align_center"));
		$table->construct_cell($forumban['lifted'], array("class" => "align_center"));
		$table->construct_cell($forumban['reason'], array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?module=tools-forumbans&amp;action=lift&amp;bid={$forumban['bid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_forum_ban_lift}')\">{$lang->lift_ban}</a>", array("class" => "align_center", "width" => '90'));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_forumbans, array("colspan" => 6));
		$table->construct_row();
	}

	$table->output($lang->forum_bans);

	// Do we need to construct the pagination?
	if($rescount > $perpage)
	{
		echo draw_admin_pagination($pagecnt, $perpage, $rescount, "index.php?module=tools-forumbans&amp;perpage=$perpage&amp;uid={$mybb->input['uid']}&amp;fid={$mybb->input['fid']}&amp;sortby={$mybb->input['sortby']}&amp;order={$order}")."<br />";
	}

	// Fetch filter options
	$sortbysel[$mybb->input['sortby']] = "selected=\"selected\"";
	$ordersel[$mybb->input['order']] = "selected=\"selected\"";

	$user_options[''] = $lang->all_users;
	$user_options['0'] = '----------';

	$query = $db->query("
		SELECT DISTINCT r.uid, u.username
		FROM ".TABLE_PREFIX."forumbans r
		LEFT JOIN ".TABLE_PREFIX."users u ON (r.uid=u.uid)
		ORDER BY u.username ASC
	");
	while($user = $db->fetch_array($query))
	{
		// Deleted Users
		if(!$user['username'])
		{
			$user['username'] = htmlspecialchars_uni($lang->na_deleted);
		}

		$selected = '';
		if($mybb->input['uid'] == $user['uid'])
		{
			$selected = "selected=\"selected\"";
		}
		$user_options[$user['uid']] = htmlspecialchars_uni($user['username']);
	}

	$forum_options[''] = $lang->all_forums;
	$forum_options['0'] = '----------';
	
	$query2 = $db->query("
		SELECT DISTINCT r.fid, t.name
		FROM ".TABLE_PREFIX."forumbans r
		LEFT JOIN ".TABLE_PREFIX."forums t ON (r.fid=t.fid)
		ORDER BY t.name ASC
	");
	while($forum = $db->fetch_array($query2))
	{
		// Deleted Forum
		if(!$forum['name'])
		{
			$forum['name'] = htmlspecialchars_uni($lang->na_deleted);
		}

		$forum_options[$forum['fid']] = $forum['name'];
	}

	$sort_by = array(
		'dateline' => $lang->date_banned_on,
		'lifted' => $lang->lifted_on,
		'username' => $lang->username,
		'forum' => $lang->forum_name
	);

	$order_array = array(
		'asc' => $lang->asc,
		'desc' => $lang->desc
	);

	$form = new Form("index.php?module=tools-forumbans", "post");
	$form_container = new FormContainer($lang->filter_forum_bans);
	$form_container->output_row($lang->username.":", "", $form->generate_select_box('uid', $user_options, $mybb->input['uid'], array('id' => 'uid')), 'uid');
	$form_container->output_row($lang->forum.":", "", $form->generate_select_box('fid', $forum_options, $mybb->input['fid'], array('id' => 'fid')), 'fid');
	$form_container->output_row($lang->sort_by, "", $form->generate_select_box('sortby', $sort_by, $mybb->input['sortby'], array('id' => 'sortby'))." {$lang->in} ".$form->generate_select_box('order', $order_array, $order, array('id' => 'order'))." {$lang->order}", 'order');
	$form_container->output_row($lang->results_per_page, "", $form->generate_numeric_field('perpage', $perpage, array('id' => 'perpage', 'min' => 1)), 'perpage');

	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->filter_forum_bans);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}
