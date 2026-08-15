<?php

!defined('IN_DISCUZ') && exit('Access Denied');
!defined('IN_ADMINCP') && exit('Access Denied');
foreach (C::t('#dsu_paulsign#dsu_paulsign')->range() as $mrc) {
  $mrc['exist'] = C::t('common_member_count')->fetch(intval($mrc['uid']));
  if (!$mrc['exist']) {
    C::t('#dsu_paulsign#dsu_paulsign')->delete(intval($mrc['uid']));
  }
}
cpmsg("Data has been Cleaned!", '', 'succeed');
