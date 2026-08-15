<?php

if (!defined('IN_DISCUZ') || $_G['adminid'] !== '1') {
  exit('Access Denied');
}
for ($i = 1; $i < 15; $i++) {
  $day_display[$i] = dgmdate(TIMESTAMP - 86400 * (14 - $i), 'm-d');
  $day_array[] = dgmdate(TIMESTAMP - 86400 * (14 - $i), 'Ymd');
}
foreach (C::t('common_stat')->fetch_all(dgmdate(TIMESTAMP - 86400 * 14, 'Ymd'), dgmdate(TIMESTAMP, 'Ymd'), 'daytime,paulsign,login') as $result) {
  $paulsign[$result['daytime']] = $result['paulsign'];
  $login[$result['daytime']] = $result['login'];
}
$stats = C::t('#dsu_paulsign#dsu_paulsignset')->fetch('1');
$today = dgmdate(TIMESTAMP, 'Ymd');
$paulsign[$today] = $stats['todayq'];
foreach ($day_array as $day) {
  $paulsign_display[] = isset($paulsign[$day]) ? intval($paulsign[$day]) : 0;
  $login_display[] = isset($login[$day]) ? intval($login[$day]) : 0;
}
$max = max(1, (int)ceil(max(max($paulsign_display), max($login_display)) * 1.2));
header('Content-Type: application/json; charset=' . CHARSET);
header('Cache-Control: no-store, private');
echo json_encode(array(
  'labels' => array_values($day_display),
  'login' => $login_display,
  'sign' => $paulsign_display,
  'max' => $max,
), JSON_UNESCAPED_UNICODE);
exit;
