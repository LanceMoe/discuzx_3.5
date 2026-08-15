<?php
/*
  dsu_paulsign Main
*/
include_once DISCUZ_ROOT . './source/plugin/dsu_paulsign/sign.func.php';

!defined('IN_DISCUZ') && exit('Access Denied');
define('IN_dsu_paulsign', '1');
$var = $_G['cache']['plugin']['dsu_paulsign'];
$tdtime = gmmktime(0, 0, 0, dgmdate($_G['timestamp'], 'n', $var['tos']), dgmdate($_G['timestamp'], 'j', $var['tos']), dgmdate($_G['timestamp'], 'Y', $var['tos'])) - $var['tos'] * 3600;
$htime = dgmdate($_G['timestamp'], 'H', $var['tos']);
loadcache('pluginlanguage_script');
$lang = $_G['cache']['pluginlanguage_script']['dsu_paulsign'];
$handlekey = json_encode(isset($_GET['handlekey']) && is_scalar($_GET['handlekey']) ? (string)$_GET['handlekey'] : '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
list($lv1name, $lv2name, $lv3name, $lv4name, $lv5name, $lv6name, $lv7name, $lv8name, $lv9name, $lv10name, $lvmastername) = array_map('dhtmlspecialchars', array_pad(explode("/hhf/", str_replace(array("\r\n", "\n", "\r"), '/hhf/', $var['lvtext'])), 11, ''));
$fastreplytexts = array_values(array_filter(explode("/hhf/", str_replace(array("\r\n", "\n", "\r"), '/hhf/', $var['fastreplytext'])), 'strlen'));
$plgroups = dsu_paulsign_unserialize_array($var['plgroups']);
$plgroups2 = dsu_paulsign_unserialize_array($var['plgroups']);
$plgroups = array_values(array_filter(array_map('intval', $plgroups), function($gid) { return $gid > 0; }));
$plgroups2 = $plgroups;
$banuids = array_values(array_filter(array_map('intval', explode(',', (string)$var['ban'])), function($uid) { return $uid > 0; }));
$qiandaodb = C::t('#dsu_paulsign#dsu_paulsign')->fetch($_G['uid']);
$stats = C::t('#dsu_paulsign#dsu_paulsignset')->fetch('1');
$lastmonth = dgmdate(C::t('#dsu_paulsign#dsu_paulsign')->getlasttime(), 'm', $var['tos']);
$nowmonth = dgmdate($_G['timestamp'], 'm', $var['tos']);
$emots = dsu_paulsign_emots($_G['setting']['paulsign_emot']);
$mobileurl = defined('IN_MOBILE') ? (IN_MOBILE == 2 ? '&mobile=2' : '&mobile=yes') : '';
if ($nowmonth != $lastmonth) {
  C::t('#dsu_paulsign#dsu_paulsign')->clearmdays();
}
if (empty($_G['uid'])) {
  showmessage('to_login', 'member.php?mod=logging&action=login', array(), array('showmsg' => true, 'login' => 1));
}
if (!$var['ifopen'] && $_G['adminid'] != 1) {
  showmessage($var['plug_clsmsg'], 'index.php');
}
if ($var['plopen'] && $plgroups) {
  $mccs = C::t('common_usergroup')->fetch_all($plgroups2);
}
if ($_GET['operation'] == 'zong' || $_GET['operation'] == 'month' || $_GET['operation'] == '' || ($_GET['operation'] == 'zdyhz' && $var['plopen']) || ($_GET['operation'] == 'rewardlist' && $var['rewardlistopen'])) {
  if ($_GET['operation'] == 'month') {
    $num = C::t('#dsu_paulsign#dsu_paulsign')->getcount('mdays', '0', 'notin');
    $page = max(1, intval($_GET['page']));
    $start_limit = ($page - 1) * 10;
    $multipage = multi($num, 10, $page, "plugin.php?id=dsu_paulsign:sign&operation={$_GET['operation']}{$mobileurl}");
  } elseif ($_GET['operation'] == 'zdyhz' || $_GET['operation'] == 'rewardlist') {
  } elseif ($_GET['operation'] == '' && $var['qddesc']) {
    $num = C::t('#dsu_paulsign#dsu_paulsign')->getcount('time', $tdtime, '>=');
    $page = max(1, intval($_GET['page']));
    $start_limit = ($page - 1) * 10;
    $multipage = multi($num, 10, $page, "plugin.php?id=dsu_paulsign:sign&operation={$_GET['operation']}{$mobileurl}");
  } else {
    $num = C::t('#dsu_paulsign#dsu_paulsign')->getcount();
    $page = max(1, intval($_GET['page']));
    $start_limit = ($page - 1) * 10;
    $multipage = multi($num, 10, $page, "plugin.php?id=dsu_paulsign:sign&operation={$_GET['operation']}{$mobileurl}");
  }
  if ($_GET['operation'] == 'zong') {
    $list_type = 'q.days';
  } elseif ($_GET['operation'] == 'month') {
    $list_type = 'q.mdays';
  } elseif ($_GET['operation'] == 'zdyhz') {
    $qdgroupid = isset($_GET['qdgroupid']) && is_scalar($_GET['qdgroupid']) ? intval($_GET['qdgroupid']) : 0;
    $plgroupids = array_map('intval', $plgroups2);
    if (in_array($qdgroupid, $plgroupids, true)) {
      $num = C::t('#dsu_paulsign#dsu_paulsign')->getcount_customgroup(array($qdgroupid));
      $page = max(1, intval($_GET['page']));
      $start_limit = ($page - 1) * 10;
      $multipage = multi($num, 10, $page, "plugin.php?id=dsu_paulsign:sign&operation={$_GET['operation']}&qdgroupid={$qdgroupid}{$mobileurl}", 0);
      $list_type = 'q.time';
      $list_ifgroup = '1';
      $list_gids = array($qdgroupid);
    } else {
      $num = C::t('#dsu_paulsign#dsu_paulsign')->getcount_customgroup($plgroups);
      $page = max(1, intval($_GET['page']));
      $start_limit = ($page - 1) * 10;
      $multipage = multi($num, 10, $page, "plugin.php?id=dsu_paulsign:sign&operation={$_GET['operation']}{$mobileurl}", 0);
      $list_type = 'q.time';
      $list_ifgroup = '1';
      $list_gids = $plgroups;
    }
  } elseif ($var['rewardlistopen'] && $_GET['operation'] == 'rewardlist') {
    $list_type = 'q.reward';
    $start_limit = '0';
  } elseif ($_GET['operation'] == '') {
    $list_type = 'q.time';
    if ($var['qddesc']) {
      $list_turn = 'ASC';
      $list_tdtime = $tdtime;
    }
  }
  $mrcs = array();
  foreach (C::t('#dsu_paulsign#dsu_paulsign')->getsignlist($list_type, $list_turn, $start_limit, $list_ifgroup, $list_gids, $list_tdtime) as $mrc) {
    $mrc['if'] = $mrc['time'] < $tdtime ? "<span class=gray>" . $lang['tdno'] . "</span>" : "<font color=green>" . $lang['tdyq'] . "</font>";
    $mrc['time'] = dgmdate($mrc['time'], 'Y-m-d H:i');
    $mrc['level'] = '';
    if (!isset($emots[$mrc['qdxq']])) {
      $mrc['qdxq'] = '';
    }
    if ($mrc['days'] >= '1500') {
      $mrc['level'] = "[LV.Master]{$lvmastername}";
    } elseif ($mrc['days'] >= '750') {
      $mrc['level'] = "[LV.10]{$lv10name}";
    } elseif ($mrc['days'] >= '365') {
      $mrc['level'] = "[LV.9]{$lv9name}";
    } elseif ($mrc['days'] >= '240') {
      $mrc['level'] = "[LV.8]{$lv8name}";
    } elseif ($mrc['days'] >= '120') {
      $mrc['level'] = "[LV.7]{$lv7name}";
    } elseif ($mrc['days'] >= '60') {
      $mrc['level'] = "[LV.6]{$lv6name}";
    } elseif ($mrc['days'] >= '30') {
      $mrc['level'] = "[LV.5]{$lv5name}";
    } elseif ($mrc['days'] >= '15') {
      $mrc['level'] = "[LV.4]{$lv4name}";
    } elseif ($mrc['days'] >= '7') {
      $mrc['level'] = "[LV.3]{$lv3name}";
    } elseif ($mrc['days'] >= '3') {
      $mrc['level'] = "[LV.2]{$lv2name}";
    } elseif ($mrc['days'] >= '1') {
      $mrc['level'] = "[LV.1]{$lv1name}";
    }
    $mrcs[] = $mrc;
  }
  $emottops = C::t('#dsu_paulsign#dsu_paulsignemot')->getemotrank();
} elseif ($_GET['operation'] == 'ban') {
  if ($_GET['formhash'] != FORMHASH) {
    showmessage('undefined_action', NULL);
  }
  if ($_G['adminid'] == 1) {
    C::t('#dsu_paulsign#dsu_paulsign')->update(intval($_GET['banuid']), array('todaysay' => $lang['ban_01']));
    showmessage("{$lang['ban_02']}", dreferer());
  } else {
    showmessage("{$lang['ban_03']}", dreferer());
  }
} elseif ($_GET['operation'] == 'qiandao') {
  paulsign_do($_G['uid'], 1);
}
if ($qiandaodb['days'] >= '1500') {
  $q['level'] = "{$lang['level']}<font color=green><b>[LV.Master]{$lvmastername}</b></font> .";
} elseif ($qiandaodb['days'] >= '750') {
  $q['lvqd'] = 1500 - $qiandaodb['days'];
  $q['level'] = "{$lang['level']}<font color=green><b>[LV.10]{$lv10name}</b></font>{$lang['level2']} <font color=#FF0000><b>{$q['lvqd']}</b></font> {$lang['level3']} <font color=#FF0000><b>[LV.Master]{$lvmastername}</b></font> .";
} elseif ($qiandaodb['days'] >= '365') {
  $q['lvqd'] = 750 - $qiandaodb['days'];
  $q['level'] = "{$lang['level']}<font color=green><b>[LV.9]{$lv9name}</b></font>{$lang['level2']} <font color=#FF0000><b>{$q['lvqd']}</b></font> {$lang['level3']} <font color=#FF0000><b>[LV.10]{$lv10name}</b></font> .";
} elseif ($qiandaodb['days'] >= '240') {
  $q['lvqd'] = 365 - $qiandaodb['days'];
  $q['level'] = "{$lang['level']}<font color=green><b>[LV.8]{$lv8name}</b></font>{$lang['level2']} <font color=#FF0000><b>{$q['lvqd']}</b></font> {$lang['level3']} <font color=#FF0000><b>[LV.9]{$lv9name}</b></font> .";
} elseif ($qiandaodb['days'] >= '120') {
  $q['lvqd'] = 240 - $qiandaodb['days'];
  $q['level'] = "{$lang['level']}<font color=green><b>[LV.7]{$lv7name}</b></font>{$lang['level2']} <font color=#FF0000><b>{$q['lvqd']}</b></font> {$lang['level3']} <font color=#FF0000><b>[LV.8]{$lv8name}</b></font> .";
} elseif ($qiandaodb['days'] >= '60') {
  $q['lvqd'] = 120 - $qiandaodb['days'];
  $q['level'] = "{$lang['level']}<font color=green><b>[LV.6]{$lv6name}</b></font>{$lang['level2']} <font color=#FF0000><b>{$q['lvqd']}</b></font> {$lang['level3']} <font color=#FF0000><b>[LV.7]{$lv7name}</b></font> .";
} elseif ($qiandaodb['days'] >= '30') {
  $q['lvqd'] = 60 - $qiandaodb['days'];
  $q['level'] = "{$lang['level']}<font color=green><b>[LV.5]{$lv5name}</b></font>{$lang['level2']} <font color=#FF0000><b>{$q['lvqd']}</b></font> {$lang['level3']} <font color=#FF0000><b>[LV.6]{$lv6name}</b></font> .";
} elseif ($qiandaodb['days'] >= '15') {
  $q['lvqd'] = 30 - $qiandaodb['days'];
  $q['level'] = "{$lang['level']}<font color=green><b>[LV.4]{$lv4name}</b></font>{$lang['level2']} <font color=#FF0000><b>{$q['lvqd']}</b></font> {$lang['level3']} <font color=#FF0000><b>[LV.5]{$lv5name}</b></font> .";
} elseif ($qiandaodb['days'] >= '7') {
  $q['lvqd'] = 15 - $qiandaodb['days'];
  $q['level'] = "{$lang['level']}<font color=green><b>[LV.3]{$lv3name}</b></font>{$lang['level2']} <font color=#FF0000><b>{$q['lvqd']}</b></font> {$lang['level3']} <font color=#FF0000><b>[LV.4]{$lv4name}</b></font> .";
} elseif ($qiandaodb['days'] >= '3') {
  $q['lvqd'] = 7 - $qiandaodb['days'];
  $q['level'] = "{$lang['level']}<font color=green><b>[LV.2]{$lv2name}</b></font>{$lang['level2']} <font color=#FF0000><b>{$q['lvqd']}</b></font> {$lang['level3']} <font color=#FF0000><b>[LV.3]{$lv3name}</b></font> .";
} elseif ($qiandaodb['days'] >= '1') {
  $q['lvqd'] = 3 - $qiandaodb['days'];
  $q['level'] = "{$lang['level']}<font color=green><b>[LV.1]{$lv1name}</b></font>{$lang['level2']} <font color=#FF0000><b>{$q['lvqd']}</b></font> {$lang['level3']} <font color=#FF0000><b>[LV.2]{$lv2name}</b></font> .";
}
$q['if'] = $qiandaodb['time'] < $tdtime ? "<span class=gray>" . $lang['tdno'] . "</span>" : "<font color=green>" . $lang['tdyq'] . "</font>";
$qtime = dgmdate($qiandaodb['time'], 'Y-m-d H:i');
$navigation = $lang['name'];
$navtitle = "$navigation";
$signBuild = 'Ver 6.0 <br>&copy; <a href="https://www.iya.app/">iYa.App</a><br>';
$signadd = 'https://www.iya.app/';
if ($_G['inajax']) {
  include template('dsu_paulsign:ajaxsign');
} else {
  include template('dsu_paulsign:sign');
}
