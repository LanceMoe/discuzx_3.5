<?php

if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}

class ai_firewall_config {

	private static $runtime;

	public static function defaults() {
		return array(
			'enabled' => '0',
			'base_url' => 'https://api.openai.com/v1',
			'api_key' => '',
			'model' => 'gpt-5-mini',
			'prompt' => '你是论坛内容安全审核员。根据站点规则判断内容是否可以直接公开。广告引流、诈骗、违法内容、色情、仇恨骚扰、恶意灌水、泄露隐私或明显破坏社区秩序的内容应进入人工审核；正常讨论应通过。存在疑点时选择 review。',
			'structured_output' => '1',
			'check_threads' => '1',
			'check_replies' => '1',
			'forum_ids' => '',
			'exempt_staff' => '1',
			'timeout' => '8',
			'max_chars' => '12000',
			'failure_mode' => 'review',
			'skip_passed_thread_logs' => '0',
			'log_days' => '30',
		);
	}

	public static function install_defaults() {
		$existing = C::t('#ai_firewall#ai_firewall_config')->fetch_all_settings();
		foreach(self::defaults() as $key => $value) {
			if(!isset($existing[$key])) {
				C::t('#ai_firewall#ai_firewall_config')->set_value($key, $value);
			}
		}
	}

	public static function get($refresh = false) {
		global $_G;
		if(self::$runtime !== null && !$refresh) {
			return self::$runtime;
		}
		$config = self::defaults();
		$rows = C::t('#ai_firewall#ai_firewall_config')->fetch_all_settings();
		foreach($rows as $key => $row) {
			if(array_key_exists($key, $config)) {
				$config[$key] = $row['config_value'];
			}
		}
		$config['api_key_encrypted'] = $config['api_key'];
		$config['api_key'] = $config['api_key'] ? authcode($config['api_key'], 'DECODE', $_G['config']['security']['authkey']) : '';
		$config['forum_ids'] = self::parse_forum_ids($config['forum_ids']);
		foreach(array('enabled', 'structured_output', 'check_threads', 'check_replies', 'exempt_staff', 'skip_passed_thread_logs') as $key) {
			$config[$key] = intval($config[$key]) ? 1 : 0;
		}
		$config['timeout'] = max(2, min(60, intval($config['timeout'])));
		$config['max_chars'] = max(500, min(50000, intval($config['max_chars'])));
		$config['log_days'] = max(1, min(3650, intval($config['log_days'])));
		$config['failure_mode'] = $config['failure_mode'] === 'pass' ? 'pass' : 'review';
		self::$runtime = $config;
		return $config;
	}

	public static function save($input, $apiKey = null) {
		global $_G;
		$current = self::get();
		$input = is_array($input) ? array_merge(self::defaults(), $input) : self::defaults();
		$defaults = self::defaults();
		foreach(array('enabled', 'base_url', 'model', 'prompt', 'structured_output', 'check_threads', 'check_replies', 'exempt_staff', 'timeout', 'max_chars', 'failure_mode', 'skip_passed_thread_logs', 'log_days') as $key) {
			if(!is_scalar($input[$key])) {
				$input[$key] = $defaults[$key];
			}
		}
		$data = array(
			'enabled' => empty($input['enabled']) ? '0' : '1',
			'base_url' => rtrim(trim((string)$input['base_url']), '/'),
			'model' => trim((string)$input['model']),
			'prompt' => trim((string)$input['prompt']),
			'structured_output' => empty($input['structured_output']) ? '0' : '1',
			'check_threads' => empty($input['check_threads']) ? '0' : '1',
			'check_replies' => empty($input['check_replies']) ? '0' : '1',
			'forum_ids' => implode(',', self::parse_forum_ids(isset($input['forum_ids']) ? $input['forum_ids'] : array())),
			'exempt_staff' => empty($input['exempt_staff']) ? '0' : '1',
			'timeout' => (string)max(2, min(60, intval($input['timeout']))),
			'max_chars' => (string)max(500, min(50000, intval($input['max_chars']))),
			'failure_mode' => isset($input['failure_mode']) && $input['failure_mode'] === 'pass' ? 'pass' : 'review',
			'skip_passed_thread_logs' => empty($input['skip_passed_thread_logs']) ? '0' : '1',
			'log_days' => (string)max(1, min(3650, intval($input['log_days']))),
		);
		if(is_scalar($apiKey) && $apiKey !== '') {
			$data['api_key'] = authcode(trim($apiKey), 'ENCODE', $_G['config']['security']['authkey']);
		} else {
			$data['api_key'] = $current['api_key_encrypted'];
		}
		foreach($data as $key => $value) {
			C::t('#ai_firewall#ai_firewall_config')->set_value($key, $value);
		}
		self::$runtime = null;
		return self::get(true);
	}

	private static function parse_forum_ids($value) {
		if(!is_array($value)) {
			$value = preg_split('/[\s,]+/', (string)$value, -1, PREG_SPLIT_NO_EMPTY);
		}
		$ids = array();
		foreach($value as $fid) {
			$fid = intval($fid);
			if($fid > 0) {
				$ids[$fid] = $fid;
			}
		}
		return array_values($ids);
	}
}
