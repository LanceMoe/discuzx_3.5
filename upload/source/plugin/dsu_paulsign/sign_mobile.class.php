<?php

if (!defined('IN_DISCUZ')) {
  exit('Access Denied');
}
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
class mobileplugin_dsu_paulsign
{
  function dsu_signtz()
  {
    dheader('Location: plugin.php?id=dsu_paulsign:sign&mobile=yes');
  }

  function global_header_mobile()
  {
    global $_G, $show_message;
    $var = $_G['cache']['plugin']['dsu_paulsign'];
    $signtime = 0;
    if (defined('IN_dsu_paulsign') || $show_message || defined('IN_dsu_paulsc') || !$_G['uid'] || !$var['ifopen'] || !$var['wap_sign']) {
      return '';
    }
    $tdtime = gmmktime(0, 0, 0, dgmdate($_G['timestamp'], 'n', $var['tos']), dgmdate($_G['timestamp'], 'j', $var['tos']), dgmdate($_G['timestamp'], 'Y', $var['tos'])) - $var['tos'] * 3600;
    $allowmem = memory('check');
    $tzgroupids = array_values(array_filter(array_map('intval', dsu_paulsign_unserialize_array($var['tzgroupid'])), function($gid) { return $gid > 0; }));
    $groupids = array_values(array_filter(array_map('intval', dsu_paulsign_unserialize_array($var['groups'])), function($gid) { return $gid > 0; }));
    $banuids = array_values(array_filter(array_map('intval', explode(',', (string)$var['ban'])), function($banuid) { return $banuid > 0; }));
    if ($var['ftopen'] && in_array((int)$_G['groupid'], $tzgroupids, true) && !in_array((int)$_G['uid'], $banuids, true) && in_array((int)$_G['groupid'], $groupids, true)) {
      if ($allowmem && $var['mcacheopen']) {
        $signtime = memory('get', 'dsu_pualsign_' . $_G['uid']);
      }
      $htime = dgmdate($_G['timestamp'], 'H', $var['tos']);
      if (!$signtime) {
        $qiandaodb = C::t('#dsu_paulsign#dsu_paulsign')->fetch($_G['uid']);
        $signtime = $qiandaodb['time'];
        if ($qiandaodb) {
          if ($allowmem && $var['mcacheopen']) {
            memory('set', 'dsu_pualsign_' . $_G['uid'], $qiandaodb['time'], 86400);
          }
          if ($qiandaodb['time'] < $tdtime) {
            if ($var['timeopen']) {
              if (!($htime < $var['stime']) && !($htime > $var['ftime'])) {
                return $this->dsu_signtz();
              }
            } else {
              return $this->dsu_signtz();
            }
          }
        } else {
          if ($var['mintdpost'] <= C::t('#dsu_paulsign#dsu_paulsign')->getuserpost($_G['uid'])) {
            if ($var['timeopen']) {
              if (!($htime < $var['stime']) && !($htime > $var['ftime'])) {
                return $this->dsu_signtz();
              }
            } else {
              return $this->dsu_signtz();
            }
          }
        }
      } else {
        if ($signtime < $tdtime) {
          if ($var['timeopen']) {
            if (!($htime < $var['stime']) && !($htime > $var['ftime'])) {
              return $this->dsu_signtz();
            }
          } else {
            return $this->dsu_signtz();
          }
        }
      }
    }
    //return '<a href="plugin.php?id=dsu_paulsign:sign">'.lang('plugin/dsu_paulsign', 'name').'</a>';
  }
}
