<?php

if (!function_exists('dsu_paulsign_unserialize_array')) {
  function dsu_paulsign_unserialize_array($value)
  {
    if (!is_string($value)) {
      return array();
    }
    $result = @unserialize($value, array('allowed_classes' => false));
    return is_array($result) ? $result : array();
  }
}
if (!function_exists('dsu_paulsign_emots')) {
  function dsu_paulsign_emots($value)
  {
    $emots = array();
    foreach (dsu_paulsign_unserialize_array($value) as $key => $emot) {
      $key = (string)$key;
      if (!preg_match('/^[A-Za-z0-9_]{1,5}$/', $key) || !is_array($emot)) {
        continue;
      }
      $emot['name'] = isset($emot['name']) && is_scalar($emot['name']) ? (string)$emot['name'] : '';
      $emots[$key] = $emot;
    }
    return $emots;
  }
}

function paulsign_do($uid, $signmode, $ifreward = true)
{
  global $_G, $_GET;
  loadcache('pluginlanguage_script');
  $var = $_G['cache']['plugin']['dsu_paulsign'];
  $lang = $_G['cache']['pluginlanguage_script']['dsu_paulsign'];
  $tdtime = gmmktime(0, 0, 0, dgmdate($_G['timestamp'], 'n', $var['tos']), dgmdate($_G['timestamp'], 'j', $var['tos']), dgmdate($_G['timestamp'], 'Y', $var['tos'])) - $var['tos'] * 3600;
  $htime = dgmdate($_G['timestamp'], 'H', $var['tos']);
  $uid = intval($uid);
  $qiandaodb = C::t('#dsu_paulsign#dsu_paulsign')->fetch($uid);
  $userinfo = C::t('#dsu_paulsign#dsu_paulsign')->get_userinfo($uid);
  $groupid = $userinfo['groupid'];
  $username = $userinfo['username'];
  if ($signmode == 1) {
    if ($_GET['formhash'] != FORMHASH) {
      showmessage('undefined_action', NULL);
    }
  }
  if ($var['timeopen']) {
    if ($htime < $var['stime']) {
      return paulsign_result($signmode, "{$lang['ts_timeearly1']}{$var['stime']}{$lang['ts_timeearly2']}");
    } elseif ($htime > $var['ftime']) {
      return paulsign_result($signmode, $lang['ts_timeov']);
    }
  }
  $allowedgroups = array_values(array_filter(array_map('intval', dsu_paulsign_unserialize_array($var['groups'])), function($gid) { return $gid > 0; }));
  if (!in_array((int)$groupid, $allowedgroups, true)) {
    return paulsign_result($signmode, $lang['ts_notallow']);
  }
  if ($var['mintdpost'] > C::t('#dsu_paulsign#dsu_paulsign')->getuserpost($uid)) {
    return paulsign_result($signmode, "{$lang['ts_minpost1']}{$var['mintdpost']}{$lang['ts_minpost2']}");
  }
  $banuids = array_values(array_filter(array_map('intval', explode(',', (string)$var['ban'])), function($banuid) { return $banuid > 0; }));
  if (in_array($uid, $banuids, true)) {
    return paulsign_result($signmode, $lang['ts_black']);
  }
  if ($qiandaodb['time'] > $tdtime) {
    return paulsign_result($signmode, $lang['ts_yq']);
  }
  $emots = dsu_paulsign_emots($_G['setting']['paulsign_emot']);
  if ($signmode == 1) {
    if (!isset($_GET['qdxq']) || !is_scalar($_GET['qdxq']) || !array_key_exists((string)$_GET['qdxq'], $emots)) {
      return paulsign_result($signmode, $lang['ts_xqnr']);
    }
    $mood = (string)$_GET['qdxq'];
  } else {
    if (!$emots) {
      return paulsign_result($signmode, $lang['ts_xqnr']);
    }
    $mood = array_rand($emots);
  }
  if (!$var['sayclose'] && $signmode == 1) {
    if ($_GET['qdmode'] == '1') {
      $todaysay = isset($_GET['todaysay']) && is_scalar($_GET['todaysay']) ? dhtmlspecialchars((string)$_GET['todaysay']) : '';
      if ($todaysay == '') {
        return paulsign_result($signmode, $lang['ts_nots']);
      }
      if (strlen($todaysay) > 100) {
        return paulsign_result($signmode, $lang['ts_ovts']);
      }
      if (strlen($todaysay) < 6) {
        return paulsign_result($signmode, $lang['ts_syts']);
      }
      if (!preg_match("/[^A-Za-z0-9.,]/", $todaysay)) {
        return paulsign_result($signmode, $lang['ts_saywater']);
      }
      if (censormod($todaysay)) {
        return paulsign_result($signmode, $lang['ts_illegaltext']);
      }
    } elseif ($_GET['qdmode'] == '2') {
      $fastreplytexts = array_values(array_filter(explode("/hhf/", str_replace(array("\r\n", "\n", "\r"), '/hhf/', $var['fastreplytext'])), 'strlen'));
      if (!isset($_GET['fastreply']) || !is_scalar($_GET['fastreply']) || !isset($fastreplytexts[(int)$_GET['fastreply']])) {
        return paulsign_result($signmode, $lang['ts_xqnr']);
      }
      $todaysay = $fastreplytexts[(int)$_GET['fastreply']];
    } elseif ($_GET['qdmode'] == '3') {
      $todaysay = "{$lang['wttodaysay']}";
    }
  } else {
    $todaysay = "{$lang['wttodaysay']}";
  }
  $lockname = 'dsu_paulsign_' . $uid;
  // islocked() records a local lock state even when another request owns the
  // lock, so retrying it in a loop can deadlock this request.  Fail closed
  // and let the user retry instead.
  if (discuz_process::islocked($lockname, 5, 1)) {
    return paulsign_result($signmode, $lang['ts_yq']);
  }
  $qiandaodb = C::t('#dsu_paulsign#dsu_paulsign')->fetch($uid);
  if (!empty($qiandaodb['time']) && $qiandaodb['time'] > $tdtime) {
    discuz_process::unlock($lockname);
    return paulsign_result($signmode, $lang['ts_yq']);
  }
  $mincredit = intval($var['mincredit']);
  $maxcredit = intval($var['maxcredit']);
  if ($mincredit > $maxcredit) {
    list($mincredit, $maxcredit) = array($maxcredit, $mincredit);
  }
  $credit = mt_rand($mincredit, $maxcredit);
  if (in_array($groupid, dsu_paulsign_unserialize_array($var['jlxgroups']), true) && $var['jlx'] !== '0') {
    $credit = $credit * $var['jlx'];
  }
  if (($tdtime - $qiandaodb['time']) < 86400 && $var['lastedop'] && $qiandaodb['lasted'] !== '0') {
    $lastednuml = intval($var['lastednuml']);
    $lastednumh = intval($var['lastednumh']);
    if ($lastednuml > $lastednumh) {
      list($lastednuml, $lastednumh) = array($lastednumh, $lastednuml);
    }
    $randlastednum = mt_rand($lastednuml, $lastednumh);
    $randlastednum = sprintf("%03d", $randlastednum);
    $randlastednum = '0.' . $randlastednum;
    $randlastednum = $randlastednum * $qiandaodb['lasted'];
    $credit = round($credit * (1 + $randlastednum));
  }
  $num = C::t('#dsu_paulsign#dsu_paulsign')->getcount('time', $tdtime, '>=');
  if (!$qiandaodb['uid']) {
    C::t('#dsu_paulsign#dsu_paulsign')->insert(array('uid' => $uid, 'time' => $_G['timestamp']));
  }
  $islast = ($tdtime - $qiandaodb['time']) < 86400 && $var['lastedop'] ? true : false;
  C::t('#dsu_paulsign#dsu_paulsign')->update_signdata($uid, $mood, $todaysay, $credit, $islast);
  if ($ifreward) {
    updatemembercount($uid, array($var['nrcredit'] => $credit));
  }
  $another_vip = '';
  if (@include_once DISCUZ_ROOT . './source/plugin/dsu_kkvip/extend/sign.api.php') {
    if ($rewarddays || $growupnum) {
      $another_vip = lang('plugin/dsu_paulsign', 'another_vip', array('rewarddays' => intval($rewarddays), 'growupnum' => intval($growupnum)));
    }
  }
  require_once libfile('function/post');
  require_once libfile('function/forum');
  if ($signmode == 1) {
    if ($var['sync_say'] && $_GET['qdmode'] == '1') {
      $setarr = array(
        'uid' => $uid,
        'username' => $username,
        'dateline' => $_G['timestamp'],
        'message' => $todaysay . $lang['fromsign'],
        'ip' => $_G['clientip'],
        'status' => 0,
      );
      $doid = C::t('home_doing')->insert($setarr, 1);
      $setarr2 = array(
        'appid' => '',
        'icon' => 'doing',
        'uid' => $uid,
        'username' => $username,
        'dateline' => $_G['timestamp'],
        'title_template' => lang('feed', 'feed_doing_title'),
        'title_data' => daddslashes(serialize(dstripslashes(array('message' => $todaysay . $lang['fromsign'])))),
        'body_template' => '',
        'body_data' => '',
        'id' => $doid,
        'idtype' => 'doid'
      );
      C::t('home_doing')->insert($setarr2, 1);
    }
    if ($var['sync_follow'] && $_GET['qdmode'] == '1' && $_G['setting']['followforumid']) {
      $tofid = $_G['setting']['followforumid'];
      $synctodaysay = dhtmlspecialchars($todaysay);
      $thread_param = array(
        'isgroup' => '0',
        'status' => '512',
        'closed' => '1',
        'highlight' => '1',
        'moderated' => '1',
        'attachment' => '0',
        'special' => '0',
        'digest' => '0',
        'displayorder' => '0',
        'lastposter' => $username,
        'lastpost' => $_G['timestamp'],
        'dateline' => $_G['timestamp'],
        'subject' => $synctodaysay,
        'authorid' => $uid,
        'author' => $username,
        'sortid' => '0',
        'typeid' => '0',
        'price' => '0',
        'readperm' => '0',
        'posttableid' => '0',
        'fid' => $tofid
      );
      $synctid = C::t('forum_thread')->insert($thread_param, true);
      $syncpid = insertpost(array('fid' => $tofid, 'tid' => $synctid, 'first' => '1', 'author' => $username, 'authorid' => $uid, 'subject' => $synctodaysay, 'dateline' => $_G['timestamp'], 'message' => $synctodaysay, 'useip' => $_G['clientip'], 'invisible' => '0', 'anonymous' => '0', 'usesig' => '0', 'htmlon' => '0', 'bbcodeoff' => '0', 'smileyoff' => '0', 'parseurloff' => '0', 'attachment' => '0'));
      updatepostcredits('+', $uid, 'post', $_G['setting']['followforumid']);
      updateforumcount($_G['setting']['followforumid']);
      $feedcontent = array(
        'tid' => $synctid,
        'content' => $synctodaysay,
      );
      C::t('forum_threadpreview')->insert($feedcontent);
      $followfeed = array(
        'uid' => $uid,
        'username' => $username,
        'tid' => $synctid,
        'note' => '',
        'dateline' => TIMESTAMP
      );
      C::t('home_follow_feed')->insert($followfeed, true);
      C::t('common_member_count')->increase($uid, array('feeds' => 1));
    }
    if ($var['sync_sign'] && $_G['group']['maxsigsize']) {
      $signhtml = cutstr(strip_tags($todaysay . $lang['fromsign']), $_G['group']['maxsigsize']);
      C::t('common_member_field_forum')->update($uid, array('sightml' => $signhtml));
    }
  }
  $extreward = explode("/hhf/", str_replace(array("\r\n", "\n", "\r"), '/hhf/', $var['jlmain']));
  $extreward_num = count($extreward);
  if ($num <= ($extreward_num - 1)) {
    list($exacr, $exacz) = explode("|", $extreward[$num]);
    $psc = $num + 1;
    if ($exacr && $exacz && $ifreward) {
      updatemembercount($uid, array($exacr => $exacz));
    }
  }
  $stats = C::t('#dsu_paulsign#dsu_paulsignset')->fetch('1');
  if ($var['qdtype'] == '2') {
    $thread = C::t('forum_thread')->fetch($var['tidnumber']);
    $hft = dgmdate($_G['timestamp'], 'Y-m-d H:i', $var['tos']);
    if ($num >= 0 && ($num <= ($extreward_num - 1)) && $exacr && $exacz) {
      $message = "[quote][size=2][color=gray][color=teal] [/color][color=gray]{$lang['tsn_01']}[/color] [color=darkorange]{$hft}[/color] {$lang['tsn_02']}[color=red]{$lang['tsn_03']}[/color][color=darkorange]{$lang['tsn_04']}{$psc}{$lang['tsn_05']}[/color]{$lang['tsn_06']} [/color][color=gray]{$_G['setting']['extcredits'][$var['nrcredit']]['title']} [/color][color=darkorange]{$credit}[/color][color=gray]{$_G['setting']['extcredits'][$var['nrcredit']]['unit']}[/color][color=gray]{$lang['tsn_17']}[/color] [color=gray]{$_G['setting']['extcredits'][$exacr]['title']} [/color][color=darkorange]{$exacz}[/color][color=gray]{$_G['setting']['extcredits'][$exacr]['unit']}.{$another_vip}[/color][/color][/size][/quote][size=3][color=dimgray]{$lang['tsn_07']}[color=red]{$todaysay}[/color]{$lang['tsn_08']}[/color][/size]";
    } else {
      $message = "[quote][size=2][color=gray][color=teal] [/color][color=gray]{$lang['tsn_01']}[/color] [color=darkorange]{$hft}[/color] {$lang['tsn_09']}{$lang['tsn_06']} [/color][color=gray]{$_G['setting']['extcredits'][$var['nrcredit']]['title']} [/color][color=darkorange]{$credit} [/color][color=gray]{$_G['setting']['extcredits'][$var['nrcredit']]['unit']}.{$another_vip}[/color][/size][/quote][size=3][color=dimgray]{$lang['tsn_07']}[color=red]{$todaysay}[/color]{$lang['tsn_08']}[/color][/size]";
    }
    $pid = insertpost(array('fid' => $thread['fid'], 'tid' => $var['tidnumber'], 'first' => '0', 'author' => $username, 'authorid' => $uid, 'subject' => '', 'dateline' => $_G['timestamp'], 'message' => $message, 'useip' => $_G['clientip'], 'invisible' => '0', 'anonymous' => '0', 'usesig' => '0', 'htmlon' => '0', 'bbcodeoff' => '0', 'smileyoff' => '0', 'parseurloff' => '0', 'attachment' => '0'));
    C::t('forum_thread')->update($var['tidnumber'], array('lastposter' => $username, 'lastpost' => $_G['timestamp']));
    C::t('forum_thread')->increase($var['tidnumber'], array('replies' => 1));
    updatepostcredits('+', $uid, 'reply', $thread['fid']);
    $lastpost = "$thread[tid]\t" . addslashes($thread['subject']) . "\t$_G[timestamp]\t$username";
    C::t('forum_forum')->update($thread['fid'], array('lastpost' => $lastpost));
    C::t('forum_forum')->update_forum_counter($thread['fid'], 0, 1, 1);
    $tidnumber = $var['tidnumber'];
  } elseif ($var['qdtype'] == '3') {
    if ($num == '0' || $stats['qdtidnumber'] == '0') {
      $subject = str_replace(array('{m}', '{d}', '{y}', '{bbname}', '{author}'), array(dgmdate($_G['timestamp'], 'n', $var['tos']), dgmdate($_G['timestamp'], 'j', $var['tos']), dgmdate($_G['timestamp'], 'Y', $var['tos']), $_G['setting']['bbname'], $username), $var['title_thread']);
      $hft = dgmdate($_G['timestamp'], 'Y-m-d H:i', $var['tos']);
      if ($exacr && $exacz) {
        $message = "[quote][size=2][color=dimgray]{$lang['tsn_10']}[/color][url={$_G['siteurl']}plugin.php?id=dsu_paulsign:sign][color=darkorange]{$lang['tsn_11']}[/color][/url][color=dimgray]{$lang['tsn_12']}[/color][/size][/quote][quote][size=2][color=gray][color=teal] [/color][color=gray]{$lang['tsn_01']}[/color] [color=darkorange]{$hft}[/color] {$lang['tsn_02']}[color=red]{$lang['tsn_03']}[/color][color=darkorange]{$lang['tsn_04']}{$lang['tsn_13']}{$lang['tsn_05']}[/color]{$lang['tsn_06']} [/color][color=gray]{$_G['setting']['extcredits'][$var['nrcredit']]['title']} [/color][color=darkorange]{$credit}[/color][color=gray]{$_G['setting']['extcredits'][$var['nrcredit']]['unit']}[/color][color=gray]{$lang['tsn_17']}[/color] [color=gray]{$_G['setting']['extcredits'][$exacr]['title']} [/color][color=darkorange]{$exacz}[/color][color=gray]{$_G['setting']['extcredits'][$exacr]['unit']}.{$another_vip}[/color][/color][/size][/quote][size=3][color=dimgray]{$lang['tsn_07']}[color=red]{$todaysay}[/color]{$lang['tsn_08']}[/color][/size]";
      } else {
        $message = "[quote][size=2][color=dimgray]{$lang['tsn_10']}[/color][url={$_G['siteurl']}plugin.php?id=dsu_paulsign:sign][color=darkorange]{$lang['tsn_11']}[/color][/url][color=dimgray]{$lang['tsn_12']}[/color][/size][/quote][quote][size=2][color=gray][color=teal] [/color][color=gray]{$lang['tsn_01']}[/color] [color=darkorange]{$hft}[/color] {$lang['tsn_02']}[color=red]{$lang['tsn_03']}[/color][color=darkorange]{$lang['tsn_04']}{$lang['tsn_13']}{$lang['tsn_05']}[/color]{$lang['tsn_06']} [/color][color=gray]{$_G['setting']['extcredits'][$var['nrcredit']]['title']} [/color][color=darkorange]{$credit}[/color][color=gray]{$_G['setting']['extcredits'][$var['nrcredit']]['unit']}.{$another_vip}[/color][/color][/size][/quote][size=3][color=dimgray]{$lang['tsn_07']}[color=red]{$todaysay}[/color]{$lang['tsn_08']}[/color][/size]";
      }
      $thread_param = array(
        'isgroup' => '0',
        'status' => '0',
        'closed' => '1',
        'highlight' => '1',
        'moderated' => '1',
        'attachment' => '0',
        'special' => '0',
        'digest' => '0',
        'displayorder' => '0',
        'lastposter' => $username,
        'lastpost' => $_G['timestamp'],
        'dateline' => $_G['timestamp'],
        'subject' => $subject,
        'authorid' => $uid,
        'author' => $username,
        'sortid' => '0',
        'typeid' => $var['qdtypeid'],
        'price' => '0',
        'readperm' => '0',
        'posttableid' => '0',
        'fid' => $var['fidnumber']
      );
      $tid = C::t('forum_thread')->insert($thread_param, true);
      C::t('#dsu_paulsign#dsu_paulsignset')->update('1', array('qdtidnumber' => $tid));
      $pid = insertpost(array('fid' => $var['fidnumber'], 'tid' => $tid, 'first' => '1', 'author' => $username, 'authorid' => $uid, 'subject' => $subject, 'dateline' => $_G['timestamp'], 'message' => $message, 'useip' => $_G['clientip'], 'invisible' => '0', 'anonymous' => '0', 'usesig' => '0', 'htmlon' => '0', 'bbcodeoff' => '0', 'smileyoff' => '0', 'parseurloff' => '0', 'attachment' => '0', ));
      $expiration = $_G['timestamp'] + 86400;
      $threadmod_param1 = array(
        'tid' => $tid,
        'uid' => $uid,
        'username' => $username,
        'dateline' => $_G['timestamp'],
        'action' => 'EHL',
        'expiration' => $expiration,
        'status' => '1'
      );
      $threadmod_param2 = array(
        'tid' => $tid,
        'uid' => $uid,
        'username' => $username,
        'dateline' => $_G['timestamp'],
        'action' => 'CLS',
        'expiration' => '0',
        'status' => '1'
      );
      C::t('forum_threadmod')->insert($threadmod_param1);
      C::t('forum_threadmod')->insert($threadmod_param2);
      updatepostcredits('+', $uid, 'post', $var['fidnumber']);
      $lastpost = "$tid\t" . addslashes($subject) . "\t$_G[timestamp]\t$username";
      C::t('forum_forum')->update($var['fidnumber'], array('lastpost' => $lastpost));
      C::t('forum_forum')->update_forum_counter($var['fidnumber'], 1, 1, 1);
      $tidnumber = $tid;
    } else {
      $tidnumber = $stats['qdtidnumber'];
      $thread = C::t('forum_thread')->fetch($tidnumber);
      $hft = dgmdate($_G['timestamp'], 'Y-m-d H:i', $var['tos']);
      if ($num >= 1 && ($num <= ($extreward_num - 1)) && $exacr && $exacz) {
        $message = "[quote][size=2][color=gray][color=teal] [/color][color=gray]{$lang['tsn_01']}[/color] [color=darkorange]{$hft}[/color] {$lang['tsn_02']}[color=red]{$lang['tsn_03']}[/color][color=darkorange]{$lang['tsn_04']}{$psc}{$lang['tsn_05']}[/color]{$lang['tsn_06']} [/color][color=gray]{$_G['setting']['extcredits'][$var['nrcredit']]['title']} [/color][color=darkorange]{$credit}[/color][color=gray]{$_G['setting']['extcredits'][$var['nrcredit']]['unit']}[/color][color=gray]{$lang['tsn_17']}[/color] [color=gray]{$_G['setting']['extcredits'][$exacr]['title']} [/color][color=darkorange]{$exacz}[/color][color=gray]{$_G['setting']['extcredits'][$exacr]['unit']}[/color][/color][/size][/quote][size=3][color=dimgray]{$lang['tsn_07']}[color=red]{$todaysay}[/color]{$lang['tsn_08']}[/color][/size]";
      } else {
        $message = "[quote][size=2][color=gray][color=teal] [/color][color=gray]{$lang['tsn_01']}[/color] [color=darkorange]{$hft}[/color] {$lang['tsn_09']}{$lang['tsn_06']} [/color][color=gray]{$_G['setting']['extcredits'][$var['nrcredit']]['title']} [/color][color=darkorange]{$credit} [/color][color=gray]{$_G['setting']['extcredits'][$var['nrcredit']]['unit']}[/color][/size][/quote][size=3][color=dimgray]{$lang['tsn_07']}[color=red]{$todaysay}[/color]{$lang['tsn_08']}[/color][/size]";
      }
      $pid = insertpost(array('fid' => $var['fidnumber'], 'tid' => $tidnumber, 'first' => '0', 'author' => $username, 'authorid' => $uid, 'subject' => '', 'dateline' => $_G['timestamp'], 'message' => $message, 'useip' => $_G['clientip'], 'invisible' => '0', 'anonymous' => '0', 'usesig' => '0', 'htmlon' => '0', 'bbcodeoff' => '0', 'smileyoff' => '0', 'parseurloff' => '0', 'attachment' => '0', ));
      C::t('forum_thread')->update($tidnumber, array('lastposter' => $username, 'lastpost' => $_G['timestamp']));
      C::t('forum_thread')->increase($tidnumber, array('replies' => 1));
      updatepostcredits('+', $uid, 'reply', $var['fidnumber']);
      $lastpost = "$tidnumber\t" . addslashes($thread['subject']) . "\t$_G[timestamp]\t$username";
      C::t('forum_forum')->update($var['fidnumber'], array('lastpost' => $lastpost));
      C::t('forum_forum')->update_forum_counter($var['fidnumber'], 0, 1, 1);
    }
  }
  if (memory('check')) {
    memory('set', 'dsu_pualsign_' . $uid, $_G['timestamp'], 86400);
  }
  if ($num == 0) {
    if ($stats['todayq'] > $stats['highestq']) {
      C::t('#dsu_paulsign#dsu_paulsignset')->update('1', array('highestq' => $stats['todayq']));
    }
    include_once libfile('function/stat');
    updatestat('paulsign');
    C::t('#dsu_paulsign#dsu_paulsignset')->update('1', array('yesterdayq' => $stats['todayq'], 'todayq' => '1'));
    C::t('#dsu_paulsign#dsu_paulsignemot')->clearcount();
  } else {
    C::t('#dsu_paulsign#dsu_paulsignset')->increase_todayq();
  }
  C::t('#dsu_paulsign#dsu_paulsignemot')->updatebyqdxq($mood);
  discuz_process::unlock($lockname);
  if ($var['tzopen']) {
    if ($exacr && $exacz) {
      return paulsign_result($signmode, "{$lang['tsn_14']}{$lang['tsn_03']}{$lang['tsn_04']}{$psc}{$lang['tsn_15']}{$lang['tsn_06']} {$_G['setting']['extcredits'][$var['nrcredit']]['title']} {$credit} {$_G['setting']['extcredits'][$var['nrcredit']]['unit']} {$lang['tsn_16']} {$_G['setting']['extcredits'][$exacr]['title']} {$exacz} {$_G['setting']['extcredits'][$exacr]['unit']}." . $another_vip, "forum.php?mod=redirect&tid={$tidnumber}&goto=lastpost#lastpost");
    }
    return paulsign_result($signmode, "{$lang['tsn_18']} {$_G['setting']['extcredits'][$var['nrcredit']]['title']} {$credit} {$_G['setting']['extcredits'][$var['nrcredit']]['unit']}." . $another_vip, "forum.php?mod=redirect&tid={$tidnumber}&goto=lastpost#lastpost");

  } else {
    if ($exacr && $exacz) {
      return paulsign_result($signmode, "{$lang['tsn_14']}{$lang['tsn_03']}{$lang['tsn_04']}{$psc}{$lang['tsn_15']}{$lang['tsn_06']} {$_G['setting']['extcredits'][$var['nrcredit']]['title']} {$credit} {$_G['setting']['extcredits'][$var['nrcredit']]['unit']} {$lang['tsn_16']} {$_G['setting']['extcredits'][$exacr]['title']} {$exacz} {$_G['setting']['extcredits'][$exacr]['unit']}." . $another_vip, "plugin.php?id=dsu_paulsign:sign");
    }
    return paulsign_result($signmode, "{$lang['tsn_18']} {$_G['setting']['extcredits'][$var['nrcredit']]['title']} {$credit} {$_G['setting']['extcredits'][$var['nrcredit']]['unit']}." . $another_vip, "plugin.php?id=dsu_paulsign:sign");
  }
}

function paulsign_result($signmode, $msg, $treferer = '')
{
  global $_G;
  if ($signmode == 1) {
    if (defined('IN_MOBILE')) {
      include template('dsu_paulsign:float');
      dexit();
    } else {
      include template('dsu_paulsign:float');
      dexit();
    }
  } else {
    return $msg;
  }
}
