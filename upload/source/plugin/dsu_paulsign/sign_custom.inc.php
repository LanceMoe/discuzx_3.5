<?php
/*
  dsu_paulsign Import
*/
!defined('IN_DISCUZ') && exit('Access Denied');
!defined('IN_ADMINCP') && exit('Access Denied');
loadcache('pluginlanguage_script');
$lang = $_G['cache']['pluginlanguage_script']['dsu_paulsign'];
if (!submitcheck('emotsubmit')) {

  $emotechos = '';
  $query = C::t('#dsu_paulsign#dsu_paulsignemot')->getemotdata();
  foreach ($query as $emot) {
    $emotid = intval($emot['id']);
    $emotorder = intval($emot['displayorder']);
    $emotqdxq = isset($emot['qdxq']) && preg_match('/^[A-Za-z0-9_]{1,5}$/', (string)$emot['qdxq']) ? (string)$emot['qdxq'] : 'kx';
    $emotname = dhtmlspecialchars(isset($emot['name']) ? (string)$emot['name'] : '');
    $emotechos .= showtablerow('', array('class="td25"', 'class="td28"'), array(
      "<input class=\"checkbox\" type=\"checkbox\" name=\"delete[]\" value=\"$emotid\">",
      "<input type=\"text\" class=\"txt\" size=\"2\" name=\"displayorder[$emotid]\" value=\"$emotorder\">",
      "<input type=\"text\" class=\"txt\" size=\"5\" name=\"qdxq[$emotid]\" value=\"$emotqdxq\">",
      "<input type=\"text\" class=\"txt\" size=\"10\" name=\"name[$emotid]\" value=\"$emotname\"><img src=\"source/plugin/dsu_paulsign/img/emot/$emotqdxq.gif\" />"
    ), true);
  }

  echo <<<EOT
<script type="text/JavaScript">
	var rowtypedata = [
		[
			[1, '', 'td25'],
			[1, '<input type="text" class="txt" size="2" name="newdisplayorder[]" value="0">', 'td28'],
			[1, '<input type="text" class="txt" size="15" name="newqdxq[]">'],
			[1, '<input type="text" class="txt" size="15" name="newname[]">'],
		],
	];
</script>
EOT;

  showformheader("plugins&operation=config&identifier=dsu_paulsign&pmod=sign_custom&submit=1");
  showtableheader('Emotion Custom');
  showsubtitle(array('', $lang['custom_01'], $lang['custom_02'], $lang['custom_03']));
  echo $emotechos;
  echo '<tr><td></td><td colspan="4"><div><a href="###" onclick="addrow(this, 0)" class="addtr">' . $lang['custom_04'] . '</a></div></td></tr>';
  showsubmit('emotsubmit', $lang['custom_05'], $lang['custom_06']);

  showtablefooter();
  showformfooter();

} else {
  if ($_G['adminid'] !== '1' || $_GET['formhash'] !== FORMHASH) {
    cpmsg($lang['custom_07'], '', 'error');
  }
  $deleteids = array();
  if (is_array($_GET['delete'])) {
    foreach ($_GET['delete'] as $deleteid) {
      $deleteids[] = intval($deleteid);
    }
  }
  if ($deleteids) {
    C::t('#dsu_paulsign#dsu_paulsignemot')->delete($deleteids);
  }
  if (is_array($_GET['qdxq'])) {
    foreach ($_GET['qdxq'] as $id => $val) {
      $id = intval($id);
      $displayorder = isset($_GET['displayorder'][$id]) ? intval($_GET['displayorder'][$id]) : 0;
      $qdxq = isset($_GET['qdxq'][$id]) && is_scalar($_GET['qdxq'][$id]) ? trim((string)$_GET['qdxq'][$id]) : '';
      $name = isset($_GET['name'][$id]) && is_scalar($_GET['name'][$id]) ? trim((string)$_GET['name'][$id]) : '';
      if (!preg_match('/^[A-Za-z0-9_]{1,5}$/', $qdxq) || $name === '' || strlen($name) > 20) {
        cpmsg($lang['custom_08'], '', 'error');
      }
      C::t('#dsu_paulsign#dsu_paulsignemot')->update($id, array('displayorder' => $displayorder, 'qdxq' => $qdxq, 'name' => $name));
    }
  }
  if (is_array($_GET['newqdxq'])) {
    foreach ($_GET['newqdxq'] as $key => $value) {
      $newqdxq1 = is_scalar($value) ? trim((string)$value) : '';
      if (!preg_match("/^[A-Za-z0-9_]{1,5}$/", $newqdxq1)) {
        cpmsg($lang['custom_08'], '', 'error');
      }
      $newname1 = isset($_GET['newname'][$key]) && is_scalar($_GET['newname'][$key]) ? trim((string)$_GET['newname'][$key]) : '';
      if ($newname1 === '' || strlen($newname1) > 20) {
        cpmsg($lang['custom_08'], '', 'error');
      }
      if ($newqdxq1 && $newname1) {
        $ifexist = C::t('#dsu_paulsign#dsu_paulsignemot')->getemotbyqdxq($newqdxq1);
        if ($ifexist) {
          cpmsg($lang['custom_09'], '', 'error');
        }
        $data = array(
          'displayorder' => intval($_GET['newdisplayorder'][$key]),
          'qdxq' => $newqdxq1,
          'name' => $newname1,
        );
        C::t('#dsu_paulsign#dsu_paulsignemot')->insert($data);
      } elseif ($newqdxq1 && !$newname1) {
        cpmsg($lang['custom_10'], '', 'error');
      }
    }
  }
  $cacheechos = array();
  $cacheechokeys = array();
  $query = C::t('#dsu_paulsign#dsu_paulsignemot')->getemotdata();
  foreach ($query as $cacheecho) {
    $cacheechos[$cacheecho['qdxq']] = $cacheecho;
    $cacheechokeys[] = $cacheecho['qdxq'];
  }
  C::t('common_setting')->update('paulsign_emot', $cacheechos);
  updatecache('setting');
  cpmsg($lang['custom_11'], '', 'succeed');
}
