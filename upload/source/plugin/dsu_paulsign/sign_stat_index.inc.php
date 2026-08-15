<?php

!defined('IN_DISCUZ') && exit('Access Denied');
!defined('IN_ADMINCP') && exit('Access Denied');
loadcache('pluginlanguage_script');
$lang = $_G['cache']['pluginlanguage_script']['dsu_paulsign'];
$siteurl = dhtmlspecialchars((string)$_G['siteurl']);
if ($_G['adminid'] !== '1') {
  cpmsg($lang['custom_07'], '', 'error');
}
showtableheader($lang['statindex_01']);
$stats = C::t('#dsu_paulsign#dsu_paulsignset')->fetch('1');
?>

<tr>
  <td class="tipsblock">
    <ul id="tipslis">
      <ul>
        <li>
          <?= $lang['statindex_04'] ?>:
          <?= $stats['highestq'] ?>
        </li>
      </ul>
    </ul>
  </td>
</tr>

<?php
showtablefooter();
showtableheader($lang['statindex_05']);
?>
<tr>
  <td align="center">
    <embed type="application/x-shockwave-flash" src="<?= $siteurl ?>source/plugin/dsu_paulsign/img/chart.swf"
      width="600" height="250" id="chart" name="chart" bgcolor="#FFFFFF" quality="high"
      allowscriptaccess="sameDomain"
      flashvars="data=<?= $siteurl ?>plugin.php%3Fid%3Ddsu_paulsign:sign_stat_data">
  </td>
</tr>
<?php
showtablefooter();
