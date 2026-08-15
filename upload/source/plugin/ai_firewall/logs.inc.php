<?php

if(!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) {
	exit('Access Denied');
}

require_once DISCUZ_ROOT.'./source/plugin/ai_firewall/language/lang_admincp.php';
require_once DISCUZ_ROOT.'./source/plugin/ai_firewall/lib/config.php';

$baseUrl = 'plugins&operation=config&do='.$pluginid.'&identifier=ai_firewall&pmod=logs';
$redirectUrl = 'action='.$baseUrl;
$config = ai_firewall_config::get();

if(!empty($_GET['cleanup']) && submitcheck('cleanup')) {
	C::t('#ai_firewall#ai_firewall_log')->delete_before(TIMESTAMP - $config['log_days'] * 86400);
	cpmsg('ai_firewall:logs_cleaned', $redirectUrl, 'succeed');
}
if(!empty($_GET['clearlogs']) && submitcheck('clearlogs')) {
	C::t('#ai_firewall#ai_firewall_log')->delete_all();
	cpmsg('ai_firewall:logs_cleared', $redirectUrl, 'succeed');
}

$allowedDecisions = array('', 'pass', 'review', 'error');
$allowedTypes = array('', 'thread', 'reply');
$decision = isset($_GET['decision']) && in_array($_GET['decision'], $allowedDecisions, true) ? $_GET['decision'] : '';
$contentType = isset($_GET['content_type']) && in_array($_GET['content_type'], $allowedTypes, true) ? $_GET['content_type'] : '';
$days = isset($_GET['days']) ? max(0, min(3650, intval($_GET['days']))) : 30;
$startTime = $days ? TIMESTAMP - $days * 86400 : 0;
$page = max(1, intval($_G['page']));
$perPage = 50;
$count = C::t('#ai_firewall#ai_firewall_log')->count_by_filter($decision, $contentType, $startTime);
$logs = C::t('#ai_firewall#ai_firewall_log')->fetch_all_by_filter($decision, $contentType, $startTime, ($page - 1) * $perPage, $perPage);
$uids = array();
$fids = array();
foreach($logs as $row) {
	if($row['uid']) $uids[$row['uid']] = $row['uid'];
	if($row['fid']) $fids[$row['fid']] = $row['fid'];
}
$users = $uids ? C::t('common_member')->fetch_all($uids) : array();
$forums = $fids ? C::t('forum_forum')->fetch_all_name_by_fid($fids) : array();
$query = '&decision='.rawurlencode($decision).'&content_type='.rawurlencode($contentType).'&days='.$days;
$multipage = multi($count, $perPage, $page, ADMINSCRIPT.'?action='.$baseUrl.$query);

showtips($ai_firewall_adminlang['log_privacy']);
showformheader($baseUrl, '', 'filterform', 'get');
showhiddenfields(array(
	'action' => 'plugins',
	'operation' => 'config',
	'do' => $pluginid,
	'identifier' => 'ai_firewall',
	'pmod' => 'logs',
));
showtableheader($ai_firewall_adminlang['filter']);
$decisionOptions = '<select name="decision"><option value="">'.$ai_firewall_adminlang['all'].'</option>';
foreach(array('pass', 'review', 'error') as $value) {
	$decisionOptions .= '<option value="'.$value.'"'.($decision === $value ? ' selected' : '').'>'.$value.'</option>';
}
$decisionOptions .= '</select>';
$typeOptions = '<select name="content_type"><option value="">'.$ai_firewall_adminlang['all'].'</option><option value="thread"'.($contentType === 'thread' ? ' selected' : '').'>'.$ai_firewall_adminlang['thread'].'</option><option value="reply"'.($contentType === 'reply' ? ' selected' : '').'>'.$ai_firewall_adminlang['reply'].'</option></select>';
showtablerow('', array('class="td25"', ''), array($ai_firewall_adminlang['decision'], $decisionOptions.' &nbsp; '.$ai_firewall_adminlang['content_type'].' '.$typeOptions.' &nbsp; 最近 <input class="txt" style="width:60px" type="number" min="0" max="3650" name="days" value="'.$days.'" /> 天 &nbsp; <input class="btn" type="submit" value="'.$ai_firewall_adminlang['filter'].'" />'));
showtablefooter();
showformfooter();
showtableheader($ai_firewall_adminlang['logs_title']);
showsubtitle(array('ID', $ai_firewall_adminlang['decision'], $ai_firewall_adminlang['content_type'], $ai_firewall_adminlang['user'], $ai_firewall_adminlang['forum'], $ai_firewall_adminlang['post'], $ai_firewall_adminlang['reason'], $ai_firewall_adminlang['http'], $ai_firewall_adminlang['latency'], $ai_firewall_adminlang['error_code'], $ai_firewall_adminlang['time']));
if(!$logs) {
	showtablerow('', 'colspan="11"', $ai_firewall_adminlang['no_logs']);
} else {
	foreach($logs as $row) {
		$username = isset($users[$row['uid']]['username']) ? $users[$row['uid']]['username'] : ($row['uid'] ? 'UID '.$row['uid'] : '-');
		$forumName = isset($forums[$row['fid']]['name']) ? $forums[$row['fid']]['name'] : ($row['fid'] ? 'FID '.$row['fid'] : '-');
		$reason = dhtmlspecialchars($row['reason']);
		if($row['categories']) $reason .= ($reason ? '<br />' : '').'<span class="lightfont">'.dhtmlspecialchars($row['categories']).'</span>';
		$postLink = '-';
		if(!empty($row['tid'])) {
			$postUrl = !empty($row['pid'])
				? 'forum.php?mod=redirect&goto=findpost&ptid='.intval($row['tid']).'&pid='.intval($row['pid'])
				: 'forum.php?mod=viewthread&tid='.intval($row['tid']);
			$postLink = '<a href="'.$postUrl.'" target="_blank" rel="noopener noreferrer">'.dhtmlspecialchars($ai_firewall_adminlang['view_post']).'</a>';
		}
		showtablerow('', array('class="td25"', '', '', '', '', '', '', '', '', '', ''), array(
			intval($row['id']),
			dhtmlspecialchars($row['decision']),
			dhtmlspecialchars($row['content_type']),
			dhtmlspecialchars($username),
			dhtmlspecialchars($forumName),
			$postLink,
			$reason ?: '-',
			intval($row['http_status']),
			intval($row['latency_ms']).' ms',
			dhtmlspecialchars($row['error_code']) ?: '-',
			dgmdate($row['created_at']),
		));
	}
}
showsubmit('', '', '', '', $multipage);
showtablefooter();

showformheader($baseUrl);
showtableheader();
$clearButton = '<input type="submit" class="btn" name="clearlogs" value="'.dhtmlspecialchars($ai_firewall_adminlang['clear']).'" onclick="return confirm(\''.addslashes($ai_firewall_adminlang['clear_confirm']).'\')" />';
showsubmit('cleanup', $ai_firewall_adminlang['cleanup'], '', $clearButton);
showtablefooter();
showformfooter();
