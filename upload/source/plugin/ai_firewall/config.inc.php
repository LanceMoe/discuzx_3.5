<?php

if(!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) {
	exit('Access Denied');
}

require_once DISCUZ_ROOT.'./source/plugin/ai_firewall/language/lang_admincp.php';
require_once DISCUZ_ROOT.'./source/plugin/ai_firewall/lib/config.php';
require_once DISCUZ_ROOT.'./source/plugin/ai_firewall/lib/client.php';
require_once DISCUZ_ROOT.'./source/plugin/ai_firewall/lib/moderator.php';

$baseUrl = 'plugins&operation=config&do='.$pluginid.'&identifier=ai_firewall&pmod=config';
$redirectUrl = 'action='.$baseUrl;
$isSave = !empty($_GET['saveconfig']);
$isTest = !empty($_GET['testconfig']);

if($isSave || $isTest) {
	if(!submitcheck($isSave ? 'saveconfig' : 'testconfig')) {
		exit;
	}
	$input = isset($_GET['config']) && is_array($_GET['config']) ? $_GET['config'] : array();
	$current = ai_firewall_config::get();
	$base = isset($input['base_url']) && is_scalar($input['base_url']) ? trim((string)$input['base_url']) : '';
	$parsed = parse_url($base);
	$newApiKey = isset($_GET['api_key']) && is_scalar($_GET['api_key']) ? trim((string)$_GET['api_key']) : '';
	$hasApiKey = $newApiKey !== '' || $current['api_key'] !== '';
	$errors = array();
	if(preg_match('/[\x00-\x20\x7f]/', $base) || !$parsed || empty($parsed['scheme']) || empty($parsed['host']) || !in_array(strtolower($parsed['scheme']), array('http', 'https'), true) || isset($parsed['user']) || isset($parsed['pass']) || isset($parsed['query']) || isset($parsed['fragment'])) {
		$errors[] = 'Base URL 必须是有效的 HTTP 或 HTTPS 地址，且不能包含账号、密码、查询参数或片段。';
	}
	if($newApiKey !== '' && preg_match('/[\r\n]/', $newApiKey)) {
		$errors[] = 'API Key 不能包含换行符。';
	}
	if(!isset($input['model']) || !is_scalar($input['model']) || trim((string)$input['model']) === '') {
		$errors[] = '模型名称不能为空。';
	}
	if(!isset($input['prompt']) || !is_scalar($input['prompt']) || trim((string)$input['prompt']) === '') {
		$errors[] = '审核 Prompt 不能为空。';
	}
	if((!empty($input['enabled']) || $isTest) && !$hasApiKey) {
		$errors[] = '启用或测试前必须配置 API Key。';
	}
	if($errors) {
		cpmsg('ai_firewall:config_invalid', '', 'error', array('errors' => implode('<br />', array_map('dhtmlspecialchars', $errors))));
	}

	$config = ai_firewall_config::save($input, $newApiKey);
	if($isTest) {
		$client = new ai_firewall_client($config);
		$response = $client->moderate(array(
			'content_type' => 'connection_test',
			'forum' => array('fid' => 0, 'name' => '后台连接测试'),
			'subject' => '连接测试',
			'message' => '这是一条无害的接口连接测试消息。',
		));
		if(!$response['ok']) {
			cpmsg('ai_firewall:test_failed', $redirectUrl, 'error', array(
				'error' => dhtmlspecialchars($response['error_code']),
				'http' => intval($response['http_status']),
				'latency' => intval($response['latency_ms']),
			));
		}
		$moderator = new ai_firewall_moderator($config);
		$parsedResult = $moderator->parse_model_result($response['raw_content']);
		if(empty($parsedResult['valid'])) {
			cpmsg('ai_firewall:test_invalid_response', $redirectUrl, 'error', array(
				'http' => intval($response['http_status']),
				'latency' => intval($response['latency_ms']),
			));
		}
		cpmsg('ai_firewall:test_succeed', $redirectUrl, 'succeed', array(
			'decision' => dhtmlspecialchars($parsedResult['decision']),
			'http' => intval($response['http_status']),
			'latency' => intval($response['latency_ms']),
		));
	}
	cpmsg('ai_firewall:config_saved', $redirectUrl, 'succeed');
}

$config = ai_firewall_config::get();
$failureOptions = array(
	array('review', $ai_firewall_adminlang['failure_review']),
	array('pass', $ai_firewall_adminlang['failure_pass']),
);

showformheader($baseUrl);
showtableheader($ai_firewall_adminlang['config_title']);
showsetting($ai_firewall_adminlang['enabled'], 'config[enabled]', $config['enabled'], 'radio', '', 0, $ai_firewall_adminlang['enabled_comment']);
showsetting($ai_firewall_adminlang['base_url'], 'config[base_url]', $config['base_url'], 'text', '', 0, $ai_firewall_adminlang['base_url_comment'], 'style="width:420px"');
showsetting($ai_firewall_adminlang['api_key'], 'api_key', '', 'password', '', 0, $config['api_key'] ? $ai_firewall_adminlang['api_key_set'] : $ai_firewall_adminlang['api_key_empty'], 'autocomplete="new-password" style="width:420px"');
showsetting($ai_firewall_adminlang['model'], 'config[model]', $config['model'], 'text', '', 0, '', 'style="width:420px"');
showsetting($ai_firewall_adminlang['prompt'], 'config[prompt]', $config['prompt'], 'textarea', '', 0, '', 'style="width:620px;height:180px"');
showsetting($ai_firewall_adminlang['check_threads'], 'config[check_threads]', $config['check_threads'], 'radio');
showsetting($ai_firewall_adminlang['check_replies'], 'config[check_replies]', $config['check_replies'], 'radio');
showsetting($ai_firewall_adminlang['forum_ids'], 'config[forum_ids]', implode(',', $config['forum_ids']), 'text', '', 0, $ai_firewall_adminlang['forum_ids_comment'], 'style="width:420px"');
showsetting($ai_firewall_adminlang['exempt_staff'], 'config[exempt_staff]', $config['exempt_staff'], 'radio');
showsetting($ai_firewall_adminlang['timeout'], 'config[timeout]', $config['timeout'], 'number', '', 0, '', 'min="2" max="60"');
showsetting($ai_firewall_adminlang['max_chars'], 'config[max_chars]', $config['max_chars'], 'number', '', 0, '', 'min="500" max="50000"');
showsetting($ai_firewall_adminlang['failure_mode'], array('config[failure_mode]', $failureOptions), $config['failure_mode'], 'select');
showsetting($ai_firewall_adminlang['log_days'], 'config[log_days]', $config['log_days'], 'number', '', 0, '', 'min="1" max="3650"');
$testButton = '<input type="submit" class="btn" name="testconfig" value="'.dhtmlspecialchars($ai_firewall_adminlang['save_test']).'" />';
showsubmit('saveconfig', $ai_firewall_adminlang['save'], '', $testButton);
showtablefooter();
showformfooter();
