<?php

!defined('IN_DISCUZ') && exit('Access Denied');
!defined('IN_ADMINCP') && exit('Access Denied');
loadcache('pluginlanguage_script');
$lang = $_G['cache']['pluginlanguage_script']['dsu_paulsign'];
$charturl = (string)$_G['siteurl'] . 'plugin.php?id=dsu_paulsign:sign_stat_data';
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
    <div class="dsu-paulsign-chart" id="dsu-paulsign-chart" data-source="<?= dhtmlspecialchars($charturl) ?>">
      <div class="dsu-paulsign-chart__legend">
        <span><i class="dsu-paulsign-chart__key dsu-paulsign-chart__key--login"></i>Login</span>
        <span><i class="dsu-paulsign-chart__key dsu-paulsign-chart__key--sign"></i>Sign</span>
      </div>
      <canvas aria-label="PaulSign statistic chart" role="img"></canvas>
      <p class="dsu-paulsign-chart__status">Loading chart data...</p>
    </div>
    <style type="text/css">
      .dsu-paulsign-chart { width: min(760px, 100%); margin: 8px auto; text-align: left; }
      .dsu-paulsign-chart__legend { display: flex; gap: 18px; justify-content: flex-end; margin: 0 10px 8px; color: #555; font-size: 12px; }
      .dsu-paulsign-chart__key { display: inline-block; width: 10px; height: 10px; margin-right: 5px; border-radius: 50%; }
      .dsu-paulsign-chart__key--login { background: #1689d4; }
      .dsu-paulsign-chart__key--sign { background: #e39120; }
      .dsu-paulsign-chart canvas { display: block; width: 100%; height: 280px; background: #fff; border: 1px solid #dfe5e8; }
      .dsu-paulsign-chart__status { margin: 8px 0; color: #777; text-align: center; }
    </style>
    <script type="text/javascript">
    (function() {
      var root = document.getElementById('dsu-paulsign-chart');
      if (!root || !window.fetch) { return; }
      var canvas = root.getElementsByTagName('canvas')[0];
      var status = root.getElementsByTagName('p')[0];
      var context = canvas.getContext('2d');
      var chartData;

      function render(data) {
        var width = Math.max(320, root.clientWidth);
        var height = 280;
        var ratio = window.devicePixelRatio || 1;
        canvas.width = width * ratio;
        canvas.height = height * ratio;
        canvas.style.height = height + 'px';
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        context.clearRect(0, 0, width, height);

        var padding = { top: 18, right: 22, bottom: 42, left: 48 };
        var plotWidth = width - padding.left - padding.right;
        var plotHeight = height - padding.top - padding.bottom;
        var maxValue = Math.max(1, Number(data.max) || 1);
        var labels = data.labels || [];
        var count = Math.max(labels.length - 1, 1);

        context.font = '12px Arial, sans-serif';
        context.strokeStyle = '#e6eaed';
        context.fillStyle = '#77828a';
        context.lineWidth = 1;
        for (var tick = 0; tick <= 5; tick++) {
          var value = Math.round(maxValue * tick / 5);
          var y = padding.top + plotHeight - plotHeight * tick / 5;
          context.beginPath();
          context.moveTo(padding.left, y);
          context.lineTo(width - padding.right, y);
          context.stroke();
          context.fillText(String(value), 7, y + 4);
        }

        for (var i = 0; i < labels.length; i++) {
          var x = padding.left + plotWidth * i / count;
          if (i === 0 || i === labels.length - 1 || i % 2 === 0) {
            context.fillText(labels[i], x - 13, height - 16);
          }
        }

        function drawSeries(values, color) {
          context.strokeStyle = color;
          context.fillStyle = color;
          context.lineWidth = 2;
          context.beginPath();
          for (var index = 0; index < values.length; index++) {
            var pointX = padding.left + plotWidth * index / count;
            var pointY = padding.top + plotHeight - (Math.max(0, Number(values[index]) || 0) / maxValue * plotHeight);
            if (index === 0) { context.moveTo(pointX, pointY); } else { context.lineTo(pointX, pointY); }
          }
          context.stroke();
          for (var dot = 0; dot < values.length; dot++) {
            var dotX = padding.left + plotWidth * dot / count;
            var dotY = padding.top + plotHeight - (Math.max(0, Number(values[dot]) || 0) / maxValue * plotHeight);
            context.beginPath();
            context.arc(dotX, dotY, 3, 0, Math.PI * 2);
            context.fill();
          }
        }

        drawSeries(data.login || [], '#1689d4');
        drawSeries(data.sign || [], '#e39120');
      }

      fetch(root.getAttribute('data-source'), { credentials: 'same-origin' })
        .then(function(response) {
          if (!response.ok) { throw new Error('Unable to load data'); }
          return response.json();
        })
        .then(function(data) {
          chartData = data;
          status.parentNode.removeChild(status);
          render(chartData);
        })
        .catch(function() {
          status.textContent = 'Unable to load chart data.';
        });

      window.addEventListener('resize', function() {
        if (chartData) { render(chartData); }
      });
    })();
    </script>
  </td>
</tr>
<?php
showtablefooter();
