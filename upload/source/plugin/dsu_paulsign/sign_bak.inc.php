<?php

!defined('IN_DISCUZ') && exit('Access Denied');
!defined('IN_ADMINCP') && exit('Access Denied');
loadcache('pluginlanguage_script');
$lang = $_G['cache']['pluginlanguage_script']['dsu_paulsign'];
if (submitcheck('backup')) {
  $filename = isset($_GET['filename']) && is_scalar($_GET['filename']) ? trim((string)$_GET['filename']) : '';
  if ($filename === '' || !preg_match('/^[A-Za-z0-9_]+$/', $filename)) {
    cpmsg($lang['bak_01']);
  }
  $backup_dir = DISCUZ_ROOT . './data/paulsign_bak/';
  if (!is_dir($backup_dir) && !@mkdir($backup_dir, 0755, true)) {
    cpmsg($lang['bak_02']);
  }
  $file = $backup_dir . $filename . '.pb';
  @touch($file);
  if (!is_writeable($file)) {
    cpmsg($lang['bak_02']);
  }
  $out_arr = array();
  // range() returns rows; fetch_all_field() only returns column metadata.
  $out_arr['main'] = C::t('#dsu_paulsign#dsu_paulsign')->range();
  $out_arr['set'] = C::t('#dsu_paulsign#dsu_paulsignset')->range();
  $out_arr['emot'] = C::t('#dsu_paulsign#dsu_paulsignemot')->range();
  $output = serialize($out_arr);
  file_put_contents($file, $output);
  cpmsg($lang['bak_03'], '', 'succeed');
  dexit();
} elseif (submitcheck('restore', 2)) {
  $filename = isset($_GET['filename']) && is_scalar($_GET['filename']) ? basename((string)$_GET['filename']) : '';
  if (!preg_match('/^[A-Za-z0-9_]+\.pb$/', $filename)) {
    cpmsg($lang['bak_04']);
  }
  $backup_dir = realpath(DISCUZ_ROOT . './data/paulsign_bak/');
  if ($backup_dir === false || !is_dir($backup_dir)) {
    cpmsg($lang['bak_04']);
  }
  $file = $backup_dir ? realpath($backup_dir . DIRECTORY_SEPARATOR . $filename) : false;
  if (!$file || dirname($file) !== $backup_dir) {
    cpmsg($lang['bak_04']);
  }
  if (!is_file($file) || !is_readable($file)) {
    cpmsg($lang['bak_04']);
  }
  $data_str = file_get_contents($file);
  $data = is_string($data_str) ? @unserialize($data_str, array('allowed_classes' => false)) : false;
  if (!is_array($data) || !isset($data['main'], $data['set'], $data['emot']) || !is_array($data['main']) || !is_array($data['set']) || !is_array($data['emot'])) {
    cpmsg($lang['bak_04']);
  }
  $main = $data['main'];
  $set = $data['set'];
  $emot = $data['emot'];
  C::t('#dsu_paulsign#dsu_paulsign')->cleartable();
  C::t('#dsu_paulsign#dsu_paulsignset')->cleartable();
  C::t('#dsu_paulsign#dsu_paulsignemot')->cleartable();
  foreach ($main as $line) {
    if (!is_array($line)) {
      continue;
    }
    $qdxq = isset($line['qdxq']) && is_scalar($line['qdxq']) && preg_match('/^[A-Za-z0-9_]{1,5}$/', (string)$line['qdxq']) ? (string)$line['qdxq'] : 'kx';
    $todaysay = isset($line['todaysay']) && is_scalar($line['todaysay']) ? substr((string)$line['todaysay'], 0, 100) : '';
    C::t('#dsu_paulsign#dsu_paulsign')->insert(array(
      'uid' => intval($line['uid']),
      'time' => intval($line['time']),
      'days' => intval($line['days']),
      'lasted' => intval($line['lasted']),
      'mdays' => intval($line['mdays']),
      'reward' => intval($line['reward']),
      'lastreward' => intval($line['lastreward']),
      'qdxq' => $qdxq,
      'todaysay' => $todaysay,
    ));
  }
  foreach ($set as $line) {
    if (is_array($line)) {
      C::t('#dsu_paulsign#dsu_paulsignset')->insert(array(
        'id' => intval($line['id']),
        'todayq' => intval($line['todayq']),
        'yesterdayq' => intval($line['yesterdayq']),
        'highestq' => intval($line['highestq']),
        'qdtidnumber' => intval($line['qdtidnumber']),
      ));
    }
  }
  foreach ($emot as $line) {
    if (!is_array($line)) {
      continue;
    }
    $qdxq = isset($line['qdxq']) && is_scalar($line['qdxq']) && preg_match('/^[A-Za-z0-9_]{1,5}$/', (string)$line['qdxq']) ? (string)$line['qdxq'] : '';
    $name = isset($line['name']) && is_scalar($line['name']) ? substr((string)$line['name'], 0, 20) : '';
    if ($qdxq === '' || $name === '') {
      continue;
    }
    C::t('#dsu_paulsign#dsu_paulsignemot')->insert(array(
      'id' => intval($line['id']),
      'displayorder' => intval($line['displayorder']),
      'qdxq' => $qdxq,
      'count' => intval($line['count']),
      'name' => $name,
    ));
  }
  require_once libfile('function/cache');
  $cacheechos = array();
  $cacheechokeys = array();
  $query = C::t('#dsu_paulsign#dsu_paulsignemot')->getemotdata();
  foreach ($query as $cacheecho) {
    $cacheechos[$cacheecho['qdxq']] = $cacheecho;
    $cacheechokeys[] = $cacheecho['qdxq'];
  }
  C::t('common_setting')->update('paulsign_emot', $cacheechos);
  updatecache('setting');
  cpmsg($lang['bak_05'], '', 'succeed');
  dexit();
}
showtableheader($lang['bak_06']);
showformheader("plugins&operation=config&identifier=dsu_paulsign&pmod=sign_bak");
showsetting($lang['bak_07'], 'filename', random(10), 'text', '', '', $lang['bak_08'] . ' /data/paulsign_bak/ ' . $lang['bak_09']);
showsubmit('backup', $lang['bak_10']);
showformfooter();
showtablefooter();
showtableheader($lang['bak_11']);
if (!is_dir(DISCUZ_ROOT . './data/paulsign_bak/')) {
  @mkdir(DISCUZ_ROOT . './data/paulsign_bak/', 0777);
  @touch(DISCUZ_ROOT . "./data/paulsign_bak/index.htm");
}
$backup_dir = @dir(DISCUZ_ROOT . './data/paulsign_bak/');
$flag = false;
while (false !== ($entry = $backup_dir->read())) {
  $file = pathinfo($entry);
  if ($file['extension'] == 'pb' && $file['basename']) {
    showtablerow(
      '',
      '',
      array(
        $lang['bak_12'] . $file['basename'],
        dgmdate(filemtime(DISCUZ_ROOT . "./data/paulsign_bak/{$file['basename']}"), 'u'),
        '<a href="?action=plugins&operation=config&identifier=dsu_paulsign&pmod=sign_bak&filename=' . $file['basename'] . '&restore=yes&formhash=' . FORMHASH . '">' . $lang['bak_13'] . '</a>',
      )
    );
    $flag = true;
  }
}
if (!$flag) {
  showtablerow('', '', array('<font color="red">' . $lang['bak_14'] . '</font>'));
}
showtablefooter();
