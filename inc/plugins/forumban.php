<?php
/**
 * Forum Ban
 * Copyright 2020 Starpaul20
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Neat trick for caching our custom template(s)
if(THIS_SCRIPT == 'forumdisplay.php')
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'forumdisplay_forumbanlink,forumdisplay_forumbannotice';
}

if(THIS_SCRIPT == 'showthread.php')
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'forumdisplay_forumbannotice';
}

if(THIS_SCRIPT == 'moderation.php')
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'moderation_forumban,moderation_forumban_no_bans,moderation_forumban_liftlist,moderation_forumban_bit';
}

// Tell MyBB when to run the hooks
$plugins->add_hook("moderation_start", "forumban_run");
$plugins->add_hook("forumdisplay_start", "forumban_link");
$plugins->add_hook("forumdisplay_threadlist", "forumban_newthread");
$plugins->add_hook("showthread_start", "forumban_showthread");
$plugins->add_hook("postbit", "forumban_postbit");
$plugins->add_hook("showthread_end", "forumban_quickreply");
$plugins->add_hook("newreply_start", "forumban_post");
$plugins->add_hook("newreply_do_newreply_start", "forumban_post");
$plugins->add_hook("newthread_start", "forumban_post");
$plugins->add_hook("newthread_do_newthread_start", "forumban_post");
$plugins->add_hook("editpost_action_start", "forumban_post");
$plugins->add_hook("editpost_do_editpost_start", "forumban_post");
$plugins->add_hook("xmlhttp_edit_post_end", "forumban_xmlhttp");
$plugins->add_hook("task_usercleanup", "forumban_lift");
$plugins->add_hook("datahandler_user_delete_content", "forumban_delete");

$plugins->add_hook("admin_user_users_merge_commit", "forumban_merge");
$plugins->add_hook("admin_forum_management_delete_commit", "forumban_delete_forum");
$plugins->add_hook("admin_tools_menu_logs", "forumban_admin_menu");
$plugins->add_hook("admin_tools_action_handler", "forumban_admin_action_handler");
$plugins->add_hook("admin_tools_permissions", "forumban_admin_permissions");
$plugins->add_hook("admin_tools_get_admin_log_action", "forumban_admin_adminlog");

// The information that shows up on the plugin manager
function forumban_info()
{
	global $lang;
	$lang->load("forumban", true);

	return array(
		"name"				=> $lang->forumban_info_name,
		"description"		=> $lang->forumban_info_desc,
		"website"			=> "http://galaxiesrealm.com/index.php",
		"author"			=> "Starpaul20",
		"authorsite"		=> "http://galaxiesrealm.com/index.php",
		"version"			=> "1.0",
		"codename"			=> "forumban",
		"compatibility"		=> "18*"
	);
}

// This function runs when the plugin is installed.
function forumban_install()
{
	global $db;
	forumban_uninstall();
	$collation = $db->build_create_table_collation();

	switch($db->type)
	{
		case "pgsql":
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."forumbans (
				bid serial,
				uid int NOT NULL default '0',
				fid int NOT NULL default '0',
				dateline numeric(30,0) NOT NULL default '0',
				lifted numeric(30,0) NOT NULL default '0',
				reason varchar(240) NOT NULL default '',
				PRIMARY KEY (bid)
			);");
			break;
		case "sqlite":
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."forumbans (
				bid INTEGER PRIMARY KEY,
				uid int NOT NULL default '0',
				fid int NOT NULL default '0',
				dateline int NOT NULL default '0',
				lifted int NOT NULL default '0',
				reason varchar(240) NOT NULL default ''
			);");
			break;
		default:
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."forumbans (
				bid int unsigned NOT NULL auto_increment,
				uid int unsigned NOT NULL default '0',
				fid int unsigned NOT NULL default '0',
				dateline int unsigned NOT NULL default '0',
				lifted int unsigned NOT NULL default '0',
				reason varchar(240) NOT NULL default '',
				PRIMARY KEY (bid)
			) ENGINE=MyISAM{$collation};");
			break;
	}
}

// Checks to make sure plugin is installed
function forumban_is_installed()
{
	global $db;
	if($db->table_exists("forumbans"))
	{
		return true;
	}
	return false;
}

// This function runs when the plugin is uninstalled.
function forumban_uninstall()
{
	global $db;

	if($db->table_exists("forumbans"))
	{
		$db->drop_table("forumbans");
	}
}

// This function runs when the plugin is activated.
function forumban_activate()
{
	global $db;

	// Insert templates
	$insert_array = array(
		'title'		=> 'moderation_forumban',
		'template'	=> $db->escape_string('<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->forum_bans_for}</title>
{$headerinclude}
</head>
<body>
{$header}
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
	<tr>
		<td class="thead" colspan="4"><strong>{$lang->forum_bans_for}</strong></td>
	</tr>
	<tr>
		<td class="tcat" width="25%"><span class="smalltext"><strong>{$lang->username}</strong></span></td>
		<td class="tcat" width="35%"><span class="smalltext"><strong>{$lang->reason}</strong></span></td>
		<td class="tcat" width="30%" align="center"><span class="smalltext"><strong>{$lang->expires_on}</strong></span></td>
		<td class="tcat" width="10%" align="center"><span class="smalltext"><strong>{$lang->options}</strong></span></td>
	</tr>
	{$ban_bit}
</table>
<br />
<form action="moderation.php" method="post">
	<input type="hidden" name="action" value="do_forumban" />
	<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
	<input type="hidden" name="fid" value="{$fid}" />
	<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
		<tr>
			<td class="thead" colspan="2"><strong>{$lang->ban_user_from_posting}</strong></td>
		</tr>
		<tr>
			<td class="trow1" width="25%"><strong>{$lang->username}:</strong></td>
			<td class="trow1" width="75%"><input type="text" class="textbox" name="username" id="username" value="{$username}" size="25" /></td>
		</tr>
		<tr>
			<td class="trow2" width="25%"><strong>{$lang->ban_reason}:</strong></td>
			<td class="trow2" width="75%"><textarea name="reason" cols="60" rows="4" maxlength="200">{$banreason}</textarea></td>
		</tr>
		<tr>
			<td class="trow1" width="25%"><strong>{$lang->ban_lift_on}:</strong></td>
			<td class="trow1" width="75%"><select name="liftban">{$liftlist}</select></td>
		</tr>
	</table>
	<br />
	<div align="center">
		<input type="submit" class="button" name="submit" value="{$lang->ban_user}" />
	</div>
</form>
{$footer}
<link rel="stylesheet" href="{$mybb->asset_url}/jscripts/select2/select2.css">
<script type="text/javascript" src="{$mybb->asset_url}/jscripts/select2/select2.min.js"></script>
<script type="text/javascript">
<!--
if(use_xmlhttprequest == "1")
{
	MyBB.select2();
	$("#username").select2({
		placeholder: "{$lang->search_user}",
		minimumInputLength: 2,
		multiple: false,
		ajax: { // instead of writing the function to execute the request we use Select2\'s convenient helper
			url: "xmlhttp.php?action=get_users",
			dataType: \'json\',
			data: function (term, page) {
				return {
					query: term, // search term
				};
			},
			results: function (data, page) { // parse the results into the format expected by Select2.
				// since we are using custom formatting functions we do not need to alter remote JSON data
				return {results: data};
			}
		},
		initSelection: function(element, callback) {
			var value = $(element).val();
			if (value !== "") {
				callback({
					id: value,
					text: value
				});
			}
		},
	});
}
// -->
</script>
</body>
</html>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'moderation_forumban_bit',
		'template'	=> $db->escape_string('<tr>
	<td class="{$alt_bg}">{$ban[\'username\']}</td>
	<td class="{$alt_bg}">{$ban[\'reason\']}</td>
	<td class="{$alt_bg}" align="center">{$ban[\'lifted\']}</td>
	<td class="{$alt_bg}" align="center"><a href="moderation.php?action=liftforumban&amp;bid={$ban[\'bid\']}&amp;my_post_key={$mybb->post_code}"><strong>{$lang->lift_ban}</strong></a></td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'moderation_forumban_no_bans',
		'template'	=> $db->escape_string('<tr>
	<td class="trow1" colspan="4" align="center">{$lang->no_forum_bans}</td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'moderation_forumban_liftlist',
		'template'	=> $db->escape_string('<option value="{$time}"{$selected}>{$title}{$thattime}</option>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'forumdisplay_forumbanlink',
		'template'	=> $db->escape_string(' | <a href="moderation.php?action=forumban&amp;fid={$fid}">{$lang->forum_bans}</a>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'forumdisplay_forumbannotice',
		'template'	=> $db->escape_string('<div class="red_alert"><strong>{$lang->error_banned_from_posting}</strong> {$lang->reason}: {$forumbanreason}<br />{$lang->ban_will_be_lifted}: {$forumbanlift}</div>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("forumdisplay_threadlist", "#".preg_quote('{$clearstoredpass}')."#i", '{$clearstoredpass}{$forumbanlink}');
	find_replace_templatesets("forumdisplay", "#".preg_quote('{$header}')."#i", '{$header}{$forumbannotice}');
	find_replace_templatesets("showthread", "#".preg_quote('{$header}')."#i", '{$header}{$forumbannotice}');

	change_admin_permission('tools', 'forumbans');
}

// This function runs when the plugin is deactivated.
function forumban_deactivate()
{
	global $db;
	$db->delete_query("templates", "title IN('moderation_forumban','moderation_forumban_bit','moderation_forumban_no_bans','moderation_forumban_liftlist','forumdisplay_forumbanlink','forumdisplay_forumbannotice')");

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("forumdisplay_threadlist", "#".preg_quote('{$forumbanlink}')."#i", '', 0);
	find_replace_templatesets("forumdisplay", "#".preg_quote('{$forumbannotice}')."#i", '', 0);
	find_replace_templatesets("showthread", "#".preg_quote('{$forumbannotice}')."#i", '', 0);

	change_admin_permission('tools', 'forumbans', -1);
}

// Forum Ban moderation page
function forumban_run()
{
	global $db, $mybb, $lang, $templates, $theme, $headerinclude, $header, $footer;
	$lang->load("forumban");

	if($mybb->input['action'] != "forumban" && $mybb->input['action'] != "do_forumban" && $mybb->input['action'] != "liftforumban")
	{
		return;
	}

	if($mybb->input['action'] == "forumban")
	{
		$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
		$forum = get_forum($fid);

		if(!is_moderator($forum['fid'], "canmanagethreads"))
		{
			error_no_permission();
		}

		if(!$forum['fid'])
		{
			error($lang->error_invalidforum);
		}

		$forum['name'] = htmlspecialchars_uni($forum['name']);
		$lang->forum_bans_for = $lang->sprintf($lang->forum_bans_for, $forum['name']);

		check_forum_password($forum['fid']);

		build_forum_breadcrumb($forum['fid']);
		add_breadcrumb($lang->forum_bans);

		$query = $db->query("
			SELECT r.*, u.username, u.usergroup, u.displaygroup
			FROM ".TABLE_PREFIX."forumbans r
			LEFT JOIN ".TABLE_PREFIX."users u ON (r.uid=u.uid)
			WHERE r.fid='{$forum['fid']}'
			ORDER BY r.dateline DESC
		");
		while($ban = $db->fetch_array($query))
		{
			$ban['reason'] = htmlspecialchars_uni($ban['reason']);
			$ban['username'] = format_name(htmlspecialchars_uni($ban['username']), $ban['usergroup'], $ban['displaygroup']);
			$ban['username'] = build_profile_link($ban['username'], $ban['uid']);

			if($ban['lifted'] == 0)
			{
				$ban['lifted'] = $lang->permanent;
			}
			else
			{
				$ban['lifted'] = my_date('relative', $ban['lifted'], '', 2);
			}

			$alt_bg = alt_trow();
			eval("\$ban_bit .= \"".$templates->get("moderation_forumban_bit")."\";");
		}

		if(!$ban_bit)
		{
			eval("\$ban_bit = \"".$templates->get("moderation_forumban_no_bans")."\";");
		}

		// Generate the banned times dropdown
		$liftlist = '';
		$bantimes = fetch_ban_times();
		foreach($bantimes as $time => $title)
		{
			$selected = '';
			if(isset($banned['bantime']) && $banned['bantime'] == $time)
			{
				$selected = " selected=\"selected\"";
			}

			$thattime = '';
			if($time != '---')
			{
				$dateline = TIME_NOW;
				if(isset($banned['dateline']))
				{
					$dateline = $banned['dateline'];
				}

				$thatime = my_date("D, jS M Y @ g:ia", ban_date2timestamp($time, $dateline));
				$thattime = " ({$thatime})";
			}

			eval("\$liftlist .= \"".$templates->get("moderation_forumban_liftlist")."\";");
		}

		eval("\$forumban = \"".$templates->get("moderation_forumban")."\";");
		output_page($forumban);
	}

	if($mybb->input['action'] == "do_forumban" && $mybb->request_method == "post")
	{
		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
		$forum = get_forum($fid);

		if(!is_moderator($forum['fid'], "canmanagethreads"))
		{
			error_no_permission();
		}

		if(!$forum['fid'])
		{
			error($lang->error_invalidforum);
		}

		$user = get_user_by_username($mybb->input['username'], array('fields' => array('username')));

		if(!$user['uid'])
		{
			error($lang->error_invaliduser);
		}

		$mybb->input['reason'] = $mybb->get_input('reason');
		if(!trim($mybb->input['reason']))
		{
			error($lang->error_missing_reason);
		}

		$query = $db->simple_select('forumbans', 'bid', "uid='{$user['uid']}' AND fid='{$forum['fid']}'");
		$existingban = $db->fetch_field($query, 'bid');

		if($existingban > 0)
		{
			error($lang->error_alreadybanned);
		}

		if($mybb->get_input('liftban') == '---')
		{
			$lifted = 0;
		}
		else
		{
			$lifted = ban_date2timestamp($mybb->get_input('liftban'), 0);
		}

		$reason = my_substr($mybb->input['reason'], 0, 240);

		$insert_array = array(
			'uid' => $user['uid'],
			'fid' => $forum['fid'],
			'dateline' => TIME_NOW,
			'reason' => $db->escape_string($reason),
			'lifted' => $db->escape_string($lifted)
		);
		$db->insert_query('forumbans', $insert_array);

		log_moderator_action(array("fid" => $forum['fid'], "uid" => $user['uid'], "username" => $user['username']), $lang->user_forum_banned);

		moderation_redirect("moderation.php?action=forumban&fid={$forum['fid']}", $lang->redirect_user_banned_posting);
	}

	if($mybb->input['action'] == "liftforumban")
	{
		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		$bid = $mybb->get_input('bid', MyBB::INPUT_INT);
		$query = $db->simple_select("forumbans", "*", "bid='{$bid}'");
		$ban = $db->fetch_array($query);

		if(!$ban['bid'])
		{
			error($lang->error_invalidforumban);
		}

		$forum = get_forum($ban['fid']);
		$user = get_user($ban['uid']);

		if(!$forum['fid'])
		{
			error($lang->error_invalidforum);
		}

		if(!is_moderator($forum['fid'], "canmanagethreads"))
		{
			error_no_permission();
		}

		$db->delete_query("forumbans", "bid='{$ban['bid']}'");

		log_moderator_action(array("fid" => $forum['fid'], "uid" => $user['uid'], "username" => $user['username']), $lang->user_forum_banned_lifted);

		moderation_redirect("moderation.php?action=forumban&fid={$forum['fid']}", $lang->redirect_forum_ban_lifted);
	}
	exit;
}

// Link to forum bans on forum display/Show ban notice on forum
function forumban_link()
{
	global $db, $mybb, $lang, $templates, $forumbanlink, $forumbannotice;
	$lang->load("forumban");

	$fid = $mybb->get_input('fid', MyBB::INPUT_INT);

	$forumbanlink = '';
	if(is_moderator($fid, "canmanagethreads"))
	{
		eval('$forumbanlink = "'.$templates->get('forumdisplay_forumbanlink').'";');
	}

	$query = $db->simple_select('forumbans', 'bid, reason, lifted', "uid='{$mybb->user['uid']}' AND fid='{$fid}'");
	$existingforumban = $db->fetch_array($query);

	$forumbannotice = '';
	if($existingforumban['bid'] > 0)
	{
		$forumbanlift = $lang->banned_lifted_never;
		$forumbanreason = htmlspecialchars_uni($existingforumban['reason']);

		if($existingforumban['lifted'] > 0)
		{
			$forumbanlift = my_date('normal', $existingforumban['lifted']);
		}

		if(empty($forumbanreason))
		{
			$forumbanreason = $lang->unknown;
		}

		if(empty($forumbanlift))
		{
			$forumbanlift = $lang->unknown;
		}

		eval('$forumbannotice = "'.$templates->get('forumdisplay_forumbannotice').'";');
	}
}

// Remove new thread button if forum banned
function forumban_newthread()
{
	global $db, $mybb, $foruminfo, $newthread;

	$query = $db->simple_select('forumbans', 'bid', "uid='{$mybb->user['uid']}' AND fid='{$foruminfo['fid']}'");
	$existingban = $db->fetch_field($query, 'bid');

	if($existingban > 0)
	{
		$newthread = '';
	}
}

// Query to see if user is forum banned (to remove postbit buttons)/Show ban notice on threads
function forumban_showthread()
{
	global $db, $mybb, $lang, $templates, $forum, $existingforumban, $forumbannotice;
	$lang->load("forumban");

	$query = $db->simple_select('forumbans', 'bid, reason, lifted', "uid='{$mybb->user['uid']}' AND fid='{$forum['fid']}'");
	$existingforumban = $db->fetch_array($query);

	$forumbannotice = '';
	if($existingforumban['bid'] > 0)
	{
		$forumbanlift = $lang->banned_lifted_never;
		$forumbanreason = htmlspecialchars_uni($existingforumban['reason']);

		if($existingforumban['lifted'] > 0)
		{
			$forumbanlift = my_date('normal', $existingforumban['lifted']);
		}

		if(empty($forumbanreason))
		{
			$forumbanreason = $lang->unknown;
		}

		if(empty($forumbanlift))
		{
			$forumbanlift = $lang->unknown;
		}

		eval('$forumbannotice = "'.$templates->get('forumdisplay_forumbannotice').'";');
	}
}

// Remove postbit buttons if forum banned
function forumban_postbit($post)
{
	global $existingban;

	if($existingban['bid'] > 0)
	{
		$post['button_edit'] = $post['button_quickdelete'] = $post['button_multiquote'] = $post['button_quote'] = '';
	}

	return $post;
}

// Remove quick reply box if forum banned
function forumban_quickreply()
{
	global $quickreply, $newreply, $existingban;

	if($existingban['bid'] > 0)
	{
		$quickreply = $newreply = '';
	}
}

// Check to see if user is banned from posting
function forumban_post()
{
	global $db, $mybb, $lang, $forum;
	$lang->load("forumban");

	$query = $db->simple_select('forumbans', 'bid, reason', "uid='{$mybb->user['uid']}' AND fid='{$forum['fid']}'");
	$existingban = $db->fetch_array($query);

	if($existingban['bid'] > 0)
	{
		$existingban['reason'] = htmlspecialchars_uni($existingban['reason']);
		$lang->error_banned_from_posting_reason = $lang->sprintf($lang->error_banned_from_posting_reason, $existingban['reason']);

		error($lang->error_banned_from_posting_reason);
	}
}

// Error if quick editing is used
function forumban_xmlhttp()
{
	global $db, $mybb, $lang, $forum;
	$lang->load("forumban");

	$query = $db->simple_select('forumbans', 'bid', "uid='{$mybb->user['uid']}' AND fid='{$forum['fid']}'");
	$existingban = $db->fetch_field($query, 'bid');

	if($existingban['bid'] > 0)
	{
		xmlhttp_error($lang->error_banned_from_posting);
	}
}

// Lift old forum bans
function forumban_lift(&$task)
{
	global $db;

	$query = $db->simple_select("forumbans", "bid", "lifted!=0 AND lifted<".TIME_NOW);
	while($forumban = $db->fetch_array($query))
	{
		$db->delete_query("forumbans", "bid='{$forumban['bid']}'");
	}
}

// Delete forum bans if user is deleted
function forumban_delete($delete)
{
	global $db;

	$db->delete_query('forumbans', 'uid IN('.$delete->delete_uids.')');

	return $delete;
}

// Update forum bans if users are merged
function forumban_merge()
{
	global $db, $source_user, $destination_user;

	$uid = array(
		"uid" => $destination_user['uid']
	);
	$db->update_query("forumbans", $uid, "uid='{$source_user['uid']}'");
}

// Delete forum bans if forum is deleted
function forumban_delete_forum()
{
	global $db, $fid, $delquery;

	$db->delete_query("forumbans", "fid='{$fid}' {$delquery}");
}

// Admin CP forum ban page
function forumban_admin_menu($sub_menu)
{
	global $lang;
	$lang->load("tools_forumbans");

	$sub_menu['140'] = array('id' => 'forumbans', 'title' => $lang->forum_bans, 'link' => 'index.php?module=tools-forumbans');

	return $sub_menu;
}

function forumban_admin_action_handler($actions)
{
	$actions['forumbans'] = array('active' => 'forumbans', 'file' => 'forumbans.php');

	return $actions;
}

function forumban_admin_permissions($admin_permissions)
{
	global $lang;
	$lang->load("tools_forumbans");

	$admin_permissions['forumbans'] = $lang->can_manage_forum_bans;

	return $admin_permissions;
}

// Admin Log display
function forumban_admin_adminlog($plugin_array)
{
	global $lang;
	$lang->load("tools_forumbans");

	return $plugin_array;
}
